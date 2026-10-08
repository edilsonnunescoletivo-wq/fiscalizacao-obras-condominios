<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;

final class CorrectionController
{
    private function requireUser(): array
    {
        if (!Auth::check()) { header('Location: /login'); exit; }
        return Auth::user();
    }

    private function rolesForCondo(int $condoId, int $userId): array
    {
        $stmt=Database::connection()->prepare('SELECT r.code FROM condominium_user cu JOIN roles r ON r.id=cu.role_id WHERE cu.condominium_id=? AND cu.user_id=? AND cu.active=1');
        $stmt->execute([$condoId,$userId]); return array_column($stmt->fetchAll(),'code');
    }

    private function loadNc(int $ncId, array $user): array
    {
        $pdo=Database::connection();
        $stmt=$pdo->prepare('SELECT nc.*,w.condominium_id,w.responsible_user_id,w.unit,c.name condominium_name FROM non_conformities nc JOIN works w ON w.id=nc.work_id JOIN condominiums c ON c.id=w.condominium_id WHERE nc.id=?');
        $stmt->execute([$ncId]); $nc=$stmt->fetch();
        if(!$nc){ http_response_code(404); exit('Não conformidade não encontrada.'); }
        $roles=$this->rolesForCondo((int)$nc['condominium_id'],(int)$user['id']);
        if(!$roles){ http_response_code(403); exit('Acesso não autorizado.'); }
        $onlyResponsible=in_array('WORK_RESPONSIBLE',$roles,true) && count(array_diff($roles,['WORK_RESPONSIBLE']))===0;
        if($onlyResponsible && (int)$nc['responsible_user_id']!==(int)$user['id']){ http_response_code(403); exit('Acesso não autorizado.'); }
        $nc['_roles']=$roles; return $nc;
    }

    public function index(): void
    {
        $user=$this->requireUser(); $workId=(int)($_GET['work']??0); $pdo=Database::connection();
        $stmt=$pdo->prepare('SELECT w.*,c.name condominium_name FROM works w JOIN condominiums c ON c.id=w.condominium_id WHERE w.id=?'); $stmt->execute([$workId]); $work=$stmt->fetch();
        if(!$work){ http_response_code(404); exit('Obra não encontrada.'); }
        $roles=$this->rolesForCondo((int)$work['condominium_id'],(int)$user['id']); if(!$roles){ http_response_code(403); exit('Acesso não autorizado.'); }
        $onlyResponsible=in_array('WORK_RESPONSIBLE',$roles,true) && count(array_diff($roles,['WORK_RESPONSIBLE']))===0;
        if($onlyResponsible && (int)$work['responsible_user_id']!==(int)$user['id']){ http_response_code(403); exit('Acesso não autorizado.'); }
        $stmt=$pdo->prepare('SELECT nc.*,u.name creator_name,cu.name correction_user_name FROM non_conformities nc JOIN users u ON u.id=nc.created_by LEFT JOIN users cu ON cu.id=nc.correction_submitted_by WHERE nc.work_id=? ORDER BY FIELD(nc.status,"OPEN","CORRECTED","CLOSED"),nc.created_at DESC'); $stmt->execute([$workId]); $items=$stmt->fetchAll();
        $stmt=$pdo->prepare('SELECT * FROM non_conformity_evidence WHERE work_id=? ORDER BY created_at DESC'); $stmt->execute([$workId]); $rows=$stmt->fetchAll(); $evidence=[]; foreach($rows as $row){$evidence[(int)$row['non_conformity_id']][]=$row;}
        $canReview=(bool)array_intersect($roles,['ADMIN','SYNDIC','MANAGER','INSPECTOR']);
        $canSubmit=in_array('WORK_RESPONSIBLE',$roles,true)||$canReview;
        require dirname(__DIR__,2).'/resources_corrections.php';
    }

    public function submit(): void
    {
        $user=$this->requireUser();
        if($_SERVER['REQUEST_METHOD']!=='POST' || !Csrf::validate($_POST['_token']??null)){ http_response_code(419); exit('Sessão expirada.'); }
        $ncId=(int)($_POST['non_conformity_id']??0); $nc=$this->loadNc($ncId,$user);
        if($nc['status']!=='OPEN'){ http_response_code(422); exit('Esta não conformidade não aceita nova correção.'); }
        $notes=trim((string)($_POST['correction_notes']??'')); if($notes===''){ http_response_code(422); exit('Descreva a correção realizada.'); }
        $pdo=Database::connection();
        $pdo->prepare('UPDATE non_conformities SET status="CORRECTED",correction_notes=?,correction_submitted_by=?,correction_submitted_at=NOW() WHERE id=? AND status="OPEN"')->execute([$notes,$user['id'],$ncId]);
        if(!empty($_FILES['evidence']['tmp_name'])) $this->storeEvidence($ncId,(int)$nc['work_id'],$user,$_FILES['evidence']);
        $pdo->prepare('INSERT INTO work_events(work_id,user_id,event_type,title,description) VALUES(?,?,?,?,?)')->execute([$nc['work_id'],$user['id'],'CORRECTION_SUBMITTED','Correção enviada para validação',$notes]);
        header('Location: /corrections?work='.(int)$nc['work_id'].'&submitted=1'); exit;
    }

    public function review(): void
    {
        $user=$this->requireUser();
        if($_SERVER['REQUEST_METHOD']!=='POST' || !Csrf::validate($_POST['_token']??null)){ http_response_code(419); exit('Sessão expirada.'); }
        $nc=$this->loadNc((int)($_POST['non_conformity_id']??0),$user);
        if(!array_intersect($nc['_roles'],['ADMIN','SYNDIC','MANAGER','INSPECTOR'])){ http_response_code(403); exit('Acesso não autorizado.'); }
        if($nc['status']!=='CORRECTED'){ http_response_code(422); exit('Não há correção aguardando validação.'); }
        $decision=(string)($_POST['decision']??''); $notes=trim((string)($_POST['review_notes']??''));
        if(!in_array($decision,['APPROVE','RETURN'],true)){ http_response_code(422); exit('Decisão inválida.'); }
        $newStatus=$decision==='APPROVE'?'CLOSED':'OPEN';
        $pdo=Database::connection();
        $pdo->prepare('UPDATE non_conformities SET status=?,resolved_by=?,resolved_at=CASE WHEN ?="CLOSED" THEN NOW() ELSE NULL END WHERE id=?')->execute([$newStatus,$user['id'],$newStatus,$nc['id']]);
        $title=$decision==='APPROVE'?'Correção validada pelo fiscal':'Correção devolvida para novo ajuste';
        $pdo->prepare('INSERT INTO work_events(work_id,user_id,event_type,title,description) VALUES(?,?,?,?,?)')->execute([$nc['work_id'],$user['id'],'CORRECTION_REVIEWED',$title,$notes?:null]);
        header('Location: /corrections?work='.(int)$nc['work_id'].'&reviewed=1'); exit;
    }

    private function storeEvidence(int $ncId,int $workId,array $user,array $file): void
    {
        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || ($file['size']??0)>10*1024*1024){ http_response_code(422); exit('Evidência inválida ou maior que 10 MB.'); }
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']); $allowed=['image/jpeg'=>'jpg','image/png'=>'png','application/pdf'=>'pdf'];
        if(!isset($allowed[$mime])){ http_response_code(422); exit('Use JPG, PNG ou PDF como evidência.'); }
        $folder=dirname(__DIR__,2).'/storage/uploads/corrections/'.$ncId; if(!is_dir($folder) && !mkdir($folder,0775,true) && !is_dir($folder)){ http_response_code(500); exit('Falha ao preparar armazenamento.'); }
        $name=bin2hex(random_bytes(16)).'.'.$allowed[$mime]; if(!move_uploaded_file($file['tmp_name'],$folder.'/'.$name)){ http_response_code(500); exit('Falha ao salvar evidência.'); }
        Database::connection()->prepare('INSERT INTO non_conformity_evidence(non_conformity_id,work_id,original_name,stored_path,mime_type,caption,uploaded_by) VALUES(?,?,?,?,?,?,?)')->execute([$ncId,$workId,basename((string)$file['name']),'storage/uploads/corrections/'.$ncId.'/'.$name,$mime,trim((string)($_POST['evidence_caption']??''))?:null,$user['id']]);
    }

    public function evidence(): void
    {
        $user=$this->requireUser(); $id=(int)($_GET['id']??0); $pdo=Database::connection();
        $stmt=$pdo->prepare('SELECT e.*,nc.id nc_id FROM non_conformity_evidence e JOIN non_conformities nc ON nc.id=e.non_conformity_id WHERE e.id=?'); $stmt->execute([$id]); $e=$stmt->fetch();
        if(!$e){ http_response_code(404); exit('Evidência não encontrada.'); }
        $this->loadNc((int)$e['nc_id'],$user);
        $root=realpath(dirname(__DIR__,2).'/storage/uploads/corrections'); $file=realpath(dirname(__DIR__,2).'/'.$e['stored_path']);
        if(!$root || !$file || !str_starts_with($file,$root.DIRECTORY_SEPARATOR)){ http_response_code(404); exit('Arquivo não encontrado.'); }
        header('X-Content-Type-Options: nosniff'); header('Content-Type: '.$e['mime_type']); header('Content-Length: '.filesize($file)); header("Content-Disposition: inline; filename*=UTF-8''".rawurlencode($e['original_name'])); readfile($file); exit;
    }
}
