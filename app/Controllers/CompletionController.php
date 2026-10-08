<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;

final class CompletionController
{
    private function requireAuth(): array
    {
        if (!Auth::check()) {
            header('Location: /login');
            exit;
        }
        return Auth::user();
    }

    private function loadWork(int $workId, int $userId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT w.*, c.name condominium_name FROM works w JOIN condominiums c ON c.id=w.condominium_id WHERE w.id=?');
        $stmt->execute([$workId]);
        $work = $stmt->fetch();
        if (!$work) {
            http_response_code(404);
            exit('Obra não encontrada.');
        }
        $stmt = $pdo->prepare('SELECT r.code FROM condominium_user cu JOIN roles r ON r.id=cu.role_id WHERE cu.condominium_id=? AND cu.user_id=? AND cu.active=1');
        $stmt->execute([$work['condominium_id'], $userId]);
        $roles = array_column($stmt->fetchAll(), 'code');
        if (!$roles) {
            http_response_code(403);
            exit('Acesso não autorizado.');
        }
        if (in_array('WORK_RESPONSIBLE', $roles, true) && count(array_diff($roles, ['WORK_RESPONSIBLE'])) === 0 && (int)$work['responsible_user_id'] !== $userId) {
            http_response_code(403);
            exit('Acesso não autorizado.');
        }
        $work['_roles'] = $roles;
        return $work;
    }

    private function event(int $workId, int $userId, string $type, string $title, ?string $description = null): void
    {
        $stmt = Database::connection()->prepare('INSERT INTO work_events(work_id,user_id,event_type,title,description) VALUES(?,?,?,?,?)');
        $stmt->execute([$workId,$userId,$type,$title,$description]);
    }

    public function show(): void
    {
        $user = $this->requireAuth();
        $work = $this->loadWork((int)($_GET['id'] ?? 0), (int)$user['id']);
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM non_conformities WHERE work_id=? AND status="OPEN"');
        $stmt->execute([$work['id']]);
        $openNc = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare('SELECT ct.*,u.name inspector_name FROM work_completion_terms ct JOIN users u ON u.id=ct.inspector_user_id WHERE ct.work_id=?');
        $stmt->execute([$work['id']]);
        $completion = $stmt->fetch() ?: null;

        $roles = $work['_roles'];
        $canInspect = (bool)array_intersect($roles, ['ADMIN','SYNDIC','MANAGER','INSPECTOR']);
        $canStart = $canInspect && in_array($work['status'], ['IN_PROGRESS','NOTIFIED'], true) && $openNc === 0;
        $canRecord = $canInspect && $work['status'] === 'COMPLETION_INSPECTION';

        require dirname(__DIR__,2) . '/resources_completion.php';
    }

    public function start(): void
    {
        $user = $this->requireAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::validate($_POST['_token'] ?? null)) {
            http_response_code(419);
            exit('Sessão expirada.');
        }
        $work = $this->loadWork((int)($_POST['work_id'] ?? 0), (int)$user['id']);
        if (!array_intersect($work['_roles'], ['ADMIN','SYNDIC','MANAGER','INSPECTOR']) || !in_array($work['status'], ['IN_PROGRESS','NOTIFIED'], true)) {
            http_response_code(422);
            exit('A obra não está disponível para vistoria final.');
        }
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM non_conformities WHERE work_id=? AND status="OPEN"');
        $stmt->execute([$work['id']]);
        if ((int)$stmt->fetchColumn() > 0) {
            http_response_code(422);
            exit('Existem não conformidades abertas. Corrija-as antes da vistoria final.');
        }
        $pdo->prepare('UPDATE works SET status="COMPLETION_INSPECTION" WHERE id=?')->execute([$work['id']]);
        $this->event((int)$work['id'], (int)$user['id'], 'COMPLETION_INSPECTION_STARTED', 'Vistoria final iniciada');
        header('Location: /work/completion?id=' . (int)$work['id'] . '&started=1');
        exit;
    }

    public function record(): void
    {
        $user = $this->requireAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::validate($_POST['_token'] ?? null)) {
            http_response_code(419);
            exit('Sessão expirada.');
        }
        $work = $this->loadWork((int)($_POST['work_id'] ?? 0), (int)$user['id']);
        if (!array_intersect($work['_roles'], ['ADMIN','SYNDIC','MANAGER','INSPECTOR']) || $work['status'] !== 'COMPLETION_INSPECTION') {
            http_response_code(422);
            exit('Vistoria final não disponível.');
        }
        $result = (string)($_POST['result'] ?? '');
        $notes = trim((string)($_POST['notes'] ?? '')) ?: null;
        if (!in_array($result, ['APPROVED','CORRECTION_REQUIRED'], true)) {
            http_response_code(422);
            exit('Resultado inválido.');
        }
        if ($result === 'CORRECTION_REQUIRED' && !$notes) {
            http_response_code(422);
            exit('Informe o motivo da correção necessária.');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO work_completion_terms(work_id,inspector_user_id,result,notes,completed_at) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE inspector_user_id=VALUES(inspector_user_id),result=VALUES(result),notes=VALUES(notes),completed_at=VALUES(completed_at)');
        $completedAt = $result === 'APPROVED' ? date('Y-m-d H:i:s') : null;
        $stmt->execute([$work['id'],$user['id'],$result,$notes,$completedAt]);
        $target = $result === 'APPROVED' ? 'COMPLETED' : 'IN_PROGRESS';
        $pdo->prepare('UPDATE works SET status=? WHERE id=?')->execute([$target,$work['id']]);
        $title = $result === 'APPROVED' ? 'Obra concluída após vistoria final' : 'Vistoria final solicitou correções';
        $this->event((int)$work['id'], (int)$user['id'], 'COMPLETION_RECORDED', $title, $notes);
        header('Location: /work/completion?id=' . (int)$work['id'] . '&recorded=1');
        exit;
    }
}
