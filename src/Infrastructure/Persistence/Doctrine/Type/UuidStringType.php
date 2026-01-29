<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Symfony\Component\Uid\Uuid;

/**
 * UUID stored as CHAR(36) (RFC 4122 string) in the database.
 */
final class UuidStringType extends Type
{
    public const string NAME = 'uuid_string';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 36, 'fixed' => true]);
    }

    #[\Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Uuid
    {
        if ($value instanceof Uuid || null === $value || '' === $value) {
            return $value instanceof Uuid ? $value : null;
        }

        return Uuid::fromString(is_scalar($value) ? (string) $value : '');
    }

    #[\Override]
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value instanceof Uuid) {
            return $value->toRfc4122();
        }
        if (null === $value || '' === $value) {
            return null;
        }

        return Uuid::fromString(is_scalar($value) ? (string) $value : '')->toRfc4122();
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
