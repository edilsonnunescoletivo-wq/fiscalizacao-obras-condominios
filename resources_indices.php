<?php
$sidebarCondoId = (int)$condominium['id'];
$sidebarRoles = $roles;
$sidebarActive = 'indices';
$displayPercent = static fn(?int $value): string => $value === null ? '—' : $value . '%';
$displayScore = static fn(?int $value): string => $value === null ? '—' : (string)$value;
$formatDate = date('d/m/Y');
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Índices · <?=htmlspecialchars($condominium['name'])?> · Fiscaliza Obras</title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <link rel="stylesheet" href="/assets/css/ui-v2.css">
    <link rel="stylesheet" href="/assets/css/insights-v4.css">
</head>
<body class="app-body">
<div class="shell">
    <?php require __DIR__ . '/resources_sidebar.php'; ?>
    <main class="content insights-layout">
        <header class="topbar condo-topbar">
            <div>
                <p class="eyebrow">Análises e desempenho</p>
                <h1>Índices de fiscalização</h1>
                <p class="muted"><?=htmlspecialchars($condominium['name'])?> · Atualizado com os registros do sistema em <?=htmlspecialchars($formatDate)?></p>
            </div>
            <a class="button secondary" href="/reports?condo=<?=$sidebarCondoId?>">Ver relatórios</a>
        </header>

        <nav class="project-tabs" aria-label="Áreas do condomínio">
            <a href="/works?condo=<?=$sidebarCondoId?>#obras">Obras</a>
            <a href="/indices?condo=<?=$sidebarCondoId?>" class="active" aria-current="page">Índices</a>
        </nav>

        <section class="insights-intro">
            <div>
                <span class="small-label">PAINEL DE INDICADORES</span>
                <h2>Saúde das obras, em uma só visão</h2>
                <p>Acompanhe conformidade, prazos e atenção operacional sem depender de planilhas. Dados limitados a este condomínio.</p>
            </div>
            <div class="insights-intro-total"><strong><?=(int)$totals['works']?></strong><span>obras cadastradas</span></div>
        </section>

        <section class="insights-grid" aria-label="Indicadores principais">
            <article class="insight-stat"><div class="insight-stat-title">Conclusão das obras <span class="insight-dot blue"></span></div>
                <strong><?=$displayPercent($indices['completion'])?></strong>
                <div class="insight-progress"><span style="width:<?=(int)($indices['completion']??0)?>%"></span></div>
                <p><?=(int)$totals['completed']?> concluída(s) de <?=(int)($totals['works']-$totals['cancelled'])?> não cancelada(s)</p>
            </article>
            <article class="insight-stat"><div class="insight-stat-title">Conformidades resolvidas <span class="insight-dot green"></span></div>
                <strong><?=$displayPercent($indices['conformity'])?></strong>
                <div class="insight-progress green"><span style="width:<?=(int)($indices['conformity']??0)?>%"></span></div>
                <p><?=(int)$totals['closed_nc']?> NC encerrada(s) de <?=(int)($totals['open_nc']+$totals['closed_nc'])?> registrada(s)</p>
            </article>
            <article class="insight-stat"><div class="insight-stat-title">Prazos em dia <span class="insight-dot amber"></span></div>
                <strong><?=$displayPercent($indices['on_time'])?></strong>
                <div class="insight-progress amber"><span style="width:<?=(int)($indices['on_time']??0)?>%"></span></div>
                <p><?=(int)$totals['dated_on_time']?> obra(s) dentro do prazo entre <?=(int)$totals['dated_active_works']?> com prazo definido e ainda abertas</p>
            </article>
            <article class="insight-stat"><div class="insight-stat-title">Cobertura de fiscalização <span class="insight-dot purple"></span></div>
                <strong><?=$displayPercent($indices['coverage'])?></strong>
                <div class="insight-progress purple"><span style="width:<?=(int)($indices['coverage']??0)?>%"></span></div>
                <p><?=(int)$totals['with_inspection']?> obra(s) vistoriada(s) de <?=(int)$totals['operational']?> operacional(is)</p>
            </article>
            <article class="insight-stat score"><div class="insight-stat-title">Índice médio de risco <span class="insight-dot red"></span></div>
                <strong><?=$displayScore($indices['average_risk'])?><small>/100</small></strong>
                <div class="insight-progress red"><span style="width:<?=(int)($indices['average_risk']??0)?>%"></span></div>
                <p>Média de obras não concluídas e não canceladas. Maior valor indica maior risco.</p>
            </article>
        </section>

        <section class="insights-quick-stats" aria-label="Alertas e movimentação">
            <div><span>Não conformidades abertas</span><strong><?=(int)$totals['open_nc']?></strong></div>
            <div class="alert-stat"><span>NCs com prazo vencido</span><strong><?=(int)$totals['overdue_nc']?></strong></div>
            <div class="alert-stat"><span>Obras com prazo vencido</span><strong><?=(int)$totals['overdue_works']?></strong></div>
            <div><span>Obras suspensas ou embargadas</span><strong><?=(int)$totals['restricted']?></strong></div>
            <div><span>Fiscalizações registradas</span><strong><?=(int)$totals['inspections']?></strong></div>
        </section>

        <div class="insights-columns">
            <section class="panel insights-panel">
                <div class="panel-header"><div><h2>Etapas das obras</h2><p>Distribuição por situação atual</p></div></div>
                <?php if(!$totals['works']):?>
                    <div class="empty-state"><p>Nenhuma obra cadastrada neste condomínio.</p></div>
                <?php else:?>
                    <div class="insights-bars">
                        <?php foreach($stages as $stage):?>
                            <div class="insights-bar-row">
                                <div class="insights-bar-heading"><span><?=htmlspecialchars($stage['label'])?></span><b><?=(int)$stage['count']?></b></div>
                                <div class="insights-bar-track"><span class="bar-<?=htmlspecialchars($stage['tone'])?>" style="width:<?=round(100*$stage['count']/$totals['works'],1)?>%"></span></div>
                            </div>
                        <?php endforeach;?>
                    </div>
                <?php endif;?>
            </section>

            <section class="panel insights-panel">
                <div class="panel-header"><div><h2>Faixas de risco</h2><p>Classificação das obras em aberto</p></div></div>
                <?php
                $riskLevels = [
                    'LOW'=>['label'=>'Baixo','class'=>'low'],
                    'MEDIUM'=>['label'=>'Moderado','class'=>'medium'],
                    'HIGH'=>['label'=>'Alto','class'=>'high'],
                    'CRITICAL'=>['label'=>'Crítico','class'=>'critical'],
                ];
                ?>
                <div class="risk-legend-list">
                    <?php foreach($riskLevels as $level=>$meta):?>
                        <div class="risk-legend-item">
                            <div><span class="risk-indicator <?=$meta['class']?>"></span><span><?=htmlspecialchars($meta['label'])?></span></div>
                            <strong><?=(int)$riskBreakdown[$level]?></strong>
                        </div>
                    <?php endforeach;?>
                </div>
                <p class="muted insights-note">Classificação calculada a partir de gravidade, pendências, atrasos, fiscalização e restrições da obra. Não substitui avaliação técnica.</p>
            </section>
        </div>

        <section class="panel insights-panel">
            <div class="panel-header">
                <div><h2>Obras que exigem atenção</h2><p>Prioridade por pontuação de risco, de 0 a 100</p></div>
                <a class="button secondary" href="/works?condo=<?=$sidebarCondoId?>">Ver todas as obras</a>
            </div>
            <?php if(!$priorities):?>
                <div class="empty-state"><p>Sem obras em aberto para avaliação de risco.</p></div>
            <?php else:?>
                <div class="insights-priority-list">
                    <?php foreach($priorities as $item):?>
                        <?php $riskLevel = strtolower((string)$item['risk']['level']); ?>
                        <a class="insights-priority-row" href="/work?id=<?=(int)$item['id']?>">
                            <div class="priority-unit"><span class="priority-work-icon">▤</span><div><strong>Unidade <?=htmlspecialchars($item['unit'])?></strong><small><?=(int)$item['pending']?> pendência(s) · <?=(int)$item['overdue']?> prazo(s) vencido(s)</small></div></div>
                            <div class="priority-risk"><span class="risk-badge risk-<?=$riskLevel?>"><?=htmlspecialchars($item['risk']['label'])?></span><strong><?=(int)$item['risk']['score']?>/100</strong><span aria-hidden="true">→</span></div>
                        </a>
                    <?php endforeach;?>
                </div>
            <?php endif;?>
        </section>
        <p class="indices-footnote">Critérios: sem histórico para o denominador, o indicador mostra “—”. As taxas descrevem os registros disponíveis, não uma certificação de conformidade.</p>
    </main>
</div>
</body>
</html>
