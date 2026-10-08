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

    private function canAccessCondo(int $condoId, int $userId): bool
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT 1 FROM condominium_user WHERE condominium_id = ? AND user_id = ? AND active = 1 LIMIT 1');
        $stmt->execute([$condoId, $userId]);
        return (bool) $stmt->fetchColumn();
    }

    public function index(): void
    {
        $user = $this->requireAuth();
        $condoId = (int)($_GET['condo'] ?? 0);
        if ($condoId <= 0 || !$this->canAccessCondo($condoId, (int)$user['id'])) {
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

        $stmt = $pdo->prepare('SELECT id, unit, owner_name, company_name, technical_name, work_type, planned_start, planned_end, status FROM works WHERE condominium_id = ? ORDER BY created_at DESC');
        $stmt->execute([$condoId]);
        $works = $stmt->fetchAll();

        require dirname(__DIR__, 2) . '/resources_works.php';
    }

    public function create(): void
    {
        $user = $this->requireAuth();
        $condoId = (int)($_GET['condo'] ?? $_POST['condominium_id'] ?? 0);
        if ($condoId <= 0 || !$this->canAccessCondo($condoId, (int)$user['id'])) {
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
            Csrf::validate($_POST['_token'] ?? '');

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
