<?php

namespace App\Core;

final class WorkRisk
{
    public static function calculate(array $work): array
    {
        $score = 0;
        $reasons = [];
        $status = (string)($work['status'] ?? '');

        if ($status === 'EMBARGOED') {
            $score += 35;
            $reasons[] = 'Obra embargada';
        } elseif ($status === 'SUSPENDED') {
            $score += 28;
            $reasons[] = 'Obra suspensa';
        } elseif ($status === 'NOTIFIED') {
            $score += 8;
            $reasons[] = 'Obra notificada';
        }

        $critical = max(0, (int)($work['critical_open'] ?? 0));
        if ($critical > 0) {
            $score += min(40, $critical * 20);
            $reasons[] = $critical . ' NC crítica(s)';
        }

        $high = max(0, (int)($work['high_open'] ?? 0));
        if ($high > 0) {
            $score += min(24, $high * 12);
            $reasons[] = $high . ' NC grave(s)';
        }

        $overdueNc = max(0, (int)($work['overdue_nc'] ?? 0));
        if ($overdueNc > 0) {
            $score += min(30, $overdueNc * 10);
            $reasons[] = $overdueNc . ' pendência(s) vencida(s)';
        }

        $pending = max(0, (int)($work['pending_count'] ?? 0));
        if ($pending > 0) {
            $score += min(15, $pending * 3);
        }

        $plannedEnd = (string)($work['planned_end'] ?? '');
        $closedStatuses = ['COMPLETED', 'CANCELLED'];
        $isLate = $plannedEnd !== '' && !in_array($status, $closedStatuses, true) && strtotime($plannedEnd . ' 23:59:59') < time();
        if ($isLate) {
            $score += 15;
            $reasons[] = 'Prazo previsto vencido';
        }

        $operational = in_array($status, ['IN_PROGRESS','NOTIFIED','SUSPENDED','EMBARGOED'], true);
        $lastInspection = (string)($work['last_inspection_at'] ?? '');
        $daysWithoutInspection = null;
        if ($operational) {
            if ($lastInspection === '') {
                $score += 12;
                $reasons[] = 'Sem fiscalização registrada';
            } else {
                $daysWithoutInspection = max(0, (int)floor((time() - strtotime($lastInspection)) / 86400));
                if ($daysWithoutInspection > 30) {
                    $score += 15;
                    $reasons[] = $daysWithoutInspection . ' dias sem fiscalização';
                } elseif ($daysWithoutInspection > 14) {
                    $score += 8;
                    $reasons[] = $daysWithoutInspection . ' dias sem fiscalização';
                }
            }
        }

        $score = min(100, max(0, $score));
        if ($score >= 75) {
            $level = 'CRITICAL';
            $label = 'Crítico';
        } elseif ($score >= 50) {
            $level = 'HIGH';
            $label = 'Alto';
        } elseif ($score >= 25) {
            $level = 'MEDIUM';
            $label = 'Moderado';
        } else {
            $level = 'LOW';
            $label = 'Baixo';
        }

        return [
            'score' => $score,
            'level' => $level,
            'label' => $label,
            'reasons' => array_values(array_unique($reasons)),
            'late' => $isLate,
            'days_without_inspection' => $daysWithoutInspection,
        ];
    }
}
