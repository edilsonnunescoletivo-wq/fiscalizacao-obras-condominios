<?php

namespace App\Controllers;

use App\Core\Auth;
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
}
