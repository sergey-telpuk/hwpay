<?php

declare(strict_types=1);

namespace App\Domain\Transfer;

enum TransactionType: string
{
    case Payment = 'payment';
}
