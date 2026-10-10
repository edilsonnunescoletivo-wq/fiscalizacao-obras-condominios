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

        $validStatuses = ['WAITING_DOCUMENTS','UNDER_REVIEW','CORRECTION_REQUIRED','TECHNICALLY_APPROVED','AUTHORIZED','IN_PROGRESS','NOTIFIED','SUSPENDED','EMBARGOED','COMPLETION_INSPECTION','COMPLETED'];
        if ($status !== '' && !in_array($status,$validStatuses,true)) {
            $status = '';
        }

        $buildScope = static function(bool $includeStatus) use ($isSuperAdmin,$userId,$condoId,$status): array {
            $where=[]; $params=[];
            if (!$isSuperAdmin) {
                $where[]='EXISTS (SELECT 1 FROM condominium_user cu WHERE cu.condominium_id=w.condominium_id AND cu.user_id=? AND cu.active=1)';
                $params[]=$userId;
                $where[]='(EXISTS (SELECT 1 FROM condominium_user cu2 JOIN roles r2 ON r2.id=cu2.role_id WHERE cu2.condominium_id=w.condominium_id AND cu2.user_id=? AND cu2.active=1 AND r2.code<>"WORK_RESPONSIBLE") OR w.responsible_user_id=?)';
                $params[]=$userId; $params[]=$userId;
            }
            if ($condoId) { $where[]='w.condominium_id=?'; $params[]=$condoId; }
            if ($includeStatus && $status!=='') { $where[]='w.status=?'; $params[]=$status; }
            return [$where ? implode(' AND ',$where) : '1=1', $params];
        };

        [$scope,$params]=$buildScope(true);
        $sql='SELECT w.id,w.condominium_id,w.unit,w.owner_name,w.work_type,w.status,w.planned_start,w.planned_end,c.name condominium_name,
          CASE WHEN w.planned_end IS NOT NULL AND w.planned_end<CURDATE() AND w.status NOT IN ("COMPLETED","CANCELLED") THEN 1 ELSE 0 END overdue,
          (SELECT COUNT(*) FROM non_conformities nc WHERE nc.work_id=w.id AND nc.status<>"CLOSED") open_nc,
          (SELECT COUNT(*) FROM inspections i WHERE i.work_id=w.id) inspections_count,
          (SELECT COUNT(*) FROM notifications n WHERE n.work_id=w.id) notifications_count
          FROM works w JOIN condominiums c ON c.id=w.condominium_id
          WHERE '.$scope.' ORDER BY overdue DESC,FIELD(w.status,"EMBARGOED","SUSPENDED","NOTIFIED","CORRECTION_REQUIRED","IN_PROGRESS","COMPLETION_INSPECTION","AUTHORIZED","TECHNICALLY_APPROVED","UNDER_REVIEW","WAITING_DOCUMENTS","COMPLETED"),w.planned_end,w.id DESC';
        $stmt=$pdo->prepare($sql); $stmt->execute($params); $works=$stmt->fetchAll();

        [$kpiScope,$kpiParams]=$buildScope(false);
        $stmt=$pdo->prepare('SELECT COUNT(*) total,
          COALESCE(SUM(w.status IN ("IN_PROGRESS","NOTIFIED","SUSPENDED","EMBARGOED","COMPLETION_INSPECTION")),0) operational,
          COALESCE(SUM(w.status="COMPLETED"),0) completed,
          COALESCE(SUM(w.status="EMBARGOED"),0) embargoed,
          COALESCE(SUM(w.status="SUSPENDED"),0) suspended,
          COALESCE(SUM(w.planned_end IS NOT NULL AND w.planned_end<CURDATE() AND w.status NOT IN ("COMPLETED","CANCELLED")),0) overdue,
          COALESCE(SUM(EXISTS(SELECT 1 FROM non_conformities nc WHERE nc.work_id=w.id AND nc.status<>"CLOSED")),0) with_open_nc
          FROM works w WHERE '.$kpiScope);
        $stmt->execute($kpiParams); $kpis=$stmt->fetch() ?: [];

        $relatedScope='EXISTS (SELECT 1 FROM works w WHERE w.id=x.work_id AND '.$kpiScope.')';
        $stmt=$pdo->prepare('SELECT COUNT(*) FROM inspections x WHERE '.$relatedScope);
        $stmt->execute($kpiParams); $kpis['inspections_total']=(int)$stmt->fetchColumn();
        $stmt=$pdo->prepare('SELECT COUNT(*) FROM notifications x WHERE '.$relatedScope);
        $stmt->execute($kpiParams); $kpis['notifications_total']=(int)$stmt->fetchColumn();
        $stmt=$pdo->prepare('SELECT COUNT(*) FROM non_conformities x WHERE x.status<>"CLOSED" AND '.$relatedScope);
        $stmt->execute($kpiParams); $kpis['open_nc_total']=(int)$stmt->fetchColumn();

        $stmt=$pdo->prepare('SELECT w.status,COUNT(*) total FROM works w WHERE '.$kpiScope.' GROUP BY w.status ORDER BY total DESC');
        $stmt->execute($kpiParams); $statusBreakdown=$stmt->fetchAll();

        $sidebarRoles=$condoId ? WorkAccess::rolesForCondo($condoId,$userId) : [];
        require dirname(__DIR__,2) . '/resources_reports.php';
    }
}
