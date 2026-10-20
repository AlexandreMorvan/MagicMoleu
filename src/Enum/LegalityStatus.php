<?php

namespace App\Enum;

enum LegalityStatus: string
{
    case Legal = 'legal';
    case NotLegal = 'not_legal';
    case Banned = 'banned';
    case Restricted = 'restricted';

    public function label(): string
    {
        return match ($this) {
            self::Legal => 'Légale',
            self::NotLegal => 'Non légale',
            self::Banned => 'Bannie',
            self::Restricted => 'Restreinte',
        };
    }
}
