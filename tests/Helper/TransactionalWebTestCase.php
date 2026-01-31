<?php

declare(strict_types=1);

namespace App\Tests\Helper;

use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Migrations run once on first createClient(). Each test runs in a transaction (rollback in tearDown).
 */
abstract class TransactionalWebTestCase extends WebTestCase
{
    private static ?EntityManagerInterface $em = null;

    private static bool $migrationsRun = false;

    /** @param array<string, mixed> $options
     * @param array<string, string> $server
     */
    #[\Override]
    protected static function createClient(array $options = [], array $server = []): KernelBrowser
    {
        $client = parent::createClient($options, $server);
        if (!self::$migrationsRun) {
            $kernel = static::$kernel;
            assert($kernel instanceof KernelInterface);
            $application = new Application($kernel);
            $application->setAutoExit(false);
            $output = new BufferedOutput();
            $exitCode = $application->run(new ArrayInput([
                'command' => 'doctrine:migrations:migrate',
                '--no-interaction' => true,
            ]), $output);
            if ($exitCode !== 0) {
                throw new RuntimeException(
                    'Doctrine migrations failed (exit ' . $exitCode . ").\n" . $output->fetch(),
                    $exitCode
                );
            }
            self::$migrationsRun = true;
        }
        if (!self::$em instanceof EntityManagerInterface) {
            $em = $client->getContainer()->get(EntityManagerInterface::class);
            assert($em instanceof EntityManagerInterface);
            self::$em = $em;
            self::$em->beginTransaction();
        }

        return $client;
    }

    #[\Override]
    protected function tearDown(): void
    {
        if (self::$em instanceof EntityManagerInterface) {
            try {
                self::$em->rollback();
            } catch (\Throwable) {
            }
            self::$em->clear();
            self::$em = null;
        }
        parent::tearDown();
    }
}
