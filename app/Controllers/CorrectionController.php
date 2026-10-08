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

    private function loadNc(int $ncId, array $user): array
    {
        $pdo=Database::connection();
        $stmt=$pdo->prepare('SELECT nc.*,w.condominium_id,w.responsible_user_id FROM non_conformities nc JOIN works w ON w.id=nc.work_id WHERE nc.id=?');
        $stmt->execute([$ncId]); $nc=$stmt->fetch();
        if(!$nc){ http_response_code(404); exit('Não conformidade não encontrada.'); }
        $stmt=$pdo->prepare('SELECT r.code FROM condominium_user cu JOIN roles r ON r.id=cu.role_id WHERE cu.condominium_id=? AND cu.user_id=? AND cu.active=1');
        $stmt->execute([$nc['condominium_id'],$user['id']]); $roles=array_column($stmt->fetchAll(),'code');
        if(!$roles){ http_response_code(403); exit('Acesso não autorizado.'); }
        $onlyResponsible=in_array('WORK_RESPONSIBLE',$roles,true) && count(array_diff($roles,['WORK_RESPONSIBLE']))===0;
        if($onlyResponsible && (int)$nc['responsible_user_id']!==(int)$user['id']){ http_response_code(403); exit('Acesso não autorizado.'); }
        $nc['_roles']=$roles; return $nc;
    }

    public function submit(): void
    {
        $user=$this->requireUser();
        if($_SERVER['REQUEST_METHOD']!=='POST' || !Csrf::validate($_POST['_token']??null)){ http_response_code(419); exit('Sessão expirada.'); }
        $ncId=(int)($_POST['non_conformity_id']??0); $nc=$this->loadNc($ncId,$user);
        if($nc['status']!=='OPEN'){ http_response_code(422); exit('Esta não conformidade não aceita nova correção.'); }
        $notes=trim((string)($_POST['correction_notes']??''));
        if($notes===''){ http_response_code(422); exit('Descreva a correção realizada.'); }
        $pdo=Database::connection();
        $pdo->prepare('UPDATE non_conformities SET status="CORRECTED",correction_notes=?,correction_submitted_by=?,correction_submitted_at=NOW() WHERE id=? AND status="OPEN"')->execute([$notes,$user['id'],$ncId]);
        if(!empty($_FILES['evidence']['tmp_name'])) $this->storeEvidence($ncId,(int)$nc['work_id'],$user,$_FILES['evidence']);
        $pdo->prepare('INSERT INTO work_events(work_id,user_id,event_type,title,description) VALUES(?,?,?,?,?)')->execute([$nc['work_id'],$user['id'],'CORRECTION_SUBMITTED','Correção enviada para validação',$notes]);
        header('Location: /work?id='.(int)$nc['work_id'].'&correction=1'); exit;
    }

    private function storeEvidence(int $ncId,int $workId,array $user,array $file): void
    {
        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || ($file['size']??0)>10*1024*1024){ http_response_code(422); exit('Evidência inválida ou maior que 10 MB.'); }
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $allowed=['image/jpeg'=>'jpg','image/png'=>'png','application/pdf'=>'pdf'];
        if(!isset($allowed[$mime])){ http_response_code(422); exit('Use JPG, PNG ou PDF como evidência.'); }
        $folder=dirname(__DIR__,2).'/storage/uploads/corrections/'.$ncId;
        if(!is_dir($folder) && !mkdir($folder,0775,true) && !is_dir($folder)){ http_response_code(500); exit('Falha ao preparar armazenamento.'); }
        $name=bin2hex(random_bytes(16)).'.'.$allowed[$mime]; $full=$folder.'/'.$name;
        if(!move_uploaded_file($file['tmp_name'],$full)){ http_response_code(500); exit('Falha ao salvar evidência.'); }
        $relative='storage/uploads/corrections/'.$ncId.'/'.$name;
        Database::connection()->prepare('INSERT INTO non_conformity_evidence(non_conformity_id,work_id,original_name,stored_path,mime_type,caption,uploaded_by) VALUES(?,?,?,?,?,?,?)')->execute([$ncId,$workId,basename((string)$file['name']),$relative,$mime,trim((string)($_POST['evidence_caption']??''))?:null,$user['id']]);
    }

    public function evidence(): void
    {
        $user=$this->requireUser(); $id=(int)($_GET['id']??0); $pdo=Database::connection();
        $stmt=$pdo->prepare('SELECT e.*,nc.id nc_id FROM non_conformity_evidence e JOIN non_conformities nc ON nc.id=e.non_conformity_id WHERE e.id=?'); $stmt->execute([$id]); $e=$stmt->fetch();
        if(!$e){ http_response_code(404); exit('Evidência não encontrada.'); }
        $this->loadNc((int)$e['nc_id'],$user);
        $root=realpath(dirname(__DIR__,2).'/storage/uploads/corrections'); $file=realpath(dirname(__DIR__,2).'/'.$e['stored_path']);
        if(!$root || !$file || !str_starts_with($file,$root.DIRECTORY_SEPARATOR)){ http_response_code(404); exit('Arquivo não encontrado.'); }
        header('X-Content-Type-Options: nosniff'); header('Content-Type: '.$e['mime_type']); header('Content-Length: '.filesize($file));
        header("Content-Disposition: inline; filename*=UTF-8''".rawurlencode($e['original_name'])); readfile($file); exit;
    }
}
