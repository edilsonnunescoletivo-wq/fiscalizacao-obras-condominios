<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\WorkAccess;

final class ReportsController
{
    public function index(): void
    {
        if (!Auth::check()) { header('Location: /login'); exit; }
        $user = Auth::user();
        $userId = (int)$user['id'];
        $pdo = Database::connection();
        $condoId = (int)($_GET['condo'] ?? 0);
        $status = trim((string)($_GET['status'] ?? ''));
        $isSuperAdmin = WorkAccess::isSuperAdmin($userId);

        if ($isSuperAdmin) {
            $condos = $pdo->query('SELECT id,name FROM condominiums WHERE active=1 ORDER BY name')->fetchAll();
        } else {
            $stmt = $pdo->prepare('SELECT DISTINCT c.id,c.name FROM condominiums c JOIN condominium_user cu ON cu.condominium_id=c.id WHERE cu.user_id=? AND cu.active=1 AND c.active=1 ORDER BY c.name');
            $stmt->execute([$userId]);
            $condos = $stmt->fetchAll();
        }

        $allowedIds = array_map('intval', array_column($condos,'id'));
        if ($condoId && !in_array($condoId,$allowedIds,true)) { http_response_code(403); exit('Acesso não autorizado.'); }

        $where = [];
        $params = [];
        if (!$isSuperAdmin) {
            $where[] = 'EXISTS (SELECT 1 FROM condominium_user cu WHERE cu.condominium_id=w.condominium_id AND cu.user_id=? AND cu.active=1)';
            $params[] = $userId;
            $where[] = '(EXISTS (SELECT 1 FROM condominium_user cu2 JOIN roles r2 ON r2.id=cu2.role_id WHERE cu2.condominium_id=w.condominium_id AND cu2.user_id=? AND cu2.active=1 AND r2.code<>"WORK_RESPONSIBLE") OR w.responsible_user_id=?)';
            $params[] = $userId;
            $params[] = $userId;
        }
        if ($condoId) { $where[]='w.condominium_id=?'; $params[]=$condoId; }
        $validStatuses = ['WAITING_DOCUMENTS','UNDER_REVIEW','CORRECTION_REQUIRED','TECHNICALLY_APPROVED','AUTHORIZED','IN_PROGRESS','NOTIFIED','SUSPENDED','EMBARGOED','COMPLETION_INSPECTION','COMPLETED'];
        if ($status !== '' && in_array($status,$validStatuses,true)) { $where[]='w.status=?'; $params[]=$status; }
        $scope = $where ? implode(' AND ',$where) : '1=1';

        $sql = 'SELECT w.id,w.condominium_id,w.unit,w.owner_name,w.work_type,w.status,w.planned_end,c.name condominium_name,
          (SELECT COUNT(*) FROM non_conformities nc WHERE nc.work_id=w.id AND nc.status<>"CLOSED") open_nc,
          (SELECT COUNT(*) FROM inspections i WHERE i.work_id=w.id) inspections_count,
          (SELECT COUNT(*) FROM notifications n WHERE n.work_id=w.id) notifications_count
          FROM works w JOIN condominiums c ON c.id=w.condominium_id
          WHERE '.$scope.' ORDER BY FIELD(w.status,"EMBARGOED","SUSPENDED","NOTIFIED","CORRECTION_REQUIRED","IN_PROGRESS","COMPLETION_INSPECTION","AUTHORIZED","TECHNICALLY_APPROVED","UNDER_REVIEW","WAITING_DOCUMENTS","COMPLETED"),w.planned_end,w.id DESC';
        $stmt = $pdo->prepare($sql); $stmt->execute($params); $works = $stmt->fetchAll();

        $kpiWhere = [];
        $kpiParams = [];
        if (!$isSuperAdmin) {
            $kpiWhere[] = 'EXISTS (SELECT 1 FROM condominium_user cu WHERE cu.condominium_id=w.condominium_id AND cu.user_id=? AND cu.active=1)';
            $kpiParams[] = $userId;
            $kpiWhere[] = '(EXISTS (SELECT 1 FROM condominium_user cu2 JOIN roles r2 ON r2.id=cu2.role_id WHERE cu2.condominium_id=w.condominium_id AND cu2.user_id=? AND cu2.active=1 AND r2.code<>"WORK_RESPONSIBLE") OR w.responsible_user_id=?)';
            $kpiParams[] = $userId;
            $kpiParams[] = $userId;
        }
        if ($condoId) { $kpiWhere[]='w.condominium_id=?'; $kpiParams[]=$condoId; }
        $kpiScope = $kpiWhere ? implode(' AND ',$kpiWhere) : '1=1';
        $stmt = $pdo->prepare('SELECT COUNT(DISTINCT w.id) total,
          COUNT(DISTINCT CASE WHEN w.status IN ("IN_PROGRESS","NOTIFIED","SUSPENDED","EMBARGOED","COMPLETION_INSPECTION") THEN w.id END) operational,
          COUNT(DISTINCT CASE WHEN w.status="COMPLETED" THEN w.id END) completed,
          COUNT(DISTINCT CASE WHEN w.status="EMBARGOED" THEN w.id END) embargoed,
          COUNT(DISTINCT CASE WHEN w.status="SUSPENDED" THEN w.id END) suspended,
          COUNT(DISTINCT CASE WHEN EXISTS(SELECT 1 FROM non_conformities nc WHERE nc.work_id=w.id AND nc.status<>"CLOSED") THEN w.id END) with_open_nc
          FROM works w WHERE '.$kpiScope);
        $stmt->execute($kpiParams); $kpis = $stmt->fetch() ?: [];

        $sidebarRoles = $condoId ? WorkAccess::rolesForCondo($condoId, $userId) : [];
        require dirname(__DIR__,2) . '/resources_reports.php';
    }
}
