<?php

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\WorkAccess;

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

    public function index(): void
    {
        $user = $this->requireAuth();
        $condoId = (int)($_GET['condo'] ?? 0);
        $roles = WorkAccess::rolesForCondo($condoId, (int)$user['id']);
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

        $canCreate = WorkAccess::canInspect($roles);
        require dirname(__DIR__, 2) . '/resources_works.php';
    }

    public function create(): void
    {
        $user = $this->requireAuth();
        $condoId = (int)($_GET['condo'] ?? $_POST['condominium_id'] ?? 0);
        $roles = WorkAccess::rolesForCondo($condoId, (int)$user['id']);
        $canCreate = WorkAccess::canInspect($roles);
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
                $data = [
                    'unit'=>trim((string)$_POST['unit']),
                    'owner_name'=>trim((string)$_POST['owner_name']),
                    'owner_email'=>trim((string)($_POST['owner_email'] ?? '')) ?: null,
                    'owner_phone'=>trim((string)($_POST['owner_phone'] ?? '')) ?: null,
                    'company_name'=>trim((string)($_POST['company_name'] ?? '')) ?: null,
                    'company_cnpj'=>trim((string)($_POST['company_cnpj'] ?? '')) ?: null,
                    'company_contact'=>trim((string)($_POST['company_contact'] ?? '')) ?: null,
                    'company_phone'=>trim((string)($_POST['company_phone'] ?? '')) ?: null,
                    'technical_name'=>trim((string)($_POST['technical_name'] ?? '')) ?: null,
                    'technical_type'=>(($_POST['technical_type'] ?? '') ?: null),
                    'technical_registry'=>trim((string)($_POST['technical_registry'] ?? '')) ?: null,
                    'technical_phone'=>trim((string)($_POST['technical_phone'] ?? '')) ?: null,
                    'technical_email'=>trim((string)($_POST['technical_email'] ?? '')) ?: null,
                    'work_type'=>trim((string)$_POST['work_type']),
                    'description'=>trim((string)($_POST['description'] ?? '')) ?: null,
                    'planned_start'=>(($_POST['planned_start'] ?? '') ?: null),
                    'planned_end'=>(($_POST['planned_end'] ?? '') ?: null),
                ];

                $sql = 'INSERT INTO works (
                    condominium_id, unit, owner_name, owner_email, owner_phone,
                    company_name, company_cnpj, company_contact, company_phone,
                    technical_name, technical_type, technical_registry, technical_phone, technical_email,
                    work_type, description, planned_start, planned_end, status, created_by
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $condoId,$data['unit'],$data['owner_name'],$data['owner_email'],$data['owner_phone'],
                    $data['company_name'],$data['company_cnpj'],$data['company_contact'],$data['company_phone'],
                    $data['technical_name'],$data['technical_type'],$data['technical_registry'],$data['technical_phone'],$data['technical_email'],
                    $data['work_type'],$data['description'],$data['planned_start'],$data['planned_end'],'WAITING_DOCUMENTS',$user['id'],
                ]);
                $workId = (int)$pdo->lastInsertId();
                $pdo->prepare('INSERT INTO work_events(work_id,user_id,event_type,title,description) VALUES(?,?,?,?,?)')->execute([$workId,$user['id'],'WORK_CREATED','Obra cadastrada','Unidade '.$data['unit'].' · '.$data['work_type']]);
                Audit::log((int)$user['id'],$condoId,'work',$workId,'CREATED',['status'=>'WAITING_DOCUMENTS','unit'=>$data['unit'],'owner_name'=>$data['owner_name'],'work_type'=>$data['work_type'],'planned_start'=>$data['planned_start'],'planned_end'=>$data['planned_end']]);

                header('Location: /works?condo=' . $condoId . '&created=1');
                exit;
            }
        }

        require dirname(__DIR__, 2) . '/resources_work_form.php';
    }
}
