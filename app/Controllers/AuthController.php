<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;

final class AuthController
{
    public function login(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['_csrf'] ?? null)) {
                http_response_code(419);
                exit('Sessão expirada. Atualize a página e tente novamente.');
            }

            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $password = (string) ($_POST['password'] ?? '');
            $stmt = Database::connection()->prepare('SELECT id, name, email, password_hash, active FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && (int) $user['active'] === 1 && password_verify($password, $user['password_hash'])) {
                Auth::login($user);
                header('Location: /');
                exit;
            }
            $error = 'E-mail ou senha inválidos.';
        }

        $csrf = Csrf::token();
        require dirname(__DIR__, 2) . '/resources_login.php';
    }

    public function logout(): void
    {
        Auth::logout();
        header('Location: /login');
        exit;
    }
}
