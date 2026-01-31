<?php

declare(strict_types=1);

namespace App\Domain\Transfer;

/** Type of transaction (e.g. payment). */
enum TransactionType: string
{
    case Payment = 'payment';
}
