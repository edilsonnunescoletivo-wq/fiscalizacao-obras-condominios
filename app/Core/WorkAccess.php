<?php

namespace App\Core;

final class WorkAccess
{
    public static function isSuperAdmin(int $userId): bool
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT is_super_admin FROM users WHERE id=? AND active=1 LIMIT 1');
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn() === 1;
    }

    public static function rolesForCondo(int $condominiumId, int $userId): array
    {
        if ($condominiumId <= 0) return [];
        if (self::isSuperAdmin($userId)) {
            return ['SUPER_ADMIN', 'ADMIN'];
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT r.code
             FROM condominium_user cu
             JOIN roles r ON r.id = cu.role_id
             WHERE cu.condominium_id = ? AND cu.user_id = ? AND cu.active = 1'
        );
        $stmt->execute([$condominiumId, $userId]);
        return array_values(array_unique(array_column($stmt->fetchAll(), 'code')));
    }

    public static function load(int $workId, int $userId, bool $requireStaff = false): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT w.*, c.name condominium_name
             FROM works w
             JOIN condominiums c ON c.id = w.condominium_id
             WHERE w.id = ?'
        );
        $stmt->execute([$workId]);
        $work = $stmt->fetch();

        if (!$work) {
            http_response_code(404);
            exit('Obra não encontrada.');
        }

        $roles = self::rolesForCondo((int)$work['condominium_id'], $userId);
        if (!$roles) {
            http_response_code(403);
            exit('Acesso não autorizado.');
        }

        $onlyResponsible = in_array('WORK_RESPONSIBLE', $roles, true)
            && count(array_diff($roles, ['WORK_RESPONSIBLE'])) === 0;

        if ($onlyResponsible && (int)$work['responsible_user_id'] !== $userId) {
            http_response_code(403);
            exit('Acesso não autorizado.');
        }

        if ($requireStaff && !array_intersect($roles, ['SUPER_ADMIN', 'ADMIN', 'SYNDIC', 'MANAGER', 'INSPECTOR'])) {
            http_response_code(403);
            exit('Acesso não autorizado.');
        }

        $work['_roles'] = $roles;
        $work['_only_responsible'] = $onlyResponsible;
        return $work;
    }

    public static function canManage(array $roles): bool
    {
        return (bool)array_intersect($roles, ['SUPER_ADMIN', 'ADMIN', 'SYNDIC', 'MANAGER']);
    }

    public static function canInspect(array $roles): bool
    {
        return (bool)array_intersect($roles, ['SUPER_ADMIN', 'ADMIN', 'SYNDIC', 'MANAGER', 'INSPECTOR']);
    }
}
