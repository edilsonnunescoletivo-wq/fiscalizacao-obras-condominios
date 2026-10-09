<?php

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\WorkAccess;

final class ChecklistPresetController
{
    private const ITEMS = [
        ['Documentação', 'Documentos obrigatórios da obra conferidos e compatíveis com o serviço em execução', 1, 10],
        ['Segurança', 'Área da obra sinalizada e isolada quando necessário', 1, 20],
        ['Segurança', 'Rotas de circulação e saídas mantidas desobstruídas', 1, 30],
        ['Segurança', 'Prestadores utilizando os EPIs aplicáveis à atividade observada', 0, 40],
        ['Áreas comuns', 'Proteções das áreas comuns e elevadores instaladas quando aplicável', 0, 50],
        ['Áreas comuns', 'Corredores, halls e acessos sem materiais, entulho ou sujeira excessiva', 1, 60],
        ['Execução', 'Serviços executados aparentam compatibilidade com o projeto/memorial aprovado', 1, 70],
        ['Elétrica', 'Intervenções elétricas sem fiação exposta ou condição aparente de risco', 0, 80],
        ['Hidráulica', 'Ausência de vazamentos aparentes ou infiltrações decorrentes da obra', 0, 90],
        ['Estrutural', 'Não foram observadas intervenções estruturais divergentes do escopo aprovado', 1, 100],
        ['Operação', 'Horários e regras operacionais do condomínio estão sendo respeitados', 0, 110],
        ['Resíduos', 'Entulho e resíduos estão acondicionados e destinados sem obstruir áreas comuns', 0, 120],
        ['Encerramento', 'Área comum impactada pela obra está limpa e sem danos aparentes', 0, 130],
    ];

    public function apply(): void
    {
        if (!Auth::check()) {
            header('Location: /login');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::validate($_POST['_token'] ?? null)) {
            http_response_code(419);
            exit('Sessão expirada.');
        }

        $user = Auth::user();
        $condoId = (int)($_POST['condo_id'] ?? 0);
        $roles = WorkAccess::rolesForCondo($condoId, (int)$user['id']);
        if ($condoId <= 0 || !WorkAccess::canManage($roles)) {
            http_response_code(403);
            exit('Apenas perfis de gestão podem aplicar o checklist padrão.');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id FROM condominiums WHERE id=? AND active=1');
        $stmt->execute([$condoId]);
        if (!$stmt->fetchColumn()) {
            http_response_code(404);
            exit('Condomínio não encontrado.');
        }

        $exists = $pdo->prepare('SELECT id FROM inspection_checklist_items WHERE condominium_id=? AND label=? AND COALESCE(category, "")=? LIMIT 1');
        $insert = $pdo->prepare('INSERT INTO inspection_checklist_items(condominium_id,label,category,required,active,sort_order) VALUES(?,?,?,?,1,?)');

        $added = 0;
        $pdo->beginTransaction();
        try {
            foreach (self::ITEMS as [$category, $label, $required, $sortOrder]) {
                $exists->execute([$condoId, $label, $category]);
                if ($exists->fetchColumn()) {
                    continue;
                }
                $insert->execute([$condoId, $label, $category, $required, $sortOrder]);
                $added++;
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        Audit::log((int)$user['id'], $condoId, 'inspection_checklist', $condoId, 'DEFAULT_PRESET_APPLIED', [
            'added_items' => $added,
            'preset_items' => count(self::ITEMS),
        ]);

        header('Location: /settings?condo=' . $condoId . '&checklist_preset=1&added=' . $added . '#checklist');
        exit;
    }
}
