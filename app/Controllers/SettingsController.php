<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;

final class SettingsController
{
    private function requireAuth(): array
    {
        if (!Auth::check()) { header('Location: /login'); exit; }
        return Auth::user();
    }

    private function requireCondoAccess(int $condoId, array $user): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT c.*, GROUP_CONCAT(r.code) role_codes FROM condominiums c JOIN condominium_user cu ON cu.condominium_id=c.id JOIN roles r ON r.id=cu.role_id WHERE c.id=? AND cu.user_id=? AND cu.active=1 GROUP BY c.id');
        $stmt->execute([$condoId,$user['id']]);
        $condo = $stmt->fetch();
        if (!$condo) { http_response_code(403); exit('Acesso não autorizado.'); }
        $roles = array_filter(explode(',', (string)$condo['role_codes']));
        if (!array_intersect($roles,['ADMIN','SYNDIC','MANAGER','INSPECTOR'])) { http_response_code(403); exit('Perfil sem acesso às configurações.'); }
        $condo['_roles']=$roles;
        return $condo;
    }

    public function index(): void
    {
        $user=$this->requireAuth();
        $condoId=(int)($_GET['condo']??0);
        $condo=$this->requireCondoAccess($condoId,$user);
        $pdo=Database::connection();
        $stmt=$pdo->prepare('SELECT * FROM condominium_rules WHERE condominium_id=?'); $stmt->execute([$condoId]); $rules=$stmt->fetch() ?: [];
        $stmt=$pdo->prepare('SELECT * FROM notification_templates WHERE condominium_id=? ORDER BY FIELD(type,"IRREGULARITY","WARNING","ADJUSTMENT","SUSPENSION","EMBARGO","RELEASE")'); $stmt->execute([$condoId]); $templates=$stmt->fetchAll();
        $stmt=$pdo->prepare('SELECT * FROM inspection_checklist_items WHERE condominium_id=? ORDER BY sort_order,id'); $stmt->execute([$condoId]); $checklist=$stmt->fetchAll();
        require dirname(__DIR__,2) . '/resources_settings.php';
    }

    public function saveRules(): void
    {
        $user=$this->requireAuth();
        if ($_SERVER['REQUEST_METHOD']!=='POST' || !Csrf::validate($_POST['_token']??null)) { http_response_code(419); exit('Sessão expirada.'); }
        $condoId=(int)($_POST['condo_id']??0); $this->requireCondoAccess($condoId,$user);
        $vals=[
            max(0,(int)($_POST['default_notification_days']??5)),
            max(0,(int)($_POST['warning_days']??3)),
            max(0,(int)($_POST['adjustment_days']??5)),
            max(0,(int)($_POST['suspension_days']??0)),
            max(0,(int)($_POST['embargo_days']??0)),
            !empty($_POST['require_photo_on_inspection'])?1:0,
            !empty($_POST['require_photo_on_non_conformity'])?1:0,
            !empty($_POST['require_final_inspection'])?1:0,
            !empty($_POST['block_completion_with_open_nc'])?1:0,
            !empty($_POST['allow_inspector_warning'])?1:0,
            !empty($_POST['allow_inspector_adjustment'])?1:0,
            $condoId
        ];
        $pdo=Database::connection();
        $sql='INSERT INTO condominium_rules(default_notification_days,warning_days,adjustment_days,suspension_days,embargo_days,require_photo_on_inspection,require_photo_on_non_conformity,require_final_inspection,block_completion_with_open_nc,allow_inspector_warning,allow_inspector_adjustment,condominium_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE default_notification_days=VALUES(default_notification_days),warning_days=VALUES(warning_days),adjustment_days=VALUES(adjustment_days),suspension_days=VALUES(suspension_days),embargo_days=VALUES(embargo_days),require_photo_on_inspection=VALUES(require_photo_on_inspection),require_photo_on_non_conformity=VALUES(require_photo_on_non_conformity),require_final_inspection=VALUES(require_final_inspection),block_completion_with_open_nc=VALUES(block_completion_with_open_nc),allow_inspector_warning=VALUES(allow_inspector_warning),allow_inspector_adjustment=VALUES(allow_inspector_adjustment)';
        $pdo->prepare($sql)->execute($vals);
        header('Location: /settings?condo='.$condoId.'&saved=1'); exit;
    }

    public function saveTemplate(): void
    {
        $user=$this->requireAuth();
        if ($_SERVER['REQUEST_METHOD']!=='POST' || !Csrf::validate($_POST['_token']??null)) { http_response_code(419); exit('Sessão expirada.'); }
        $condoId=(int)($_POST['condo_id']??0); $this->requireCondoAccess($condoId,$user);
        $type=(string)($_POST['type']??'');
        if (!in_array($type,['IRREGULARITY','WARNING','ADJUSTMENT','SUSPENSION','EMBARGO','RELEASE'],true)) { http_response_code(422); exit('Tipo inválido.'); }
        $title=trim((string)($_POST['title']??''));
        if ($title==='') { http_response_code(422); exit('Título obrigatório.'); }
        $pdo=Database::connection();
        $sql='INSERT INTO notification_templates(condominium_id,type,title,default_reason,default_body,default_deadline_days,active) VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE title=VALUES(title),default_reason=VALUES(default_reason),default_body=VALUES(default_body),default_deadline_days=VALUES(default_deadline_days),active=VALUES(active)';
        $pdo->prepare($sql)->execute([$condoId,$type,$title,trim((string)($_POST['default_reason']??''))?:null,trim((string)($_POST['default_body']??''))?:null,max(0,(int)($_POST['default_deadline_days']??0)),!empty($_POST['active'])?1:0]);
        header('Location: /settings?condo='.$condoId.'&template=1'); exit;
    }

    public function addChecklistItem(): void
    {
        $user=$this->requireAuth();
        if ($_SERVER['REQUEST_METHOD']!=='POST' || !Csrf::validate($_POST['_token']??null)) { http_response_code(419); exit('Sessão expirada.'); }
        $condoId=(int)($_POST['condo_id']??0); $this->requireCondoAccess($condoId,$user);
        $label=trim((string)($_POST['label']??'')); if ($label==='') { http_response_code(422); exit('Item obrigatório.'); }
        Database::connection()->prepare('INSERT INTO inspection_checklist_items(condominium_id,label,category,required,active,sort_order) VALUES(?,?,?,?,1,?)')->execute([$condoId,$label,trim((string)($_POST['category']??''))?:null,!empty($_POST['required'])?1:0,(int)($_POST['sort_order']??0)]);
        header('Location: /settings?condo='.$condoId.'&checklist=1'); exit;
    }
}
