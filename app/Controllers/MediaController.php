<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\WorkAccess;

final class MediaController
{
    private function requireAuth(): array
    {
        if (!Auth::check()) {
            header('Location: /login');
            exit;
        }
        return Auth::user();
    }

    public function uploadInspectionPhoto(): void
    {
        $user = $this->requireAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::validate($_POST['_token'] ?? null)) {
            http_response_code(419);
            exit('Sessão expirada.');
        }
        $inspectionId = (int)($_POST['inspection_id'] ?? 0);
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT work_id FROM inspections WHERE id=?');
        $stmt->execute([$inspectionId]);
        $workId = (int)$stmt->fetchColumn();
        if (!$workId) {
            http_response_code(404);
            exit('Fiscalização não encontrada.');
        }
        WorkAccess::load($workId, (int)$user['id'], true);
        $file = $_FILES['photo'] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > 8 * 1024 * 1024) {
            http_response_code(422);
            exit('Foto inválida ou maior que 8 MB.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $ext = match ($mime) { 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', default => null };
        if ($ext === null) {
            http_response_code(422);
            exit('Formato não permitido. Use JPG, PNG ou WEBP.');
        }
        $folder = dirname(__DIR__, 2) . '/storage/uploads/inspections/' . $inspectionId;
        if (!is_dir($folder) && !mkdir($folder, 0775, true) && !is_dir($folder)) {
            http_response_code(500);
            exit('Não foi possível preparar a pasta.');
        }
        $stored = bin2hex(random_bytes(16)) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $folder . '/' . $stored)) {
            http_response_code(500);
            exit('Não foi possível salvar a foto.');
        }
        $relative = 'storage/uploads/inspections/' . $inspectionId . '/' . $stored;
        $caption = trim((string)($_POST['caption'] ?? '')) ?: null;
        $stmt = $pdo->prepare('INSERT INTO inspection_photos(inspection_id,work_id,original_name,stored_path,caption,uploaded_by) VALUES(?,?,?,?,?,?)');
        $stmt->execute([$inspectionId,$workId,basename((string)$file['name']),$relative,$caption,$user['id']]);
        $stmt = $pdo->prepare('INSERT INTO work_events(work_id,user_id,event_type,title,description) VALUES(?,?,?,?,?)');
        $stmt->execute([$workId,$user['id'],'INSPECTION_PHOTO_ADDED','Foto de fiscalização adicionada',$caption]);
        header('Location: /inspection/photos?id=' . $inspectionId . '&uploaded=1');
        exit;
    }

    public function inspectionPhotos(): void
    {
        $user = $this->requireAuth();
        $inspectionId = (int)($_GET['id'] ?? 0);
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT i.*, w.unit, w.work_type FROM inspections i JOIN works w ON w.id=i.work_id WHERE i.id=?');
        $stmt->execute([$inspectionId]);
        $inspection = $stmt->fetch();
        if (!$inspection) {
            http_response_code(404);
            exit('Fiscalização não encontrada.');
        }
        $work = WorkAccess::load((int)$inspection['work_id'], (int)$user['id']);
        $stmt = $pdo->prepare('SELECT p.*, u.name uploaded_by_name FROM inspection_photos p LEFT JOIN users u ON u.id=p.uploaded_by WHERE p.inspection_id=? ORDER BY p.created_at DESC');
        $stmt->execute([$inspectionId]);
        $photos = $stmt->fetchAll();
        $canUploadPhoto = WorkAccess::canInspect($work['_roles']);
        require dirname(__DIR__, 2) . '/resources_inspection_photos.php';
    }

    public function photo(): void
    {
        $user = $this->requireAuth();
        $photoId = (int)($_GET['id'] ?? 0);
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM inspection_photos WHERE id=?');
        $stmt->execute([$photoId]);
        $photo = $stmt->fetch();
        if (!$photo) {
            http_response_code(404);
            exit('Foto não encontrada.');
        }
        WorkAccess::load((int)$photo['work_id'], (int)$user['id']);

        $storageRoot = realpath(dirname(__DIR__, 2) . '/storage/uploads/inspections');
        $path = realpath(dirname(__DIR__, 2) . '/' . ltrim((string)$photo['stored_path'], '/\\'));
        if ($storageRoot === false || $path === false || !str_starts_with($path, $storageRoot . DIRECTORY_SEPARATOR) || !is_file($path)) {
            http_response_code(404);
            exit('Arquivo não encontrado.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (!in_array($mime, ['image/jpeg','image/png','image/webp'], true)) {
            http_response_code(415);
            exit('Tipo de arquivo inválido.');
        }

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store, max-age=0');
        readfile($path);
        exit;
    }
}
