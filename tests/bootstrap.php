<?php

declare(strict_types=1);

use Composer\Autoload\ClassLoader;

if (!is_file($autoload = __DIR__.'/../vendor/autoload.php')) {
    throw new LogicException('Run "composer install" to install dependencies.');
}

/** @var ClassLoader $loader */
$loader = require $autoload;
$loader->addPsr4('App\\', __DIR__.'/../src');

return $loader;
