<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Full schema from doctrine:migrations:diff (run against empty DB).
 * FKs and FX accounts added manually for app consistency.
 */
final class Version20260129155938 extends AbstractMigration
{
    private const string FX_DEBIT_ACCOUNT_ID  = '00000000-0000-0000-0000-000000000001';
    private const string FX_CREDIT_ACCOUNT_ID = '00000000-0000-0000-0000-000000000002';

    #[\Override]
    public function getDescription(): string
    {
        return 'Schema (accounts, transactions, ledger_entries, holds, fx_transactions), FKs, technical FX accounts';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE accounts (id CHAR(36) NOT NULL, owner_type VARCHAR(20) NOT NULL, owner_id VARCHAR(36) NOT NULL, currency VARCHAR(3) NOT NULL, type VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, INDEX accounts_owner_idx (owner_type, owner_id), INDEX accounts_currency_idx (currency), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE fx_transactions (id CHAR(36) NOT NULL, transaction_id CHAR(36) NOT NULL, rate NUMERIC(18, 10) NOT NULL, spread NUMERIC(18, 10) NOT NULL, created_at DATETIME NOT NULL, base_amount_amount BIGINT NOT NULL, base_amount_currency VARCHAR(3) NOT NULL, quote_amount_amount BIGINT NOT NULL, quote_amount_currency VARCHAR(3) NOT NULL, UNIQUE INDEX fx_transactions_transaction_id_unique (transaction_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE holds (id CHAR(36) NOT NULL, account_id CHAR(36) NOT NULL, status VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, reason LONGTEXT DEFAULT NULL, expires_at DATETIME DEFAULT NULL, amount_amount BIGINT NOT NULL, amount_currency VARCHAR(3) NOT NULL, INDEX holds_account_idx (account_id), INDEX holds_account_status_idx (account_id, status), INDEX holds_expires_idx (expires_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE ledger_entries (id CHAR(36) NOT NULL, transaction_id CHAR(36) NOT NULL, account_id CHAR(36) NOT NULL, side VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, amount_amount BIGINT NOT NULL, amount_currency VARCHAR(3) NOT NULL, INDEX ledger_tx_idx (transaction_id), INDEX ledger_account_time_idx (account_id, created_at), INDEX ledger_account_id_idx (account_id, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE transactions (id CHAR(36) NOT NULL, external_id VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, from_account_id CHAR(36) NOT NULL, to_account_id CHAR(36) NOT NULL, created_at DATETIME NOT NULL, meta JSON NOT NULL, amount_amount BIGINT NOT NULL, amount_currency VARCHAR(3) NOT NULL, INDEX transactions_created_idx (created_at), INDEX transactions_status_idx (status), INDEX transactions_type_idx (type), UNIQUE INDEX transactions_external_id_unique (external_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');

        $this->addSql('ALTER TABLE holds ADD CONSTRAINT fk_holds_account FOREIGN KEY (account_id) REFERENCES accounts (id)');
        $this->addSql('ALTER TABLE ledger_entries ADD CONSTRAINT fk_ledger_account FOREIGN KEY (account_id) REFERENCES accounts (id)');
        $this->addSql('ALTER TABLE ledger_entries ADD CONSTRAINT fk_ledger_transaction FOREIGN KEY (transaction_id) REFERENCES transactions (id)');
        $this->addSql('ALTER TABLE fx_transactions ADD CONSTRAINT fk_fx_transaction FOREIGN KEY (transaction_id) REFERENCES transactions (id)');

        $now = new \DateTimeImmutable()->format('Y-m-d H:i:s');
        $this->addSql(
            'INSERT INTO accounts (id, owner_type, owner_id, currency, type, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?), (?, ?, ?, ?, ?, ?, ?)',
            [self::FX_DEBIT_ACCOUNT_ID, 'system', 'fx', 'USD', 'fx_pool', 'active', $now, self::FX_CREDIT_ACCOUNT_ID, 'system', 'fx', 'EUR', 'fx_pool', 'active', $now],
            [\Doctrine\DBAL\ParameterType::STRING, \Doctrine\DBAL\ParameterType::STRING, \Doctrine\DBAL\ParameterType::STRING, \Doctrine\DBAL\ParameterType::STRING, \Doctrine\DBAL\ParameterType::STRING, \Doctrine\DBAL\ParameterType::STRING, \Doctrine\DBAL\ParameterType::STRING, \Doctrine\DBAL\ParameterType::STRING, \Doctrine\DBAL\ParameterType::STRING, \Doctrine\DBAL\ParameterType::STRING, \Doctrine\DBAL\ParameterType::STRING, \Doctrine\DBAL\ParameterType::STRING, \Doctrine\DBAL\ParameterType::STRING],
        );
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM ledger_entries WHERE account_id IN (?, ?)', [self::FX_DEBIT_ACCOUNT_ID, self::FX_CREDIT_ACCOUNT_ID], [\Doctrine\DBAL\ParameterType::STRING, \Doctrine\DBAL\ParameterType::STRING]);
        $this->addSql('DELETE FROM accounts WHERE id IN (?, ?)', [self::FX_DEBIT_ACCOUNT_ID, self::FX_CREDIT_ACCOUNT_ID], [\Doctrine\DBAL\ParameterType::STRING, \Doctrine\DBAL\ParameterType::STRING]);
        $this->addSql('ALTER TABLE holds DROP FOREIGN KEY fk_holds_account');
        $this->addSql('ALTER TABLE ledger_entries DROP FOREIGN KEY fk_ledger_account');
        $this->addSql('ALTER TABLE ledger_entries DROP FOREIGN KEY fk_ledger_transaction');
        $this->addSql('ALTER TABLE fx_transactions DROP FOREIGN KEY fk_fx_transaction');
        $this->addSql('DROP TABLE ledger_entries');
        $this->addSql('DROP TABLE holds');
        $this->addSql('DROP TABLE fx_transactions');
        $this->addSql('DROP TABLE transactions');
        $this->addSql('DROP TABLE accounts');
    }
}
