<?php

declare(strict_types=1);

namespace App\Domain\Transfer;

enum TransactionStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
}
