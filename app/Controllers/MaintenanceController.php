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
    private const MIGRATION = '011_visual_covers.sql';

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

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM schema_migrations WHERE migration=?');
        $stmt->execute([self::MIGRATION]);
        $alreadyApplied = (int)$stmt->fetchColumn() > 0;
        $message = null;
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['_token'] ?? null)) {
                http_response_code(419);
                exit('Sessão expirada.');
            }
            if ($alreadyApplied) {
                $message = 'A migration 011 já estava aplicada. Nenhuma alteração foi necessária.';
            } else {
                $path = dirname(__DIR__, 2) . '/database/migrations/' . self::MIGRATION;
                try {
                    $this->runSqlFile($pdo, $path);
                    $insert = $pdo->prepare('INSERT INTO schema_migrations(migration) VALUES(?)');
                    $insert->execute([self::MIGRATION]);
                    $message = 'Migration 011 aplicada com sucesso no banco fiscalizacao1.';
                    $alreadyApplied = true;
                } catch (Throwable $e) {
                    http_response_code(500);
                    $error = 'Falha ao aplicar a migration. A execução foi interrompida para verificação manual.';
                }
            }
        }

        $autoRun = !$alreadyApplied && $_SERVER['REQUEST_METHOD'] === 'GET' && (string)($_GET['autorun'] ?? '') === '1';
        $token = Csrf::token();
        ?>
        <!doctype html>
        <html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Atualização do banco · Homologação</title><link rel="stylesheet" href="/assets/css/app.css"><meta name="referrer" content="no-referrer"></head>
        <body><main class="container" style="max-width:760px;padding-top:48px"><section class="panel"><p class="eyebrow">Manutenção controlada</p><h1>Migration 011</h1><p>Ambiente: <strong>homologação</strong> · Banco validado: <strong><?=htmlspecialchars($database)?></strong></p>
        <?php if($message):?><div class="alert success"><?=htmlspecialchars($message)?></div><?php endif;?>
        <?php if($error):?><div class="alert error"><?=htmlspecialchars($error)?></div><?php endif;?>
        <?php if(!$alreadyApplied && !$error):?><p>Esta atualização adiciona somente os campos de foto de capa do condomínio e da obra.</p><form id="migration-form" method="post"><input type="hidden" name="_token" value="<?=htmlspecialchars($token)?>"><button class="button primary" type="submit">Aplicar migration 011</button></form><?php endif;?>
        <?php if($alreadyApplied):?><p><a class="button primary" href="/">Voltar ao painel</a></p><?php endif;?>
        </section></main>
        <?php if($autoRun):?><script>document.getElementById('migration-form')?.submit();</script><?php endif;?>
        </body></html>
        <?php
    }
}
