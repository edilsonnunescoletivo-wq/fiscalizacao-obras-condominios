<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nova obra - <?= htmlspecialchars($condominium['name']) ?></title>
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
                <p class="eyebrow">Nova obra</p>
                <h1><?= htmlspecialchars($condominium['name']) ?></h1>
            </div>
            <a class="button secondary" href="/works?condo=<?= (int)$condominium['id'] ?>">Voltar</a>
        </header>

        <?php if (!empty($error)): ?>
            <div class="alert error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" class="form-stack">
            <input type="hidden" name="_token" value="<?= htmlspecialchars(App\Core\Csrf::token()) ?>">
            <input type="hidden" name="condominium_id" value="<?= (int)$condominium['id'] ?>">

            <section class="panel">
                <div class="panel-header"><div><h2>Unidade e proprietário</h2><p>Dados básicos da unidade em obra.</p></div></div>
                <div class="form-grid">
                    <label><span>Unidade *</span><input name="unit" required value="<?= htmlspecialchars($_POST['unit'] ?? '') ?>"></label>
                    <label><span>Proprietário *</span><input name="owner_name" required value="<?= htmlspecialchars($_POST['owner_name'] ?? '') ?>"></label>
                    <label><span>E-mail</span><input type="email" name="owner_email" value="<?= htmlspecialchars($_POST['owner_email'] ?? '') ?>"></label>
                    <label><span>Telefone</span><input name="owner_phone" value="<?= htmlspecialchars($_POST['owner_phone'] ?? '') ?>"></label>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header"><div><h2>Empresa responsável</h2><p>Dados da empresa executora da obra.</p></div></div>
                <div class="form-grid">
                    <label><span>Empresa</span><input name="company_name" value="<?= htmlspecialchars($_POST['company_name'] ?? '') ?>"></label>
                    <label><span>CNPJ</span><input name="company_cnpj" value="<?= htmlspecialchars($_POST['company_cnpj'] ?? '') ?>"></label>
                    <label><span>Contato</span><input name="company_contact" value="<?= htmlspecialchars($_POST['company_contact'] ?? '') ?>"></label>
                    <label><span>Telefone</span><input name="company_phone" value="<?= htmlspecialchars($_POST['company_phone'] ?? '') ?>"></label>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header"><div><h2>Responsável técnico</h2><p>Arquiteto, engenheiro ou outro responsável habilitado.</p></div></div>
                <div class="form-grid">
                    <label><span>Nome</span><input name="technical_name" value="<?= htmlspecialchars($_POST['technical_name'] ?? '') ?>"></label>
                    <label><span>Tipo</span><select name="technical_type"><option value="">Selecione</option><option value="ARCHITECT">Arquiteto</option><option value="ENGINEER">Engenheiro</option><option value="OTHER">Outro</option></select></label>
                    <label><span>CREA/CAU/Registro</span><input name="technical_registry" value="<?= htmlspecialchars($_POST['technical_registry'] ?? '') ?>"></label>
                    <label><span>Telefone</span><input name="technical_phone" value="<?= htmlspecialchars($_POST['technical_phone'] ?? '') ?>"></label>
                    <label><span>E-mail</span><input type="email" name="technical_email" value="<?= htmlspecialchars($_POST['technical_email'] ?? '') ?>"></label>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header"><div><h2>Informações da obra</h2><p>Escopo, período previsto e descrição do serviço.</p></div></div>
                <div class="form-grid">
                    <label><span>Tipo da obra *</span><select name="work_type" required><option value="">Selecione</option><option>Reforma interna</option><option>Reforma estrutural</option><option>Elétrica</option><option>Hidráulica</option><option>Fachada</option><option>Piscina</option><option>Cobertura</option><option>Ampliação</option><option>Demolição</option><option>Outros</option></select></label>
                    <label><span>Início previsto</span><input type="date" name="planned_start" value="<?= htmlspecialchars($_POST['planned_start'] ?? '') ?>"></label>
                    <label><span>Término previsto</span><input type="date" name="planned_end" value="<?= htmlspecialchars($_POST['planned_end'] ?? '') ?>"></label>
                    <label class="full"><span>Descrição</span><textarea name="description" rows="5"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea></label>
                </div>
            </section>

            <div class="form-actions">
                <a class="button secondary" href="/works?condo=<?= (int)$condominium['id'] ?>">Cancelar</a>
                <button class="button primary" type="submit">Salvar obra</button>
            </div>
        </form>
    </main>
</div>
</body>
</html>
