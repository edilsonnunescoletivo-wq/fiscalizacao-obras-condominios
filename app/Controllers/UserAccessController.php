<?php

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\WorkAccess;

final class UserAccessController
{
    private function requireAccess(bool $write = false): array
    {
        if (!Auth::check()) {
            header('Location: /login');
            exit;
        }
        $user = Auth::user();
        $condoId = (int)($_GET['condo'] ?? $_POST['condo_id'] ?? 0);
        $roles = WorkAccess::rolesForCondo($condoId, (int)$user['id']);
        $canManageAccess = (bool)array_intersect($roles, ['SUPER_ADMIN','ADMIN','SYNDIC']);
        if ($condoId <= 0 || !$roles || !$canManageAccess) {
            http_response_code(403);
            exit('Acesso não autorizado.');
        }
        if ($write && ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::validate($_POST['_token'] ?? null))) {
            http_response_code(419);
            exit('Sessão expirada.');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id,name FROM condominiums WHERE id=? AND active=1');
        $stmt->execute([$condoId]);
        $condominium = $stmt->fetch();
        if (!$condominium) {
            http_response_code(404);
            exit('Condomínio não encontrado.');
        }
        return [$user, $condominium, $roles];
    }

    public function index(): void
    {
        [, $condominium, $roles] = $this->requireAccess();
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT cu.id access_id,cu.active,u.id user_id,u.name,u.email,u.active user_active,r.id role_id,r.code role_code,r.name role_name FROM condominium_user cu JOIN users u ON u.id=cu.user_id JOIN roles r ON r.id=cu.role_id WHERE cu.condominium_id=? ORDER BY u.name,r.name');
        $stmt->execute([$condominium['id']]);
        $accesses = $stmt->fetchAll();

        $globalAdmins = $pdo->query('SELECT id,name,email,active FROM users WHERE is_super_admin=1 ORDER BY name')->fetchAll();
        $allowedRoleCodes = ['ADMIN','SYNDIC','MANAGER','INSPECTOR','VIEWER'];
        $placeholders = implode(',', array_fill(0, count($allowedRoleCodes), '?'));
        $stmt = $pdo->prepare('SELECT id,code,name FROM roles WHERE code IN (' . $placeholders . ') ORDER BY FIELD(code,"ADMIN","SYNDIC","MANAGER","INSPECTOR","VIEWER")');
        $stmt->execute($allowedRoleCodes);
        $availableRoles = $stmt->fetchAll();

        require dirname(__DIR__, 2) . '/resources_users.php';
    }

    public function add(): void
    {
        [$user, $condominium] = $this->requireAccess(true);
        $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
        $roleCode = (string)($_POST['role_code'] ?? '');
        $allowedRoleCodes = ['ADMIN','SYNDIC','MANAGER','INSPECTOR','VIEWER'];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($roleCode, $allowedRoleCodes, true)) {
            header('Location: /users?condo=' . (int)$condominium['id'] . '&error=invalid');
            exit;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id,name FROM users WHERE LOWER(email)=? AND active=1 LIMIT 1');
        $stmt->execute([$email]);
        $target = $stmt->fetch();
        if (!$target) {
            header('Location: /users?condo=' . (int)$condominium['id'] . '&error=user');
            exit;
        }

        $stmt = $pdo->prepare('SELECT id FROM roles WHERE code=? LIMIT 1');
        $stmt->execute([$roleCode]);
        $roleId = (int)$stmt->fetchColumn();
        if ($roleId <= 0) {
            http_response_code(422);
            exit('Perfil inválido.');
        }

        $stmt = $pdo->prepare('INSERT INTO condominium_user(condominium_id,user_id,role_id,active) VALUES(?,?,?,1) ON DUPLICATE KEY UPDATE active=1');
        $stmt->execute([$condominium['id'],$target['id'],$roleId]);
        $stmt = $pdo->prepare('SELECT id FROM condominium_user WHERE condominium_id=? AND user_id=? AND role_id=?');
        $stmt->execute([$condominium['id'],$target['id'],$roleId]);
        $accessId = (int)$stmt->fetchColumn();
        Audit::log((int)$user['id'], (int)$condominium['id'], 'condominium_user', $accessId, 'ACCESS_GRANTED', ['target_user_id'=>(int)$target['id'],'role'=>$roleCode]);
        header('Location: /users?condo=' . (int)$condominium['id'] . '&added=1');
        exit;
    }

    public function toggle(): void
    {
        [$user, $condominium] = $this->requireAccess(true);
        $accessId = (int)($_POST['access_id'] ?? 0);
        $targetActive = (int)($_POST['active'] ?? 0) === 1 ? 1 : 0;
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT cu.id,cu.user_id,cu.active,r.code role_code FROM condominium_user cu JOIN roles r ON r.id=cu.role_id WHERE cu.id=? AND cu.condominium_id=?');
        $stmt->execute([$accessId,$condominium['id']]);
        $access = $stmt->fetch();
        if (!$access) {
            http_response_code(404);
            exit('Acesso não encontrado.');
        }
        if ($access['role_code'] === 'WORK_RESPONSIBLE') {
            http_response_code(422);
            exit('O acesso do responsável pela obra deve ser gerenciado pela própria obra.');
        }
        if ((int)$access['user_id'] === (int)$user['id'] && $targetActive === 0) {
            http_response_code(422);
            exit('Você não pode desativar o próprio acesso nesta tela.');
        }

        $pdo->prepare('UPDATE condominium_user SET active=? WHERE id=? AND condominium_id=?')->execute([$targetActive,$accessId,$condominium['id']]);
        Audit::log((int)$user['id'], (int)$condominium['id'], 'condominium_user', $accessId, $targetActive ? 'ACCESS_ENABLED' : 'ACCESS_DISABLED', ['target_user_id'=>(int)$access['user_id'],'role'=>$access['role_code']]);
        header('Location: /users?condo=' . (int)$condominium['id'] . '&updated=1');
        exit;
    }
}
