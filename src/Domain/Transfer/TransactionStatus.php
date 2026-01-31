<?php

declare(strict_types=1);

namespace App\Domain\Transfer;

/** Lifecycle status of a transfer transaction. */
enum TransactionStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
}
