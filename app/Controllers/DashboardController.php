<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;

final class DashboardController
{
    public function index(): void
    {
        if (!Auth::check()) {
            header('Location: /login');
            exit;
        }

        $pdo = Database::connection();
        $userId = Auth::user()['id'];
        $stmt = $pdo->prepare(
            'SELECT c.id, c.name,
                COUNT(DISTINCT w.id) total_works,
                SUM(w.status = "IN_PROGRESS") in_progress,
                SUM(w.status = "AUTHORIZED") authorized,
                SUM(w.status = "NOTIFIED") notified,
                SUM(w.status = "EMBARGOED") embargoed
             FROM condominiums c
             JOIN condominium_user cu ON cu.condominium_id = c.id AND cu.user_id = ? AND cu.active = 1
             LEFT JOIN works w ON w.condominium_id = c.id
             WHERE c.active = 1
             GROUP BY c.id, c.name
             ORDER BY c.name'
        );
        $stmt->execute([$userId]);
        $condominiums = $stmt->fetchAll();
        $user = Auth::user();

        require dirname(__DIR__, 2) . '/resources_dashboard.php';
    }
}
