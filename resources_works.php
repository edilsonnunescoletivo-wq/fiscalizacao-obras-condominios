<?php
$statusLabels = [
    'DRAFT' => 'Rascunho','WAITING_DOCUMENTS' => 'Aguardando documentos','UNDER_REVIEW' => 'Em análise','CORRECTION_REQUIRED' => 'Correção necessária','TECHNICALLY_APPROVED' => 'Aprovada tecnicamente','AUTHORIZED' => 'Autorizada','IN_PROGRESS' => 'Em andamento','NOTIFIED' => 'Notificada','SUSPENDED' => 'Suspensa','EMBARGOED' => 'Embargada','COMPLETION_INSPECTION' => 'Vistoria de conclusão','COMPLETED' => 'Concluída','CANCELLED' => 'Cancelada',
];
$riskClass = static fn(array $risk): string => 'risk-' . strtolower((string)$risk['level']);
$sidebarCondoId=(int)$condominium['id'];
$sidebarRoles=$roles;
$sidebarActive='condo';
$baseParams=['condo'=>(int)$condominium['id']];
if($query!=='')$baseParams['q']=$query;
if($statusFilter!=='')$baseParams['status']=$statusFilter;
if($scope!=='')$baseParams['scope']=$scope;
$urlFor=static function(array $changes=[]) use($baseParams): string {
    $params=$baseParams;
    foreach($changes as $key=>$value){if($value===null||$value==='')unset($params[$key]);else $params[$key]=$value;}
    return '/works?'.http_build_query($params);
};
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Obras - <?=htmlspecialchars($condominium['name'])?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <link rel="stylesheet" href="/assets/css/ui-v2.css">
</head>
<body class="app-body">
<div class="shell">
    <?php require __DIR__ . '/resources_sidebar.php'; ?>
    <main class="content">
        <header class="topbar condo-topbar">
            <div>
                <p class="eyebrow">Painel operacional</p>
                <h1><?=htmlspecialchars($condominium['name'])?></h1>
                <p class="muted">Priorize riscos, acompanhe pendências e fiscalize todas as obras em uma única visão.</p>
            </div>
            <?php if($canCreate):?><a class="button primary" href="/works/create?condo=<?=(int)$condominium['id']?>">+ Nova obra</a><?php endif;?>
        </header>

        <?php if(isset($_GET['created'])):?><div class="alert success">Obra cadastrada com sucesso.</div><?php endif;?>
        <?php if(isset($_GET['photo'])):?><div class="alert success">Foto da obra atualizada com sucesso.</div><?php endif;?>
        <?php if(isset($_GET['condo_photo'])):?><div class="alert success">Foto do condomínio atualizada com sucesso.</div><?php endif;?>

        <section class="condo-summary-card">
            <div class="condo-summary-cover">
                <?php if(!empty($condominium['photo_path'])):?>
                    <img src="/condominium/photo?id=<?=(int)$condominium['id']?>" alt="Foto de <?=htmlspecialchars($condominium['name'])?>">
                <?php else:?>
                    <div class="cover-placeholder large"><span><?=htmlspecialchars(mb_strtoupper(mb_substr($condominium['name'],0,1)))?></span><small>Sem foto cadastrada</small></div>
                <?php endif;?>
            </div>
            <div class="condo-summary-content">
                <span class="small-label">Resumo operacional</span>
                <h2><?=htmlspecialchars($condominium['name'])?></h2>
                <div class="metrics condo-summary-metrics">
                    <span><b><?=(int)$summary['total']?></b> Obras</span>
                    <span><b><?=(int)$summary['operational']?></b> Operacionais</span>
                    <span><b><?=(int)$summary['open_nc']?></b> NCs abertas</span>
                    <span><b><?=(int)$summary['overdue_nc']?></b> NCs vencidas</span>
                </div>
                <div class="inline-actions">
                    <a class="button secondary" href="/condominium/pending?condo=<?=(int)$condominium['id']?>">Central de pendências</a>
                    <a class="button secondary" href="/reports?condo=<?=(int)$condominium['id']?>">Relatórios</a>
                    <?php if($canManage):?><a class="button secondary" href="/settings?condo=<?=(int)$condominium['id']?>">Configurar fiscalização</a><?php else:?><a class="button secondary" href="/fiscal-panel?condo=<?=(int)$condominium['id']?>">Painel do Fiscal</a><?php endif;?>
                </div>
                <?php if($canManage):?>
                    <form class="photo-inline-form" method="post" action="/condominium/photo/upload" enctype="multipart/form-data" style="margin-top:14px">
                        <input type="hidden" name="_token" value="<?=htmlspecialchars(App\Core\Csrf::token())?>">
                        <input type="hidden" name="condominium_id" value="<?=(int)$condominium['id']?>">
                        <input type="hidden" name="return_to" value="works">
                        <label class="button secondary file-button"><?=!empty($condominium['photo_path'])?'Alterar foto':'Escolher foto'?><input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required></label>
                        <button class="button secondary" type="submit">Salvar foto</button>
                    </form>
                <?php endif;?>
            </div>
        </section>

        <section class="operational-kpis">
            <a href="<?=$urlFor(['scope'=>null,'view'=>$viewMode])?>" class="operational-kpi <?=$scope===''?'selected':''?>"><small>Total</small><strong><?=(int)$summary['total']?></strong><span>Todas as obras</span></a>
            <a href="<?=$urlFor(['scope'=>'operational','view'=>$viewMode])?>" class="operational-kpi <?=$scope==='operational'?'selected':''?>"><small>Em campo</small><strong><?=(int)$summary['operational']?></strong><span>Em andamento / notificadas</span></a>
            <a href="<?=$urlFor(['scope'=>'pending','view'=>$viewMode])?>" class="operational-kpi <?=$scope==='pending'?'selected':''?>"><small>Pendências</small><strong><?=(int)$summary['open_nc']?></strong><span>Não conformidades abertas</span></a>
            <a href="<?=$urlFor(['scope'=>'overdue','view'=>$viewMode])?>" class="operational-kpi danger <?=$scope==='overdue'?'selected':''?>"><small>Atrasos</small><strong><?=(int)$summary['overdue_works']+(int)$summary['overdue_nc']?></strong><span>Prazo de obra ou NC vencido</span></a>
            <a href="<?=$urlFor(['scope'=>'restricted','view'=>$viewMode])?>" class="operational-kpi warning <?=$scope==='restricted'?'selected':''?>"><small>Restritas</small><strong><?=(int)$summary['suspended']+(int)$summary['embargoed']?></strong><span>Suspensas / embargadas</span></a>
            <a href="<?=$urlFor(['scope'=>'completed','view'=>$viewMode])?>" class="operational-kpi <?=$scope==='completed'?'selected':''?>"><small>Concluídas</small><strong><?=(int)$summary['completed']?></strong><span>Obras encerradas</span></a>
        </section>

        <section class="panel" id="obras">
            <div class="panel-header works-header">
                <div><h2>Obras</h2><p>Por padrão, as obras com maior risco aparecem primeiro.</p></div>
                <div class="view-switch" role="group" aria-label="Visualização">
                    <a class="<?= $viewMode==='cards'?'active':'' ?>" href="<?=$urlFor(['view'=>'cards'])?>">▦ Cards</a>
                    <a class="<?= $viewMode==='kanban'?'active':'' ?>" href="<?=$urlFor(['view'=>'kanban'])?>">☷ Kanban</a>
                </div>
            </div>
            <form class="works-filters" method="get" action="/works">
                <input type="hidden" name="condo" value="<?=(int)$condominium['id']?>">
                <input type="hidden" name="view" value="<?=htmlspecialchars($viewMode)?>">
                <?php if($scope!==''):?><input type="hidden" name="scope" value="<?=htmlspecialchars($scope)?>"><?php endif;?>
                <input type="search" name="q" value="<?=htmlspecialchars($query)?>" placeholder="Buscar unidade, proprietário, empresa...">
                <select name="status">
                    <option value="">Todos os status</option>
                    <?php foreach($statusLabels as $value=>$label):?>
                        <option value="<?=htmlspecialchars($value)?>" <?=$statusFilter===$value?'selected':''?>><?=htmlspecialchars($label)?></option>
                    <?php endforeach;?>
                </select>
                <button type="submit" class="button secondary">Filtrar</button>
                <?php if($query!==''||$statusFilter!==''||$scope!==''):?><a class="button secondary" href="/works?condo=<?=(int)$condominium['id']?>&view=<?=htmlspecialchars($viewMode)?>">Limpar</a><?php endif;?>
            </form>

            <?php if(!$works):?>
                <div class="empty-state">
                    <h3>Nenhuma obra encontrada</h3>
                    <p><?=$canCreate?'Cadastre uma obra ou ajuste os filtros para continuar.':'Não há obra vinculada ao seu usuário neste condomínio.'?></p>
                    <?php if($canCreate):?><a class="button primary" href="/works/create?condo=<?=(int)$condominium['id']?>">Cadastrar obra</a><?php endif;?>
                </div>
            <?php elseif($viewMode==='kanban'):?>
                <div class="kanban-board">
                    <?php foreach($kanban as $column):?>
                        <section class="kanban-column">
                            <div class="kanban-column-head"><strong><?=htmlspecialchars($column['label'])?></strong><span><?=count($column['works'])?></span></div>
                            <div class="kanban-stack">
                            <?php if(!$column['works']):?><div class="kanban-empty">Nenhuma obra</div><?php endif;?>
                            <?php foreach($column['works'] as $work):?>
                                <article class="kanban-card <?=$riskClass($work['risk'])?>">
                                    <div class="row-gap"><div><small>Unidade</small><h3><?=htmlspecialchars($work['unit'])?></h3></div><span class="risk-badge <?=$riskClass($work['risk'])?>"><?=$work['risk']['score']?> · <?=htmlspecialchars($work['risk']['label'])?></span></div>
                                    <p><?=htmlspecialchars($work['owner_name'])?></p>
                                    <span class="kanban-status"><?=htmlspecialchars($statusLabels[$work['status']]??$work['status'])?></span>
                                    <div class="kanban-metrics"><span><?=(int)$work['pending_count']?> pend.</span><span><?=(int)$work['inspections_count']?> vist.</span></div>
                                    <?php if((int)$work['overdue_nc']>0||!empty($work['risk']['late'])):?><div class="risk-reason">⚠ Prazo vencido</div><?php endif;?>
                                    <small class="last-activity">Última atividade: <?=htmlspecialchars($work['last_activity_at'])?></small>
                                    <a class="button secondary" href="/work?id=<?=(int)$work['id']?>">Abrir obra</a>
                                </article>
                            <?php endforeach;?>
                            </div>
                        </section>
                    <?php endforeach;?>
                </div>
            <?php else:?>
                <div class="work-card-grid">
                    <?php foreach($works as $work):?>
                        <article class="work-card <?=$riskClass($work['risk'])?>">
                            <div class="cover-frame work-cover">
                                <?php if(!empty($work['cover_photo_path'])):?>
                                    <img src="/work/photo?id=<?=(int)$work['id']?>" alt="Foto da obra <?=htmlspecialchars($work['unit'])?>">
                                <?php else:?>
                                    <div class="cover-placeholder"><span>🏗</span><small>Sem foto da obra</small></div>
                                <?php endif;?>
                                <span class="work-status-badge"><?=htmlspecialchars($statusLabels[$work['status']]??$work['status'])?></span>
                                <span class="risk-badge card-risk <?=$riskClass($work['risk'])?>">Risco <?=$work['risk']['score']?> · <?=htmlspecialchars($work['risk']['label'])?></span>
                            </div>
                            <div class="work-card-body">
                                <div class="card-title-row">
                                    <div><span class="small-label">Unidade</span><h3><?=htmlspecialchars($work['unit'])?></h3></div>
                                    <span class="total-pill"><?=htmlspecialchars($work['work_type']?:'Obra')?></span>
                                </div>
                                <?php if(!empty($work['risk']['reasons'])):?><div class="risk-reasons"><?php foreach(array_slice($work['risk']['reasons'],0,2) as $reason):?><span><?=htmlspecialchars($reason)?></span><?php endforeach;?></div><?php endif;?>
                                <div class="work-summary-list">
                                    <div><small>Proprietário</small><strong><?=htmlspecialchars($work['owner_name'])?></strong></div>
                                    <div><small>Empresa</small><strong><?=htmlspecialchars($work['company_name']?:'-')?></strong></div>
                                    <div><small>Responsável técnico</small><strong><?=htmlspecialchars($work['technical_name']?:'-')?></strong></div>
                                    <div><small>Período</small><strong><?=htmlspecialchars($work['planned_start']?:'-')?> → <?=htmlspecialchars($work['planned_end']?:'-')?></strong></div>
                                </div>
                                <div class="work-mini-metrics">
                                    <span class="<?=((int)$work['pending_count']>0)?'metric-alert':''?>"><b><?=(int)$work['pending_count']?></b> Pendências</span>
                                    <span><b><?=(int)$work['inspections_count']?></b> Fiscalizações</span>
                                    <span><b><?=(int)$work['notifications_count']?></b> Notificações</span>
                                </div>
                                <div class="last-activity-row"><small>Última atividade</small><strong><?=htmlspecialchars($work['last_activity_at'])?></strong></div>
                                <div class="card-actions-row">
                                    <a class="button primary" href="/work?id=<?=(int)$work['id']?>">Abrir obra</a>
                                    <a class="button secondary" href="/work/diary?id=<?=(int)$work['id']?>">Diário</a>
                                    <?php if($canCreate&&in_array($work['status'],['IN_PROGRESS','NOTIFIED','SUSPENDED','EMBARGOED'],true)):?><a class="button secondary" href="/inspection/new?work=<?=(int)$work['id']?>">Nova vistoria</a><?php endif;?>
                                    <?php if((int)$work['pending_count']>0):?><a class="button secondary" href="/condominium/pending?condo=<?=(int)$condominium['id']?>&work=<?=(int)$work['id']?>">Pendências</a><?php endif;?>
                                </div>
                            </div>
                        </article>
                    <?php endforeach;?>
                </div>
            <?php endif;?>
        </section>
    </main>
</div>
</body>
</html>
