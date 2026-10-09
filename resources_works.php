<?php
$statusLabels = [
    'DRAFT' => 'Rascunho','WAITING_DOCUMENTS' => 'Aguardando documentos','UNDER_REVIEW' => 'Em análise','CORRECTION_REQUIRED' => 'Correção necessária','TECHNICALLY_APPROVED' => 'Aprovada tecnicamente','AUTHORIZED' => 'Autorizada','IN_PROGRESS' => 'Em andamento','NOTIFIED' => 'Notificada','SUSPENDED' => 'Suspensa','EMBARGOED' => 'Embargada','COMPLETION_INSPECTION' => 'Vistoria de conclusão','COMPLETED' => 'Concluída','CANCELLED' => 'Cancelada',
];
$sidebarCondoId=(int)$condominium['id'];
$sidebarRoles=$roles;
$sidebarActive='condo';
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
                <p class="eyebrow">Painel do condomínio</p>
                <h1><?=htmlspecialchars($condominium['name'])?></h1>
                <p class="muted">Acompanhe todas as obras, fiscalizações e pendências em uma única visão.</p>
            </div>
            <?php if($canCreate):?><a class="button primary" href="/works/create?condo=<?=(int)$condominium['id']?>">+ Nova obra</a><?php endif;?>
        </header>

        <?php if(isset($_GET['created'])):?><div class="alert success">Obra cadastrada com sucesso.</div><?php endif;?>
        <?php if(isset($_GET['photo'])):?><div class="alert success">Foto da obra atualizada com sucesso.</div><?php endif;?>

        <section class="condo-summary-card">
            <div class="condo-summary-cover">
                <?php if(!empty($condominium['photo_path'])):?>
                    <img src="/condominium/photo?id=<?=(int)$condominium['id']?>" alt="Foto de <?=htmlspecialchars($condominium['name'])?>">
                <?php else:?>
                    <div class="cover-placeholder large"><span><?=htmlspecialchars(mb_strtoupper(mb_substr($condominium['name'],0,1)))?></span><small>Sem foto cadastrada</small></div>
                <?php endif;?>
            </div>
            <div class="condo-summary-content">
                <span class="small-label">Resumo do condomínio</span>
                <h2><?=htmlspecialchars($condominium['name'])?></h2>
                <div class="metrics condo-summary-metrics">
                    <span><b><?=(int)$summary['total']?></b> Obras</span>
                    <span><b><?=(int)$summary['in_progress']?></b> Em andamento</span>
                    <span><b><?=(int)$summary['completed']?></b> Concluídas</span>
                    <span><b><?=(int)$summary['suspended']+(int)$summary['embargoed']?></b> Suspensas/Embargadas</span>
                </div>
                <div class="inline-actions">
                    <a class="button secondary" href="/reports?condo=<?=(int)$condominium['id']?>">Relatórios</a>
                    <?php if($canCreate):?><a class="button secondary" href="/settings?condo=<?=(int)$condominium['id']?>">Configurar fiscalização</a><?php endif;?>
                </div>
            </div>
        </section>

        <section class="panel" id="obras">
            <div class="panel-header works-header">
                <div><h2>Obras</h2><p>Visualize situação, responsáveis, pendências e movimentações de cada unidade.</p></div>
                <form class="works-filters" method="get" action="/works">
                    <input type="hidden" name="condo" value="<?=(int)$condominium['id']?>">
                    <input type="search" name="q" value="<?=htmlspecialchars($query)?>" placeholder="Buscar unidade, proprietário...">
                    <select name="status">
                        <option value="">Todos os status</option>
                        <?php foreach($statusLabels as $value=>$label):?>
                            <option value="<?=htmlspecialchars($value)?>" <?=$statusFilter===$value?'selected':''?>><?=htmlspecialchars($label)?></option>
                        <?php endforeach;?>
                    </select>
                    <button type="submit" class="button secondary">Filtrar</button>
                    <?php if($query!==''||$statusFilter!==''):?><a class="button secondary" href="/works?condo=<?=(int)$condominium['id']?>">Limpar</a><?php endif;?>
                </form>
            </div>

            <?php if(!$works):?>
                <div class="empty-state">
                    <h3>Nenhuma obra encontrada</h3>
                    <p><?=$canCreate?'Cadastre uma obra ou ajuste os filtros para continuar.':'Não há obra vinculada ao seu usuário neste condomínio.'?></p>
                    <?php if($canCreate):?><a class="button primary" href="/works/create?condo=<?=(int)$condominium['id']?>">Cadastrar obra</a><?php endif;?>
                </div>
            <?php else:?>
                <div class="work-card-grid">
                    <?php foreach($works as $work):?>
                        <article class="work-card">
                            <div class="cover-frame work-cover">
                                <?php if(!empty($work['cover_photo_path'])):?>
                                    <img src="/work/photo?id=<?=(int)$work['id']?>" alt="Foto da obra <?=htmlspecialchars($work['unit'])?>">
                                <?php else:?>
                                    <div class="cover-placeholder"><span>🏗</span><small>Adicionar foto da obra</small></div>
                                <?php endif;?>
                                <span class="work-status-badge"><?=htmlspecialchars($statusLabels[$work['status']]??$work['status'])?></span>
                            </div>
                            <div class="work-card-body">
                                <div class="card-title-row">
                                    <div><span class="small-label">Unidade</span><h3><?=htmlspecialchars($work['unit'])?></h3></div>
                                    <span class="total-pill"><?=htmlspecialchars($work['work_type']?:'Obra')?></span>
                                </div>
                                <div class="work-summary-list">
                                    <div><small>Proprietário</small><strong><?=htmlspecialchars($work['owner_name'])?></strong></div>
                                    <div><small>Empresa</small><strong><?=htmlspecialchars($work['company_name']?:'-')?></strong></div>
                                    <div><small>Responsável técnico</small><strong><?=htmlspecialchars($work['technical_name']?:'-')?></strong></div>
                                    <div><small>Período</small><strong><?=htmlspecialchars($work['planned_start']?:'-')?> → <?=htmlspecialchars($work['planned_end']?:'-')?></strong></div>
                                </div>
                                <div class="work-mini-metrics">
                                    <span><b><?=(int)$work['pending_count']?></b> Pendências</span>
                                    <span><b><?=(int)$work['inspections_count']?></b> Fiscalizações</span>
                                    <span><b><?=(int)$work['notifications_count']?></b> Notificações</span>
                                </div>
                                <div class="card-actions-row">
                                    <a class="button primary" href="/work?id=<?=(int)$work['id']?>">Abrir obra</a>
                                    <?php if($canCreate):?>
                                    <form class="cover-upload" method="post" action="/work/photo/upload" enctype="multipart/form-data">
                                        <input type="hidden" name="_token" value="<?=htmlspecialchars(App\Core\Csrf::token())?>">
                                        <input type="hidden" name="work_id" value="<?=(int)$work['id']?>">
                                        <input type="hidden" name="return_to" value="works">
                                        <label class="button secondary file-button">Foto<input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required onchange="this.form.submit()"></label>
                                    </form>
                                    <?php endif;?>
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
