<?php

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\WorkAccess;

final class OperationsController
{
    private function requireAuth(): array
    {
        if (!Auth::check()) { header('Location: /login'); exit; }
        return Auth::user();
    }

    private function loadWork(int $workId, array $user): array
    {
        return WorkAccess::load($workId, (int)$user['id']);
    }

    private function requirePost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::validate($_POST['_token'] ?? null)) {
            http_response_code(419);
            exit('Sessão expirada. Atualize a página e tente novamente.');
        }
    }

    private function event(int $workId, int $userId, string $type, string $title, ?string $description = null): void
    {
        Database::connection()->prepare('INSERT INTO work_events (work_id, user_id, event_type, title, description) VALUES (?,?,?,?,?)')->execute([$workId, $userId, $type, $title, $description]);
    }

    private function rules(int $condoId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM condominium_rules WHERE condominium_id=?');
        $stmt->execute([$condoId]);
        return $stmt->fetch() ?: [
            'default_notification_days'=>5,'warning_days'=>3,'adjustment_days'=>5,'suspension_days'=>0,'embargo_days'=>0,
            'require_photo_on_inspection'=>0,'require_photo_on_non_conformity'=>0,'require_final_inspection'=>1,
            'block_completion_with_open_nc'=>1,'allow_inspector_warning'=>1,'allow_inspector_adjustment'=>1,
        ];
    }

    public function createInspection(): void
    {
        $this->requirePost();
        $user = $this->requireAuth();
        $workId = (int)($_POST['work_id'] ?? 0);
        $work = $this->loadWork($workId, $user);
        if (!WorkAccess::canInspect($work['_roles'])) { http_response_code(403); exit('Acesso não autorizado'); }
        if (!in_array($work['status'], ['IN_PROGRESS','NOTIFIED','SUSPENDED','EMBARGOED'], true)) { http_response_code(422); exit('Fiscalizações operacionais só podem ser registradas após o início da obra.'); }

        $stage = trim((string)($_POST['stage'] ?? '')) ?: null;
        $notes = trim((string)($_POST['notes'] ?? '')) ?: null;
        $result = (string)($_POST['result'] ?? 'COMPLIANT');
        if (!in_array($result, ['COMPLIANT','WITH_ISSUES','CRITICAL'], true)) { http_response_code(422); exit('Resultado inválido.'); }

        $checklist = [
            'protection'=>!empty($_POST['check_protection']),
            'cleanliness'=>!empty($_POST['check_cleanliness']),
            'ppe'=>!empty($_POST['check_ppe']),
            'project_compliance'=>!empty($_POST['check_project']),
        ];
        foreach ((array)($_POST['custom_check'] ?? []) as $id => $value) $checklist['custom_' . (int)$id] = (bool)$value;

        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO inspections (work_id, inspector_user_id, inspected_at, stage, notes, checklist_json, result) VALUES (?,?,NOW(),?,?,?,?)');
        $stmt->execute([$workId, $user['id'], $stage, $notes, json_encode($checklist, JSON_UNESCAPED_UNICODE), $result]);
        $inspectionId = (int)$pdo->lastInsertId();
        $this->event($workId, (int)$user['id'], 'INSPECTION_CREATED', 'Fiscalização registrada', 'Vistoria #' . $inspectionId . ($stage ? ' · ' . $stage : ''));
        Audit::log((int)$user['id'], (int)$work['condominium_id'], 'inspection', $inspectionId, 'CREATED', ['work_id'=>$workId,'result'=>$result,'stage'=>$stage]);
        header('Location: /work?id=' . $workId . '&inspection=1');
        exit;
    }

    public function createNonConformity(): void
    {
        $this->requirePost();
        $user = $this->requireAuth();
        $workId = (int)($_POST['work_id'] ?? 0);
        $inspectionId = (int)($_POST['inspection_id'] ?? 0) ?: null;
        $work = $this->loadWork($workId, $user);
        if (!WorkAccess::canInspect($work['_roles'])) { http_response_code(403); exit('Acesso não autorizado'); }
        if (!in_array($work['status'], ['IN_PROGRESS','NOTIFIED','SUSPENDED','EMBARGOED'], true)) { http_response_code(422); exit('Não conformidades operacionais só podem ser registradas após o início da obra.'); }

        $title = trim((string)($_POST['title'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $severity = (string)($_POST['severity'] ?? 'MEDIUM');
        $deadline = ($_POST['corrective_deadline'] ?? '') ?: null;
        if ($title === '' || $description === '' || !in_array($severity, ['LOW','MEDIUM','HIGH','CRITICAL'], true)) { http_response_code(422); exit('Preencha os dados da não conformidade.'); }

        $rules = $this->rules((int)$work['condominium_id']);
        $evidenceFile = $_FILES['nc_photo'] ?? null;
        $requiresPhoto = !empty($rules['require_photo_on_non_conformity']);
        if ($requiresPhoto && (!$evidenceFile || ($evidenceFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE)) {
            http_response_code(422);
            exit('Este condomínio exige foto ao registrar uma não conformidade.');
        }
        if ($evidenceFile && ($evidenceFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $this->validateNonConformityPhoto($evidenceFile);
        }

        $pdo = Database::connection();
        if ($inspectionId) {
            $stmt = $pdo->prepare('SELECT 1 FROM inspections WHERE id=? AND work_id=?');
            $stmt->execute([$inspectionId, $workId]);
            if (!$stmt->fetchColumn()) { http_response_code(422); exit('Fiscalização inválida para esta obra.'); }
        }

        $pdo->beginTransaction();
        $storedEvidencePath = null;
        try {
            $stmt = $pdo->prepare('INSERT INTO non_conformities (inspection_id, work_id, severity, title, description, corrective_deadline, created_by) VALUES (?,?,?,?,?,?,?)');
            $stmt->execute([$inspectionId, $workId, $severity, $title, $description, $deadline, $user['id']]);
            $ncId = (int)$pdo->lastInsertId();

            $evidenceId = null;
            if ($evidenceFile && ($evidenceFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                [$evidenceId, $storedEvidencePath] = $this->storeNonConformityPhoto($ncId, $workId, (int)$user['id'], $evidenceFile);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($storedEvidencePath && is_file($storedEvidencePath)) @unlink($storedEvidencePath);
            throw $e;
        }

        $stmt = $pdo->prepare('SELECT suggested_action FROM severity_action_rules WHERE condominium_id=? AND severity=? AND active=1 LIMIT 1');
        $stmt->execute([$work['condominium_id'],$severity]);
        $suggested = (string)($stmt->fetchColumn() ?: 'NONE');
        $actionLabels=['NONE'=>'Nenhuma ação automática','WARNING'=>'Sugerir advertência','ADJUSTMENT'=>'Sugerir adequação','SUSPENSION'=>'Sugerir suspensão','EMBARGO'=>'Sugerir embargo'];
        $this->event($workId, (int)$user['id'], 'NON_CONFORMITY_CREATED', 'Não conformidade registrada', $title . ' · ' . $severity . ' · ' . ($actionLabels[$suggested] ?? $suggested));
        Audit::log((int)$user['id'], (int)$work['condominium_id'], 'non_conformity', $ncId, 'CREATED', [
            'work_id'=>$workId,'severity'=>$severity,'suggested_action'=>$suggested,'deadline'=>$deadline,
            'evidence_id'=>$evidenceId ?? null,'photo_required'=>$requiresPhoto,
        ]);
        header('Location: /work?id=' . $workId . '&nc=1&suggested=' . rawurlencode($suggested));
        exit;
    }

    private function validateNonConformityPhoto(array $file): void
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > 10 * 1024 * 1024) {
            http_response_code(422);
            exit('Foto inválida ou maior que 10 MB.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!in_array($mime, ['image/jpeg','image/png'], true)) {
            http_response_code(422);
            exit('A evidência inicial da não conformidade deve ser JPG ou PNG.');
        }
    }

    private function storeNonConformityPhoto(int $ncId, int $workId, int $userId, array $file): array
    {
        $this->validateNonConformityPhoto($file);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $ext = $mime === 'image/jpeg' ? 'jpg' : 'png';
        $folder = dirname(__DIR__,2) . '/storage/uploads/corrections/' . $ncId;
        if (!is_dir($folder) && !mkdir($folder,0775,true) && !is_dir($folder)) {
            throw new \RuntimeException('Falha ao preparar armazenamento da evidência.');
        }
        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        $fullPath = $folder . '/' . $name;
        if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
            throw new \RuntimeException('Falha ao salvar a foto da não conformidade.');
        }
        $relative = 'storage/uploads/corrections/' . $ncId . '/' . $name;
        $stmt = Database::connection()->prepare('INSERT INTO non_conformity_evidence(non_conformity_id,work_id,original_name,stored_path,mime_type,caption,uploaded_by) VALUES(?,?,?,?,?,?,?)');
        $stmt->execute([$ncId,$workId,basename((string)$file['name']),$relative,$mime,'Registro inicial da não conformidade',$userId]);
        return [(int)Database::connection()->lastInsertId(), $fullPath];
    }

    public function closeNonConformity(): void
    {
        $this->requirePost();
        $user = $this->requireAuth();
        $workId = (int)($_POST['work_id'] ?? 0);
        $ncId = (int)($_POST['non_conformity_id'] ?? 0);
        $status = (string)($_POST['status'] ?? 'CORRECTED');
        $work = $this->loadWork($workId, $user);
        if (!WorkAccess::canInspect($work['_roles']) || !in_array($status, ['CORRECTED','CLOSED'], true)) { http_response_code(403); exit('Acesso não autorizado'); }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE non_conformities SET status=?, resolved_by=?, resolved_at=NOW() WHERE id=? AND work_id=? AND status="OPEN"');
        $stmt->execute([$status, $user['id'], $ncId, $workId]);
        if ($stmt->rowCount() !== 1) { http_response_code(422); exit('Não conformidade não está disponível para encerramento.'); }
        $this->event($workId, (int)$user['id'], 'NON_CONFORMITY_UPDATED', $status === 'CLOSED' ? 'Não conformidade encerrada' : 'Não conformidade corrigida');
        Audit::log((int)$user['id'], (int)$work['condominium_id'], 'non_conformity', $ncId, 'STATUS_CHANGED', ['work_id'=>$workId,'status'=>$status]);
        header('Location: /work?id=' . $workId . '&nc_updated=1');
        exit;
    }

    public function createNotification(): void
    {
        $this->requirePost();
        $user = $this->requireAuth();
        $workId = (int)($_POST['work_id'] ?? 0);
        $work = $this->loadWork($workId, $user);
        if (!WorkAccess::canInspect($work['_roles'])) { http_response_code(403); exit('Acesso não autorizado'); }

        $type = (string)($_POST['type'] ?? 'IRREGULARITY');
        if (!in_array($type, ['IRREGULARITY','WARNING','ADJUSTMENT','SUSPENSION','EMBARGO','RELEASE'], true)) { http_response_code(422); exit('Tipo de notificação inválido.'); }

        $pdo = Database::connection();
        $rules = $this->rules((int)$work['condominium_id']);
        $stmt = $pdo->prepare('SELECT * FROM notification_templates WHERE condominium_id=? AND type=? AND active=1 LIMIT 1');
        $stmt->execute([$work['condominium_id'],$type]);
        $template = $stmt->fetch() ?: [];

        $isInspectorOnly = in_array('INSPECTOR',$work['_roles'],true) && !array_intersect($work['_roles'],['ADMIN','SYNDIC','MANAGER']);
        if ($isInspectorOnly && $type === 'WARNING' && empty($rules['allow_inspector_warning'])) { http_response_code(403); exit('Este condomínio não autoriza o fiscal a emitir advertência.'); }
        if ($isInspectorOnly && $type === 'ADJUSTMENT' && empty($rules['allow_inspector_adjustment'])) { http_response_code(403); exit('Este condomínio não autoriza o fiscal a solicitar adequação.'); }
        if (in_array($type, ['SUSPENSION','EMBARGO','RELEASE'], true) && !WorkAccess::canManage($work['_roles'])) { http_response_code(403); exit('Este tipo de notificação exige perfil administrativo.'); }
        if (in_array($type, ['SUSPENSION','EMBARGO'], true) && !in_array($work['status'], ['IN_PROGRESS','NOTIFIED'], true)) { http_response_code(422); exit('A obra precisa estar em andamento para ser suspensa ou embargada.'); }
        if ($type === 'RELEASE' && !in_array($work['status'], ['SUSPENDED','EMBARGOED','NOTIFIED'], true)) { http_response_code(422); exit('Somente obra suspensa ou embargada pode ser liberada.'); }
        if (!in_array($type, ['SUSPENSION','EMBARGO','RELEASE'], true) && !in_array($work['status'], ['IN_PROGRESS','NOTIFIED'], true)) { http_response_code(422); exit('Notificações operacionais só podem ser emitidas para obra em andamento.'); }

        $reason = trim((string)($_POST['reason'] ?? '')) ?: trim((string)($template['default_reason'] ?? ''));
        $body = trim((string)($_POST['body'] ?? '')) ?: trim((string)($template['default_body'] ?? ''));
        $deadline = ($_POST['deadline'] ?? '') ?: null;
        if (!$deadline && $type !== 'RELEASE') {
            $days = isset($template['default_deadline_days']) ? (int)$template['default_deadline_days'] : match($type) {
                'WARNING'=>(int)$rules['warning_days'], 'ADJUSTMENT'=>(int)$rules['adjustment_days'],
                'SUSPENSION'=>(int)$rules['suspension_days'], 'EMBARGO'=>(int)$rules['embargo_days'],
                default=>(int)$rules['default_notification_days']
            };
            if ($days > 0) $deadline = date('Y-m-d H:i:s', strtotime('+' . $days . ' days'));
        }
        if ($reason === '' || $body === '') { http_response_code(422); exit('Preencha os dados da notificação ou configure um modelo padrão para este condomínio.'); }

        $pdo->beginTransaction();
        try {
            $tmp = 'TMP-' . bin2hex(random_bytes(8));
            $stmt = $pdo->prepare('INSERT INTO notifications (work_id, type, number, reason, body, deadline, status, created_by, issued_at) VALUES (?,?,?,?,?,?,"ISSUED",?,NOW())');
            $stmt->execute([$workId, $type, $tmp, $reason, $body, $deadline, $user['id']]);
            $notificationId = (int)$pdo->lastInsertId();
            $number = str_pad((string)$notificationId, 4, '0', STR_PAD_LEFT) . '/' . date('Y');
            $pdo->prepare('UPDATE notifications SET number=? WHERE id=?')->execute([$number, $notificationId]);

            $nextStatus = match ($type) {
                'SUSPENSION' => 'SUSPENDED',
                'EMBARGO' => 'EMBARGOED',
                'RELEASE' => 'IN_PROGRESS',
                default => null,
            };
            if ($nextStatus !== null) {
                $pdo->prepare('UPDATE works SET status=? WHERE id=?')->execute([$nextStatus, $workId]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        $labels = ['SUSPENSION'=>'Obra suspensa','EMBARGO'=>'Obra embargada','RELEASE'=>'Obra liberada','IRREGULARITY'=>'Notificação emitida','WARNING'=>'Advertência emitida','ADJUSTMENT'=>'Solicitação de adequação emitida'];
        $this->event($workId, (int)$user['id'], 'NOTIFICATION_ISSUED', $labels[$type] ?? 'Notificação emitida', $number . ' · ' . $reason);
        Audit::log((int)$user['id'], (int)$work['condominium_id'], 'notification', $notificationId, 'ISSUED', ['work_id'=>$workId,'type'=>$type,'number'=>$number,'deadline'=>$deadline,'work_status_changed_to'=>$nextStatus]);
        header('Location: /work?id=' . $workId . '&notification=1');
        exit;
    }
}
