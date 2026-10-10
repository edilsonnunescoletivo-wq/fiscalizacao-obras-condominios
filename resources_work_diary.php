<?php
$kindLabels=['EVENT'=>'Evento','INSPECTION'=>'Fiscalização','NC'=>'Não conformidade','CORRECTION'=>'Correção','NOTIFICATION'=>'Notificação'];
$sidebarCondoId=(int)$work['condominium_id'];
$sidebarWorkId=(int)$work['id'];
$sidebarWorkStatus=(string)$work['status'];
$sidebarRoles=$roles;
$sidebarActive='diary';
?>
<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Diário da obra · Unidade <?=htmlspecialchars($work['unit'])?></title><link rel="stylesheet" href="/assets/css/app.css"><link rel="stylesheet" href="/assets/css/ui-v2.css"></head>
<body class="app-body"><div class="shell">
<?php require __DIR__ . '/resources_sidebar.php'; ?>
<main class="content">
<header class="topbar"><div><p class="eyebrow"><?=htmlspecialchars($work['condominium_name'])?></p><h1>Diário da Obra · Unidade <?=htmlspecialchars($work['unit'])?></h1><p class="muted">Linha do tempo consolidada de atividades, fiscalizações, não conformidades, correções e notificações.</p></div><a class="button secondary" href="/work?id=<?=(int)$work['id']?>">← Voltar à obra</a></header>
<section class="panel diary-summary"><div class="info-grid"><div><small>Proprietário</small><strong><?=htmlspecialchars($work['owner_name'])?></strong></div><div><small>Tipo</small><strong><?=htmlspecialchars($work['work_type']?:'-')?></strong></div><div><small>Status</small><strong><?=htmlspecialchars($work['status'])?></strong></div><div><small>Período previsto</small><strong><?=htmlspecialchars($work['planned_start']?:'-')?> → <?=htmlspecialchars($work['planned_end']?:'-')?></strong></div></div></section>
<section class="panel"><div class="panel-header"><div><h2>Linha do tempo</h2><p><?=count($entries)?> registro(s) consolidados.</p></div></div>
<?php if(!$entries):?><div class="empty-state"><h3>Sem movimentações</h3><p>A obra ainda não possui registros suficientes para formar o diário.</p></div><?php else:?><div class="diary-timeline">
<?php foreach($entries as $entry):?>
<article class="diary-entry kind-<?=strtolower($entry['kind'])?>"><div class="diary-marker"></div><div class="diary-entry-card"><div class="row-gap"><div><span class="diary-kind"><?=htmlspecialchars($kindLabels[$entry['kind']]??$entry['kind'])?></span><h3><?=htmlspecialchars($entry['title'])?></h3></div><time><?=htmlspecialchars($entry['at'])?></time></div><small><?=htmlspecialchars($entry['actor'])?></small><?php if($entry['meta']):?><div class="diary-meta"><?=htmlspecialchars($entry['meta'])?></div><?php endif;?><?php if($entry['description']):?><p><?=nl2br(htmlspecialchars($entry['description']))?></p><?php endif;?><?php if($entry['link']):?><a class="button secondary" href="<?=htmlspecialchars($entry['link'])?>" <?=str_contains($entry['link'],'/notification/pdf')?'target="_blank"':''?>><?=htmlspecialchars($entry['link_label']?:'Abrir')?></a><?php endif;?></div></article>
<?php endforeach;?>
</div><?php endif;?>
</section>
</main></div></body></html>
