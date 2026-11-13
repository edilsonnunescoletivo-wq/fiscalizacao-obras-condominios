<?php
use App\Core\Auth;
use App\Core\Csrf;
$sidebarCondoId=(int)$condominium['id'];
$sidebarRoles=$roles;
$sidebarActive='users';
$roleLabels=['ADMIN'=>'Administrador','SYNDIC'=>'Síndico','MANAGER'=>'Gerente','INSPECTOR'=>'Fiscal','WORK_RESPONSIBLE'=>'Responsável pela obra','VIEWER'=>'Consulta'];
$roleDescriptions=[
'ADMIN'=>'Gestão completa do condomínio, configurações, obras, fiscalizações e usuários.',
'SYNDIC'=>'Gestão operacional e de acessos do condomínio, incluindo autorizações administrativas.',
'MANAGER'=>'Gerencia obras e decisões operacionais, sem administrar usuários locais.',
'INSPECTOR'=>'Fiscaliza, analisa documentos, registra NCs e emite as notificações permitidas pelas regras.',
'VIEWER'=>'Consulta informações e andamento sem executar ações operacionais.',
'WORK_RESPONSIBLE'=>'Acesso restrito à própria obra, concedido por convite.',
];
$currentUserId=(int)(Auth::user()['id']??0);
$globalAdminIds=array_map('intval',array_column($globalAdmins,'id'));
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Usuários e permissões · <?=htmlspecialchars($condominium['name'])?></title>
<link rel="stylesheet" href="/assets/css/app.css">
<link rel="stylesheet" href="/assets/css/ui-v2.css">
<style>.role-guide-item{border:1px solid #eaecf0;border-radius:10px;padding:12px 14px;background:#f8fafc}.role-guide-item strong,.role-guide-item span{display:block}.role-guide-item span{color:#667085;font-size:12px;line-height:1.45;margin-top:5px}</style>
</head>
<body class="app-body"><div class="shell">
<?php require __DIR__ . '/resources_sidebar.php'; ?>
<main class="content">
<header class="topbar condo-topbar"><div><p class="eyebrow">GESTÃO DE ACESSOS</p><h1>Usuários / Permissões</h1><p class="muted"><?=htmlspecialchars($condominium['name'])?> · vínculos e perfis de acesso do condomínio.</p></div><a class="button secondary" href="/works?condo=<?=(int)$condominium['id']?>">← Painel</a></header>
<?php if(isset($_GET['added'])):?><div class="alert success">Acesso adicionado ou reativado com sucesso.</div><?php endif;?>
<?php if(isset($_GET['updated'])):?><div class="alert success">Situação do acesso atualizada.</div><?php endif;?>
<?php if(($_GET['error']??'')==='user'):?><div class="alert error">Não foi encontrado usuário ativo com esse e-mail. O usuário precisa existir antes de receber um perfil neste condomínio.</div><?php endif;?>
<?php if(($_GET['error']??'')==='invalid'):?><div class="alert error">Informe um e-mail válido e um perfil permitido.</div><?php endif;?>

<section class="panel"><div class="panel-header"><div><h2>Perfis disponíveis</h2><p>Resumo das permissões para escolher o nível de acesso correto.</p></div></div><div class="status-breakdown-grid role-guide"><?php foreach(['ADMIN','SYNDIC','MANAGER','INSPECTOR','VIEWER'] as $roleCode):?><div class="role-guide-item"><strong><?=htmlspecialchars($roleLabels[$roleCode])?></strong><span><?=htmlspecialchars($roleDescriptions[$roleCode])?></span></div><?php endforeach;?></div><div class="description-box" style="margin-top:12px"><b>Responsável pela obra:</b> <?=htmlspecialchars($roleDescriptions['WORK_RESPONSIBLE'])?></div></section>

<?php if($globalAdmins):?><section class="panel"><div class="panel-header"><div><h2>Administração global</h2><p>Super administradores têm acesso global e não dependem de vínculo local.</p></div></div><div class="operation-list"><?php foreach($globalAdmins as $admin):?><article class="operation-card"><div><strong><?=htmlspecialchars($admin['name'])?></strong><small><?=htmlspecialchars($admin['email'])?></small></div><span class="badge"><?=$admin['active']?'Super Admin ativo':'Inativo'?></span></article><?php endforeach;?></div></section><?php endif;?>

<section class="panel"><div class="panel-header"><div><h2>Adicionar acesso</h2><p>Vincule um usuário já cadastrado ao condomínio. Responsável pela Obra continua sendo liberado pelo convite da própria obra.</p></div></div><form method="post" action="/users/access/add" class="form-grid"><input type="hidden" name="_token" value="<?=htmlspecialchars(Csrf::token())?>"><input type="hidden" name="condo_id" value="<?=(int)$condominium['id']?>"><label>E-mail do usuário<input type="email" name="email" required placeholder="usuario@exemplo.com"></label><label>Perfil<select name="role_code" required><?php foreach($availableRoles as $role):?><option value="<?=htmlspecialchars($role['code'])?>"><?=htmlspecialchars($roleLabels[$role['code']]??$role['name'])?></option><?php endforeach;?></select></label><div class="full form-actions"><button class="button primary" type="submit">Adicionar acesso</button></div></form></section>

<section class="panel"><div class="panel-header"><div><h2>Acessos do condomínio</h2><p><?=count($accesses)?> vínculo(s) cadastrados.</p></div></div><?php if(!$accesses):?><div class="empty-state"><p>Nenhum vínculo local cadastrado.</p></div><?php else:?><div class="table-wrap"><table><thead><tr><th>Usuário</th><th>E-mail</th><th>Perfil</th><th>Situação</th><th>Ação</th></tr></thead><tbody><?php foreach($accesses as $access):?><tr><td><?=htmlspecialchars($access['name'])?></td><td><?=htmlspecialchars($access['email'])?></td><td><?=htmlspecialchars($roleLabels[$access['role_code']]??$access['role_name'])?><small class="table-subline"><?=htmlspecialchars($roleDescriptions[$access['role_code']]??'')?></small></td><td><span class="badge"><?=($access['active']&&$access['user_active'])?'Ativo':'Inativo'?></span></td><td><?php if((int)$access['user_id']===$currentUserId):?><span class="muted">Seu acesso</span><?php elseif(in_array((int)$access['user_id'],$globalAdminIds,true)):?><span class="muted">Super Admin global</span><?php elseif($access['role_code']==='WORK_RESPONSIBLE'):?><span class="muted">Gerenciado pela obra</span><?php else:?><form method="post" action="/users/access/toggle"><input type="hidden" name="_token" value="<?=htmlspecialchars(Csrf::token())?>"><input type="hidden" name="condo_id" value="<?=(int)$condominium['id']?>"><input type="hidden" name="access_id" value="<?=(int)$access['access_id']?>"><input type="hidden" name="active" value="<?=$access['active']?0:1?>"><button class="button <?=$access['active']?'danger':'secondary'?>" type="submit"><?=$access['active']?'Desativar':'Reativar'?></button></form><?php endif;?></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></section>
</main></div></body></html>
