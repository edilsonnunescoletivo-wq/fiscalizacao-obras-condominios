<?php

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Csrf;
use App\Core\Database;

final class PasswordSetupController
{
    public function show(): void
    {
        header('Cache-Control: no-store, private, max-age=0');
        header('Referrer-Policy: no-referrer');

        $token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            http_response_code(404);
            exit('Link inválido ou expirado.');
        }

        $pdo = Database::connection();
        $hash = hash('sha256', $token);
        $stmt = $pdo->prepare(
            'SELECT pst.id,pst.user_id,pst.expires_at,pst.used_at,u.name,u.email,u.active
             FROM password_setup_tokens pst
             JOIN users u ON u.id=pst.user_id
             WHERE pst.token_hash=? LIMIT 1'
        );
        $stmt->execute([$hash]);
        $setup = $stmt->fetch();

        if (!$setup || $setup['used_at'] !== null || strtotime((string)$setup['expires_at']) <= time() || (int)$setup['active'] !== 1) {
            http_response_code(410);
            exit('Este link já foi utilizado ou expirou.');
        }

        $error = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['_token'] ?? null)) {
                http_response_code(419);
                exit('Sessão expirada. Atualize a página e tente novamente.');
            }

            $password = (string)($_POST['password'] ?? '');
            $confirmation = (string)($_POST['password_confirmation'] ?? '');
            if (strlen($password) < 10) {
                $error = 'A senha deve ter pelo menos 10 caracteres.';
            } elseif (!hash_equals($password, $confirmation)) {
                $error = 'As senhas informadas não coincidem.';
            }

            if ($error === null) {
                $pdo->beginTransaction();
                try {
                    $lock = $pdo->prepare(
                        'SELECT pst.id,pst.user_id,pst.expires_at,pst.used_at
                         FROM password_setup_tokens pst
                         WHERE pst.token_hash=? FOR UPDATE'
                    );
                    $lock->execute([$hash]);
                    $current = $lock->fetch();
                    if (!$current || $current['used_at'] !== null || strtotime((string)$current['expires_at']) <= time()) {
                        throw new \RuntimeException('Este link já foi utilizado ou expirou.');
                    }

                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare('UPDATE users SET password_hash=?,active=1 WHERE id=?');
                    $stmt->execute([$newHash, (int)$current['user_id']]);

                    $stmt = $pdo->prepare('UPDATE password_setup_tokens SET used_at=NOW() WHERE user_id=? AND used_at IS NULL');
                    $stmt->execute([(int)$current['user_id']]);
                    $pdo->commit();

                    Audit::log((int)$current['user_id'], null, 'user', (int)$current['user_id'], 'PASSWORD_INITIALIZED', [
                        'method' => 'one_time_setup_link',
                    ]);

                    header('Location: /login?password_created=1');
                    exit;
                } catch (\Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    $error = $e->getMessage();
                }
            }
        }

        $csrf = Csrf::token();
        require dirname(__DIR__, 2) . '/resources_setup_password.php';
    }
}
