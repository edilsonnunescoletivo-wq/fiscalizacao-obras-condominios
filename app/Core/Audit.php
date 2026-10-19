<?php

namespace App\Core;

final class Audit
{
    public static function log(
        ?int $userId,
        ?int $condominiumId,
        string $entityType,
        ?int $entityId,
        string $action,
        array $details = []
    ): void {
        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare(
                'INSERT INTO audit_log (user_id, condominium_id, entity_type, entity_id, action, details, ip_address)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $userId,
                $condominiumId,
                $entityType,
                $entityId,
                $action,
                $details ? json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                self::clientIp(),
            ]);
        } catch (\Throwable $e) {
            error_log('Audit log failure: ' . $e->getMessage());
        }
    }

    private static function clientIp(): ?string
    {
        $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
        if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }
        return mb_substr($ip, 0, 45);
    }
}
