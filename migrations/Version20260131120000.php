<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

/** Remove expires_at from holds (expired status removed). */
final class Version20260131120000 extends AbstractMigration
{
    #[Override]
    public function getDescription(): string
    {
        return 'Drop holds.expires_at and holds_expires_idx';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX holds_expires_idx ON holds');
        $this->addSql('ALTER TABLE holds DROP COLUMN expires_at');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE holds ADD expires_at DATETIME DEFAULT NULL');
        $this->addSql('CREATE INDEX holds_expires_idx ON holds (expires_at)');
    }
}
