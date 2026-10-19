<?php
use App\Core\Csrf;
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Checklist padrão · <?=htmlspecialchars($condo['name'])?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <link rel="stylesheet" href="/assets/css/ui-v2.css">
</head>
<body class="app-body">
<div class="shell">
    <?php require __DIR__ . '/resources_sidebar.php'; ?>
    <main class="content">
        <header class="topbar">
            <div>
                <p class="eyebrow">Fiscalização</p>
                <h1>Checklist padrão</h1>
                <p class="muted"><?=htmlspecialchars($condo['name'])?> · base inicial para vistorias, totalmente editável depois.</p>
            </div>
            <a class="button secondary" href="/settings?condo=<?=(int)$condo['id']?>#checklist">Editar checklist</a>
        </header>

        <?php if(isset($_GET['applied'])):?>
            <div class="alert success"><?=(int)($_GET['added']??0)?> item(ns) padrão adicionado(s) com sucesso.</div>
        <?php endif;?>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <h2>Base recomendada</h2>
                    <p>O modelo acrescenta somente itens ausentes e preserva tudo que já foi personalizado no condomínio.</p>
                </div>
            </div>
            <div class="metrics condo-summary-metrics">
                <span><b><?=$activeCount?></b> Itens ativos hoje</span>
                <span><b><?=$presetCount?></b> Itens no modelo padrão</span>
                <span><b><?=$missing?></b> Padrões ainda ausentes</span>
            </div>
            <div class="description-box">
                O checklist padrão cobre documentação, segurança, áreas comuns, execução, elétrica, hidráulica, estrutura, operação, resíduos e encerramento. Ele é uma ferramenta operacional de vistoria e não substitui análise técnica profissional, ART/RRT, projeto ou normas aplicáveis.
            </div>
            <form method="post" action="/settings/checklist/preset" class="form-stack">
                <input type="hidden" name="_token" value="<?=htmlspecialchars(Csrf::token())?>">
                <input type="hidden" name="condo_id" value="<?=(int)$condo['id']?>">
                <div class="form-actions">
                    <button class="button primary" type="submit" <?=$missing===0?'disabled':''?>><?=$missing===0?'Checklist padrão já aplicado':'Adicionar itens padrão ausentes'?></button>
                </div>
            </form>
        </section>

        <section class="panel">
            <h2>Como usar</h2>
            <p class="muted">Após aplicar, abra “Configurações do condomínio” para editar texto, categoria, ordem, obrigatoriedade ou desativar qualquer item. As novas fiscalizações passam a carregar automaticamente os itens ativos.</p>
            <div class="inline-actions">
                <a class="button secondary" href="/settings?condo=<?=(int)$condo['id']?>#checklist">Abrir configuração completa</a>
                <a class="button secondary" href="/condominium/inspections?condo=<?=(int)$condo['id']?>">Ver fiscalizações</a>
            </div>
        </section>
    </main>
</div>
</body>
</html>
