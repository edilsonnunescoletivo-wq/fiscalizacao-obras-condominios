<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\WorkAccess;
use PDO;
use Throwable;

final class MaintenanceController
{
    private const ALLOWED_HOST = 'fiscalizacao-homolog.sindicosgestao.com.br';
    private const EXPECTED_DATABASE = 'fiscalizacao1';
    private const MIN_MANAGED_MIGRATION = 12;

    private function guard(): array
    {
        $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        $host = explode(':', $host, 2)[0];
        if ($host !== self::ALLOWED_HOST) {
            http_response_code(404);
            exit('Página não encontrada.');
        }
        if (!Auth::check()) {
            header('Location: /login');
            exit;
        }
        $user = Auth::user();
        if (!WorkAccess::isSuperAdmin((int)$user['id'])) {
            http_response_code(403);
            exit('Acesso não autorizado.');
        }
        return $user;
    }

    private function runSqlFile(PDO $pdo, string $path): void
    {
        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new \RuntimeException('Não foi possível ler a migration.');
        }
        $statements = preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [];
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if ($statement === '') continue;
            $pdo->exec($statement);
        }
    }

    private function managedMigrations(): array
    {
        $dir = dirname(__DIR__, 2) . '/database/migrations';
        $files = glob($dir . '/*.sql') ?: [];
        $managed = [];
        foreach ($files as $path) {
            $name = basename($path);
            if (!preg_match('/^(\d{3})_/', $name, $match)) continue;
            if ((int)$match[1] < self::MIN_MANAGED_MIGRATION) continue;
            $managed[$name] = $path;
        }
        ksort($managed, SORT_NATURAL);
        return $managed;
    }

    private function pendingMigrations(PDO $pdo): array
    {
        $managed = $this->managedMigrations();
        if (!$managed) return [];
        $applied = array_flip(array_column($pdo->query('SELECT migration FROM schema_migrations')->fetchAll(), 'migration'));
        return array_filter($managed, static fn(string $path, string $name): bool => !isset($applied[$name]), ARRAY_FILTER_USE_BOTH);
    }

    public function migrations(): void
    {
        $this->guard();
        header('Cache-Control: no-store, private');
        header('Referrer-Policy: no-referrer');

        $pdo = Database::connection();
        $database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
        if ($database !== self::EXPECTED_DATABASE) {
            http_response_code(409);
            exit('Banco inesperado. Migração bloqueada.');
        }

        $historyReady = (bool)$pdo->query("SHOW TABLES LIKE 'schema_migrations'")->fetchColumn();
        if (!$historyReady) {
            http_response_code(409);
            exit('Controle de migrations não inicializado.');
        }

        $pending = $this->pendingMigrations($pdo);
        $message = null;
        $error = null;
        $appliedNow = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['_token'] ?? null)) {
                http_response_code(419);
                exit('Sessão expirada.');
            }

            if (!$pending) {
                $message = 'Nenhuma migration pendente. O banco de homologação já está atualizado.';
            } else {
                foreach ($pending as $name => $path) {
                    try {
                        $this->runSqlFile($pdo, $path);
                        $insert = $pdo->prepare('INSERT INTO schema_migrations(migration) VALUES(?)');
                        $insert->execute([$name]);
                        $appliedNow[] = $name;
                    } catch (Throwable $e) {
                        http_response_code(500);
                        $error = 'Falha ao aplicar ' . $name . '. A execução foi interrompida para verificação manual.';
                        break;
                    }
                }
                if (!$error) {
                    $message = count($appliedNow) . ' migration(s) aplicada(s) com sucesso no banco fiscalizacao1: ' . implode(', ', $appliedNow) . '.';
                }
            }
            $pending = $this->pendingMigrations($pdo);
        }

        $autoRun = (bool)$pending && $_SERVER['REQUEST_METHOD'] === 'GET' && (string)($_GET['autorun'] ?? '') === '1';
        $token = Csrf::token();
        ?>
        <!doctype html>
        <html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Atualização do banco · Homologação</title><link rel="stylesheet" href="/assets/css/app.css"><meta name="referrer" content="no-referrer"></head>
        <body><main class="container" style="max-width:760px;padding-top:48px"><section class="panel"><p class="eyebrow">Manutenção controlada</p><h1>Migrations da homologação</h1><p>Ambiente: <strong>homologação</strong> · Banco validado: <strong><?=htmlspecialchars($database)?></strong></p>
        <?php if($message):?><div class="alert success"><?=htmlspecialchars($message)?></div><?php endif;?>
        <?php if($error):?><div class="alert error"><?=htmlspecialchars($error)?></div><?php endif;?>
        <?php if($pending && !$error):?><p>Atualizações pendentes:</p><ul><?php foreach(array_keys($pending) as $name):?><li><code><?=htmlspecialchars($name)?></code></li><?php endforeach;?></ul><form id="migration-form" method="post"><input type="hidden" name="_token" value="<?=htmlspecialchars($token)?>"><button class="button primary" type="submit">Aplicar migrations pendentes</button></form><?php endif;?>
        <?php if(!$pending):?><div class="alert success">Banco de homologação atualizado.</div><p><a class="button primary" href="/">Voltar ao painel</a></p><?php endif;?>
        </section></main>
        <?php if($autoRun):?><script>document.getElementById('migration-form')?.submit();</script><?php endif;?>
        </body></html>
        <?php
    }
}
