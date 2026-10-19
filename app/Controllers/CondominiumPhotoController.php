<?php

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\WorkAccess;

final class CondominiumPhotoController
{
    public function remove(): void
    {
        if (!Auth::check()) {
            header('Location: /login');
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::validate($_POST['_token'] ?? null)) {
            http_response_code(419);
            exit('Sessão expirada.');
        }

        $user = Auth::user();
        $condoId = (int)($_POST['condominium_id'] ?? 0);
        $roles = WorkAccess::rolesForCondo($condoId, (int)$user['id']);
        if ($condoId <= 0 || !WorkAccess::canManage($roles)) {
            http_response_code(403);
            exit('Acesso não autorizado.');
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT photo_path FROM condominiums WHERE id=? AND active=1');
        $stmt->execute([$condoId]);
        $photoPath = $stmt->fetchColumn();
        if ($photoPath === false) {
            http_response_code(404);
            exit('Condomínio não encontrado.');
        }

        $pdo->prepare('UPDATE condominiums SET photo_original_name=NULL, photo_path=NULL WHERE id=?')->execute([$condoId]);

        if (is_string($photoPath) && $photoPath !== '') {
            $allowedRoot = realpath(dirname(__DIR__, 2) . '/storage/uploads/condominiums');
            $filePath = realpath(dirname(__DIR__, 2) . '/' . ltrim($photoPath, '/\\'));
            if ($allowedRoot !== false && $filePath !== false && str_starts_with($filePath, $allowedRoot . DIRECTORY_SEPARATOR) && is_file($filePath)) {
                @unlink($filePath);
            }
        }

        Audit::log((int)$user['id'], $condoId, 'condominium', $condoId, 'PHOTO_REMOVED', []);

        $returnTo = (string)($_POST['return_to'] ?? 'dashboard');
        if ($returnTo === 'works') {
            header('Location: /works?condo=' . $condoId . '&condo_photo_removed=1');
        } else {
            header('Location: /?photo_removed=1');
        }
        exit;
    }
}
