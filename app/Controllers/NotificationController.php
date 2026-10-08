<?php

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\WorkAccess;

final class NotificationController
{
    public function updateStatus(): void
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
        $notificationId = (int)($_POST['notification_id'] ?? 0);
        $target = (string)($_POST['target_status'] ?? '');
        $work = WorkAccess::load($workId, (int)$user['id'], true);
        $roles = $work['_roles'];

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM notifications WHERE id=? AND work_id=?');
        $stmt->execute([$notificationId, $workId]);
        $notification = $stmt->fetch();
        if (!$notification) {
            http_response_code(404);
            exit('Notificação não encontrada.');
        }

        $current = (string)$notification['status'];
        $allowed = [
            'ISSUED' => ['DELIVERED','RESOLVED','CANCELLED'],
            'DELIVERED' => ['RESOLVED','CANCELLED'],
        ];
        if (!in_array($target, $allowed[$current] ?? [], true)) {
            http_response_code(422);
            exit('Transição de notificação não permitida.');
        }
        if ($target === 'CANCELLED' && !WorkAccess::canManage($roles)) {
            http_response_code(403);
            exit('Somente a administração pode cancelar uma notificação.');
        }
        $activeRestriction = in_array($notification['type'], ['SUSPENSION','EMBARGO'], true)
            && in_array($work['status'], ['SUSPENDED','EMBARGOED'], true);
        if ($activeRestriction && in_array($target, ['RESOLVED','CANCELLED'], true)) {
            http_response_code(422);
            exit('Emita a Liberação da obra antes de resolver ou cancelar a suspensão/embargo.');
        }

        $stmt = $pdo->prepare('UPDATE notifications SET status=? WHERE id=? AND work_id=? AND status=?');
        $stmt->execute([$target, $notificationId, $workId, $current]);
        if ($stmt->rowCount() !== 1) {
            http_response_code(409);
            exit('A notificação foi alterada por outro usuário. Atualize a página.');
        }

        $labels = ['DELIVERED'=>'Notificação marcada como entregue','RESOLVED'=>'Notificação resolvida','CANCELLED'=>'Notificação cancelada'];
        $pdo->prepare('INSERT INTO work_events(work_id,user_id,event_type,title,description) VALUES(?,?,?,?,?)')->execute([
            $workId,$user['id'],'NOTIFICATION_STATUS_CHANGED',$labels[$target] ?? 'Status da notificação alterado',$notification['number'].' · '.$current.' → '.$target
        ]);
        Audit::log((int)$user['id'], (int)$work['condominium_id'], 'notification', $notificationId, 'STATUS_CHANGED', [
            'work_id'=>$workId,'before'=>$current,'after'=>$target,'number'=>$notification['number'],'type'=>$notification['type'],
        ]);

        header('Location: /work?id=' . $workId . '&notification_status=1');
        exit;
    }
}
