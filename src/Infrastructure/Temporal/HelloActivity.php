<?php

declare(strict_types=1);

namespace App\Infrastructure\Temporal;

use Temporal\Activity\ActivityMethod;

final class HelloActivity implements HelloActivityInterface
{
    #[ActivityMethod(name: 'greet')]
    public function greet(string $name): string
    {
        return 'Hello, '.$name.'!';
    }
}
