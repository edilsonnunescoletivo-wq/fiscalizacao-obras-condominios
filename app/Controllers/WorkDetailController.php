<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;

final class WorkDetailController
{
    private function requireAuth(): array
    {
        if (!Auth::check()) {
            header('Location: /login');
            exit;
        }
        return Auth::user();
    }

    private function rolesForCondo(int $condoId, int $userId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT r.code FROM condominium_user cu JOIN roles r ON r.id = cu.role_id WHERE cu.condominium_id = ? AND cu.user_id = ? AND cu.active = 1');
        $stmt->execute([$condoId, $userId]);
        return array_column($stmt->fetchAll(), 'code');
    }

    private function loadWork(int $workId, array $user): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT w.*, c.name condominium_name FROM works w JOIN condominiums c ON c.id = w.condominium_id WHERE w.id = ?');
        $stmt->execute([$workId]);
        $work = $stmt->fetch();
        if (!$work) {
            http_response_code(404);
            exit('Obra não encontrada');
        }

        $roles = $this->rolesForCondo((int)$work['condominium_id'], (int)$user['id']);
        if (!$roles) {
            http_response_code(403);
            exit('Acesso não autorizado');
        }

        if (in_array('WORK_RESPONSIBLE', $roles, true) && count(array_diff($roles, ['WORK_RESPONSIBLE'])) === 0 && (int)$work['responsible_user_id'] !== (int)$user['id']) {
            http_response_code(403);
            exit('Acesso não autorizado');
        }

        $work['_roles'] = $roles;
        return $work;
    }

    private function event(int $workId, int $userId, string $type, string $title, ?string $description = null): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO work_events (work_id, user_id, event_type, title, description) VALUES (?,?,?,?,?)');
        $stmt->execute([$workId, $userId, $type, $title, $description]);
    }

    public function show(): void
    {
        $user = $this->requireAuth();
        $workId = (int)($_GET['id'] ?? 0);
        $work = $this->loadWork($workId, $user);
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT dt.id, dt.name, dt.required_default, wd.id document_id, wd.version, wd.original_name, wd.status, wd.review_notes, wd.uploaded_at, wd.reviewed_at FROM document_types dt LEFT JOIN work_documents wd ON wd.id = (SELECT wd2.id FROM work_documents wd2 WHERE wd2.work_id = ? AND wd2.document_type_id = dt.id ORDER BY wd2.version DESC LIMIT 1) WHERE dt.active = 1 AND (dt.condominium_id IS NULL OR dt.condominium_id = ?) ORDER BY dt.required_default DESC, dt.name');
        $stmt->execute([$workId, $work['condominium_id']]);
        $documents = $stmt->fetchAll();

        $stmt = $pdo->prepare('SELECT we.*, u.name user_name FROM work_events we LEFT JOIN users u ON u.id = we.user_id WHERE we.work_id = ? ORDER BY we.created_at DESC, we.id DESC');
        $stmt->execute([$workId]);
        $events = $stmt->fetchAll();

        $roles = $work['_roles'];
        $canReview = (bool) array_intersect($roles, ['ADMIN','SYNDIC','MANAGER','INSPECTOR']);
        $canAuthorize = (bool) array_intersect($roles, ['ADMIN','SYNDIC','MANAGER']);
        $canUpload = $canReview || in_array('WORK_RESPONSIBLE', $roles, true);

        require dirname(__DIR__, 2) . '/resources_work_detail.php';
    }

    public function uploadDocument(): void
    {
        $user = $this->requireAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::validate($_POST['_token'] ?? null)) {
            http_response_code(419);
            exit('Sessão expirada.');
        }

        $workId = (int)($_POST['work_id'] ?? 0);
        $typeId = (int)($_POST['document_type_id'] ?? 0);
        $work = $this->loadWork($workId, $user);
        $roles = $work['_roles'];
        $canUpload = (bool) array_intersect($roles, ['ADMIN','SYNDIC','MANAGER','INSPECTOR','WORK_RESPONSIBLE']);
        if (!$canUpload || $typeId <= 0 || empty($_FILES['document']['tmp_name'])) {
            http_response_code(422);
            exit('Documento inválido.');
        }

        $file = $_FILES['document'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > 10 * 1024 * 1024) {
            http_response_code(422);
            exit('Arquivo inválido ou maior que 10 MB.');
        }

        $allowed = ['application/pdf', 'image/jpeg', 'image/png'];
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!in_array($mime, $allowed, true)) {
            http_response_code(422);
            exit('Formato não permitido. Use PDF, JPG ou PNG.');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT COALESCE(MAX(version),0)+1 FROM work_documents WHERE work_id = ? AND document_type_id = ?');
        $stmt->execute([$workId, $typeId]);
        $version = (int)$stmt->fetchColumn();

        $ext = match ($mime) { 'application/pdf' => 'pdf', 'image/jpeg' => 'jpg', default => 'png' };
        $folder = dirname(__DIR__, 2) . '/storage/uploads/works/' . $workId;
        if (!is_dir($folder)) {
            mkdir($folder, 0775, true);
        }
        $storedName = bin2hex(random_bytes(16)) . '.' . $ext;
        $fullPath = $folder . '/' . $storedName;
        if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
            http_response_code(500);
            exit('Não foi possível salvar o arquivo.');
        }

        $relative = 'storage/uploads/works/' . $workId . '/' . $storedName;
        $stmt = $pdo->prepare('INSERT INTO work_documents (work_id, document_type_id, version, original_name, stored_path, status, uploaded_by) VALUES (?,?,?,?,?,?,?)');
        $stmt->execute([$workId, $typeId, $version, basename((string)$file['name']), $relative, 'SUBMITTED', $user['id']]);
        $pdo->prepare('UPDATE works SET status = CASE WHEN status IN ("WAITING_DOCUMENTS","CORRECTION_REQUIRED") THEN "UNDER_REVIEW" ELSE status END WHERE id = ?')->execute([$workId]);
        $this->event($workId, (int)$user['id'], 'DOCUMENT_UPLOADED', 'Documento enviado', basename((string)$file['name']) . ' · versão ' . $version);

        header('Location: /work?id=' . $workId . '&uploaded=1');
        exit;
    }

    public function reviewDocument(): void
    {
        $user = $this->requireAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::validate($_POST['_token'] ?? null)) {
            http_response_code(419);
            exit('Sessão expirada.');
        }
        $workId = (int)($_POST['work_id'] ?? 0);
        $documentId = (int)($_POST['document_id'] ?? 0);
        $decision = (string)($_POST['decision'] ?? '');
        $notes = trim((string)($_POST['review_notes'] ?? '')) ?: null;
        $work = $this->loadWork($workId, $user);
        if (!array_intersect($work['_roles'], ['ADMIN','SYNDIC','MANAGER','INSPECTOR'])) {
            http_response_code(403);
            exit('Acesso não autorizado');
        }
        if (!in_array($decision, ['APPROVED','CORRECTION_REQUIRED','REJECTED'], true)) {
            http_response_code(422);
            exit('Decisão inválida');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE work_documents SET status = ?, review_notes = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ? AND work_id = ?');
        $stmt->execute([$decision, $notes, $user['id'], $documentId, $workId]);
        if ($decision === 'CORRECTION_REQUIRED' || $decision === 'REJECTED') {
            $pdo->prepare('UPDATE works SET status = "CORRECTION_REQUIRED" WHERE id = ?')->execute([$workId]);
        }
        $labels = ['APPROVED' => 'Documento aprovado', 'CORRECTION_REQUIRED' => 'Correção solicitada', 'REJECTED' => 'Documento reprovado'];
        $this->event($workId, (int)$user['id'], 'DOCUMENT_REVIEWED', $labels[$decision], $notes);
        header('Location: /work?id=' . $workId . '&reviewed=1');
        exit;
    }

    public function transition(): void
    {
        $user = $this->requireAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::validate($_POST['_token'] ?? null)) {
            http_response_code(419);
            exit('Sessão expirada.');
        }
        $workId = (int)($_POST['work_id'] ?? 0);
        $target = (string)($_POST['target_status'] ?? '');
        $work = $this->loadWork($workId, $user);
        $roles = $work['_roles'];
        $canReview = (bool) array_intersect($roles, ['ADMIN','SYNDIC','MANAGER','INSPECTOR']);
        $canAuthorize = (bool) array_intersect($roles, ['ADMIN','SYNDIC','MANAGER']);

        $allowed = [];
        if ($canReview && in_array($work['status'], ['UNDER_REVIEW','CORRECTION_REQUIRED','WAITING_DOCUMENTS'], true)) {
            $allowed[] = 'TECHNICALLY_APPROVED';
        }
        if ($canAuthorize && $work['status'] === 'TECHNICALLY_APPROVED') {
            $allowed[] = 'AUTHORIZED';
        }
        if ($canAuthorize && $work['status'] === 'AUTHORIZED') {
            $allowed[] = 'IN_PROGRESS';
        }
        if (!in_array($target, $allowed, true)) {
            http_response_code(422);
            exit('Transição de status não permitida.');
        }

        $pdo = Database::connection();
        if ($target === 'TECHNICALLY_APPROVED') {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM document_types dt LEFT JOIN work_documents wd ON wd.id = (SELECT wd2.id FROM work_documents wd2 WHERE wd2.work_id = ? AND wd2.document_type_id = dt.id ORDER BY wd2.version DESC LIMIT 1) WHERE dt.active = 1 AND dt.required_default = 1 AND (dt.condominium_id IS NULL OR dt.condominium_id = ?) AND (wd.id IS NULL OR wd.status <> "APPROVED")');
            $stmt->execute([$workId, $work['condominium_id']]);
            if ((int)$stmt->fetchColumn() > 0) {
                http_response_code(422);
                exit('Existem documentos obrigatórios pendentes de aprovação.');
            }
        }

        $pdo->prepare('UPDATE works SET status = ? WHERE id = ?')->execute([$target, $workId]);
        $labels = ['TECHNICALLY_APPROVED' => 'Obra aprovada tecnicamente', 'AUTHORIZED' => 'Obra autorizada para início', 'IN_PROGRESS' => 'Obra iniciada'];
        $this->event($workId, (int)$user['id'], 'STATUS_CHANGED', $labels[$target]);
        header('Location: /work?id=' . $workId . '&status=1');
        exit;
    }
}
