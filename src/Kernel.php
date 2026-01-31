<?php

declare(strict_types=1);

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

/** Application kernel (Symfony 8). */
final class Kernel extends BaseKernel
{
    use MicroKernelTrait;
}
