<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (file_exists(dirname(__DIR__).'/config/bootstrap.php')) {
    require dirname(__DIR__).'/config/bootstrap.php';
} elseif (method_exists(Dotenv::class, 'bootEnv')) {
    new Dotenv()->bootEnv(dirname(__DIR__).'/.env');
}

$console = sprintf('%s/../bin/console', __DIR__);
passthru(
    "APP_ENV=test php " . escapeshellarg($console) . " cache:clear --env=test --no-warmup",
    $clearResult,
);
if ($clearResult !== 0) {
    throw new RuntimeException('Test cache clear failed.');
}
passthru(
    "APP_ENV=test php " . escapeshellarg($console) . " doctrine:migrations:migrate --no-interaction",
    $result,
);
if ($result !== 0) {
    throw new RuntimeException(
        'Doctrine migrations failed. Run: php bin/console doctrine:migrations:migrate --no-interaction --env=test',
    );
}
