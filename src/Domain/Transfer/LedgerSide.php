<?php

declare(strict_types=1);

namespace App\Domain\Transfer;

/** Double-entry ledger side. */
enum LedgerSide: string
{
    case Debit = 'debit';
    case Credit = 'credit';
}
