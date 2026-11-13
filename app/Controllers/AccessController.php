<?php

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\WorkAccess;

final class AccessController
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
        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO work_events (work_id, user_id, event_type, title, description) VALUES (?,?,?,?,?)');
        $stmt->execute([$workId, $userId, $type, $title, $description]);
    }

    public function invite(): void
    {
        $user = $this->requireAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::validate($_POST['_token'] ?? null)) {
            http_response_code(419);
            exit('Sessão expirada.');
        }
        $workId = (int)($_POST['work_id'] ?? 0);
        $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(422);
            exit('E-mail inválido.');
        }

        $work = WorkAccess::load($workId, (int)$user['id']);
        if (!WorkAccess::canManage($work['_roles'])) {
            http_response_code(403);
            exit('Acesso não autorizado.');
        }

        $pdo = Database::connection();
        $rawToken = bin2hex(random_bytes(32));
        $hash = hash('sha256', $rawToken);
        $pdo->prepare('UPDATE work_access_invites SET expires_at = NOW() WHERE work_id = ? AND email = ? AND accepted_at IS NULL')->execute([$workId, $email]);
        $stmt = $pdo->prepare('INSERT INTO work_access_invites(work_id,email,token_hash,expires_at,created_by) VALUES(?,?,?,DATE_ADD(NOW(), INTERVAL 72 HOUR),?)');
        $stmt->execute([$workId, $email, $hash, $user['id']]);
        $inviteId = (int)$pdo->lastInsertId();

        $this->event($workId, (int)$user['id'], 'WORK_RESPONSIBLE_INVITED', 'Convite do responsável gerado', $email);
        Audit::log((int)$user['id'], (int)$work['condominium_id'], 'work_access_invite', $inviteId, 'CREATED', [
            'work_id' => $workId,
            'email' => $email,
            'expires_in_hours' => 72,
        ]);

        $base = rtrim((string)env('APP_URL', ''), '/');
        $url = ($base !== '' ? $base : '') . '/invite/accept?token=' . urlencode($rawToken);
        header('Location: /work?id=' . $workId . '&invite=1&invite_url=' . urlencode($url));
        exit;
    }

    public function accept(): void
    {
        $token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
        if ($token === '') {
            http_response_code(422);
            exit('Convite inválido.');
        }
        $pdo = Database::connection();
        $hash = hash('sha256', $token);
        $stmt = $pdo->prepare('SELECT i.*, w.condominium_id, w.unit, c.name condominium_name FROM work_access_invites i JOIN works w ON w.id=i.work_id JOIN condominiums c ON c.id=w.condominium_id WHERE i.token_hash=? AND i.accepted_at IS NULL AND i.expires_at > NOW()');
        $stmt->execute([$hash]);
        $invite = $stmt->fetch();
        if (!$invite) {
            http_response_code(410);
            exit('Convite expirado ou já utilizado.');
        }

        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$invite['email']]);
        $existing = $stmt->fetch();
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['_token'] ?? null)) {
                http_response_code(419);
                exit('Sessão expirada.');
            }

            $name = trim((string)($_POST['name'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            if ($existing) {
                if (!password_verify($password, $existing['password_hash'])) {
                    $error = 'Senha inválida para a conta já existente.';
                }
            } elseif ($name === '' || strlen($password) < 8) {
                $error = 'Informe seu nome e uma senha com pelo menos 8 caracteres.';
            }

            if (!$error) {
                $pdo->beginTransaction();
                try {
                    $lock = $pdo->prepare('SELECT id FROM work_access_invites WHERE id=? AND token_hash=? AND accepted_at IS NULL AND expires_at > NOW() FOR UPDATE');
                    $lock->execute([$invite['id'], $hash]);
                    if (!$lock->fetchColumn()) {
                        throw new \RuntimeException('Convite não está mais disponível.');
                    }

                    if ($existing) {
                        $userId = (int)$existing['id'];
                        $loginUser = $existing;
                    } else {
                        $stmt = $pdo->prepare('INSERT INTO users(name,email,password_hash,active) VALUES(?,?,?,1)');
                        $stmt->execute([$name, $invite['email'], password_hash($password, PASSWORD_DEFAULT)]);
                        $userId = (int)$pdo->lastInsertId();
                        $loginUser = ['id' => $userId, 'name' => $name, 'email' => $invite['email']];
                    }

                    $roleId = (int)$pdo->query("SELECT id FROM roles WHERE code='WORK_RESPONSIBLE' LIMIT 1")->fetchColumn();
                    if ($roleId <= 0) {
                        throw new \RuntimeException('Perfil de responsável pela obra não encontrado.');
                    }

                    $stmt = $pdo->prepare('INSERT IGNORE INTO condominium_user(condominium_id,user_id,role_id,active) VALUES(?,?,?,1)');
                    $stmt->execute([$invite['condominium_id'], $userId, $roleId]);
                    $pdo->prepare('UPDATE works SET responsible_user_id=? WHERE id=?')->execute([$userId, $invite['work_id']]);
                    $stmt = $pdo->prepare('UPDATE work_access_invites SET accepted_at=NOW() WHERE id=? AND accepted_at IS NULL');
                    $stmt->execute([$invite['id']]);
                    if ($stmt->rowCount() !== 1) {
                        throw new \RuntimeException('Convite já utilizado.');
                    }
                    $pdo->commit();
                } catch (\Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    throw $e;
                }

                Audit::log($userId, (int)$invite['condominium_id'], 'work', (int)$invite['work_id'], 'RESPONSIBLE_ACCESS_ACCEPTED', [
                    'invite_id' => (int)$invite['id'],
                    'email' => $invite['email'],
                ]);
                Auth::login($loginUser);
                header('Location: /responsible/work?id=' . (int)$invite['work_id'] . '&access=1');
                exit;
            }
        }
        require dirname(__DIR__, 2) . '/resources_invite_accept.php';
    }
}
