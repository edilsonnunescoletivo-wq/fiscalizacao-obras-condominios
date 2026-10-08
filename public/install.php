<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$envPath = $root . '/.env';

if (is_file($envPath)) {
    http_response_code(404);
    exit('Instalador indisponível.');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['install_csrf'])) {
    $_SESSION['install_csrf'] = bin2hex(random_bytes(32));
}

$error = null;
$success = false;

function runSqlFile(PDO $pdo, string $path): void
{
    $sql = file_get_contents($path);
    if ($sql === false) {
        throw new RuntimeException('Não foi possível ler ' . basename($path));
    }

    $statements = preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [];
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if ($statement === '') continue;
        $pdo->exec($statement);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals($_SESSION['install_csrf'], (string)($_POST['_token'] ?? ''))) {
            throw new RuntimeException('Sessão expirada. Atualize a página e tente novamente.');
        }

        $dbPassword = (string)($_POST['db_password'] ?? '');
        $adminName = trim((string)($_POST['admin_name'] ?? ''));
        $adminEmail = mb_strtolower(trim((string)($_POST['admin_email'] ?? '')));
        $adminPassword = (string)($_POST['admin_password'] ?? '');
        $condoName = trim((string)($_POST['condo_name'] ?? 'Condomínio Demonstração'));

        if ($dbPassword === '' || $adminName === '' || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Preencha os dados obrigatórios corretamente.');
        }
        if (strlen($adminPassword) < 10) {
            throw new RuntimeException('A senha do administrador deve ter pelo menos 10 caracteres.');
        }
        if ($condoName === '') {
            $condoName = 'Condomínio Demonstração';
        }

        $host = 'fiscalizacao1.mysql.dbaas.com.br';
        $database = 'fiscalizacao1';
        $username = 'fiscalizacao1';
        $port = '3306';

        $pdo = new PDO(
            "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
            $username,
            $dbPassword,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        $existing = $pdo->query("SHOW TABLES LIKE 'users'")->fetchColumn();
        if ($existing) {
            throw new RuntimeException('O banco já possui estrutura instalada. O instalador não fará sobrescrita.');
        }

        $migrationFiles = glob($root . '/database/migrations/*.sql') ?: [];
        sort($migrationFiles, SORT_NATURAL);
        if (!$migrationFiles) {
            throw new RuntimeException('Nenhuma migration foi encontrada.');
        }

        foreach ($migrationFiles as $migration) {
            runSqlFile($pdo, $migration);
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO users(name,email,password_hash,active) VALUES(?,?,?,1)');
            $stmt->execute([$adminName, $adminEmail, password_hash($adminPassword, PASSWORD_DEFAULT)]);
            $userId = (int)$pdo->lastInsertId();

            $stmt = $pdo->prepare('INSERT INTO condominiums(name,active) VALUES(?,1)');
            $stmt->execute([$condoName]);
            $condoId = (int)$pdo->lastInsertId();

            $roleId = (int)$pdo->query("SELECT id FROM roles WHERE code='ADMIN' LIMIT 1")->fetchColumn();
            if ($roleId <= 0) {
                throw new RuntimeException('Perfil ADMIN não encontrado após as migrations.');
            }

            $stmt = $pdo->prepare('INSERT INTO condominium_user(condominium_id,user_id,role_id,active) VALUES(?,?,?,1)');
            $stmt->execute([$condoId, $userId, $roleId]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $appUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $escapedPassword = str_replace(["\\", '"', "\r", "\n"], ["\\\\", '\\"', '', ''], $dbPassword);
        $env = implode("\n", [
            'APP_ENV=homologation',
            'APP_DEBUG=false',
            'APP_URL=' . $appUrl,
            'APP_NAME="Fiscalização de Obras"',
            '',
            'DB_HOST=' . $host,
            'DB_PORT=' . $port,
            'DB_DATABASE=' . $database,
            'DB_USERNAME=' . $username,
            'DB_PASSWORD="' . $escapedPassword . '"',
            '',
            'SESSION_NAME=fiscalizacao_obras_homolog_session',
            '',
        ]);

        if (file_put_contents($envPath, $env, LOCK_EX) === false) {
            throw new RuntimeException('As tabelas foram criadas, mas não foi possível gravar o arquivo .env.');
        }
        @chmod($envPath, 0600);

        unset($_SESSION['install_csrf']);
        $success = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Instalação · Fiscalização de Obras</title>
<style>
body{margin:0;font-family:Inter,system-ui,-apple-system,Segoe UI,sans-serif;background:#f4f7fb;color:#18212f}.wrap{width:min(620px,92%);margin:48px auto}.card{background:#fff;border:1px solid #e5eaf1;border-radius:18px;padding:28px;box-shadow:0 10px 35px rgba(16,24,40,.08)}h1{margin:0 0 8px}p{color:#667085;line-height:1.5}.grid{display:grid;gap:16px;margin-top:24px}label{display:grid;gap:7px;font-weight:600}input{padding:12px;border:1px solid #d0d5dd;border-radius:9px;font:inherit}button,a.btn{display:inline-block;border:0;background:#175cd3;color:#fff;padding:12px 18px;border-radius:9px;text-decoration:none;font:inherit;cursor:pointer}.alert{padding:13px 15px;border-radius:10px;margin:18px 0}.error{background:#fff2f0;color:#b42318}.ok{background:#ecfdf3;color:#027a48}.note{background:#f8fafc;border-radius:10px;padding:12px;font-size:14px}</style>
</head>
<body><div class="wrap"><div class="card">
<h1>Instalação da homologação</h1>
<p>Conecta exclusivamente ao banco <strong>fiscalizacao1</strong>, cria a estrutura inicial e o primeiro administrador.</p>
<?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?>
<div class="alert ok"><strong>Instalação concluída.</strong><br>O instalador agora está bloqueado automaticamente.</div>
<p><a class="btn" href="/login">Abrir tela de login</a></p>
<?php else: ?>
<div class="note">A senha do banco é enviada somente entre seu navegador e a hospedagem. Ela não é armazenada no GitHub.</div>
<form method="post" class="grid" autocomplete="off">
<input type="hidden" name="_token" value="<?= htmlspecialchars($_SESSION['install_csrf']) ?>">
<label>Senha do banco fiscalizacao1<input type="password" name="db_password" required autocomplete="new-password"></label>
<label>Seu nome<input name="admin_name" required maxlength="150"></label>
<label>Seu e-mail de administrador<input type="email" name="admin_email" required maxlength="190"></label>
<label>Senha do administrador<input type="password" name="admin_password" required minlength="10" autocomplete="new-password"></label>
<label>Condomínio de demonstração<input name="condo_name" value="Condomínio Demonstração" maxlength="180"></label>
<button type="submit">Instalar homologação</button>
</form>
<?php endif; ?>
</div></div></body></html>
