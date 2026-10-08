<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;

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
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT w.id,w.condominium_id FROM works w WHERE w.id = ?');
        $stmt->execute([$workId]);
        $work = $stmt->fetch();
        if (!$work) {
            http_response_code(404);
            exit('Obra não encontrada.');
        }
        $stmt = $pdo->prepare('SELECT r.code FROM condominium_user cu JOIN roles r ON r.id=cu.role_id WHERE cu.condominium_id=? AND cu.user_id=? AND cu.active=1');
        $stmt->execute([$work['condominium_id'], $user['id']]);
        $roles = array_column($stmt->fetchAll(), 'code');
        if (!array_intersect($roles, ['ADMIN','SYNDIC','MANAGER'])) {
            http_response_code(403);
            exit('Acesso não autorizado.');
        }
        $rawToken = bin2hex(random_bytes(32));
        $hash = hash('sha256', $rawToken);
        $pdo->prepare('UPDATE work_access_invites SET expires_at = NOW() WHERE work_id = ? AND email = ? AND accepted_at IS NULL')->execute([$workId, $email]);
        $stmt = $pdo->prepare('INSERT INTO work_access_invites(work_id,email,token_hash,expires_at,created_by) VALUES(?,?,?,DATE_ADD(NOW(), INTERVAL 72 HOUR),?)');
        $stmt->execute([$workId, $email, $hash, $user['id']]);
        $this->event($workId, (int)$user['id'], 'WORK_RESPONSIBLE_INVITED', 'Convite do responsável gerado', $email);
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
            if ($existing) {
                $password = (string)($_POST['password'] ?? '');
                if (!password_verify($password, $existing['password_hash'])) {
                    $error = 'Senha inválida para a conta já existente.';
                } else {
                    $userId = (int)$existing['id'];
                }
            } else {
                $name = trim((string)($_POST['name'] ?? ''));
                $password = (string)($_POST['password'] ?? '');
                if ($name === '' || strlen($password) < 8) {
                    $error = 'Informe seu nome e uma senha com pelo menos 8 caracteres.';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO users(name,email,password_hash,active) VALUES(?,?,?,1)');
                    $stmt->execute([$name, $invite['email'], password_hash($password, PASSWORD_DEFAULT)]);
                    $userId = (int)$pdo->lastInsertId();
                }
            }
            if (!$error) {
                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->query("SELECT id FROM roles WHERE code='WORK_RESPONSIBLE' LIMIT 1");
                    $roleId = (int)$stmt->fetchColumn();
                    $stmt = $pdo->prepare('INSERT IGNORE INTO condominium_user(condominium_id,user_id,role_id,active) VALUES(?,?,?,1)');
                    $stmt->execute([$invite['condominium_id'], $userId, $roleId]);
                    $pdo->prepare('UPDATE works SET responsible_user_id=? WHERE id=?')->execute([$userId, $invite['work_id']]);
                    $pdo->prepare('UPDATE work_access_invites SET accepted_at=NOW() WHERE id=?')->execute([$invite['id']]);
                    $pdo->commit();
                } catch (\Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    throw $e;
                }
                $_SESSION['user_id'] = $userId;
                header('Location: /work?id=' . (int)$invite['work_id'] . '&access=1');
                exit;
            }
        }
        require dirname(__DIR__, 2) . '/resources_invite_accept.php';
    }
}
