<?php
$statusLabels=['OPEN'=>'Aberta','CORRECTED'=>'Corrigida','CLOSED'=>'Encerrada','ISSUED'=>'Emitida','DELIVERED'=>'Entregue','RESOLVED'=>'Resolvida','CANCELLED'=>'Cancelada','APPROVED'=>'Aprovada','CORRECTION_REQUIRED'=>'Correção necessária','SUBMITTED'=>'Enviado','REJECTED'=>'Reprovado','COMPLETED'=>'Concluída'];
$documentStatusLabels=['PENDING'=>'Pendente','SUBMITTED'=>'Enviado','APPROVED'=>'Aprovado','CORRECTION_REQUIRED'=>'Correção necessária','REJECTED'=>'Reprovado'];
$resultLabels=['COMPLIANT'=>'Conforme','WITH_ISSUES'=>'Com apontamentos','CRITICAL'=>'Crítica','APPROVED'=>'Aprovada para conclusão','CORRECTION_REQUIRED'=>'Correção necessária'];
$severityLabels=['LOW'=>'Leve','MEDIUM'=>'Moderada','HIGH'=>'Grave','CRITICAL'=>'Crítica'];
$typeLabels=['IRREGULARITY'=>'Irregularidade','WARNING'=>'Advertência','ADJUSTMENT'=>'Adequação','SUSPENSION'=>'Suspensão','EMBARGO'=>'Embargo','RELEASE'=>'Liberação'];
$workStatusLabels=['IN_PROGRESS'=>'Em andamento','NOTIFIED'=>'Notificada','SUSPENDED'=>'Suspensa','EMBARGOED'=>'Embargada','COMPLETION_INSPECTION'=>'Vistoria final','COMPLETED'=>'Concluída'];
$sidebarCondoId=(int)$condominium['id'];
$sidebarRoles=$roles;
$sidebarActive=$module;
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=htmlspecialchars($title)?> · <?=htmlspecialchars($condominium['name'])?></title>
<link rel="stylesheet" href="/assets/css/app.css">
<link rel="stylesheet" href="/assets/css/ui-v2.css">
</head>
<body class="app-body"><div class="shell">
<?php require __DIR__ . '/resources_sidebar.php'; ?>
<main class="content">
<header class="topbar condo-topbar"><div><p class="eyebrow">MÓDULO DO CONDOMÍNIO</p><h1><?=htmlspecialchars($title)?></h1><p class="muted"><?=htmlspecialchars($condominium['name'])?> · <?=htmlspecialchars($description)?></p></div><a class="button secondary" href="/works?condo=<?=(int)$condominium['id']?>">← Painel</a></header>
<section class="panel"><div class="panel-header"><div><h2><?=htmlspecialchars($title)?></h2><p><?=count($items)?> registro(s).</p></div></div>
<?php if(!$items):?><div class="empty-state"><p>Nenhum registro encontrado neste módulo.</p></div><?php else:?><div class="operation-list">
<?php foreach($items as $item): ?>
<article class="operation-card <?=!empty($item['overdue'])?'row-overdue':''?>">
<?php if($module==='inspections'): ?>
<div><div class="row-gap"><strong>Vistoria #<?=(int)$item['id']?> · Unidade <?=htmlspecialchars($item['unit'])?></strong><span class="badge"><?=htmlspecialchars($resultLabels[$item['result']]??$item['result'])?></span></div><small><?=htmlspecialchars($item['inspector_name'])?> · <?=htmlspecialchars($item['inspected_at'])?></small><?php if($item['stage']):?><p><b>Etapa:</b> <?=htmlspecialchars($item['stage'])?></p><?php endif;?><?php if($item['notes']):?><p><?=nl2br(htmlspecialchars($item['notes']))?></p><?php endif;?></div><div class="inline-actions"><a class="button primary" target="_blank" href="/inspection/pdf?id=<?=(int)$item['id']?>">Relatório PDF</a><a class="button secondary" href="/inspection/photos?id=<?=(int)$item['id']?>">Evidências</a><a class="button secondary" href="/work?id=<?=(int)$item['work_id']?>#fiscalizacoes">Abrir obra</a></div>
<?php elseif($module==='corrections'): ?>
<div><div class="row-gap"><strong><?=htmlspecialchars($item['title'])?> · Unidade <?=htmlspecialchars($item['unit'])?></strong><span class="badge"><?=htmlspecialchars($severityLabels[$item['severity']]??$item['severity'])?> · <?=htmlspecialchars($statusLabels[$item['status']]??$item['status'])?></span><?php if(!empty($item['overdue'])):?><span class="late-pill">Prazo vencido</span><?php endif;?></div><small><?=htmlspecialchars($item['creator_name'])?> · <?=htmlspecialchars($item['created_at'])?></small><?php if($item['inspection_stage']):?><p><b>Vistoria:</b> <?=htmlspecialchars($item['inspection_stage'])?></p><?php endif;?><p><?=nl2br(htmlspecialchars($item['description']))?></p><?php if($item['corrective_deadline']):?><p><b>Prazo:</b> <?=htmlspecialchars(date('d/m/Y H:i',strtotime($item['corrective_deadline'])))?></p><?php endif;?></div><div class="inline-actions"><a class="button secondary" href="/corrections?work=<?=(int)$item['work_id']?>">Correções</a><a class="button secondary" href="/work?id=<?=(int)$item['work_id']?>#correcoes">Abrir obra</a></div>
<?php elseif($module==='notifications'): ?>
<div><div class="row-gap"><strong><?=htmlspecialchars($typeLabels[$item['type']]??$item['type'])?> · <?=htmlspecialchars($item['number'])?> · Unidade <?=htmlspecialchars($item['unit'])?></strong><span class="badge"><?=htmlspecialchars($statusLabels[$item['status']]??$item['status'])?></span><?php if(!empty($item['active_restriction'])):?><span class="late-pill">Restrição ativa</span><?php elseif(!empty($item['overdue'])):?><span class="late-pill">Prazo vencido</span><?php endif;?></div><small><?=htmlspecialchars($item['creator_name'])?> · <?=htmlspecialchars($item['issued_at']?:$item['created_at'])?><?php if(!empty($item['work_status'])):?> · Obra: <?=htmlspecialchars($workStatusLabels[$item['work_status']]??$item['work_status'])?><?php endif;?></small><p><b><?=htmlspecialchars($item['reason'])?></b></p><?php if($item['deadline']):?><p><b>Prazo:</b> <?=htmlspecialchars(date('d/m/Y H:i',strtotime($item['deadline'])))?></p><?php endif;?></div><div class="inline-actions"><a class="button secondary" href="/notifications?work=<?=(int)$item['work_id']?>">Gerenciar</a><a class="button secondary" target="_blank" href="/notification/pdf?id=<?=(int)$item['id']?>">PDF</a></div>
<?php elseif($module==='documents'): ?>
<div><div class="row-gap"><strong><?=htmlspecialchars($item['document_name'])?> · Unidade <?=htmlspecialchars($item['unit'])?></strong><span class="badge"><?=htmlspecialchars($documentStatusLabels[$item['status']]??$item['status'])?></span></div><small><?=htmlspecialchars($item['original_name'])?> · v<?=(int)$item['version']?> · <?=htmlspecialchars($item['uploaded_at'])?></small></div><div class="inline-actions"><a class="button secondary" target="_blank" href="/work/document?id=<?=(int)$item['id']?>">Abrir documento</a><a class="button secondary" href="/work?id=<?=(int)$item['work_id']?>#documentos">Abrir obra</a></div>
<?php elseif($module==='completion'): ?>
<div><div class="row-gap"><strong>Unidade <?=htmlspecialchars($item['unit'])?></strong><span class="badge"><?=htmlspecialchars($resultLabels[$item['result']]??$item['result'])?></span></div><small><?=htmlspecialchars($item['inspector_name'])?> · <?=htmlspecialchars($item['completed_at']?:$item['updated_at'])?></small><?php if($item['notes']):?><p><?=nl2br(htmlspecialchars($item['notes']))?></p><?php endif;?></div><div class="inline-actions"><a class="button secondary" href="/work/completion?id=<?=(int)$item['work_id']?>">Conclusão</a><?php if($item['result']==='APPROVED'):?><a class="button secondary" target="_blank" href="/work/completion/term?id=<?=(int)$item['work_id']?>">Termo PDF</a><?php endif;?></div>
<?php elseif($module==='dossier'): ?>
<div><div class="row-gap"><strong>Unidade <?=htmlspecialchars($item['unit'])?> · <?=htmlspecialchars($item['owner_name'])?></strong><span class="badge">Concluída</span></div><small><?=htmlspecialchars($item['work_type']?:'Obra')?> · <?=htmlspecialchars($item['completed_at']?:$item['updated_at'])?></small></div><div class="inline-actions"><a class="button secondary" href="/work?id=<?=(int)$item['work_id']?>">Abrir obra</a><a class="button primary" target="_blank" href="/work/dossier?id=<?=(int)$item['work_id']?>">Dossiê PDF</a></div>
<?php endif; ?>
</article>
<?php endforeach; ?>
</div><?php endif; ?>
</section>
</main></div></body></html>
