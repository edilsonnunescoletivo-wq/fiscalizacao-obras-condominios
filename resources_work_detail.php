<?php
use App\Core\Csrf;
$statusLabels = [
    'DRAFT' => 'Rascunho','WAITING_DOCUMENTS' => 'Aguardando documentos','UNDER_REVIEW' => 'Em análise','CORRECTION_REQUIRED' => 'Correção necessária','TECHNICALLY_APPROVED' => 'Aprovada tecnicamente','AUTHORIZED' => 'Autorizada','IN_PROGRESS' => 'Em andamento','NOTIFIED' => 'Notificada','SUSPENDED' => 'Suspensa','EMBARGOED' => 'Embargada','COMPLETION_INSPECTION' => 'Vistoria de conclusão','COMPLETED' => 'Concluída','CANCELLED' => 'Cancelada',
];
$documentLabels = ['SUBMITTED'=>'Enviado','APPROVED'=>'Aprovado','CORRECTION_REQUIRED'=>'Correção necessária','REJECTED'=>'Reprovado','PENDING'=>'Pendente'];
$severityLabels = ['LOW'=>'Leve','MEDIUM'=>'Moderada','HIGH'=>'Grave','CRITICAL'=>'Crítica'];
$notificationLabels = ['IRREGULARITY'=>'Irregularidade','WARNING'=>'Advertência','ADJUSTMENT'=>'Adequação','SUSPENSION'=>'Suspensão','EMBARGO'=>'Embargo','RELEASE'=>'Liberação'];
$inspectionLabels = ['COMPLIANT'=>'Conforme','WITH_ISSUES'=>'Com apontamentos','CRITICAL'=>'Crítica'];
$steps = ['WAITING_DOCUMENTS'=>'Documentos','UNDER_REVIEW'=>'Análise','CORRECTION_REQUIRED'=>'Correção','TECHNICALLY_APPROVED'=>'Aprovação técnica','AUTHORIZED'=>'Autorização','IN_PROGRESS'=>'Em andamento'];
?>
<!doctype html>
<html lang="pt-BR">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Obra <?= htmlspecialchars($work['unit']) ?></title><link rel="stylesheet" href="/assets/css/app.css"></head>
<body class="app-body">
<div class="shell">
<aside class="sidebar"><div class="brand">Fiscaliza Obras</div><nav><a href="/">Visão geral</a><a class="active" href="/works?condo=<?= (int)$work['condominium_id'] ?>">Obras</a><a href="#fiscalizacoes">Fiscalizações</a><a href="#notificacoes">Notificações</a><a href="#historico">Histórico</a></nav></aside>
<main class="content">
<header class="topbar"><div><p class="eyebrow"><?= htmlspecialchars($work['condominium_name']) ?></p><h1>Unidade <?= htmlspecialchars($work['unit']) ?></h1><p class="muted"><?= htmlspecialchars($work['work_type'] ?? '') ?> · <?= htmlspecialchars($statusLabels[$work['status']] ?? $work['status']) ?></p></div><a class="button secondary" href="/works?condo=<?= (int)$work['condominium_id'] ?>">← Voltar</a></header>

<?php if (isset($_GET['uploaded'])): ?><div class="alert success">Documento enviado com sucesso.</div><?php endif; ?>
<?php if (isset($_GET['reviewed'])): ?><div class="alert success">Análise do documento registrada.</div><?php endif; ?>
<?php if (isset($_GET['status'])): ?><div class="alert success">Status da obra atualizado.</div><?php endif; ?>
<?php if (isset($_GET['inspection'])): ?><div class="alert success">Fiscalização registrada com sucesso.</div><?php endif; ?>
<?php if (isset($_GET['nc'])): ?><div class="alert success">Não conformidade registrada.</div><?php endif; ?>
<?php if (isset($_GET['nc_updated'])): ?><div class="alert success">Não conformidade atualizada.</div><?php endif; ?>
<?php if (isset($_GET['notification'])): ?><div class="alert success">Notificação emitida e status da obra atualizado.</div><?php endif; ?>

<section class="workflow-strip">
<?php foreach ($steps as $code=>$label): $isCurrent = $work['status']===$code; ?>
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
<?php if ((!$canReview && !$canAuthorize) || in_array($work['status'], ['IN_PROGRESS','NOTIFIED','SUSPENDED','EMBARGOED'], true)): ?><p class="muted">Use as áreas de fiscalização e notificações abaixo para o acompanhamento operacional.</p><?php endif; ?>
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

<section class="panel" id="fiscalizacoes">
<div class="panel-header"><div><h2>Fiscalizações</h2><p>Vistorias, checklist e resultado técnico.</p></div></div>
<?php if ($canInspect): ?>
<form class="form-stack operation-form" method="post" action="/work/inspection/create">
<input type="hidden" name="_token" value="<?= htmlspecialchars(Csrf::token()) ?>"><input type="hidden" name="work_id" value="<?= (int)$work['id'] ?>">
<div class="form-grid"><label>Etapa<input name="stage" placeholder="Ex.: Demolição, elétrica, acabamento"></label><label>Resultado<select name="result"><option value="COMPLIANT">Conforme</option><option value="WITH_ISSUES">Com apontamentos</option><option value="CRITICAL">Crítica</option></select></label><label class="full">Observações<textarea name="notes" rows="3"></textarea></label></div>
<div class="check-grid"><label><input type="checkbox" name="check_protection" value="1"> Proteções das áreas comuns</label><label><input type="checkbox" name="check_cleanliness" value="1"> Limpeza e organização</label><label><input type="checkbox" name="check_ppe" value="1"> EPIs adequados</label><label><input type="checkbox" name="check_project" value="1"> Execução conforme projeto</label></div>
<div class="form-actions"><button type="submit">Registrar fiscalização</button></div>
</form>
<?php endif; ?>
<div class="operation-list">
<?php if (!$inspections): ?><div class="empty-state"><p>Nenhuma fiscalização registrada.</p></div><?php endif; ?>
<?php foreach ($inspections as $inspection): ?><article class="operation-card"><div><strong>Vistoria #<?= (int)$inspection['id'] ?> · <?= htmlspecialchars($inspectionLabels[$inspection['result']] ?? $inspection['result']) ?></strong><small><?= htmlspecialchars($inspection['inspector_name']) ?> · <?= htmlspecialchars($inspection['inspected_at']) ?></small><?php if ($inspection['stage']): ?><p><b>Etapa:</b> <?= htmlspecialchars($inspection['stage']) ?></p><?php endif; ?><?php if ($inspection['notes']): ?><p><?= nl2br(htmlspecialchars($inspection['notes'])) ?></p><?php endif; ?></div></article><?php endforeach; ?>
</div>
</section>

<section class="panel">
<div class="panel-header"><div><h2>Não conformidades</h2><p>Irregularidades identificadas e seus prazos de correção.</p></div></div>
<?php if ($canInspect): ?>
<form class="form-stack operation-form" method="post" action="/work/non-conformity/create"><input type="hidden" name="_token" value="<?= htmlspecialchars(Csrf::token()) ?>"><input type="hidden" name="work_id" value="<?= (int)$work['id'] ?>"><div class="form-grid"><label>Fiscalização vinculada<select name="inspection_id"><option value="">Sem vínculo</option><?php foreach ($inspections as $inspection): ?><option value="<?= (int)$inspection['id'] ?>">#<?= (int)$inspection['id'] ?> · <?= htmlspecialchars($inspection['stage'] ?: 'Fiscalização') ?></option><?php endforeach; ?></select></label><label>Gravidade<select name="severity"><option value="LOW">Leve</option><option value="MEDIUM">Moderada</option><option value="HIGH">Grave</option><option value="CRITICAL">Crítica</option></select></label><label>Título<input name="title" required></label><label>Prazo para correção<input type="datetime-local" name="corrective_deadline"></label><label class="full">Descrição<textarea name="description" rows="3" required></textarea></label></div><div class="form-actions"><button type="submit">Registrar não conformidade</button></div></form>
<?php endif; ?>
<div class="operation-list">
<?php if (!$nonConformities): ?><div class="empty-state"><p>Nenhuma não conformidade registrada.</p></div><?php endif; ?>
<?php foreach ($nonConformities as $nc): ?><article class="operation-card"><div><div class="row-gap"><strong><?= htmlspecialchars($nc['title']) ?></strong><span class="badge severity-<?= strtolower($nc['severity']) ?>"><?= htmlspecialchars($severityLabels[$nc['severity']] ?? $nc['severity']) ?> · <?= htmlspecialchars($nc['status']) ?></span></div><small><?= htmlspecialchars($nc['creator_name']) ?> · <?= htmlspecialchars($nc['created_at']) ?></small><p><?= nl2br(htmlspecialchars($nc['description'])) ?></p><?php if ($nc['corrective_deadline']): ?><p><b>Prazo:</b> <?= htmlspecialchars($nc['corrective_deadline']) ?></p><?php endif; ?></div><?php if ($canInspect && $nc['status']==='OPEN'): ?><form method="post" action="/work/non-conformity/close" class="inline-actions"><input type="hidden" name="_token" value="<?= htmlspecialchars(Csrf::token()) ?>"><input type="hidden" name="work_id" value="<?= (int)$work['id'] ?>"><input type="hidden" name="non_conformity_id" value="<?= (int)$nc['id'] ?>"><button name="status" value="CORRECTED" class="button secondary">Marcar corrigida</button><button name="status" value="CLOSED" class="button primary">Encerrar</button></form><?php endif; ?></article><?php endforeach; ?>
</div>
</section>

<section class="panel" id="notificacoes">
<div class="panel-header"><div><h2>Notificações e restrições</h2><p>Emissão de irregularidade, advertência, adequação, suspensão, embargo e liberação.</p></div></div>
<?php if ($canNotify): ?>
<form class="form-stack operation-form" method="post" action="/work/notification/create"><input type="hidden" name="_token" value="<?= htmlspecialchars(Csrf::token()) ?>"><input type="hidden" name="work_id" value="<?= (int)$work['id'] ?>"><div class="form-grid"><label>Tipo<select name="type"><option value="IRREGULARITY">Irregularidade</option><option value="WARNING">Advertência</option><option value="ADJUSTMENT">Solicitação de adequação</option><?php if ($canRestrictWork): ?><option value="SUSPENSION">Suspensão</option><option value="EMBARGO">Embargo</option><option value="RELEASE">Liberação</option><?php endif; ?></select></label><label>Prazo<input type="datetime-local" name="deadline"></label><label class="full">Motivo<input name="reason" required></label><label class="full">Texto da notificação<textarea name="body" rows="4" required></textarea></label></div><div class="form-actions"><button type="submit">Emitir notificação</button></div></form>
<?php endif; ?>
<div class="operation-list">
<?php if (!$notifications): ?><div class="empty-state"><p>Nenhuma notificação emitida.</p></div><?php endif; ?>
<?php foreach ($notifications as $notification): ?><article class="operation-card"><div><div class="row-gap"><strong>Notificação <?= htmlspecialchars($notification['number']) ?></strong><span class="badge"><?= htmlspecialchars($notificationLabels[$notification['type']] ?? $notification['type']) ?></span></div><small><?= htmlspecialchars($notification['creator_name']) ?> · <?= htmlspecialchars($notification['issued_at'] ?? $notification['created_at']) ?></small><p><b><?= htmlspecialchars($notification['reason']) ?></b></p><p><?= nl2br(htmlspecialchars($notification['body'])) ?></p><?php if ($notification['deadline']): ?><p><b>Prazo:</b> <?= htmlspecialchars($notification['deadline']) ?></p><?php endif; ?></div></article><?php endforeach; ?>
</div>
</section>

<section class="panel" id="historico">
<div class="panel-header"><div><h2>Histórico da obra</h2><p>Linha do tempo de documentos, análises, fiscalizações, notificações e mudanças de status.</p></div></div>
<div class="timeline">
<?php if (!$events): ?><div class="empty-state"><p>Ainda não há movimentações registradas.</p></div><?php endif; ?>
<?php foreach ($events as $event): ?><div class="timeline-item"><span class="timeline-dot"></span><div><strong><?= htmlspecialchars($event['title']) ?></strong><small><?= htmlspecialchars($event['user_name'] ?? 'Sistema') ?> · <?= htmlspecialchars($event['created_at']) ?></small><?php if ($event['description']): ?><p><?= nl2br(htmlspecialchars($event['description'])) ?></p><?php endif; ?></div></div><?php endforeach; ?>
</div>
</section>
</main></div></body></html>
