<?php

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\WorkAccess;
use App\Core\WorkRisk;

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
        $stmt = $pdo->prepare('SELECT id, name, photo_path FROM condominiums WHERE id = ? AND active = 1');
        $stmt->execute([$condoId]);
        $condominium = $stmt->fetch();
        if (!$condominium) {
            http_response_code(404);
            exit('Condomínio não encontrado');
        }

        $statusFilter = trim((string)($_GET['status'] ?? ''));
        $query = trim((string)($_GET['q'] ?? ''));
        $scope = trim((string)($_GET['scope'] ?? ''));
        $viewMode = (string)($_GET['view'] ?? 'cards');
        if (!in_array($viewMode, ['cards','kanban'], true)) $viewMode = 'cards';
        $validScopes = ['', 'operational', 'pending', 'overdue', 'restricted', 'completed'];
        if (!in_array($scope, $validScopes, true)) $scope = '';
        $validStatuses = ['DRAFT','WAITING_DOCUMENTS','UNDER_REVIEW','CORRECTION_REQUIRED','TECHNICALLY_APPROVED','AUTHORIZED','IN_PROGRESS','NOTIFIED','SUSPENDED','EMBARGOED','COMPLETION_INSPECTION','COMPLETED','CANCELLED'];
        if ($statusFilter !== '' && !in_array($statusFilter, $validStatuses, true)) {
            $statusFilter = '';
        }

        $baseSql = 'SELECT w.id, w.unit, w.owner_name, w.company_name, w.technical_name, w.work_type, w.planned_start, w.planned_end, w.status, w.cover_photo_path, w.created_at, w.updated_at,
            (SELECT COUNT(*) FROM non_conformities nc WHERE nc.work_id=w.id AND nc.status <> "CLOSED") pending_count,
            (SELECT COUNT(*) FROM non_conformities nc WHERE nc.work_id=w.id AND nc.status <> "CLOSED" AND nc.severity="CRITICAL") critical_open,
            (SELECT COUNT(*) FROM non_conformities nc WHERE nc.work_id=w.id AND nc.status <> "CLOSED" AND nc.severity="HIGH") high_open,
            (SELECT COUNT(*) FROM non_conformities nc WHERE nc.work_id=w.id AND nc.status <> "CLOSED" AND nc.corrective_deadline IS NOT NULL AND nc.corrective_deadline < NOW()) overdue_nc,
            (SELECT COUNT(*) FROM inspections i WHERE i.work_id=w.id) inspections_count,
            (SELECT MAX(i.inspected_at) FROM inspections i WHERE i.work_id=w.id) last_inspection_at,
            (SELECT COUNT(*) FROM notifications n WHERE n.work_id=w.id) notifications_count,
            (SELECT MAX(we.created_at) FROM work_events we WHERE we.work_id=w.id) last_activity_at
            FROM works w WHERE w.condominium_id = ?';
        $params = [$condoId];

        if (in_array('WORK_RESPONSIBLE', $roles, true) && count(array_diff($roles, ['WORK_RESPONSIBLE'])) === 0) {
            $baseSql .= ' AND w.responsible_user_id = ?';
            $params[] = $user['id'];
        }
        if ($statusFilter !== '') {
            $baseSql .= ' AND w.status = ?';
            $params[] = $statusFilter;
        }
        if ($query !== '') {
            $baseSql .= ' AND (w.unit LIKE ? OR w.owner_name LIKE ? OR COALESCE(w.company_name,"") LIKE ? OR COALESCE(w.technical_name,"") LIKE ?)';
            $like = '%' . $query . '%';
            array_push($params, $like, $like, $like, $like);
        }

        $baseSql .= ' ORDER BY w.created_at DESC';
        $stmt = $pdo->prepare($baseSql);
        $stmt->execute($params);
        $works = $stmt->fetchAll();

        foreach ($works as &$work) {
            $work['risk'] = WorkRisk::calculate($work);
            $work['last_activity_at'] = $work['last_activity_at'] ?: $work['updated_at'] ?: $work['created_at'];
        }
        unset($work);

        if ($scope !== '') {
            $works = array_values(array_filter($works, static function (array $work) use ($scope): bool {
                return match ($scope) {
                    'operational' => in_array($work['status'], ['IN_PROGRESS','NOTIFIED','SUSPENDED','EMBARGOED'], true),
                    'pending' => (int)$work['pending_count'] > 0,
                    'overdue' => (int)$work['overdue_nc'] > 0 || !empty($work['risk']['late']),
                    'restricted' => in_array($work['status'], ['SUSPENDED','EMBARGOED'], true),
                    'completed' => $work['status'] === 'COMPLETED',
                    default => true,
                };
            }));
        }

        usort($works, static function (array $a, array $b): int {
            $riskCompare = ((int)$b['risk']['score']) <=> ((int)$a['risk']['score']);
            if ($riskCompare !== 0) return $riskCompare;
            return strcmp((string)$b['last_activity_at'], (string)$a['last_activity_at']);
        });

        $summaryStmt = $pdo->prepare('SELECT
            COUNT(*) total,
            COALESCE(SUM(status IN ("IN_PROGRESS","NOTIFIED","SUSPENDED","EMBARGOED")),0) operational,
            COALESCE(SUM(status="IN_PROGRESS"),0) in_progress,
            COALESCE(SUM(status="COMPLETED"),0) completed,
            COALESCE(SUM(status="SUSPENDED"),0) suspended,
            COALESCE(SUM(status="EMBARGOED"),0) embargoed,
            COALESCE(SUM(planned_end IS NOT NULL AND planned_end < CURDATE() AND status NOT IN ("COMPLETED","CANCELLED")),0) overdue_works,
            (SELECT COUNT(*) FROM non_conformities nc JOIN works wx ON wx.id=nc.work_id WHERE wx.condominium_id=? AND nc.status <> "CLOSED") open_nc,
            (SELECT COUNT(*) FROM non_conformities nc JOIN works wx ON wx.id=nc.work_id WHERE wx.condominium_id=? AND nc.status <> "CLOSED" AND nc.corrective_deadline IS NOT NULL AND nc.corrective_deadline < NOW()) overdue_nc
            FROM works WHERE condominium_id=?');
        $summaryStmt->execute([$condoId,$condoId,$condoId]);
        $summary = $summaryStmt->fetch() ?: ['total'=>0,'operational'=>0,'in_progress'=>0,'completed'=>0,'suspended'=>0,'embargoed'=>0,'overdue_works'=>0,'open_nc'=>0,'overdue_nc'=>0];

        $kanban = [
            'DOCUMENTS' => ['label'=>'Documentação', 'works'=>[]],
            'APPROVAL' => ['label'=>'Aprovação / Autorização', 'works'=>[]],
            'OPERATIONAL' => ['label'=>'Em andamento', 'works'=>[]],
            'RESTRICTED' => ['label'=>'Suspensas / Embargadas', 'works'=>[]],
            'COMPLETION' => ['label'=>'Conclusão', 'works'=>[]],
            'COMPLETED' => ['label'=>'Concluídas', 'works'=>[]],
        ];
        foreach ($works as $work) {
            $column = match ($work['status']) {
                'DRAFT','WAITING_DOCUMENTS','UNDER_REVIEW','CORRECTION_REQUIRED' => 'DOCUMENTS',
                'TECHNICALLY_APPROVED','AUTHORIZED' => 'APPROVAL',
                'IN_PROGRESS','NOTIFIED' => 'OPERATIONAL',
                'SUSPENDED','EMBARGOED' => 'RESTRICTED',
                'COMPLETION_INSPECTION' => 'COMPLETION',
                'COMPLETED','CANCELLED' => 'COMPLETED',
                default => 'DOCUMENTS',
            };
            $kanban[$column]['works'][] = $work;
        }

        $canCreate = WorkAccess::canInspect($roles);
        $canManage = WorkAccess::canManage($roles);
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
        $stmt = $pdo->prepare('SELECT id, name, photo_path FROM condominiums WHERE id = ? AND active = 1');
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
