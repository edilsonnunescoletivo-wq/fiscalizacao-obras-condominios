<?php
use App\Core\Csrf;
$severityLabels=['LOW'=>'Leve','MEDIUM'=>'Moderada','HIGH'=>'Grave','CRITICAL'=>'Crítica'];
$priorityLabels=['LOW'=>'Baixa','NORMAL'=>'Normal','HIGH'=>'Alta','URGENT'=>'Urgente'];
$statusLabels=['OPEN'=>'Aberta','CORRECTED'=>'Correção enviada'];
$workStatusLabels=['IN_PROGRESS'=>'Em andamento','NOTIFIED'=>'Notificada','SUSPENDED'=>'Suspensa','EMBARGOED'=>'Embargada','COMPLETION_INSPECTION'=>'Vistoria de conclusão','COMPLETED'=>'Concluída'];
$sidebarCondoId=(int)$condominium['id'];
$sidebarRoles=$roles;
$sidebarActive='pending';
?>
<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Central de Pendências · <?=htmlspecialchars($condominium['name'])?></title><link rel="stylesheet" href="/assets/css/app.css"><link rel="stylesheet" href="/assets/css/ui-v2.css"><link rel="stylesheet" href="/assets/css/operational-v3.css"></head>
<body class="app-body"><div class="shell">
<?php require __DIR__ . '/resources_sidebar.php'; ?>
<main class="content">
<header class="topbar condo-topbar"><div><p class="eyebrow">Fiscalização</p><h1>Central de Pendências</h1><p class="muted"><?=htmlspecialchars($condominium['name'])?> · Priorize não conformidades por gravidade, urgência, prazo e responsável.</p></div><a class="button secondary" href="/works?condo=<?=(int)$condominium['id']?>">← Painel</a></header>

<?php if(isset($_GET['updated'])):?><div class="alert success">Prioridade e responsável da pendência atualizados.</div><?php endif;?>
<?php if(!$routingReady):?><div class="alert">A Central de Pendências continua disponível. O direcionamento por responsável e prioridade será ativado automaticamente após a atualização controlada do banco.</div><?php endif;?>

<section class="operational-kpis compact">
    <div class="operational-kpi"><small>Abertas</small><strong><?=(int)$summary['open_count']?></strong><span>Aguardando correção</span></div>
    <div class="operational-kpi"><small>Em validação</small><strong><?=(int)$summary['corrected_count']?></strong><span>Correção enviada</span></div>
    <div class="operational-kpi danger"><small>Vencidas</small><strong><?=(int)$summary['overdue_count']?></strong><span>Prazo ultrapassado</span></div>
    <div class="operational-kpi danger"><small>Urgentes</small><strong><?=(int)$summary['urgent_count']?></strong><span><?=$routingReady?'Prioridade máxima':'Estimativa pela gravidade'?></span></div>
</section>

<section class="panel">
<div class="panel-header works-header"><div><h2>Pendências operacionais</h2><p><?=count($items)?> item(ns) conforme os filtros selecionados.</p></div></div>
<form class="works-filters pending-filters" method="get" action="/condominium/pending">
<input type="hidden" name="condo" value="<?=(int)$condominium['id']?>">
<select name="work"><option value="">Todas as obras</option><?php foreach($works as $w):?><option value="<?=(int)$w['id']?>" <?=$workFilter===(int)$w['id']?'selected':''?>>Unidade <?=htmlspecialchars($w['unit'])?> · <?=htmlspecialchars($w['owner_name'])?></option><?php endforeach;?></select>
<select name="severity"><option value="">Todas as gravidades</option><?php foreach($severityLabels as $value=>$label):?><option value="<?=$value?>" <?=$severity===$value?'selected':''?>><?=$label?></option><?php endforeach;?></select>
<?php if($routingReady):?><select name="priority"><option value="">Todas as prioridades</option><?php foreach($priorityLabels as $value=>$label):?><option value="<?=$value?>" <?=$priority===$value?'selected':''?>><?=$label?></option><?php endforeach;?></select>
<select name="assignee"><option value="">Todos os responsáveis</option><?php foreach($assignees as $a):?><option value="<?=(int)$a['id']?>" <?=$assignee===(int)$a['id']?'selected':''?>><?=htmlspecialchars($a['name'])?></option><?php endforeach;?></select><?php endif;?>
<select name="status"><option value="">Todos os estados</option><option value="OPEN" <?=$status==='OPEN'?'selected':''?>>Aguardando correção</option><option value="CORRECTED" <?=$status==='CORRECTED'?'selected':''?>>Aguardando validação</option></select>
<label class="checkbox-filter"><input type="checkbox" name="overdue" value="1" <?=$onlyOverdue?'checked':''?>> Somente vencidas</label>
<button class="button secondary">Filtrar</button>
<?php if($workFilter||$severity!==''||$priority!==''||$status!==''||$assignee||$onlyOverdue):?><a class="button secondary" href="/condominium/pending?condo=<?=(int)$condominium['id']?>">Limpar</a><?php endif;?>
</form>

<?php if(!$items):?><div class="empty-state"><h3>Nenhuma pendência encontrada</h3><p>Não existem itens compatíveis com os filtros atuais.</p></div><?php else:?><div class="pending-grid">
<?php foreach($items as $item):?>
<article class="pending-card severity-<?=strtolower($item['severity'])?> <?=$item['overdue']?'is-overdue':''?>">
<div class="pending-card-head"><div><small>Unidade <?=htmlspecialchars($item['unit'])?></small><h3><?=htmlspecialchars($item['title'])?></h3></div><div class="pending-badges"><span class="risk-badge risk-<?=strtolower($item['priority']==='URGENT'?'critical':($item['priority']==='HIGH'?'high':($item['priority']==='NORMAL'?'medium':'low')))?>">Prioridade <?=htmlspecialchars($priorityLabels[$item['priority']]??$item['priority'])?></span><span class="badge"><?=htmlspecialchars($severityLabels[$item['severity']]??$item['severity'])?></span><span class="badge"><?=htmlspecialchars($statusLabels[$item['status']]??$item['status'])?></span><?php if($item['overdue']):?><span class="late-pill">VENCIDA</span><?php endif;?></div></div>
<p><?=nl2br(htmlspecialchars($item['description']))?></p>
<div class="pending-meta"><span><small>Obra</small><strong><?=htmlspecialchars($workStatusLabels[$item['work_status']]??$item['work_status'])?></strong></span><span><small>Prazo</small><strong><?=htmlspecialchars($item['corrective_deadline']?:'Sem prazo')?></strong></span><span><small>Responsável</small><strong><?=htmlspecialchars($routingReady?($item['assigned_name']?:'Não atribuído'):'Aguardando ativação')?></strong></span><span><small>Fiscalização</small><strong><?=htmlspecialchars($item['inspection_stage']?:'Sem vínculo')?></strong></span><span><small>Registrada por</small><strong><?=htmlspecialchars($item['creator_name'])?></strong></span></div>
<?php if($item['status']==='CORRECTED'):?><div class="correction-highlight"><strong>Correção enviada<?= $item['correction_submitted_at']?' em '.htmlspecialchars($item['correction_submitted_at']):'' ?>.</strong><?php if($item['correction_notes']):?><span><?=nl2br(htmlspecialchars($item['correction_notes']))?></span><?php endif;?></div><?php endif;?>
<?php if($canRoute):?><form class="works-filters pending-filters" method="post" action="/work/non-conformity/routing" style="margin-top:12px"><input type="hidden" name="_token" value="<?=htmlspecialchars(Csrf::token())?>"><input type="hidden" name="work_id" value="<?=(int)$item['work_id']?>"><input type="hidden" name="non_conformity_id" value="<?=(int)$item['id']?>"><select name="priority" aria-label="Prioridade"><?php foreach($priorityLabels as $value=>$label):?><option value="<?=$value?>" <?=$item['priority']===$value?'selected':''?>>Prioridade: <?=$label?></option><?php endforeach;?></select><select name="assigned_user_id" aria-label="Responsável"><option value="">Sem responsável atribuído</option><?php foreach($assignees as $a):?><option value="<?=(int)$a['id']?>" <?=(int)$item['assigned_user_id']===(int)$a['id']?'selected':''?>><?=htmlspecialchars($a['name'])?><?=!empty($a['role_names'])?' · '.htmlspecialchars($a['role_names']):''?></option><?php endforeach;?></select><button class="button secondary" type="submit">Salvar direcionamento</button></form><?php endif;?>
<div class="card-actions-row"><a class="button primary" href="/corrections?work=<?=(int)$item['work_id']?>">Analisar correção</a><a class="button secondary" href="/work?id=<?=(int)$item['work_id']?>#correcoes">Abrir obra</a><a class="button secondary" href="/work/diary?id=<?=(int)$item['work_id']?>">Diário</a></div>
</article>
<?php endforeach;?>
</div><?php endif;?>
</section>
</main></div></body></html>
