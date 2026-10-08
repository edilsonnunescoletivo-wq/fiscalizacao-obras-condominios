<?php use App\Core\Csrf; ?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Painel | Fiscalização de Obras</title>
    <link rel="stylesheet" href="/assets/css/app.css">
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
        <p>Selecione um condomínio para acompanhar obras, fiscalizações, documentos e pendências.</p>
    </section>
    <section class="cards">
        <?php foreach ($condominiums as $c): ?>
            <article class="card condo-card">
                <div class="card-title-row">
                    <div>
                        <span class="small-label">Empreendimento</span>
                        <h2><?= htmlspecialchars($c['name']) ?></h2>
                    </div>
                    <span class="total-pill"><?= (int)$c['total_works'] ?> obras</span>
                </div>
                <div class="metrics">
                    <span><b><?= (int)$c['in_progress'] ?></b> Em andamento</span>
                    <span><b><?= (int)$c['authorized'] ?></b> Autorizadas</span>
                    <span><b><?= (int)$c['notified'] ?></b> Notificadas</span>
                    <span><b><?= (int)$c['embargoed'] ?></b> Embargadas</span>
                </div>
                <a class="button primary" href="/works?condo=<?= (int)$c['id'] ?>">Acessar condomínio</a>
            </article>
        <?php endforeach; ?>
        <?php if (!$condominiums): ?>
            <div class="empty">Nenhum condomínio vinculado ao seu usuário.</div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
