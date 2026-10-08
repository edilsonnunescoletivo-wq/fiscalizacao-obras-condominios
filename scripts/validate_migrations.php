<?php

declare(strict_types=1);

$host = getenv('TEST_DB_HOST') ?: '127.0.0.1';
$port = getenv('TEST_DB_PORT') ?: '3306';
$db = getenv('TEST_DB_DATABASE') ?: 'fiscalizacao_test';
$user = getenv('TEST_DB_USERNAME') ?: 'root';
$pass = getenv('TEST_DB_PASSWORD') ?: 'rootpass';

$pdo = new PDO(
    "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4",
    $user,
    $pass,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

function runSqlFile(PDO $pdo, string $path): void
{
    $sql = file_get_contents($path);
    if ($sql === false) {
        throw new RuntimeException('Não foi possível ler ' . $path);
    }
    $statements = preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [];
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if ($statement === '') continue;
        $pdo->exec($statement);
    }
}

$migrations = glob(dirname(__DIR__) . '/database/migrations/*.sql') ?: [];
sort($migrations, SORT_NATURAL);
if (!$migrations) {
    throw new RuntimeException('Nenhuma migration encontrada.');
}

foreach ($migrations as $migration) {
    echo 'Executando ' . basename($migration) . PHP_EOL;
    runSqlFile($pdo, $migration);
}

$requiredTables = [
    'users','roles','condominiums','condominium_user','works','document_types','work_documents',
    'work_events','inspections','inspection_photos','non_conformities','non_conformity_evidence',
    'notifications','notification_sequences','work_access_invites','work_completion_terms',
    'condominium_rules','notification_templates','inspection_checklist_items','severity_action_rules','audit_log',
    'schema_migrations',
];

$present = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
$missing = array_values(array_diff($requiredTables, $present));
if ($missing) {
    throw new RuntimeException('Tabelas ausentes após migrations: ' . implode(', ', $missing));
}

$roles = $pdo->query('SELECT code FROM roles ORDER BY code')->fetchAll(PDO::FETCH_COLUMN);
foreach (['ADMIN','SYNDIC','MANAGER','INSPECTOR','WORK_RESPONSIBLE','VIEWER'] as $role) {
    if (!in_array($role, $roles, true)) {
        throw new RuntimeException('Perfil ausente: ' . $role);
    }
}

$indexStmt = $pdo->query("SHOW INDEX FROM notifications WHERE Key_name='idx_notifications_number'");
if (!$indexStmt->fetch()) {
    throw new RuntimeException('Índice de número de notificação não encontrado após migration 008.');
}

$uniqueLegacy = $pdo->query("SHOW INDEX FROM notifications WHERE Key_name='uq_notification_number'")->fetch();
if ($uniqueLegacy) {
    throw new RuntimeException('Índice UNIQUE legado uq_notification_number ainda existe.');
}

$expectedMigrations = array_map('basename', $migrations);
$recordedMigrations = $pdo->query('SELECT migration FROM schema_migrations ORDER BY migration')->fetchAll(PDO::FETCH_COLUMN);
$missingHistory = array_values(array_diff($expectedMigrations, $recordedMigrations));
if ($missingHistory) {
    throw new RuntimeException('Migrations sem histórico: ' . implode(', ', $missingHistory));
}

if (count($recordedMigrations) !== count($expectedMigrations)) {
    throw new RuntimeException('Histórico de migrations possui quantidade inesperada de registros.');
}

echo 'OK: ' . count($migrations) . ' migrations aplicadas, registradas e estrutura essencial validada.' . PHP_EOL;
