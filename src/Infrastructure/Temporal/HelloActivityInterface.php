<?php

declare(strict_types=1);

namespace App\Infrastructure\Temporal;

use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;

#[ActivityInterface(prefix: 'HelloActivity.')]
interface HelloActivityInterface
{
    #[ActivityMethod(name: 'greet')]
    public function greet(string $name): string;
}
