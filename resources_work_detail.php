<?php
use App\Core\Csrf;
$statusLabels = [
    'DRAFT' => 'Rascunho','WAITING_DOCUMENTS' => 'Aguardando documentos','UNDER_REVIEW' => 'Em análise','CORRECTION_REQUIRED' => 'Correção necessária','TECHNICALLY_APPROVED' => 'Aprovada tecnicamente','AUTHORIZED' => 'Autorizada','IN_PROGRESS' => 'Em andamento','NOTIFIED' => 'Notificada','SUSPENDED' => 'Suspensa','EMBARGOED' => 'Embargada','COMPLETION_INSPECTION' => 'Vistoria de conclusão','COMPLETED' => 'Concluída','CANCELLED' => 'Cancelada',
];
$documentLabels = ['SUBMITTED'=>'Enviado','APPROVED'=>'Aprovado','CORRECTION_REQUIRED'=>'Correção necessária','REJECTED'=>'Reprovado','PENDING'=>'Pendente'];
$steps = ['WAITING_DOCUMENTS'=>'Documentos','UNDER_REVIEW'=>'Análise','CORRECTION_REQUIRED'=>'Correção','TECHNICALLY_APPROVED'=>'Aprovação técnica','AUTHORIZED'=>'Autorização','IN_PROGRESS'=>'Em andamento'];
?>
<!doctype html>
<html lang="pt-BR">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Obra <?= htmlspecialchars($work['unit']) ?></title><link rel="stylesheet" href="/assets/css/app.css"></head>
<body class="app-body">
<div class="shell">
<aside class="sidebar"><div class="brand">Fiscaliza Obras</div><nav><a href="/">Visão geral</a><a class="active" href="/works?condo=<?= (int)$work['condominium_id'] ?>">Obras</a><a href="#">Fiscalizações</a><a href="#">Notificações</a><a href="#">Relatórios</a></nav></aside>
<main class="content">
<header class="topbar"><div><p class="eyebrow"><?= htmlspecialchars($work['condominium_name']) ?></p><h1>Unidade <?= htmlspecialchars($work['unit']) ?></h1><p class="muted"><?= htmlspecialchars($work['work_type'] ?? '') ?> · <?= htmlspecialchars($statusLabels[$work['status']] ?? $work['status']) ?></p></div><a class="button secondary" href="/works?condo=<?= (int)$work['condominium_id'] ?>">← Voltar</a></header>

<?php if (isset($_GET['uploaded'])): ?><div class="alert success">Documento enviado com sucesso.</div><?php endif; ?>
<?php if (isset($_GET['reviewed'])): ?><div class="alert success">Análise do documento registrada.</div><?php endif; ?>
<?php if (isset($_GET['status'])): ?><div class="alert success">Status da obra atualizado.</div><?php endif; ?>

<section class="workflow-strip">
<?php $activeReached = true; foreach ($steps as $code=>$label): $isCurrent = $work['status']===$code; ?>
<div class="workflow-step <?= $isCurrent ? 'current' : '' ?>"><span></span><strong><?= htmlspecialchars($label) ?></strong></div>
<?php endforeach; ?>
</section>

<div class="detail-grid">
<section class="panel">
<div class="panel-header"><div><h2>Dados da obra</h2><p>Informações principais cadastradas.</p></div><span class="badge"><?= htmlspecialchars($statusLabels[$work['status']] ?? $work['status']) ?></span></div>
<div class="info-grid">
<div><small>Proprietário</small><strong><?= htmlspecialchars($work['owner_name']) ?></strong><span><?= htmlspecialchars($work['owner_phone'] ?? '-') ?></span></div>
<div><small>Empresa</small><strong><?= htmlspecialchars($work['company_name'] ?? '-') ?></strong><span><?= htmlspecialchars($work['company_phone'] ?? '-') ?></span></div>
<div><small>Responsável técnico</small><strong><?= htmlspecialchars($work['technical_name'] ?? '-') ?></strong><span><?= htmlspecialchars($work['technical_registry'] ?? '-') ?></span></div>
<div><small>Período previsto</small><strong><?= htmlspecialchars($work['planned_start'] ?? '-') ?> → <?= htmlspecialchars($work['planned_end'] ?? '-') ?></strong></div>
</div>
<?php if (!empty($work['description'])): ?><div class="description-box"><?= nl2br(htmlspecialchars($work['description'])) ?></div><?php endif; ?>
</section>

<section class="panel actions-panel">
<h2>Ações da etapa</h2>
<?php if ($canReview && in_array($work['status'], ['WAITING_DOCUMENTS','UNDER_REVIEW','CORRECTION_REQUIRED'], true)): ?>
<form method="post" action="/work/transition"><input type="hidden" name="_token" value="<?= htmlspecialchars(Csrf::token()) ?>"><input type="hidden" name="work_id" value="<?= (int)$work['id'] ?>"><input type="hidden" name="target_status" value="TECHNICALLY_APPROVED"><button class="button primary" type="submit">Aprovar tecnicamente</button></form>
<?php endif; ?>
<?php if ($canAuthorize && $work['status']==='TECHNICALLY_APPROVED'): ?>
<form method="post" action="/work/transition"><input type="hidden" name="_token" value="<?= htmlspecialchars(Csrf::token()) ?>"><input type="hidden" name="work_id" value="<?= (int)$work['id'] ?>"><input type="hidden" name="target_status" value="AUTHORIZED"><button class="button primary" type="submit">Autorizar início da obra</button></form>
<?php endif; ?>
<?php if ($canAuthorize && $work['status']==='AUTHORIZED'): ?>
<form method="post" action="/work/transition"><input type="hidden" name="_token" value="<?= htmlspecialchars(Csrf::token()) ?>"><input type="hidden" name="work_id" value="<?= (int)$work['id'] ?>"><input type="hidden" name="target_status" value="IN_PROGRESS"><button class="button primary" type="submit">Registrar início da obra</button></form>
<?php endif; ?>
<?php if ((!$canReview && !$canAuthorize) || $work['status']==='IN_PROGRESS'): ?><p class="muted">Nenhuma ação pendente para o seu perfil nesta etapa.</p><?php endif; ?>
</section>
</div>

<section class="panel">
<div class="panel-header"><div><h2>Documentos da obra</h2><p>Envio, análise e controle de versões.</p></div></div>
<div class="document-list">
<?php foreach ($documents as $doc): ?>
<article class="document-row">
<div class="document-main"><div><strong><?= htmlspecialchars($doc['name']) ?></strong><?php if ($doc['required_default']): ?><span class="required-tag">Obrigatório</span><?php endif; ?></div><p><?= $doc['document_id'] ? htmlspecialchars($doc['original_name']) . ' · v' . (int)$doc['version'] : 'Nenhum arquivo enviado' ?></p><?php if (!empty($doc['review_notes'])): ?><div class="review-note"><?= nl2br(htmlspecialchars($doc['review_notes'])) ?></div><?php endif; ?></div>
<div class="document-status"><span class="badge"><?= htmlspecialchars($documentLabels[$doc['status'] ?? 'PENDING'] ?? ($doc['status'] ?? 'Pendente')) ?></span></div>
<?php if ($canUpload): ?>
<form class="upload-form" method="post" action="/work/document/upload" enctype="multipart/form-data"><input type="hidden" name="_token" value="<?= htmlspecialchars(Csrf::token()) ?>"><input type="hidden" name="work_id" value="<?= (int)$work['id'] ?>"><input type="hidden" name="document_type_id" value="<?= (int)$doc['id'] ?>"><input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" required><button type="submit" class="button secondary"><?= $doc['document_id'] ? 'Nova versão' : 'Enviar' ?></button></form>
<?php endif; ?>
<?php if ($canReview && $doc['document_id'] && $doc['status']==='SUBMITTED'): ?>
<form class="review-form" method="post" action="/work/document/review"><input type="hidden" name="_token" value="<?= htmlspecialchars(Csrf::token()) ?>"><input type="hidden" name="work_id" value="<?= (int)$work['id'] ?>"><input type="hidden" name="document_id" value="<?= (int)$doc['document_id'] ?>"><textarea name="review_notes" placeholder="Observação da análise"></textarea><div class="review-actions"><button name="decision" value="APPROVED" class="button primary" type="submit">Aprovar</button><button name="decision" value="CORRECTION_REQUIRED" class="button warning" type="submit">Pedir correção</button><button name="decision" value="REJECTED" class="button danger" type="submit">Reprovar</button></div></form>
<?php endif; ?>
</article>
<?php endforeach; ?>
</div>
</section>

<section class="panel">
<div class="panel-header"><div><h2>Histórico da obra</h2><p>Linha do tempo de documentos, análises e mudanças de status.</p></div></div>
<div class="timeline">
<?php if (!$events): ?><div class="empty-state"><p>Ainda não há movimentações registradas.</p></div><?php endif; ?>
<?php foreach ($events as $event): ?><div class="timeline-item"><span class="timeline-dot"></span><div><strong><?= htmlspecialchars($event['title']) ?></strong><small><?= htmlspecialchars($event['user_name'] ?? 'Sistema') ?> · <?= htmlspecialchars($event['created_at']) ?></small><?php if ($event['description']): ?><p><?= nl2br(htmlspecialchars($event['description'])) ?></p><?php endif; ?></div></div><?php endforeach; ?>
</div>
</section>
</main></div></body></html>
