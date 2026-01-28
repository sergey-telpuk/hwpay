<?php

declare(strict_types=1);

namespace App\Tests\Helper;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class TransactionalWebTestCase extends WebTestCase
{
    private static ?EntityManagerInterface $em = null;

    #[\Override]
    protected static function createClient(array $options = [], array $server = []): KernelBrowser
    {
        $client = parent::createClient($options, $server);
        if (!self::$em instanceof EntityManagerInterface) {
            self::$em = $client->getContainer()->get(EntityManagerInterface::class);
            self::$em->getConnection()->executeStatement('DELETE FROM account');
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
                // No active transaction (e.g. middleware already committed)
            }
            self::$em->clear();
            self::$em = null;
        }
        parent::tearDown();
    }
}
