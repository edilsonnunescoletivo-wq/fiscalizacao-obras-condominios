<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\WorkAccess;

final class CondominiumModuleController
{
    private function requireStaff(): array
    {
        if (!Auth::check()) {
            header('Location: /login');
            exit;
        }

        $user = Auth::user();
        $condoId = (int)($_GET['condo'] ?? 0);
        $roles = WorkAccess::rolesForCondo($condoId, (int)$user['id']);
        if ($condoId <= 0 || !WorkAccess::canInspect($roles)) {
            http_response_code(403);
            exit('Acesso não autorizado.');
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

    public function inspections(): void
    {
        [, $condominium, $roles] = $this->requireStaff();
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT i.id,i.inspected_at,i.stage,i.result,i.notes,w.id work_id,w.unit,w.owner_name,u.name inspector_name FROM inspections i JOIN works w ON w.id=i.work_id JOIN users u ON u.id=i.inspector_user_id WHERE w.condominium_id=? ORDER BY i.inspected_at DESC,i.id DESC');
        $stmt->execute([$condominium['id']]);
        $items = $stmt->fetchAll();
        $module = 'inspections';
        $title = 'Fiscalizações';
        $description = 'Todas as vistorias registradas nas obras deste condomínio.';
        require dirname(__DIR__, 2) . '/resources_condominium_module.php';
    }

    public function nonConformities(): void
    {
        [, $condominium, $roles] = $this->requireStaff();
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT nc.id,nc.title,nc.severity,nc.status,nc.corrective_deadline,nc.created_at,nc.description,w.id work_id,w.unit,w.owner_name,u.name creator_name,i.stage inspection_stage FROM non_conformities nc JOIN works w ON w.id=nc.work_id JOIN users u ON u.id=nc.created_by LEFT JOIN inspections i ON i.id=nc.inspection_id WHERE w.condominium_id=? ORDER BY FIELD(nc.status,"OPEN","CORRECTED","CLOSED"),nc.created_at DESC,nc.id DESC');
        $stmt->execute([$condominium['id']]);
        $items = $stmt->fetchAll();
        $module = 'corrections';
        $title = 'Não conformidades / Correções';
        $description = 'Pendências, correções enviadas e itens já encerrados em todas as obras.';
        require dirname(__DIR__, 2) . '/resources_condominium_module.php';
    }

    public function notifications(): void
    {
        [, $condominium, $roles] = $this->requireStaff();
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT n.id,n.type,n.number,n.reason,n.deadline,n.status,n.issued_at,n.created_at,w.id work_id,w.unit,w.owner_name,u.name creator_name FROM notifications n JOIN works w ON w.id=n.work_id JOIN users u ON u.id=n.created_by WHERE w.condominium_id=? ORDER BY COALESCE(n.issued_at,n.created_at) DESC,n.id DESC');
        $stmt->execute([$condominium['id']]);
        $items = $stmt->fetchAll();
        $module = 'notifications';
        $title = 'Notificações';
        $description = 'Advertências, adequações, suspensões, embargos e liberações emitidas.';
        require dirname(__DIR__, 2) . '/resources_condominium_module.php';
    }

    public function documents(): void
    {
        [, $condominium, $roles] = $this->requireStaff();
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT wd.id,wd.version,wd.original_name,wd.status,wd.uploaded_at,wd.reviewed_at,w.id work_id,w.unit,w.owner_name,dt.name document_name FROM work_documents wd JOIN works w ON w.id=wd.work_id JOIN document_types dt ON dt.id=wd.document_type_id WHERE w.condominium_id=? ORDER BY wd.uploaded_at DESC,wd.id DESC');
        $stmt->execute([$condominium['id']]);
        $items = $stmt->fetchAll();
        $module = 'documents';
        $title = 'Documentos';
        $description = 'Documentos enviados em todas as obras, com versão e situação da análise.';
        require dirname(__DIR__, 2) . '/resources_condominium_module.php';
    }

    public function completions(): void
    {
        [, $condominium, $roles] = $this->requireStaff();
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT wct.id,wct.result,wct.notes,wct.completed_at,wct.updated_at,w.id work_id,w.unit,w.owner_name,w.status,u.name inspector_name FROM work_completion_terms wct JOIN works w ON w.id=wct.work_id JOIN users u ON u.id=wct.inspector_user_id WHERE w.condominium_id=? ORDER BY COALESCE(wct.completed_at,wct.updated_at) DESC,wct.id DESC');
        $stmt->execute([$condominium['id']]);
        $items = $stmt->fetchAll();
        $module = 'completion';
        $title = 'Conclusões';
        $description = 'Vistorias finais e resultados de conclusão das obras.';
        require dirname(__DIR__, 2) . '/resources_condominium_module.php';
    }

    public function dossiers(): void
    {
        [, $condominium, $roles] = $this->requireStaff();
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT w.id work_id,w.unit,w.owner_name,w.work_type,w.status,w.updated_at,wct.completed_at FROM works w LEFT JOIN work_completion_terms wct ON wct.work_id=w.id WHERE w.condominium_id=? AND w.status="COMPLETED" ORDER BY COALESCE(wct.completed_at,w.updated_at) DESC,w.id DESC');
        $stmt->execute([$condominium['id']]);
        $items = $stmt->fetchAll();
        $module = 'dossier';
        $title = 'Dossiês digitais';
        $description = 'Obras concluídas com acesso direto ao dossiê digital final.';
        require dirname(__DIR__, 2) . '/resources_condominium_module.php';
    }
}
