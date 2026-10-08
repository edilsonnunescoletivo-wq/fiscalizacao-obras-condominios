<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;

final class InspectionFormController
{
    private function loadWork(int $workId, array $user): array
    {
        $pdo=Database::connection();
        $stmt=$pdo->prepare('SELECT w.*,c.name condominium_name FROM works w JOIN condominiums c ON c.id=w.condominium_id WHERE w.id=?');
        $stmt->execute([$workId]); $work=$stmt->fetch();
        if(!$work){ http_response_code(404); exit('Obra não encontrada.'); }
        $stmt=$pdo->prepare('SELECT r.code FROM condominium_user cu JOIN roles r ON r.id=cu.role_id WHERE cu.condominium_id=? AND cu.user_id=? AND cu.active=1');
        $stmt->execute([$work['condominium_id'],$user['id']]); $roles=array_column($stmt->fetchAll(),'code');
        if(!array_intersect($roles,['ADMIN','SYNDIC','MANAGER','INSPECTOR'])){ http_response_code(403); exit('Acesso não autorizado.'); }
        $work['_roles']=$roles; return $work;
    }

    public function show(): void
    {
        if(!Auth::check()){ header('Location: /login'); exit; }
        $user=Auth::user(); $work=$this->loadWork((int)($_GET['work']??0),$user);
        $pdo=Database::connection();
        $stmt=$pdo->prepare('SELECT * FROM inspection_checklist_items WHERE condominium_id=? AND active=1 ORDER BY sort_order,id');
        $stmt->execute([$work['condominium_id']]); $items=$stmt->fetchAll();
        $stmt=$pdo->prepare('SELECT * FROM condominium_rules WHERE condominium_id=?'); $stmt->execute([$work['condominium_id']]); $rules=$stmt->fetch()?:[];
        require dirname(__DIR__,2).'/resources_inspection_form.php';
    }

    public function create(): void
    {
        if(!Auth::check()){ header('Location: /login'); exit; }
        if($_SERVER['REQUEST_METHOD']!=='POST' || !Csrf::validate($_POST['_token']??null)){ http_response_code(419); exit('Sessão expirada.'); }
        $user=Auth::user(); $work=$this->loadWork((int)($_POST['work_id']??0),$user);
        if(!in_array($work['status'],['IN_PROGRESS','NOTIFIED','SUSPENDED','EMBARGOED'],true)){ http_response_code(422); exit('A obra precisa estar em fase operacional.'); }
        $pdo=Database::connection();
        $stmt=$pdo->prepare('SELECT * FROM inspection_checklist_items WHERE condominium_id=? AND active=1 ORDER BY sort_order,id'); $stmt->execute([$work['condominium_id']]); $items=$stmt->fetchAll();
        $answers=[]; $checked=array_map('intval',(array)($_POST['checklist']??[]));
        foreach($items as $item){ $ok=in_array((int)$item['id'],$checked,true); if($item['required'] && !$ok){ http_response_code(422); exit('O item obrigatório "'.$item['label'].'" precisa ser confirmado.'); } $answers[(string)$item['id']]=['label'=>$item['label'],'category'=>$item['category'],'required'=>(bool)$item['required'],'checked'=>$ok]; }
        $rulesStmt=$pdo->prepare('SELECT require_photo_on_inspection FROM condominium_rules WHERE condominium_id=?'); $rulesStmt->execute([$work['condominium_id']]); $requirePhoto=(int)($rulesStmt->fetchColumn()?:0)===1;
        if($requirePhoto && empty($_FILES['photo']['tmp_name'])){ http_response_code(422); exit('Este condomínio exige foto na fiscalização.'); }
        $result=(string)($_POST['result']??'COMPLIANT'); if(!in_array($result,['COMPLIANT','WITH_ISSUES','CRITICAL'],true)){ http_response_code(422); exit('Resultado inválido.'); }
        $stmt=$pdo->prepare('INSERT INTO inspections(work_id,inspector_user_id,inspected_at,stage,notes,checklist_json,result) VALUES(?,?,NOW(),?,?,?,?)');
        $stmt->execute([$work['id'],$user['id'],trim((string)($_POST['stage']??''))?:null,trim((string)($_POST['notes']??''))?:null,json_encode($answers,JSON_UNESCAPED_UNICODE),$result]);
        $inspectionId=(int)$pdo->lastInsertId();
        if(!empty($_FILES['photo']['tmp_name'])) $this->storePhoto($inspectionId,(int)$work['id'],$user,$_FILES['photo']);
        $pdo->prepare('INSERT INTO work_events(work_id,user_id,event_type,title,description) VALUES(?,?,?,?,?)')->execute([$work['id'],$user['id'],'INSPECTION_CREATED','Fiscalização registrada','Vistoria #'.$inspectionId]);
        header('Location: /work?id='.(int)$work['id'].'&inspection=1'); exit;
    }

    private function storePhoto(int $inspectionId,int $workId,array $user,array $file): void
    {
        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || ($file['size']??0)>10*1024*1024){ http_response_code(422); exit('Foto inválida ou maior que 10 MB.'); }
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']); $ext=['image/jpeg'=>'jpg','image/png'=>'png'][$mime]??null;
        if(!$ext){ http_response_code(422); exit('A foto deve ser JPG ou PNG.'); }
        $folder=dirname(__DIR__,2).'/storage/uploads/inspections/'.$inspectionId; if(!is_dir($folder) && !mkdir($folder,0775,true) && !is_dir($folder)){ http_response_code(500); exit('Falha no armazenamento.'); }
        $name=bin2hex(random_bytes(16)).'.'.$ext; if(!move_uploaded_file($file['tmp_name'],$folder.'/'.$name)){ http_response_code(500); exit('Falha ao salvar a foto.'); }
        Database::connection()->prepare('INSERT INTO inspection_photos(inspection_id,work_id,original_name,stored_path,mime_type,caption,uploaded_by) VALUES(?,?,?,?,?,?,?)')->execute([$inspectionId,$workId,basename((string)$file['name']),'storage/uploads/inspections/'.$inspectionId.'/'.$name,$mime,'Fiscalização',$user['id']]);
    }
}
