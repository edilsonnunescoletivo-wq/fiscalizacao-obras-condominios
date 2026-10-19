<?php

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\WorkAccess;

final class InspectionAcceptanceController
{
    private const DECLARATIONS = [
        'INSPECTOR' => 'Declaro que revisei os dados, checklist, observações e evidências desta fiscalização e confirmo o registro técnico sob minha responsabilidade.',
        'WORK_RESPONSIBLE' => 'Declaro que tomei ciência do conteúdo desta fiscalização, incluindo apontamentos, evidências e eventuais orientações de correção, sem que este aceite represente concordância técnica automática.',
        'MANAGEMENT' => 'Declaro que revisei o registro desta fiscalização para fins de validação administrativa e rastreabilidade interna.',
    ];

    private const TYPE_LABELS = [
        'INSPECTOR' => 'Fiscal',
        'WORK_RESPONSIBLE' => 'Responsável pela obra',
        'MANAGEMENT' => 'Gestão',
    ];

    public function show(): void
    {
        if (!Auth::check()) {
            header('Location: /login');
            exit;
        }

        $user = Auth::user();
        $inspectionId = (int)($_GET['id'] ?? 0);
        $returnTo = (string)($_GET['return_to'] ?? 'photos') === 'responsible' ? 'responsible' : 'photos';
        $pdo = Database::connection();

        $stmt = $pdo->prepare(
            'SELECT i.*,u.name inspector_name,w.unit,w.owner_name,w.work_type,w.condominium_id,w.responsible_user_id,c.name condominium_name
             FROM inspections i
             JOIN users u ON u.id=i.inspector_user_id
             JOIN works w ON w.id=i.work_id
             JOIN condominiums c ON c.id=w.condominium_id
             WHERE i.id=?'
        );
        $stmt->execute([$inspectionId]);
        $inspection = $stmt->fetch();
        if (!$inspection) {
            http_response_code(404);
            exit('Fiscalização não encontrada.');
        }

        $work = WorkAccess::load((int)$inspection['work_id'], (int)$user['id']);
        $roles = $work['_roles'];
        $featureReady = (bool)$pdo->query("SHOW TABLES LIKE 'inspection_acceptances'")->fetchColumn();
        $acceptances = [];
        $availableTypes = [];

        if ($featureReady) {
            $stmt = $pdo->prepare('SELECT * FROM inspection_acceptances WHERE inspection_id=? ORDER BY signed_at,id');
            $stmt->execute([$inspectionId]);
            $acceptances = $stmt->fetchAll();

            $eligible = [];
            if ((int)$inspection['inspector_user_id'] === (int)$user['id']) $eligible[] = 'INSPECTOR';
            if ((int)$inspection['responsible_user_id'] === (int)$user['id'] && in_array('WORK_RESPONSIBLE', $roles, true)) $eligible[] = 'WORK_RESPONSIBLE';
            if (WorkAccess::canManage($roles)) $eligible[] = 'MANAGEMENT';

            $alreadySigned = [];
            foreach ($acceptances as $acceptance) {
                if ((int)$acceptance['signer_user_id'] === (int)$user['id']) {
                    $alreadySigned[] = (string)$acceptance['signer_type'];
                }
            }
            $availableTypes = array_values(array_diff(array_unique($eligible), array_unique($alreadySigned)));
        }

        $typeLabels = self::TYPE_LABELS;
        $declarations = self::DECLARATIONS;
        $backUrl = $returnTo === 'responsible'
            ? '/responsible/work?id=' . (int)$inspection['work_id'] . '#fiscalizacoes'
            : '/inspection/photos?id=' . $inspectionId;

        require dirname(__DIR__, 2) . '/resources_inspection_acceptance.php';
    }

    public function sign(): void
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
        $inspectionId = (int)($_POST['inspection_id'] ?? 0);
        $signerType = (string)($_POST['signer_type'] ?? '');
        $typedName = trim((string)($_POST['typed_name'] ?? ''));
        $confirmed = (string)($_POST['accept'] ?? '') === '1';
        $returnTo = (string)($_POST['return_to'] ?? 'photos') === 'responsible' ? 'responsible' : 'photos';

        if ($inspectionId <= 0 || !$confirmed || mb_strlen($typedName) < 3 || mb_strlen($typedName) > 150) {
            http_response_code(422);
            exit('Confirme a declaração e informe seu nome completo.');
        }
        if (!isset(self::DECLARATIONS[$signerType])) {
            http_response_code(422);
            exit('Tipo de aceite inválido.');
        }

        $pdo = Database::connection();
        if (!$pdo->query("SHOW TABLES LIKE 'inspection_acceptances'")->fetchColumn()) {
            http_response_code(503);
            exit('O recurso de aceite eletrônico ainda não foi ativado neste ambiente.');
        }

        $stmt = $pdo->prepare(
            'SELECT i.id,i.work_id,i.inspector_user_id,w.condominium_id,w.responsible_user_id
             FROM inspections i
             JOIN works w ON w.id=i.work_id
             WHERE i.id=?'
        );
        $stmt->execute([$inspectionId]);
        $inspection = $stmt->fetch();
        if (!$inspection) {
            http_response_code(404);
            exit('Fiscalização não encontrada.');
        }

        $work = WorkAccess::load((int)$inspection['work_id'], (int)$user['id'], true);
        $roles = $work['_roles'];
        $allowed = match ($signerType) {
            'INSPECTOR' => (int)$inspection['inspector_user_id'] === (int)$user['id'],
            'WORK_RESPONSIBLE' => (int)$inspection['responsible_user_id'] === (int)$user['id'] && in_array('WORK_RESPONSIBLE', $roles, true),
            'MANAGEMENT' => WorkAccess::canManage($roles),
            default => false,
        };
        if (!$allowed) {
            http_response_code(403);
            exit('Você não pode registrar este tipo de aceite.');
        }

        $declaration = self::DECLARATIONS[$signerType];
        $stmt = $pdo->prepare('SELECT id FROM inspection_acceptances WHERE inspection_id=? AND signer_user_id=? AND signer_type=? LIMIT 1');
        $stmt->execute([$inspectionId, $user['id'], $signerType]);
        if ($stmt->fetchColumn()) {
            $this->redirect($returnTo, (int)$inspection['work_id'], $inspectionId, 'signed_existing');
        }

        $signedAt = (string)$pdo->query('SELECT DATE_FORMAT(NOW(), "%Y-%m-%d %H:%i:%s")')->fetchColumn();
        $ipAddress = trim((string)($_SERVER['REMOTE_ADDR'] ?? '')) ?: null;
        if ($ipAddress !== null) $ipAddress = mb_substr($ipAddress, 0, 45);
        $userAgent = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
        $userAgentHash = $userAgent !== '' ? hash('sha256', $userAgent) : null;
        $accountName = trim((string)($user['name'] ?? ''));
        $email = trim((string)($user['email'] ?? '')) ?: null;

        $canonical = implode('|', [
            'inspection=' . $inspectionId,
            'work=' . (int)$inspection['work_id'],
            'user=' . (int)$user['id'],
            'type=' . $signerType,
            'account=' . $accountName,
            'typed=' . $typedName,
            'declaration=' . $declaration,
            'signed_at=' . $signedAt,
        ]);
        $secret = trim((string)\env('ACCEPTANCE_HMAC_KEY', ''));
        $signatureMethod = $secret !== '' ? 'HMAC_SHA256' : 'SHA256';
        $signatureHash = $secret !== '' ? hash_hmac('sha256', $canonical, $secret) : hash('sha256', $canonical);

        $stmt = $pdo->prepare(
            'INSERT INTO inspection_acceptances
             (inspection_id,work_id,signer_user_id,signer_type,account_name_snapshot,email_snapshot,typed_name,declaration,signature_method,signature_hash,ip_address,user_agent_hash,signed_at)
             VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $inspectionId,(int)$inspection['work_id'],(int)$user['id'],$signerType,$accountName,$email,$typedName,$declaration,
            $signatureMethod,$signatureHash,$ipAddress,$userAgentHash,$signedAt,
        ]);
        $acceptanceId = (int)$pdo->lastInsertId();

        $pdo->prepare('INSERT INTO work_events(work_id,user_id,event_type,title,description) VALUES(?,?,?,?,?)')
            ->execute([(int)$inspection['work_id'],(int)$user['id'],'INSPECTION_ACCEPTED','Aceite eletrônico da fiscalização',(self::TYPE_LABELS[$signerType] ?? $signerType).' · Vistoria #'.$inspectionId]);
        Audit::log((int)$user['id'], (int)$inspection['condominium_id'], 'inspection_acceptance', $acceptanceId, 'SIGNED', [
            'inspection_id'=>$inspectionId,
            'work_id'=>(int)$inspection['work_id'],
            'signer_type'=>$signerType,
            'signature_method'=>$signatureMethod,
            'signature_hash'=>$signatureHash,
        ]);

        $this->redirect($returnTo, (int)$inspection['work_id'], $inspectionId, 'signed');
    }

    private function redirect(string $returnTo, int $workId, int $inspectionId, string $flag): never
    {
        $url = '/inspection/acceptance?id=' . $inspectionId . '&return_to=' . ($returnTo === 'responsible' ? 'responsible' : 'photos') . '&' . $flag . '=1';
        header('Location: ' . $url);
        exit;
    }
}
