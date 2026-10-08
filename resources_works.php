<?php
$statusLabels = [
    'DRAFT' => 'Rascunho',
    'WAITING_DOCUMENTS' => 'Aguardando documentos',
    'UNDER_REVIEW' => 'Em análise',
    'CORRECTION_REQUIRED' => 'Correção necessária',
    'TECHNICALLY_APPROVED' => 'Aprovada tecnicamente',
    'AUTHORIZED' => 'Autorizada',
    'IN_PROGRESS' => 'Em andamento',
    'NOTIFIED' => 'Notificada',
    'SUSPENDED' => 'Suspensa',
    'EMBARGOED' => 'Embargada',
    'COMPLETION_INSPECTION' => 'Vistoria de conclusão',
    'COMPLETED' => 'Concluída',
    'CANCELLED' => 'Cancelada',
];
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Obras - <?= htmlspecialchars($condominium['name']) ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="app-body">
<div class="shell">
    <aside class="sidebar">
        <div class="brand">Fiscaliza Obras</div>
        <nav>
            <a href="/">Visão geral</a>
            <a class="active" href="/works?condo=<?= (int)$condominium['id'] ?>">Obras</a>
            <a href="#">Fiscalizações</a>
            <a href="#">Notificações</a>
            <a href="#">Relatórios</a>
        </nav>
    </aside>
    <main class="content">
        <header class="topbar">
            <div>
                <p class="eyebrow">Condomínio</p>
                <h1><?= htmlspecialchars($condominium['name']) ?></h1>
            </div>
            <a class="button primary" href="/works/create?condo=<?= (int)$condominium['id'] ?>">+ Nova obra</a>
        </header>

        <?php if (isset($_GET['created'])): ?>
            <div class="alert success">Obra cadastrada com sucesso.</div>
        <?php endif; ?>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <h2>Obras cadastradas</h2>
                    <p>Acompanhe situação, unidade e responsáveis.</p>
                </div>
            </div>

            <?php if (!$works): ?>
                <div class="empty-state">
                    <h3>Nenhuma obra cadastrada</h3>
                    <p>Cadastre a primeira obra deste condomínio para iniciar o fluxo documental e de fiscalização.</p>
                    <a class="button primary" href="/works/create?condo=<?= (int)$condominium['id'] ?>">Cadastrar primeira obra</a>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Unidade</th>
                                <th>Proprietário</th>
                                <th>Tipo</th>
                                <th>Empresa</th>
                                <th>Responsável técnico</th>
                                <th>Status</th>
                                <th>Prazo</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($works as $work): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($work['unit']) ?></strong></td>
                                <td><?= htmlspecialchars($work['owner_name']) ?></td>
                                <td><?= htmlspecialchars($work['work_type'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($work['company_name'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($work['technical_name'] ?? '-') ?></td>
                                <td><span class="badge"><?= htmlspecialchars($statusLabels[$work['status']] ?? $work['status']) ?></span></td>
                                <td><?= htmlspecialchars($work['planned_end'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>
</body>
</html>
