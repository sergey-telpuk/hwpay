<?php

declare(strict_types=1);

namespace App\Domain\Transfer;

enum HoldStatus: string
{
    case Active = 'active';
    case Captured = 'captured';
    case Released = 'released';
    case Expired = 'expired';
}
