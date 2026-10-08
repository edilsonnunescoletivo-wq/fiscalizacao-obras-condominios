<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;

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

    private function canAccessWork(int $workId, int $userId, bool $requireStaff = false): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT w.*, c.name condominium_name FROM works w JOIN condominiums c ON c.id=w.condominium_id WHERE w.id=?');
        $stmt->execute([$workId]);
        $work = $stmt->fetch();
        if (!$work) {
            http_response_code(404);
            exit('Obra não encontrada.');
        }
        $stmt = $pdo->prepare('SELECT r.code FROM condominium_user cu JOIN roles r ON r.id=cu.role_id WHERE cu.condominium_id=? AND cu.user_id=? AND cu.active=1');
        $stmt->execute([$work['condominium_id'], $userId]);
        $roles = array_column($stmt->fetchAll(), 'code');
        if (!$roles) {
            http_response_code(403);
            exit('Acesso não autorizado.');
        }
        if (in_array('WORK_RESPONSIBLE', $roles, true) && count(array_diff($roles, ['WORK_RESPONSIBLE'])) === 0 && (int)$work['responsible_user_id'] !== $userId) {
            http_response_code(403);
            exit('Acesso não autorizado.');
        }
        if ($requireStaff && !array_intersect($roles, ['ADMIN','SYNDIC','MANAGER','INSPECTOR'])) {
            http_response_code(403);
            exit('Acesso não autorizado.');
        }
        return $work;
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
        $this->canAccessWork($workId, (int)$user['id'], true);
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
        $work = $this->canAccessWork((int)$inspection['work_id'], (int)$user['id']);
        $stmt = $pdo->prepare('SELECT p.*, u.name uploaded_by_name FROM inspection_photos p LEFT JOIN users u ON u.id=p.uploaded_by WHERE p.inspection_id=? ORDER BY p.created_at DESC');
        $stmt->execute([$inspectionId]);
        $photos = $stmt->fetchAll();
        $stmt = $pdo->prepare('SELECT r.code FROM condominium_user cu JOIN roles r ON r.id=cu.role_id WHERE cu.condominium_id=? AND cu.user_id=? AND cu.active=1');
        $stmt->execute([$work['condominium_id'], $user['id']]);
        $roles = array_column($stmt->fetchAll(), 'code');
        $canUploadPhoto = (bool)array_intersect($roles, ['ADMIN','SYNDIC','MANAGER','INSPECTOR']);
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
        $this->canAccessWork((int)$photo['work_id'], (int)$user['id']);
        $path = dirname(__DIR__, 2) . '/' . $photo['stored_path'];
        if (!is_file($path)) {
            http_response_code(404);
            exit('Arquivo não encontrado.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        readfile($path);
    }
}
