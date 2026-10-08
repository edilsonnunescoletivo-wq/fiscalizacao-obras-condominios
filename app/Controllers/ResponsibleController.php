<?php

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\WorkAccess;

final class ResponsibleController
{
    private function requireUser(): array
    {
        if (!Auth::check()) {
            header('Location: /login');
            exit;
        }
        return Auth::user();
    }

    public function index(): void
    {
        $user = $this->requireUser();
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT w.id,w.unit,w.owner_name,w.work_type,w.status,w.planned_end,c.name condominium_name,
                (SELECT COUNT(*) FROM non_conformities nc WHERE nc.work_id=w.id AND nc.status<>"CLOSED") pending_corrections,
                (SELECT COUNT(*) FROM work_documents wd WHERE wd.work_id=w.id AND wd.status IN ("CORRECTION_REQUIRED","REJECTED")) document_corrections,
                (SELECT COUNT(*) FROM notifications n WHERE n.work_id=w.id AND n.status IN ("ISSUED","DELIVERED")) active_notifications
             FROM works w
             JOIN condominiums c ON c.id=w.condominium_id
             JOIN condominium_user cu ON cu.condominium_id=w.condominium_id AND cu.user_id=? AND cu.active=1
             JOIN roles r ON r.id=cu.role_id AND r.code="WORK_RESPONSIBLE"
             WHERE w.responsible_user_id=?
             GROUP BY w.id,w.unit,w.owner_name,w.work_type,w.status,w.planned_end,c.name
             ORDER BY FIELD(w.status,"CORRECTION_REQUIRED","NOTIFIED","SUSPENDED","EMBARGOED","IN_PROGRESS","WAITING_DOCUMENTS","UNDER_REVIEW","AUTHORIZED","TECHNICALLY_APPROVED","COMPLETION_INSPECTION","COMPLETED","DRAFT","CANCELLED"),w.updated_at DESC'
        );
        $stmt->execute([(int)$user['id'], (int)$user['id']]);
        $works = $stmt->fetchAll();
        require dirname(__DIR__, 2) . '/resources_responsible.php';
    }

    public function work(): void
    {
        $user = $this->requireUser();
        $workId = (int)($_GET['id'] ?? 0);
        $work = WorkAccess::load($workId, (int)$user['id']);
        $roles = $work['_roles'];
        $onlyResponsible = !empty($work['_only_responsible']);
        if ($onlyResponsible && (int)$work['responsible_user_id'] !== (int)$user['id']) {
            http_response_code(403);
            exit('Acesso não autorizado.');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT dt.id,dt.name,dt.required_default,wd.id document_id,wd.version,wd.original_name,wd.status,wd.review_notes,wd.uploaded_at,wd.reviewed_at
             FROM document_types dt
             LEFT JOIN work_documents wd ON wd.id=(SELECT wd2.id FROM work_documents wd2 WHERE wd2.work_id=? AND wd2.document_type_id=dt.id ORDER BY wd2.version DESC LIMIT 1)
             WHERE dt.active=1 AND (dt.condominium_id IS NULL OR dt.condominium_id=?)
             ORDER BY dt.required_default DESC,dt.name'
        );
        $stmt->execute([$workId, $work['condominium_id']]);
        $documents = $stmt->fetchAll();

        $stmt = $pdo->prepare('SELECT nc.*,u.name correction_user_name FROM non_conformities nc LEFT JOIN users u ON u.id=nc.correction_submitted_by WHERE nc.work_id=? ORDER BY FIELD(nc.status,"OPEN","CORRECTED","CLOSED"),nc.created_at DESC');
        $stmt->execute([$workId]);
        $corrections = $stmt->fetchAll();

        $stmt = $pdo->prepare('SELECT n.* FROM notifications n WHERE n.work_id=? AND n.status<>"DRAFT" ORDER BY n.created_at DESC');
        $stmt->execute([$workId]);
        $notifications = $stmt->fetchAll();

        $stmt = $pdo->prepare('SELECT ct.*,u.name inspector_name FROM work_completion_terms ct JOIN users u ON u.id=ct.inspector_user_id WHERE ct.work_id=? LIMIT 1');
        $stmt->execute([$workId]);
        $completion = $stmt->fetch() ?: null;

        $documentPending = 0;
        $documentCorrection = 0;
        foreach ($documents as $document) {
            if ((int)$document['required_default'] === 1 && empty($document['document_id'])) $documentPending++;
            if (in_array((string)($document['status'] ?? ''), ['CORRECTION_REQUIRED','REJECTED'], true)) $documentCorrection++;
        }
        $openCorrections = count(array_filter($corrections, fn(array $item): bool => $item['status'] !== 'CLOSED'));
        $activeNotifications = count(array_filter($notifications, fn(array $item): bool => in_array($item['status'], ['ISSUED','DELIVERED'], true)));
        $canUpload = in_array('WORK_RESPONSIBLE', $roles, true) || WorkAccess::canInspect($roles);

        require dirname(__DIR__, 2) . '/resources_responsible_work.php';
    }

    public function uploadDocument(): void
    {
        $user = $this->requireUser();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::validate($_POST['_token'] ?? null)) {
            http_response_code(419);
            exit('Sessão expirada.');
        }

        $workId = (int)($_POST['work_id'] ?? 0);
        $typeId = (int)($_POST['document_type_id'] ?? 0);
        $work = WorkAccess::load($workId, (int)$user['id']);
        if (!in_array('WORK_RESPONSIBLE', $work['_roles'], true) && !WorkAccess::canInspect($work['_roles'])) {
            http_response_code(403);
            exit('Acesso não autorizado.');
        }
        if ($typeId <= 0 || empty($_FILES['document']['tmp_name'])) {
            http_response_code(422);
            exit('Documento inválido.');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id FROM document_types WHERE id=? AND active=1 AND (condominium_id IS NULL OR condominium_id=?)');
        $stmt->execute([$typeId, $work['condominium_id']]);
        if (!$stmt->fetchColumn()) {
            http_response_code(422);
            exit('Tipo de documento inválido para este condomínio.');
        }

        $file = $_FILES['document'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > 10 * 1024 * 1024) {
            http_response_code(422);
            exit('Arquivo inválido ou maior que 10 MB.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $allowed = ['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'];
        if (!isset($allowed[$mime])) {
            http_response_code(422);
            exit('Formato não permitido. Use PDF, JPG ou PNG.');
        }

        $stmt = $pdo->prepare('SELECT COALESCE(MAX(version),0)+1 FROM work_documents WHERE work_id=? AND document_type_id=?');
        $stmt->execute([$workId, $typeId]);
        $version = (int)$stmt->fetchColumn();

        $folder = dirname(__DIR__, 2) . '/storage/uploads/works/' . $workId;
        if (!is_dir($folder) && !mkdir($folder, 0775, true) && !is_dir($folder)) {
            http_response_code(500);
            exit('Não foi possível preparar a pasta de upload.');
        }
        $storedName = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
        if (!move_uploaded_file($file['tmp_name'], $folder . '/' . $storedName)) {
            http_response_code(500);
            exit('Não foi possível salvar o arquivo.');
        }

        $relative = 'storage/uploads/works/' . $workId . '/' . $storedName;
        $originalName = basename((string)$file['name']);
        $stmt = $pdo->prepare('INSERT INTO work_documents(work_id,document_type_id,version,original_name,stored_path,status,uploaded_by) VALUES(?,?,?,?,?,?,?)');
        $stmt->execute([$workId,$typeId,$version,$originalName,$relative,'SUBMITTED',$user['id']]);
        $documentId = (int)$pdo->lastInsertId();
        $pdo->prepare('UPDATE works SET status=CASE WHEN status IN ("WAITING_DOCUMENTS","CORRECTION_REQUIRED") THEN "UNDER_REVIEW" ELSE status END WHERE id=?')->execute([$workId]);
        $pdo->prepare('INSERT INTO work_events(work_id,user_id,event_type,title,description) VALUES(?,?,?,?,?)')->execute([$workId,$user['id'],'DOCUMENT_UPLOADED','Documento enviado',$originalName.' · versão '.$version]);
        Audit::log((int)$user['id'], (int)$work['condominium_id'], 'work_document', $documentId, 'UPLOADED', [
            'work_id'=>$workId,
            'document_type_id'=>$typeId,
            'version'=>$version,
            'original_name'=>$originalName,
        ]);

        header('Location: /responsible/work?id=' . $workId . '&uploaded=1');
        exit;
    }
}
