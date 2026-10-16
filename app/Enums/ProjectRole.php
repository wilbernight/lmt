<?php

namespace App\Enums;

enum ProjectRole: string
{
    case Manager = 'manager';
    case Member = 'member';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Manager => 'مدیر پروژه',
            self::Member => 'عضو',
            self::Viewer => 'ناظر',
        };
    }
}
