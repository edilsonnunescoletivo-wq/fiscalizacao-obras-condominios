<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\WorkAccess;

final class FiscalPanelController
{
    public function index(): void
    {
        if (!Auth::check()) {
            header('Location: /login');
            exit;
        }

        $user = Auth::user();
        $condoId = (int)($_GET['condo'] ?? 0);
        $roles = WorkAccess::rolesForCondo($condoId, (int)$user['id']);
        if ($condoId <= 0 || !WorkAccess::canInspect($roles)) {
            http_response_code(403);
            exit('Acesso não autorizado.');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id,name FROM condominiums WHERE id=? AND active=1');
        $stmt->execute([$condoId]);
        $condominium = $stmt->fetch();
        if (!$condominium) {
            http_response_code(404);
            exit('Condomínio não encontrado.');
        }

        $stmt = $pdo->prepare('SELECT * FROM condominium_rules WHERE condominium_id=?');
        $stmt->execute([$condoId]);
        $rules = $stmt->fetch() ?: [];

        $stmt = $pdo->prepare('SELECT id,label,category,required,sort_order FROM inspection_checklist_items WHERE condominium_id=? AND active=1 ORDER BY sort_order,id');
        $stmt->execute([$condoId]);
        $checklist = $stmt->fetchAll();

        $stmt = $pdo->prepare('SELECT severity,suggested_action,active FROM severity_action_rules WHERE condominium_id=? ORDER BY FIELD(severity,"LOW","MEDIUM","HIGH","CRITICAL")');
        $stmt->execute([$condoId]);
        $severityRules = $stmt->fetchAll();

        $stmt = $pdo->prepare('SELECT
            COUNT(*) total_works,
            COALESCE(SUM(status IN ("IN_PROGRESS","NOTIFIED","SUSPENDED","EMBARGOED","COMPLETION_INSPECTION")),0) operational_works,
            COALESCE(SUM(status="SUSPENDED"),0) suspended_works,
            COALESCE(SUM(status="EMBARGOED"),0) embargoed_works
            FROM works WHERE condominium_id=?');
        $stmt->execute([$condoId]);
        $workStats = $stmt->fetch() ?: [];

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM non_conformities nc JOIN works w ON w.id=nc.work_id WHERE w.condominium_id=? AND nc.status<>"CLOSED"');
        $stmt->execute([$condoId]);
        $openNc = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM inspections i JOIN works w ON w.id=i.work_id WHERE w.condominium_id=? AND i.inspected_at>=DATE_SUB(NOW(), INTERVAL 30 DAY)');
        $stmt->execute([$condoId]);
        $inspections30d = (int)$stmt->fetchColumn();

        $sidebarCondoId = $condoId;
        $sidebarRoles = $roles;
        $sidebarActive = 'fiscal';
        require dirname(__DIR__, 2) . '/resources_fiscal_panel.php';
    }
}
