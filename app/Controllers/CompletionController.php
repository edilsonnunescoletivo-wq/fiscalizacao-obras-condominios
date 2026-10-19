<?php

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\WorkAccess;

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

    private function event(int $workId, int $userId, string $type, string $title, ?string $description = null): void
    {
        $stmt = Database::connection()->prepare('INSERT INTO work_events(work_id,user_id,event_type,title,description) VALUES(?,?,?,?,?)');
        $stmt->execute([$workId,$userId,$type,$title,$description]);
    }

    private function rules(int $condoId): array
    {
        $stmt = Database::connection()->prepare('SELECT require_final_inspection,block_completion_with_open_nc FROM condominium_rules WHERE condominium_id=?');
        $stmt->execute([$condoId]);
        return $stmt->fetch() ?: ['require_final_inspection'=>1,'block_completion_with_open_nc'=>1];
    }

    private function unresolvedCount(int $workId): int
    {
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM non_conformities WHERE work_id=? AND status<>"CLOSED"');
        $stmt->execute([$workId]);
        return (int)$stmt->fetchColumn();
    }

    public function show(): void
    {
        $user = $this->requireAuth();
        $work = WorkAccess::load((int)($_GET['id'] ?? 0), (int)$user['id']);
        $pdo = Database::connection();
        $rules = $this->rules((int)$work['condominium_id']);
        $openNc = $this->unresolvedCount((int)$work['id']);

        $stmt = $pdo->prepare('SELECT ct.*,u.name inspector_name FROM work_completion_terms ct JOIN users u ON u.id=ct.inspector_user_id WHERE ct.work_id=?');
        $stmt->execute([$work['id']]);
        $completion = $stmt->fetch() ?: null;

        $roles = $work['_roles'];
        $canInspect = WorkAccess::canInspect($roles);
        $canManage = WorkAccess::canManage($roles);
        $requiresFinalInspection = !isset($rules['require_final_inspection']) || (int)$rules['require_final_inspection'] === 1;
        $blockWithOpenNc = !isset($rules['block_completion_with_open_nc']) || (int)$rules['block_completion_with_open_nc'] === 1;
        $blockedByNc = $blockWithOpenNc && $openNc > 0;
        $operational = in_array($work['status'], ['IN_PROGRESS','NOTIFIED'], true);
        $canStart = $requiresFinalInspection && $canInspect && $operational && !$blockedByNc;
        $canCompleteDirectly = !$requiresFinalInspection && $canManage && $operational && !$blockedByNc;
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
        $work = WorkAccess::load((int)($_POST['work_id'] ?? 0), (int)$user['id']);
        if (!WorkAccess::canInspect($work['_roles']) || !in_array($work['status'], ['IN_PROGRESS','NOTIFIED'], true)) {
            http_response_code(422);
            exit('A obra não está disponível para vistoria final.');
        }

        $rules = $this->rules((int)$work['condominium_id']);
        if (isset($rules['require_final_inspection']) && (int)$rules['require_final_inspection'] === 0) {
            http_response_code(422);
            exit('Este condomínio dispensou a vistoria final. Use a conclusão administrativa.');
        }
        if ((int)($rules['block_completion_with_open_nc'] ?? 1) === 1 && $this->unresolvedCount((int)$work['id']) > 0) {
            http_response_code(422);
            exit('Existem não conformidades ainda não encerradas. Regularize-as antes da vistoria final.');
        }

        $pdo = Database::connection();
        $pdo->prepare('UPDATE works SET status="COMPLETION_INSPECTION" WHERE id=?')->execute([$work['id']]);
        $this->event((int)$work['id'], (int)$user['id'], 'COMPLETION_INSPECTION_STARTED', 'Vistoria final iniciada');
        Audit::log((int)$user['id'], (int)$work['condominium_id'], 'work', (int)$work['id'], 'COMPLETION_INSPECTION_STARTED');
        header('Location: /work/completion?id=' . (int)$work['id'] . '&started=1');
        exit;
    }

    public function completeDirectly(): void
    {
        $user = $this->requireAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::validate($_POST['_token'] ?? null)) {
            http_response_code(419);
            exit('Sessão expirada.');
        }
        $work = WorkAccess::load((int)($_POST['work_id'] ?? 0), (int)$user['id']);
        if (!WorkAccess::canManage($work['_roles']) || !in_array($work['status'], ['IN_PROGRESS','NOTIFIED'], true)) {
            http_response_code(403);
            exit('Acesso não autorizado.');
        }
        $rules = $this->rules((int)$work['condominium_id']);
        if ((int)($rules['require_final_inspection'] ?? 1) === 1) {
            http_response_code(422);
            exit('Este condomínio exige vistoria final.');
        }
        if ((int)($rules['block_completion_with_open_nc'] ?? 1) === 1 && $this->unresolvedCount((int)$work['id']) > 0) {
            http_response_code(422);
            exit('Existem não conformidades ainda não encerradas.');
        }

        $notes = trim((string)($_POST['notes'] ?? '')) ?: 'Conclusão administrativa com vistoria final dispensada pela configuração do condomínio.';
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO work_completion_terms(work_id,inspector_user_id,result,notes,completed_at) VALUES(?,?,"APPROVED",?,NOW()) ON DUPLICATE KEY UPDATE inspector_user_id=VALUES(inspector_user_id),result="APPROVED",notes=VALUES(notes),completed_at=NOW()');
            $stmt->execute([$work['id'],$user['id'],$notes]);
            $pdo->prepare('UPDATE works SET status="COMPLETED" WHERE id=?')->execute([$work['id']]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        $this->event((int)$work['id'], (int)$user['id'], 'COMPLETION_RECORDED', 'Obra concluída administrativamente', $notes);
        Audit::log((int)$user['id'], (int)$work['condominium_id'], 'work', (int)$work['id'], 'COMPLETED_WITHOUT_FINAL_INSPECTION', ['notes'=>$notes]);
        header('Location: /work/completion?id=' . (int)$work['id'] . '&recorded=1');
        exit;
    }

    public function record(): void
    {
        $user = $this->requireAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::validate($_POST['_token'] ?? null)) {
            http_response_code(419);
            exit('Sessão expirada.');
        }
        $work = WorkAccess::load((int)($_POST['work_id'] ?? 0), (int)$user['id']);
        if (!WorkAccess::canInspect($work['_roles']) || $work['status'] !== 'COMPLETION_INSPECTION') {
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

        $rules = $this->rules((int)$work['condominium_id']);
        if ($result === 'APPROVED' && (int)($rules['block_completion_with_open_nc'] ?? 1) === 1 && $this->unresolvedCount((int)$work['id']) > 0) {
            http_response_code(422);
            exit('A obra recebeu nova pendência durante a vistoria final. Encerre-a antes de concluir.');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO work_completion_terms(work_id,inspector_user_id,result,notes,completed_at) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE inspector_user_id=VALUES(inspector_user_id),result=VALUES(result),notes=VALUES(notes),completed_at=VALUES(completed_at)');
        $completedAt = $result === 'APPROVED' ? date('Y-m-d H:i:s') : null;
        $stmt->execute([$work['id'],$user['id'],$result,$notes,$completedAt]);
        $target = $result === 'APPROVED' ? 'COMPLETED' : 'IN_PROGRESS';
        $pdo->prepare('UPDATE works SET status=? WHERE id=?')->execute([$target,$work['id']]);
        $title = $result === 'APPROVED' ? 'Obra concluída após vistoria final' : 'Vistoria final solicitou correções';
        $this->event((int)$work['id'], (int)$user['id'], 'COMPLETION_RECORDED', $title, $notes);
        Audit::log((int)$user['id'], (int)$work['condominium_id'], 'work', (int)$work['id'], 'COMPLETION_RECORDED', ['result'=>$result,'target_status'=>$target,'notes'=>$notes]);
        header('Location: /work/completion?id=' . (int)$work['id'] . '&recorded=1');
        exit;
    }
}
