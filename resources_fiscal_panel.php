<?php
$severityLabels=['LOW'=>'Leve','MEDIUM'=>'Moderada','HIGH'=>'Grave','CRITICAL'=>'Crítica'];
$actionLabels=['NONE'=>'Nenhuma','WARNING'=>'Advertência','ADJUSTMENT'=>'Adequação','SUSPENSION'=>'Suspensão','EMBARGO'=>'Embargo'];
$ruleBool=static fn(string $key, bool $default=false): string => (($rules[$key] ?? $default) ? 'Sim' : 'Não');
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Painel do Fiscal · <?=htmlspecialchars($condominium['name'])?></title>
<link rel="stylesheet" href="/assets/css/app.css">
<link rel="stylesheet" href="/assets/css/ui-v2.css">
</head>
<body class="app-body"><div class="shell">
<?php require __DIR__ . '/resources_sidebar.php'; ?>
<main class="content">
<header class="topbar condo-topbar"><div><p class="eyebrow">PAINEL DO FISCAL</p><h1><?=htmlspecialchars($condominium['name'])?></h1><p class="muted">Consulta operacional das regras e do checklist vigente. Esta tela é somente leitura.</p></div><a class="button secondary" href="/works?condo=<?=(int)$condominium['id']?>">← Painel</a></header>

<div class="stats-grid fiscal-stats">
<article class="stat-card"><span>Obras operacionais</span><strong><?=(int)($workStats['operational_works']??0)?></strong></article>
<article class="stat-card"><span>NCs abertas</span><strong><?=$openNc?></strong></article>
<article class="stat-card"><span>Fiscalizações · 30 dias</span><strong><?=$inspections30d?></strong></article>
<article class="stat-card"><span>Suspensas</span><strong><?=(int)($workStats['suspended_works']??0)?></strong></article>
<article class="stat-card"><span>Embargadas</span><strong><?=(int)($workStats['embargoed_works']??0)?></strong></article>
<article class="stat-card"><span>Checklist ativo</span><strong><?=count($checklist)?></strong></article>
</div>

<section class="panel"><div class="panel-header"><div><h2>Regras vigentes</h2><p>Parâmetros que afetam fiscalizações, não conformidades e conclusão.</p></div></div>
<div class="rule-summary-grid">
<div><small>Foto na fiscalização</small><strong><?=$ruleBool('require_photo_on_inspection')?></strong></div>
<div><small>Foto em NC</small><strong><?=$ruleBool('require_photo_on_non_conformity')?></strong></div>
<div><small>Vistoria final</small><strong><?=$ruleBool('require_final_inspection',true)?></strong></div>
<div><small>Bloqueio com NC aberta</small><strong><?=$ruleBool('block_completion_with_open_nc',true)?></strong></div>
<div><small>Fiscal pode advertir</small><strong><?=$ruleBool('allow_inspector_warning',true)?></strong></div>
<div><small>Fiscal pode pedir adequação</small><strong><?=$ruleBool('allow_inspector_adjustment',true)?></strong></div>
</div></section>

<section class="panel"><div class="panel-header"><div><h2>Checklist de fiscalização</h2><p><?=count($checklist)?> item(ns) ativo(s), na ordem em que aparecem durante a vistoria.</p></div></div>
<?php if(!$checklist):?><div class="empty-state"><p>Nenhum item de checklist ativo neste condomínio.</p></div><?php else:?><div class="operation-list">
<?php foreach($checklist as $item):?><article class="operation-card"><div><small><?=htmlspecialchars($item['category']?:'Geral')?> · Ordem <?=(int)$item['sort_order']?></small><strong><?=htmlspecialchars($item['label'])?></strong></div><span class="badge"><?=$item['required']?'Obrigatório':'Opcional'?></span></article><?php endforeach;?>
</div><?php endif;?></section>

<section class="panel"><div class="panel-header"><div><h2>Ação sugerida por gravidade</h2><p>Estas regras orientam a providência após uma não conformidade; não executam suspensão ou embargo automaticamente.</p></div></div>
<?php if(!$severityRules):?><div class="empty-state"><p>Nenhuma regra específica configurada.</p></div><?php else:?><div class="operation-list">
<?php foreach($severityRules as $rule):?><article class="operation-card"><div><strong><?=htmlspecialchars($severityLabels[$rule['severity']]??$rule['severity'])?></strong><small><?=$rule['active']?'Regra ativa':'Regra inativa'?></small></div><span class="badge"><?=htmlspecialchars($actionLabels[$rule['suggested_action']]??$rule['suggested_action'])?></span></article><?php endforeach;?>
</div><?php endif;?></section>
</main></div></body></html>
