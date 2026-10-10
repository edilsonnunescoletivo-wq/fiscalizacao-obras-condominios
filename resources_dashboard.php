<?php use App\Core\Csrf; ?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Painel | Fiscalização de Obras</title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <link rel="stylesheet" href="/assets/css/ui-v2.css">
</head>
<body>
<header class="topbar public-topbar">
    <div><strong>Fiscalização de Obras</strong><small>Painel multi-condomínio</small></div>
    <div class="inline-actions"><span><?= htmlspecialchars($user['name']) ?></span><form method="post" action="/logout"><input type="hidden" name="_token" value="<?=htmlspecialchars(Csrf::token())?>"><button class="button secondary" type="submit">Sair</button></form></div>
</header>
<main class="container">
    <section class="hero">
        <p class="eyebrow">Visão geral</p>
        <h1>Meus empreendimentos</h1>
        <p>Os condomínios com maior necessidade de atenção operacional aparecem primeiro.</p>
    </section>

    <?php if(isset($_GET['photo'])):?><div class="alert success">Foto do condomínio atualizada com sucesso.</div><?php endif;?>

    <section class="cards condo-cards">
        <?php foreach ($condominiums as $c): ?>
            <article class="card condo-card visual-card attention-<?=htmlspecialchars($c['attention_level'])?>">
                <div class="cover-frame condo-cover">
                    <?php if (!empty($c['photo_path'])): ?>
                        <img src="/condominium/photo?id=<?=(int)$c['id']?>" alt="Foto de <?=htmlspecialchars($c['name'])?>">
                    <?php else: ?>
                        <div class="cover-placeholder"><span><?=htmlspecialchars(mb_strtoupper(mb_substr($c['name'], 0, 1)))?></span><small>Adicionar foto do condomínio</small></div>
                    <?php endif; ?>
                    <?php if((int)$c['critical_nc']>0 || (int)$c['overdue_nc']>0 || (int)$c['restricted']>0):?><span class="attention-badge">Atenção operacional</span><?php endif;?>
                </div>
                <div class="visual-card-body">
                    <div class="card-title-row">
                        <div>
                            <span class="small-label">Empreendimento</span>
                            <h2><?= htmlspecialchars($c['name']) ?></h2>
                        </div>
                        <span class="total-pill"><?= (int)$c['total_works'] ?> obras</span>
                    </div>
                    <div class="metrics compact-metrics condo-operational-metrics">
                        <span><b><?= (int)$c['operational'] ?></b> Operacionais</span>
                        <span class="<?=((int)$c['open_nc']>0)?'metric-alert':''?>"><b><?= (int)$c['open_nc'] ?></b> NCs abertas</span>
                        <span class="<?=((int)$c['overdue_nc']>0)?'metric-alert':''?>"><b><?= (int)$c['overdue_nc'] ?></b> NCs vencidas</span>
                        <span class="<?=((int)$c['restricted']>0)?'metric-alert':''?>"><b><?= (int)$c['restricted'] ?></b> Restritas</span>
                    </div>
                    <?php if((int)$c['critical_nc']>0 || (int)$c['overdue_works']>0):?><div class="condo-attention-line"><?php if((int)$c['critical_nc']>0):?><span><?=(int)$c['critical_nc']?> NC crítica(s)</span><?php endif;?><?php if((int)$c['overdue_works']>0):?><span><?=(int)$c['overdue_works']?> obra(s) atrasada(s)</span><?php endif;?></div><?php endif;?>
                    <div class="card-actions-row">
                        <a class="button primary" href="/works?condo=<?= (int)$c['id'] ?>">Acessar condomínio</a>
                        <?php if((int)$c['open_nc']>0):?><a class="button secondary" href="/condominium/pending?condo=<?=(int)$c['id']?>">Pendências</a><?php endif;?>
                    </div>
                    <?php if (!empty($c['can_manage'])): ?>
                        <form class="photo-inline-form" method="post" action="/condominium/photo/upload" enctype="multipart/form-data" style="margin-top:12px">
                            <input type="hidden" name="_token" value="<?=htmlspecialchars(Csrf::token())?>">
                            <input type="hidden" name="condominium_id" value="<?=(int)$c['id']?>">
                            <input type="hidden" name="return_to" value="dashboard">
                            <label class="button secondary file-button">Escolher foto<input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required></label>
                            <button class="button secondary" type="submit">Salvar foto</button>
                        </form>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
        <?php if (!$condominiums): ?>
            <div class="empty">Nenhum condomínio vinculado ao seu usuário.</div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
