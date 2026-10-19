<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\WorkAccess;

final class DashboardController
{
    public function index(): void
    {
        if (!Auth::check()) {
            header('Location: /login');
            exit;
        }

        $pdo = Database::connection();
        $userId = (int)Auth::user()['id'];
        $isSuperAdmin = WorkAccess::isSuperAdmin($userId);

        if (!$isSuperAdmin) {
            $roleStmt = $pdo->prepare('SELECT DISTINCT r.code FROM condominium_user cu JOIN roles r ON r.id=cu.role_id WHERE cu.user_id=? AND cu.active=1');
            $roleStmt->execute([$userId]);
            $userRoles = array_column($roleStmt->fetchAll(), 'code');
            if ($userRoles && count(array_diff($userRoles, ['WORK_RESPONSIBLE'])) === 0) {
                header('Location: /responsible');
                exit;
            }
        }

        $metricsSql = '
                    COUNT(DISTINCT w.id) total_works,
                    COALESCE(SUM(w.status IN ("IN_PROGRESS","NOTIFIED","SUSPENDED","EMBARGOED")),0) operational,
                    COALESCE(SUM(w.status = "IN_PROGRESS"),0) in_progress,
                    COALESCE(SUM(w.status = "AUTHORIZED"),0) authorized,
                    COALESCE(SUM(w.status = "NOTIFIED"),0) notified,
                    COALESCE(SUM(w.status IN ("SUSPENDED","EMBARGOED")),0) restricted,
                    COALESCE(SUM(w.status = "COMPLETED"),0) completed,
                    COALESCE(SUM(w.planned_end IS NOT NULL AND w.planned_end < CURDATE() AND w.status NOT IN ("COMPLETED","CANCELLED")),0) overdue_works,
                    (SELECT COUNT(*) FROM non_conformities nc JOIN works wx ON wx.id=nc.work_id WHERE wx.condominium_id=c.id AND nc.status <> "CLOSED") open_nc,
                    (SELECT COUNT(*) FROM non_conformities nc JOIN works wx ON wx.id=nc.work_id WHERE wx.condominium_id=c.id AND nc.status <> "CLOSED" AND nc.corrective_deadline IS NOT NULL AND nc.corrective_deadline < NOW()) overdue_nc,
                    (SELECT COUNT(*) FROM non_conformities nc JOIN works wx ON wx.id=nc.work_id WHERE wx.condominium_id=c.id AND nc.status <> "CLOSED" AND nc.severity="CRITICAL") critical_nc';

        if ($isSuperAdmin) {
            $stmt = $pdo->query(
                'SELECT c.id, c.name, c.photo_path,' . $metricsSql . '
                 FROM condominiums c
                 LEFT JOIN works w ON w.condominium_id = c.id
                 WHERE c.active = 1
                 GROUP BY c.id, c.name, c.photo_path
                 ORDER BY critical_nc DESC, overdue_nc DESC, restricted DESC, c.name'
            );
            $condominiums = $stmt->fetchAll();
        } else {
            $stmt = $pdo->prepare(
                'SELECT c.id, c.name, c.photo_path,' . $metricsSql . '
                 FROM condominiums c
                 JOIN condominium_user cu ON cu.condominium_id = c.id AND cu.user_id = ? AND cu.active = 1
                 LEFT JOIN works w ON w.condominium_id = c.id
                 WHERE c.active = 1
                 GROUP BY c.id, c.name, c.photo_path
                 ORDER BY critical_nc DESC, overdue_nc DESC, restricted DESC, c.name'
            );
            $stmt->execute([$userId]);
            $condominiums = $stmt->fetchAll();
        }

        foreach ($condominiums as &$condominium) {
            $roles = WorkAccess::rolesForCondo((int)$condominium['id'], $userId);
            $condominium['can_manage'] = WorkAccess::canManage($roles);
            $attention = (int)$condominium['critical_nc'] * 3
                + (int)$condominium['overdue_nc'] * 2
                + (int)$condominium['restricted'] * 2
                + (int)$condominium['overdue_works'];
            $condominium['attention_level'] = $attention >= 8 ? 'critical' : ($attention >= 4 ? 'high' : ($attention >= 1 ? 'medium' : 'low'));
        }
        unset($condominium);

        $user = Auth::user();
        require dirname(__DIR__, 2) . '/resources_dashboard.php';
    }
}
