<?php

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\WorkAccess;

final class DocumentController
{
    public function show(): void
    {
        if (!Auth::check()) {
            header('Location: /login');
            exit;
        }

        $user = Auth::user();
        $documentId = (int)($_GET['id'] ?? 0);
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT wd.*, w.condominium_id FROM work_documents wd JOIN works w ON w.id=wd.work_id WHERE wd.id=?');
        $stmt->execute([$documentId]);
        $document = $stmt->fetch();
        if (!$document) {
            http_response_code(404);
            exit('Documento não encontrado.');
        }

        WorkAccess::load((int)$document['work_id'], (int)$user['id']);

        $storageRoot = realpath(dirname(__DIR__, 2) . '/storage/uploads/works');
        $path = realpath(dirname(__DIR__, 2) . '/' . ltrim((string)$document['stored_path'], '/\\'));
        if ($storageRoot === false || $path === false || !str_starts_with($path, $storageRoot . DIRECTORY_SEPARATOR) || !is_file($path)) {
            http_response_code(404);
            exit('Arquivo não encontrado.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (!in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true)) {
            http_response_code(415);
            exit('Tipo de arquivo inválido.');
        }

        Audit::log((int)$user['id'], (int)$document['condominium_id'], 'work_document', $documentId, 'VIEWED', [
            'work_id' => (int)$document['work_id'],
            'version' => (int)$document['version'],
        ]);

        $filename = preg_replace('/[\r\n"\\\/]+/', '-', (string)$document['original_name']) ?: 'documento';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store, max-age=0');
        readfile($path);
        exit;
    }
}
