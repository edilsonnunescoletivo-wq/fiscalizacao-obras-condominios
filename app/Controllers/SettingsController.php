<?php

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\WorkAccess;

final class SettingsController
{
    private function requireAuth(): array
    {
        if (!Auth::check()) { header('Location: /login'); exit; }
        return Auth::user();
    }

    private function requireCondoAccess(int $condoId, array $user): array
    {
        if ($condoId <= 0) { http_response_code(404); exit('Condomínio não encontrado.'); }
        $roles = WorkAccess::rolesForCondo($condoId, (int)$user['id']);
        if (!WorkAccess::canInspect($roles)) { http_response_code(403); exit('Perfil sem acesso às configurações.'); }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM condominiums WHERE id=? AND active=1');
        $stmt->execute([$condoId]);
        $condo = $stmt->fetch();
        if (!$condo) { http_response_code(404); exit('Condomínio não encontrado.'); }
        $condo['_roles'] = $roles;
        return $condo;
    }

    private function requirePost(): void
    {
        if ($_SERVER['REQUEST_METHOD']!=='POST' || !Csrf::validate($_POST['_token']??null)) { http_response_code(419); exit('Sessão expirada.'); }
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
        $stmt=$pdo->prepare('SELECT * FROM document_types WHERE condominium_id=? ORDER BY required_default DESC,name'); $stmt->execute([$condoId]); $documentTypes=$stmt->fetchAll();
        $stmt=$pdo->prepare('SELECT * FROM severity_action_rules WHERE condominium_id=? ORDER BY FIELD(severity,"LOW","MEDIUM","HIGH","CRITICAL")'); $stmt->execute([$condoId]); $severityRules=$stmt->fetchAll();
        require dirname(__DIR__,2) . '/resources_settings.php';
    }

    public function saveRules(): void
    {
        $user=$this->requireAuth(); $this->requirePost();
        $condoId=(int)($_POST['condo_id']??0); $this->requireCondoAccess($condoId,$user);
        $pdo=Database::connection();
        $stmt=$pdo->prepare('SELECT * FROM condominium_rules WHERE condominium_id=?'); $stmt->execute([$condoId]); $before=$stmt->fetch()?:null;
        $after=[
            'default_notification_days'=>max(0,(int)($_POST['default_notification_days']??5)),
            'warning_days'=>max(0,(int)($_POST['warning_days']??3)),
            'adjustment_days'=>max(0,(int)($_POST['adjustment_days']??5)),
            'suspension_days'=>max(0,(int)($_POST['suspension_days']??0)),
            'embargo_days'=>max(0,(int)($_POST['embargo_days']??0)),
            'require_photo_on_inspection'=>!empty($_POST['require_photo_on_inspection'])?1:0,
            'require_photo_on_non_conformity'=>!empty($_POST['require_photo_on_non_conformity'])?1:0,
            'require_final_inspection'=>!empty($_POST['require_final_inspection'])?1:0,
            'block_completion_with_open_nc'=>!empty($_POST['block_completion_with_open_nc'])?1:0,
            'allow_inspector_warning'=>!empty($_POST['allow_inspector_warning'])?1:0,
            'allow_inspector_adjustment'=>!empty($_POST['allow_inspector_adjustment'])?1:0,
        ];
        $sql='INSERT INTO condominium_rules(default_notification_days,warning_days,adjustment_days,suspension_days,embargo_days,require_photo_on_inspection,require_photo_on_non_conformity,require_final_inspection,block_completion_with_open_nc,allow_inspector_warning,allow_inspector_adjustment,condominium_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE default_notification_days=VALUES(default_notification_days),warning_days=VALUES(warning_days),adjustment_days=VALUES(adjustment_days),suspension_days=VALUES(suspension_days),embargo_days=VALUES(embargo_days),require_photo_on_inspection=VALUES(require_photo_on_inspection),require_photo_on_non_conformity=VALUES(require_photo_on_non_conformity),require_final_inspection=VALUES(require_final_inspection),block_completion_with_open_nc=VALUES(block_completion_with_open_nc),allow_inspector_warning=VALUES(allow_inspector_warning),allow_inspector_adjustment=VALUES(allow_inspector_adjustment)';
        $vals=array_values($after); $vals[]=$condoId;
        $pdo->prepare($sql)->execute($vals);
        Audit::log((int)$user['id'],$condoId,'condominium_rules',$condoId,'UPDATED',['before'=>$before,'after'=>$after]);
        header('Location: /settings?condo='.$condoId.'&saved=1'); exit;
    }

    public function saveTemplate(): void
    {
        $user=$this->requireAuth(); $this->requirePost();
        $condoId=(int)($_POST['condo_id']??0); $this->requireCondoAccess($condoId,$user);
        $type=(string)($_POST['type']??'');
        if (!in_array($type,['IRREGULARITY','WARNING','ADJUSTMENT','SUSPENSION','EMBARGO','RELEASE'],true)) { http_response_code(422); exit('Tipo inválido.'); }
        $title=trim((string)($_POST['title']??'')); if ($title==='') { http_response_code(422); exit('Título obrigatório.'); }
        $pdo=Database::connection(); $stmt=$pdo->prepare('SELECT * FROM notification_templates WHERE condominium_id=? AND type=?'); $stmt->execute([$condoId,$type]); $before=$stmt->fetch()?:null;
        $after=['type'=>$type,'title'=>$title,'default_reason'=>trim((string)($_POST['default_reason']??''))?:null,'default_body'=>trim((string)($_POST['default_body']??''))?:null,'default_deadline_days'=>max(0,(int)($_POST['default_deadline_days']??0)),'active'=>!empty($_POST['active'])?1:0];
        $sql='INSERT INTO notification_templates(condominium_id,type,title,default_reason,default_body,default_deadline_days,active) VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE title=VALUES(title),default_reason=VALUES(default_reason),default_body=VALUES(default_body),default_deadline_days=VALUES(default_deadline_days),active=VALUES(active)';
        $pdo->prepare($sql)->execute([$condoId,$type,$after['title'],$after['default_reason'],$after['default_body'],$after['default_deadline_days'],$after['active']]);
        $stmt=$pdo->prepare('SELECT id FROM notification_templates WHERE condominium_id=? AND type=?'); $stmt->execute([$condoId,$type]); $id=(int)$stmt->fetchColumn();
        Audit::log((int)$user['id'],$condoId,'notification_template',$id,'UPDATED',['before'=>$before,'after'=>$after]);
        header('Location: /settings?condo='.$condoId.'&template=1'); exit;
    }

    public function addChecklistItem(): void
    {
        $user=$this->requireAuth(); $this->requirePost();
        $condoId=(int)($_POST['condo_id']??0); $this->requireCondoAccess($condoId,$user);
        $label=trim((string)($_POST['label']??'')); if ($label==='') { http_response_code(422); exit('Item obrigatório.'); }
        $pdo=Database::connection();
        $data=['label'=>$label,'category'=>trim((string)($_POST['category']??''))?:null,'required'=>!empty($_POST['required'])?1:0,'sort_order'=>(int)($_POST['sort_order']??0),'active'=>1];
        $pdo->prepare('INSERT INTO inspection_checklist_items(condominium_id,label,category,required,active,sort_order) VALUES(?,?,?,?,1,?)')->execute([$condoId,$data['label'],$data['category'],$data['required'],$data['sort_order']]);
        $id=(int)$pdo->lastInsertId(); Audit::log((int)$user['id'],$condoId,'inspection_checklist_item',$id,'CREATED',['after'=>$data]);
        header('Location: /settings?condo='.$condoId.'&checklist=1'); exit;
    }

    public function updateChecklistItem(): void
    {
        $user=$this->requireAuth(); $this->requirePost();
        $condoId=(int)($_POST['condo_id']??0); $this->requireCondoAccess($condoId,$user); $id=(int)($_POST['id']??0);
        $label=trim((string)($_POST['label']??'')); if ($id<=0 || $label==='') { http_response_code(422); exit('Item inválido.'); }
        $pdo=Database::connection(); $stmt=$pdo->prepare('SELECT * FROM inspection_checklist_items WHERE id=? AND condominium_id=?'); $stmt->execute([$id,$condoId]); $before=$stmt->fetch(); if(!$before){http_response_code(404);exit('Item não encontrado.');}
        $after=['label'=>$label,'category'=>trim((string)($_POST['category']??''))?:null,'required'=>!empty($_POST['required'])?1:0,'active'=>!empty($_POST['active'])?1:0,'sort_order'=>(int)($_POST['sort_order']??0)];
        $pdo->prepare('UPDATE inspection_checklist_items SET label=?,category=?,required=?,active=?,sort_order=? WHERE id=? AND condominium_id=?')->execute([$after['label'],$after['category'],$after['required'],$after['active'],$after['sort_order'],$id,$condoId]);
        Audit::log((int)$user['id'],$condoId,'inspection_checklist_item',$id,'UPDATED',['before'=>$before,'after'=>$after]);
        header('Location: /settings?condo='.$condoId.'&checklist=1'); exit;
    }

    public function addDocumentType(): void
    {
        $user=$this->requireAuth(); $this->requirePost();
        $condoId=(int)($_POST['condo_id']??0); $this->requireCondoAccess($condoId,$user);
        $name=trim((string)($_POST['name']??'')); if ($name==='') { http_response_code(422); exit('Nome do documento é obrigatório.'); }
        $pdo=Database::connection(); $required=!empty($_POST['required_default'])?1:0;
        $pdo->prepare('INSERT INTO document_types(condominium_id,name,required_default,active) VALUES(?,?,?,1)')->execute([$condoId,$name,$required]);
        $id=(int)$pdo->lastInsertId(); Audit::log((int)$user['id'],$condoId,'document_type',$id,'CREATED',['after'=>['name'=>$name,'required_default'=>$required,'active'=>1]]);
        header('Location: /settings?condo='.$condoId.'&document=1'); exit;
    }

    public function updateDocumentType(): void
    {
        $user=$this->requireAuth(); $this->requirePost();
        $condoId=(int)($_POST['condo_id']??0); $this->requireCondoAccess($condoId,$user); $id=(int)($_POST['id']??0);
        $name=trim((string)($_POST['name']??'')); if ($id<=0 || $name==='') { http_response_code(422); exit('Documento inválido.'); }
        $pdo=Database::connection(); $stmt=$pdo->prepare('SELECT * FROM document_types WHERE id=? AND condominium_id=?'); $stmt->execute([$id,$condoId]); $before=$stmt->fetch(); if(!$before){http_response_code(404);exit('Documento não encontrado.');}
        $after=['name'=>$name,'required_default'=>!empty($_POST['required_default'])?1:0,'active'=>!empty($_POST['active'])?1:0];
        $pdo->prepare('UPDATE document_types SET name=?,required_default=?,active=? WHERE id=? AND condominium_id=?')->execute([$after['name'],$after['required_default'],$after['active'],$id,$condoId]);
        Audit::log((int)$user['id'],$condoId,'document_type',$id,'UPDATED',['before'=>$before,'after'=>$after]);
        header('Location: /settings?condo='.$condoId.'&document=1'); exit;
    }

    public function saveSeverityRule(): void
    {
        $user=$this->requireAuth(); $this->requirePost();
        $condoId=(int)($_POST['condo_id']??0); $this->requireCondoAccess($condoId,$user);
        $severity=(string)($_POST['severity']??''); $action=(string)($_POST['suggested_action']??'NONE');
        if (!in_array($severity,['LOW','MEDIUM','HIGH','CRITICAL'],true) || !in_array($action,['NONE','WARNING','ADJUSTMENT','SUSPENSION','EMBARGO'],true)) { http_response_code(422); exit('Regra inválida.'); }
        $pdo=Database::connection(); $stmt=$pdo->prepare('SELECT * FROM severity_action_rules WHERE condominium_id=? AND severity=?'); $stmt->execute([$condoId,$severity]); $before=$stmt->fetch()?:null;
        $after=['severity'=>$severity,'suggested_action'=>$action,'auto_fill_notification'=>!empty($_POST['auto_fill_notification'])?1:0,'active'=>!empty($_POST['active'])?1:0];
        $sql='INSERT INTO severity_action_rules(condominium_id,severity,suggested_action,auto_fill_notification,active) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE suggested_action=VALUES(suggested_action),auto_fill_notification=VALUES(auto_fill_notification),active=VALUES(active)';
        $pdo->prepare($sql)->execute([$condoId,$severity,$action,$after['auto_fill_notification'],$after['active']]);
        $stmt=$pdo->prepare('SELECT id FROM severity_action_rules WHERE condominium_id=? AND severity=?'); $stmt->execute([$condoId,$severity]); $id=(int)$stmt->fetchColumn();
        Audit::log((int)$user['id'],$condoId,'severity_action_rule',$id,'UPDATED',['before'=>$before,'after'=>$after]);
        header('Location: /settings?condo='.$condoId.'&severity=1'); exit;
    }
}
