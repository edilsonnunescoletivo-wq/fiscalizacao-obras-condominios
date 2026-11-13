<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\WorkAccess;
use App\Core\WorkRisk;

final class IndicesController
{
    public function index(): void
    {
        if (!Auth::check()) {
            header('Location: /login');
            exit;
        }

        $userId = (int)Auth::user()['id'];
        $condoId = (int)($_GET['condo'] ?? 0);
        $roles = WorkAccess::rolesForCondo($condoId, $userId);
        $onlyResponsible = in_array('WORK_RESPONSIBLE', $roles, true)
            && count(array_diff($roles, ['WORK_RESPONSIBLE'])) === 0;

        if ($condoId <= 0 || !$roles || $onlyResponsible) {
            http_response_code(403);
            exit('Acesso não autorizado.');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id, name FROM condominiums WHERE id=? AND active=1');
        $stmt->execute([$condoId]);
        $condominium = $stmt->fetch();
        if (!$condominium) {
            http_response_code(404);
            exit('Condomínio não encontrado.');
        }

        // Contagens correlacionadas evitam multiplicação de linhas causada por JOINs de NC e vistorias.
        $stmt = $pdo->prepare('SELECT w.id, w.unit, w.status, w.planned_end, w.created_at,
            (SELECT COUNT(*) FROM non_conformities nc WHERE nc.work_id=w.id AND nc.status<>"CLOSED") pending_count,
            (SELECT COUNT(*) FROM non_conformities nc WHERE nc.work_id=w.id AND nc.status="CLOSED") closed_nc,
            (SELECT COUNT(*) FROM non_conformities nc WHERE nc.work_id=w.id AND nc.status<>"CLOSED" AND nc.severity="CRITICAL") critical_open,
            (SELECT COUNT(*) FROM non_conformities nc WHERE nc.work_id=w.id AND nc.status<>"CLOSED" AND nc.severity="HIGH") high_open,
            (SELECT COUNT(*) FROM non_conformities nc WHERE nc.work_id=w.id AND nc.status<>"CLOSED" AND nc.corrective_deadline IS NOT NULL AND nc.corrective_deadline<NOW()) overdue_nc,
            (SELECT COUNT(*) FROM inspections i WHERE i.work_id=w.id) inspections_count,
            (SELECT MAX(i.inspected_at) FROM inspections i WHERE i.work_id=w.id) last_inspection_at
            FROM works w WHERE w.condominium_id=? ORDER BY w.created_at DESC');
        $stmt->execute([$condoId]);
        $works = $stmt->fetchAll();

        $totals = [
            'works'=>0, 'cancelled'=>0, 'completed'=>0, 'operational'=>0,
            'with_inspection'=>0, 'inspections'=>0, 'open_nc'=>0, 'closed_nc'=>0,
            'overdue_nc'=>0, 'critical_nc'=>0, 'overdue_works'=>0,
            'dated_active_works'=>0, 'dated_on_time'=>0, 'restricted'=>0,
            'risk_score_sum'=>0, 'risk_evaluated'=>0,
        ];
        $riskBreakdown = ['LOW'=>0, 'MEDIUM'=>0, 'HIGH'=>0, 'CRITICAL'=>0];
        $stages = [
            'preparation'=>['label'=>'Documentação e análise', 'count'=>0, 'tone'=>'slate'],
            'approval'=>['label'=>'Aprovadas / autorizadas', 'count'=>0, 'tone'=>'blue'],
            'active'=>['label'=>'Em execução', 'count'=>0, 'tone'=>'cyan'],
            'restricted'=>['label'=>'Suspensas / embargadas', 'count'=>0, 'tone'=>'orange'],
            'completion'=>['label'=>'Em vistoria final', 'count'=>0, 'tone'=>'purple'],
            'completed'=>['label'=>'Concluídas', 'count'=>0, 'tone'=>'green'],
            'cancelled'=>['label'=>'Canceladas', 'count'=>0, 'tone'=>'slate'],
        ];
        $priorities = [];
        foreach ($works as $work) {
            $status = (string)$work['status'];
            $totals['works']++;
            $totals['inspections'] += (int)$work['inspections_count'];
            $totals['open_nc'] += (int)$work['pending_count'];
            $totals['closed_nc'] += (int)$work['closed_nc'];
            $totals['overdue_nc'] += (int)$work['overdue_nc'];
            $totals['critical_nc'] += (int)$work['critical_open'];

            $stage = match ($status) {
                'DRAFT','WAITING_DOCUMENTS','UNDER_REVIEW','CORRECTION_REQUIRED' => 'preparation',
                'TECHNICALLY_APPROVED','AUTHORIZED' => 'approval',
                'IN_PROGRESS','NOTIFIED' => 'active',
                'SUSPENDED','EMBARGOED' => 'restricted',
                'COMPLETION_INSPECTION' => 'completion',
                'COMPLETED' => 'completed',
                'CANCELLED' => 'cancelled',
                default => 'preparation',
            };
            $stages[$stage]['count']++;

            if ($status === 'CANCELLED') {
                $totals['cancelled']++;
                continue;
            }
            if ($status === 'COMPLETED') {
                $totals['completed']++;
                continue;
            }

            // As taxas de prazo consideram apenas obras não encerradas com prazo informado.
            if (!empty($work['planned_end'])) {
                $totals['dated_active_works']++;
                if ((string)$work['planned_end'] >= date('Y-m-d')) {
                    $totals['dated_on_time']++;
                } else {
                    $totals['overdue_works']++;
                }
            }
            $isOperational = in_array($status, ['IN_PROGRESS','NOTIFIED','SUSPENDED','EMBARGOED'], true);
            if ($isOperational) {
                $totals['operational']++;
                if ((int)$work['inspections_count'] > 0) {
                    $totals['with_inspection']++;
                }
            }
            if (in_array($status, ['SUSPENDED','EMBARGOED'], true)) {
                $totals['restricted']++;
            }

            $risk = WorkRisk::calculate($work);
            $totals['risk_score_sum'] += (int)$risk['score'];
            $totals['risk_evaluated']++;
            $riskBreakdown[$risk['level']]++;
            $priorities[] = [
                'id'=>(int)$work['id'],
                'unit'=>(string)$work['unit'],
                'status'=>$status,
                'risk'=>$risk,
                'pending'=>(int)$work['pending_count'],
                'overdue'=>(int)$work['overdue_nc'] + (int)!empty($risk['late']),
            ];
        }
        usort($priorities, static fn(array $a, array $b): int =>
            ($b['risk']['score'] <=> $a['risk']['score']) ?: ($b['overdue'] <=> $a['overdue'])
        );
        $priorities = array_slice($priorities, 0, 5);

        $percent = static fn(int $num, int $den): ?int => $den > 0 ? (int)round(100 * $num / $den) : null;
        $indices = [
            'completion'=>$percent($totals['completed'], $totals['works']-$totals['cancelled']),
            'conformity'=>$percent($totals['closed_nc'], $totals['open_nc']+$totals['closed_nc']),
            'on_time'=>$percent($totals['dated_on_time'], $totals['dated_active_works']),
            'coverage'=>$percent($totals['with_inspection'], $totals['operational']),
            'average_risk'=>$totals['risk_evaluated'] > 0
                ? (int)round($totals['risk_score_sum'] / $totals['risk_evaluated']) : null,
        ];

        require dirname(__DIR__, 2) . '/resources_indices.php';
    }
}
