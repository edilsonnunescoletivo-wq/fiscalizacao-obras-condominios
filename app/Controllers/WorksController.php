<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;

final class WorksController
{
    private function requireAuth(): array
    {
        if (!Auth::check()) {
            header('Location: /login');
            exit;
        }
        return Auth::user();
    }

    private function rolesForCondo(int $condoId, int $userId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT r.code FROM condominium_user cu
             JOIN roles r ON r.id = cu.role_id
             WHERE cu.condominium_id = ? AND cu.user_id = ? AND cu.active = 1'
        );
        $stmt->execute([$condoId, $userId]);
        return array_column($stmt->fetchAll(), 'code');
    }

    public function index(): void
    {
        $user = $this->requireAuth();
        $condoId = (int)($_GET['condo'] ?? 0);
        $roles = $this->rolesForCondo($condoId, (int)$user['id']);
        if ($condoId <= 0 || !$roles) {
            http_response_code(403);
            exit('Acesso não autorizado');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id, name FROM condominiums WHERE id = ? AND active = 1');
        $stmt->execute([$condoId]);
        $condominium = $stmt->fetch();
        if (!$condominium) {
            http_response_code(404);
            exit('Condomínio não encontrado');
        }

        $baseSql = 'SELECT id, unit, owner_name, company_name, technical_name, work_type, planned_start, planned_end, status FROM works WHERE condominium_id = ?';
        $params = [$condoId];
        if (in_array('WORK_RESPONSIBLE', $roles, true) && count(array_diff($roles, ['WORK_RESPONSIBLE'])) === 0) {
            $baseSql .= ' AND responsible_user_id = ?';
            $params[] = $user['id'];
        }
        $baseSql .= ' ORDER BY created_at DESC';
        $stmt = $pdo->prepare($baseSql);
        $stmt->execute($params);
        $works = $stmt->fetchAll();

        $canCreate = (bool) array_intersect($roles, ['ADMIN','SYNDIC','MANAGER','INSPECTOR']);
        require dirname(__DIR__, 2) . '/resources_works.php';
    }

    public function create(): void
    {
        $user = $this->requireAuth();
        $condoId = (int)($_GET['condo'] ?? $_POST['condominium_id'] ?? 0);
        $roles = $this->rolesForCondo($condoId, (int)$user['id']);
        $canCreate = (bool) array_intersect($roles, ['ADMIN','SYNDIC','MANAGER','INSPECTOR']);
        if ($condoId <= 0 || !$canCreate) {
            http_response_code(403);
            exit('Acesso não autorizado');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id, name FROM condominiums WHERE id = ? AND active = 1');
        $stmt->execute([$condoId]);
        $condominium = $stmt->fetch();
        if (!$condominium) {
            http_response_code(404);
            exit('Condomínio não encontrado');
        }

        $error = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['_token'] ?? null)) {
                http_response_code(419);
                exit('Sessão expirada. Atualize a página e tente novamente.');
            }

            $required = ['unit','owner_name','work_type'];
            foreach ($required as $field) {
                if (trim((string)($_POST[$field] ?? '')) === '') {
                    $error = 'Preencha os campos obrigatórios.';
                    break;
                }
            }

            if (!$error) {
                $sql = 'INSERT INTO works (
                    condominium_id, unit, owner_name, owner_email, owner_phone,
                    company_name, company_cnpj, company_contact, company_phone,
                    technical_name, technical_type, technical_registry, technical_phone, technical_email,
                    work_type, description, planned_start, planned_end, status, created_by
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $condoId,
                    trim($_POST['unit']),
                    trim($_POST['owner_name']),
                    trim($_POST['owner_email'] ?? '') ?: null,
                    trim($_POST['owner_phone'] ?? '') ?: null,
                    trim($_POST['company_name'] ?? '') ?: null,
                    trim($_POST['company_cnpj'] ?? '') ?: null,
                    trim($_POST['company_contact'] ?? '') ?: null,
                    trim($_POST['company_phone'] ?? '') ?: null,
                    trim($_POST['technical_name'] ?? '') ?: null,
                    ($_POST['technical_type'] ?? '') ?: null,
                    trim($_POST['technical_registry'] ?? '') ?: null,
                    trim($_POST['technical_phone'] ?? '') ?: null,
                    trim($_POST['technical_email'] ?? '') ?: null,
                    trim($_POST['work_type']),
                    trim($_POST['description'] ?? '') ?: null,
                    ($_POST['planned_start'] ?? '') ?: null,
                    ($_POST['planned_end'] ?? '') ?: null,
                    'WAITING_DOCUMENTS',
                    $user['id'],
                ]);

                header('Location: /works?condo=' . $condoId . '&created=1');
                exit;
            }
        }

        require dirname(__DIR__, 2) . '/resources_work_form.php';
    }
}
