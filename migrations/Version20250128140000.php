<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250128140000 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Create account table for fund transfers';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('account')) {
            return;
        }
        $platform = $this->connection->getDatabasePlatform();
        $target = new Schema();
        $table = $target->createTable('account');
        $table->addColumn('id', 'string', ['length' => 36]);
        $table->addColumn('balance_minor', 'bigint');
        $table->addColumn('currency', 'string', ['length' => 3, 'default' => 'USD']);
        $table->setPrimaryKey(['id']);

        foreach ($target->toSql($platform) as $sql) {
            $this->addSql($sql);
        }
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE account');
    }
}
