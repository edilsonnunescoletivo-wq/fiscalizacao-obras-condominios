<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/config/bootstrap.php';

use App\Core\Database;

$pdo = Database::connection();

function runMigrationSql(PDO $pdo, string $path): void
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

$exists = $pdo->query("SHOW TABLES LIKE 'schema_migrations'")->fetchColumn();
if (!$exists) {
    fwrite(STDERR, "Controle de migrations não inicializado. Em uma instalação existente, aplique primeiro 009_schema_migrations.sql de forma controlada.\n");
    exit(2);
}

$applied = array_flip($pdo->query('SELECT migration FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN));
$files = glob(dirname(__DIR__) . '/database/migrations/*.sql') ?: [];
sort($files, SORT_NATURAL);

$pending = [];
foreach ($files as $path) {
    $name = basename($path);
    if (!isset($applied[$name])) {
        $pending[] = $path;
    }
}

if (!$pending) {
    echo "Banco já está atualizado.\n";
    exit(0);
}

echo 'Migrations pendentes: ' . count($pending) . PHP_EOL;
foreach ($pending as $path) {
    $name = basename($path);
    echo 'Aplicando ' . $name . '...' . PHP_EOL;
    try {
        runMigrationSql($pdo, $path);
        $stmt = $pdo->prepare('INSERT INTO schema_migrations(migration) VALUES(?)');
        $stmt->execute([$name]);
        echo 'OK ' . $name . PHP_EOL;
    } catch (Throwable $e) {
        fwrite(STDERR, 'FALHA em ' . $name . ': ' . $e->getMessage() . PHP_EOL);
        fwrite(STDERR, "Interrompido. Não execute novamente às cegas; verifique se houve DDL parcial desta migration.\n");
        exit(1);
    }
}

echo "Banco atualizado com sucesso.\n";
