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

        if ($isSuperAdmin) {
            $stmt = $pdo->query(
                'SELECT c.id, c.name, c.photo_path,
                    COUNT(DISTINCT w.id) total_works,
                    COALESCE(SUM(w.status = "IN_PROGRESS"),0) in_progress,
                    COALESCE(SUM(w.status = "AUTHORIZED"),0) authorized,
                    COALESCE(SUM(w.status = "NOTIFIED"),0) notified,
                    COALESCE(SUM(w.status = "EMBARGOED"),0) embargoed,
                    COALESCE(SUM(w.status = "COMPLETED"),0) completed
                 FROM condominiums c
                 LEFT JOIN works w ON w.condominium_id = c.id
                 WHERE c.active = 1
                 GROUP BY c.id, c.name, c.photo_path
                 ORDER BY c.name'
            );
            $condominiums = $stmt->fetchAll();
        } else {
            $stmt = $pdo->prepare(
                'SELECT c.id, c.name, c.photo_path,
                    COUNT(DISTINCT w.id) total_works,
                    COALESCE(SUM(w.status = "IN_PROGRESS"),0) in_progress,
                    COALESCE(SUM(w.status = "AUTHORIZED"),0) authorized,
                    COALESCE(SUM(w.status = "NOTIFIED"),0) notified,
                    COALESCE(SUM(w.status = "EMBARGOED"),0) embargoed,
                    COALESCE(SUM(w.status = "COMPLETED"),0) completed
                 FROM condominiums c
                 JOIN condominium_user cu ON cu.condominium_id = c.id AND cu.user_id = ? AND cu.active = 1
                 LEFT JOIN works w ON w.condominium_id = c.id
                 WHERE c.active = 1
                 GROUP BY c.id, c.name, c.photo_path
                 ORDER BY c.name'
            );
            $stmt->execute([$userId]);
            $condominiums = $stmt->fetchAll();
        }

        foreach ($condominiums as &$condominium) {
            $roles = WorkAccess::rolesForCondo((int)$condominium['id'], $userId);
            $condominium['can_manage'] = WorkAccess::canManage($roles);
        }
        unset($condominium);

        $user = Auth::user();
        require dirname(__DIR__, 2) . '/resources_dashboard.php';
    }
}
