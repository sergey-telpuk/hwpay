<?php

declare(strict_types=1);

namespace App\Domain\Transfer;

enum LedgerSide: string
{
    case Debit = 'debit';
    case Credit = 'credit';
}
