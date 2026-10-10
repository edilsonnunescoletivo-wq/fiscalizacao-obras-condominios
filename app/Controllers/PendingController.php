<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\WorkAccess;

final class PendingController
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

        $workFilter = (int)($_GET['work'] ?? 0);
        $severity = (string)($_GET['severity'] ?? '');
        $priority = (string)($_GET['priority'] ?? '');
        $status = (string)($_GET['status'] ?? '');
        $assignee = (int)($_GET['assignee'] ?? 0);
        $onlyOverdue = (string)($_GET['overdue'] ?? '') === '1';
        if (!in_array($severity, ['', 'LOW','MEDIUM','HIGH','CRITICAL'], true)) $severity = '';
        if (!in_array($priority, ['', 'LOW','NORMAL','HIGH','URGENT'], true)) $priority = '';
        if (!in_array($status, ['', 'OPEN','CORRECTED'], true)) $status = '';

        $sql = 'SELECT nc.id,nc.title,nc.description,nc.severity,nc.priority,nc.assigned_user_id,nc.status,nc.corrective_deadline,nc.created_at,nc.correction_submitted_at,nc.correction_notes,
            w.id work_id,w.unit,w.owner_name,w.status work_status,u.name creator_name,au.name assigned_name,i.stage inspection_stage,
            CASE WHEN nc.corrective_deadline IS NOT NULL AND nc.corrective_deadline < NOW() AND nc.status <> "CLOSED" THEN 1 ELSE 0 END overdue
            FROM non_conformities nc
            JOIN works w ON w.id=nc.work_id
            JOIN users u ON u.id=nc.created_by
            LEFT JOIN users au ON au.id=nc.assigned_user_id
            LEFT JOIN inspections i ON i.id=nc.inspection_id
            WHERE w.condominium_id=? AND nc.status <> "CLOSED"';
        $params = [$condoId];
        if ($workFilter > 0) { $sql .= ' AND w.id=?'; $params[] = $workFilter; }
        if ($severity !== '') { $sql .= ' AND nc.severity=?'; $params[] = $severity; }
        if ($priority !== '') { $sql .= ' AND nc.priority=?'; $params[] = $priority; }
        if ($status !== '') { $sql .= ' AND nc.status=?'; $params[] = $status; }
        if ($assignee > 0) { $sql .= ' AND nc.assigned_user_id=?'; $params[] = $assignee; }
        if ($onlyOverdue) $sql .= ' AND nc.corrective_deadline IS NOT NULL AND nc.corrective_deadline < NOW()';
        $sql .= ' ORDER BY overdue DESC, FIELD(nc.priority,"URGENT","HIGH","NORMAL","LOW"), FIELD(nc.severity,"CRITICAL","HIGH","MEDIUM","LOW"), nc.corrective_deadline IS NULL, nc.corrective_deadline, nc.created_at DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll();

        $summaryStmt = $pdo->prepare('SELECT
            COALESCE(SUM(nc.status="OPEN"),0) open_count,
            COALESCE(SUM(nc.status="CORRECTED"),0) corrected_count,
            COALESCE(SUM(nc.status <> "CLOSED" AND nc.priority="URGENT"),0) urgent_count,
            COALESCE(SUM(nc.status <> "CLOSED" AND nc.corrective_deadline IS NOT NULL AND nc.corrective_deadline < NOW()),0) overdue_count
            FROM non_conformities nc JOIN works w ON w.id=nc.work_id WHERE w.condominium_id=?');
        $summaryStmt->execute([$condoId]);
        $summary = $summaryStmt->fetch() ?: ['open_count'=>0,'corrected_count'=>0,'urgent_count'=>0,'overdue_count'=>0];

        $worksStmt = $pdo->prepare('SELECT id,unit,owner_name FROM works WHERE condominium_id=? ORDER BY unit');
        $worksStmt->execute([$condoId]);
        $works = $worksStmt->fetchAll();

        $assigneeStmt = $pdo->prepare(
            'SELECT u.id,u.name,GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ", ") role_names
             FROM condominium_user cu
             JOIN users u ON u.id=cu.user_id AND u.active=1
             JOIN roles r ON r.id=cu.role_id
             WHERE cu.condominium_id=? AND cu.active=1 AND r.code<>"VIEWER"
             GROUP BY u.id,u.name
             ORDER BY u.name'
        );
        $assigneeStmt->execute([$condoId]);
        $assignees = $assigneeStmt->fetchAll();

        $canRoute = WorkAccess::canInspect($roles);
        require dirname(__DIR__, 2) . '/resources_pending.php';
    }
}
