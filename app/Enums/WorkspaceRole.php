<?php

namespace App\Enums;

enum WorkspaceRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';
    case Guest = 'guest';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'مالک',
            self::Admin => 'مدیر',
            self::Member => 'عضو',
            self::Guest => 'مهمان',
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::Owner => 3,
            self::Admin => 2,
            self::Member => 1,
            self::Guest => 0,
        };
    }

    public function atLeast(self $role): bool
    {
        return $this->rank() >= $role->rank();
    }

    /** Roles that may be granted through an invitation or a role change. */
    public static function assignable(): array
    {
        return [self::Admin->value, self::Member->value, self::Guest->value];
    }
}
