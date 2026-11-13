<?php

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\WorkAccess;

final class NonConformityRoutingController
{
    public function update(): void
    {
        if (!Auth::check()) {
            header('Location: /login');
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::validate($_POST['_token'] ?? null)) {
            http_response_code(419);
            exit('Sessão expirada.');
        }

        $user = Auth::user();
        $workId = (int)($_POST['work_id'] ?? 0);
        $ncId = (int)($_POST['non_conformity_id'] ?? 0);
        $priority = (string)($_POST['priority'] ?? 'NORMAL');
        $assignedUserId = (int)($_POST['assigned_user_id'] ?? 0);
        $assignedUserId = $assignedUserId > 0 ? $assignedUserId : null;

        if (!in_array($priority, ['LOW','NORMAL','HIGH','URGENT'], true)) {
            http_response_code(422);
            exit('Prioridade inválida.');
        }

        $work = WorkAccess::load($workId, (int)$user['id'], true);
        if (!WorkAccess::canInspect($work['_roles'])) {
            http_response_code(403);
            exit('Acesso não autorizado.');
        }

        $pdo = Database::connection();
        $routingReady = (bool)$pdo->query("SHOW COLUMNS FROM non_conformities LIKE 'priority'")->fetch()
            && (bool)$pdo->query("SHOW COLUMNS FROM non_conformities LIKE 'assigned_user_id'")->fetch();
        if (!$routingReady) {
            http_response_code(409);
            exit('O direcionamento de pendências ainda aguarda a atualização controlada do banco de homologação.');
        }

        $stmt = $pdo->prepare('SELECT id,status,priority,assigned_user_id,title FROM non_conformities WHERE id=? AND work_id=?');
        $stmt->execute([$ncId,$workId]);
        $nc = $stmt->fetch();
        if (!$nc) {
            http_response_code(404);
            exit('Pendência não encontrada.');
        }
        if ($nc['status'] === 'CLOSED') {
            http_response_code(422);
            exit('Pendência encerrada não pode ser reatribuída.');
        }

        $assignedName = null;
        if ($assignedUserId !== null) {
            $stmt = $pdo->prepare(
                'SELECT u.name
                 FROM condominium_user cu
                 JOIN users u ON u.id=cu.user_id AND u.active=1
                 JOIN roles r ON r.id=cu.role_id
                 WHERE cu.condominium_id=? AND cu.user_id=? AND cu.active=1 AND r.code<>"VIEWER"
                 LIMIT 1'
            );
            $stmt->execute([(int)$work['condominium_id'],$assignedUserId]);
            $assignedName = $stmt->fetchColumn();
            if ($assignedName === false) {
                http_response_code(422);
                exit('Responsável inválido para este condomínio.');
            }
        }

        $stmt = $pdo->prepare('UPDATE non_conformities SET priority=?, assigned_user_id=? WHERE id=? AND work_id=? AND status<>"CLOSED"');
        $stmt->execute([$priority,$assignedUserId,$ncId,$workId]);

        $priorityLabels = ['LOW'=>'Baixa','NORMAL'=>'Normal','HIGH'=>'Alta','URGENT'=>'Urgente'];
        $description = 'Prioridade: ' . ($priorityLabels[$priority] ?? $priority) . ' · Responsável: ' . ($assignedName ?: 'Não atribuído');
        $pdo->prepare('INSERT INTO work_events(work_id,user_id,event_type,title,description) VALUES(?,?,?,?,?)')
            ->execute([$workId,$user['id'],'NON_CONFORMITY_ROUTED','Pendência direcionada',$nc['title'].' · '.$description]);

        Audit::log((int)$user['id'], (int)$work['condominium_id'], 'non_conformity', $ncId, 'ROUTING_UPDATED', [
            'work_id'=>$workId,
            'before_priority'=>$nc['priority'],
            'after_priority'=>$priority,
            'before_assigned_user_id'=>$nc['assigned_user_id'],
            'after_assigned_user_id'=>$assignedUserId,
        ]);

        header('Location: /condominium/pending?condo=' . (int)$work['condominium_id'] . '&updated=1');
        exit;
    }
}
