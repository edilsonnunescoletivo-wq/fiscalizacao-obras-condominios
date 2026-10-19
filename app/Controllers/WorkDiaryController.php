<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\WorkAccess;

final class WorkDiaryController
{
    public function show(): void
    {
        if (!Auth::check()) {
            header('Location: /login');
            exit;
        }
        $user = Auth::user();
        $workId = (int)($_GET['id'] ?? 0);
        $work = WorkAccess::load($workId, (int)$user['id']);
        $pdo = Database::connection();

        $entries = [];

        $stmt = $pdo->prepare('SELECT we.*,u.name user_name FROM work_events we LEFT JOIN users u ON u.id=we.user_id WHERE we.work_id=? ORDER BY we.created_at DESC,we.id DESC');
        $stmt->execute([$workId]);
        foreach ($stmt->fetchAll() as $event) {
            $type = strtoupper((string)$event['event_type']);
            if (str_contains($type, 'INSPECTION') || str_contains($type, 'NON_CONFORMITY') || str_contains($type, 'NOTIFICATION')) {
                continue;
            }
            $entries[] = [
                'at'=>$event['created_at'],
                'kind'=>'EVENT',
                'title'=>$event['title'],
                'description'=>$event['description'],
                'actor'=>$event['user_name'] ?: 'Sistema',
                'meta'=>null,
                'link'=>null,
                'link_label'=>null,
            ];
        }

        $stmt = $pdo->prepare('SELECT i.*,u.name inspector_name,(SELECT COUNT(*) FROM inspection_photos p WHERE p.inspection_id=i.id) photo_count FROM inspections i JOIN users u ON u.id=i.inspector_user_id WHERE i.work_id=?');
        $stmt->execute([$workId]);
        foreach ($stmt->fetchAll() as $inspection) {
            $entries[] = [
                'at'=>$inspection['inspected_at'],
                'kind'=>'INSPECTION',
                'title'=>'Fiscalização #' . (int)$inspection['id'],
                'description'=>$inspection['notes'],
                'actor'=>$inspection['inspector_name'],
                'meta'=>trim(($inspection['stage'] ?: 'Etapa não informada') . ' · ' . $inspection['result'] . ' · ' . (int)$inspection['photo_count'] . ' foto(s)'),
                'link'=>'/inspection/photos?id=' . (int)$inspection['id'],
                'link_label'=>'Evidências',
            ];
        }

        $stmt = $pdo->prepare('SELECT nc.*,u.name creator_name FROM non_conformities nc JOIN users u ON u.id=nc.created_by WHERE nc.work_id=?');
        $stmt->execute([$workId]);
        foreach ($stmt->fetchAll() as $nc) {
            $description = $nc['description'];
            if ($nc['correction_notes']) $description .= "\nCorreção: " . $nc['correction_notes'];
            $entries[] = [
                'at'=>$nc['created_at'],
                'kind'=>'NC',
                'title'=>'Não conformidade · ' . $nc['title'],
                'description'=>$description,
                'actor'=>$nc['creator_name'],
                'meta'=>$nc['severity'] . ' · ' . $nc['status'] . ($nc['corrective_deadline'] ? ' · Prazo ' . $nc['corrective_deadline'] : ''),
                'link'=>'/corrections?work=' . $workId,
                'link_label'=>'Correções',
            ];
            if ($nc['correction_submitted_at']) {
                $entries[] = [
                    'at'=>$nc['correction_submitted_at'],
                    'kind'=>'CORRECTION',
                    'title'=>'Correção enviada · ' . $nc['title'],
                    'description'=>$nc['correction_notes'],
                    'actor'=>'Responsável pela correção',
                    'meta'=>'Aguardando ou já submetida à validação da fiscalização',
                    'link'=>'/corrections?work=' . $workId,
                    'link_label'=>'Abrir correção',
                ];
            }
        }

        $stmt = $pdo->prepare('SELECT n.*,u.name creator_name FROM notifications n JOIN users u ON u.id=n.created_by WHERE n.work_id=?');
        $stmt->execute([$workId]);
        foreach ($stmt->fetchAll() as $notification) {
            $entries[] = [
                'at'=>$notification['issued_at'] ?: $notification['created_at'],
                'kind'=>'NOTIFICATION',
                'title'=>$notification['type'] . ' · ' . $notification['number'],
                'description'=>$notification['reason'],
                'actor'=>$notification['creator_name'],
                'meta'=>$notification['status'] . ($notification['deadline'] ? ' · Prazo ' . $notification['deadline'] : ''),
                'link'=>'/notification/pdf?id=' . (int)$notification['id'],
                'link_label'=>'PDF',
            ];
        }

        usort($entries, static fn(array $a, array $b): int => strcmp((string)$b['at'], (string)$a['at']));

        $roles = $work['_roles'];
        require dirname(__DIR__, 2) . '/resources_work_diary.php';
    }
}
