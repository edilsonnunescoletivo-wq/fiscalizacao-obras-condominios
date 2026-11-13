<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Criar senha | Fiscalização de Obras</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<main class="container" style="max-width:620px;padding-top:48px">
    <section class="panel">
        <p class="eyebrow">Primeiro acesso</p>
        <h1>Criar sua senha</h1>
        <p>Conta: <strong><?= htmlspecialchars((string)$setup['email']) ?></strong></p>
        <p class="muted">Este link é de uso único e expira em <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)$setup['expires_at']))) ?>.</p>

        <?php if ($error): ?>
            <div class="alert danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" class="form-stack" autocomplete="off">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
            <label>Nova senha
                <input type="password" name="password" minlength="10" required autocomplete="new-password">
            </label>
            <label>Confirmar nova senha
                <input type="password" name="password_confirmation" minlength="10" required autocomplete="new-password">
            </label>
            <button type="submit" class="button primary">Criar senha</button>
        </form>
    </section>
</main>
</body>
</html>
