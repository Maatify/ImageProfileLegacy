<?php

declare(strict_types=1);

namespace Maatify\ImageProfileLegacy\ValueObject;

use Maatify\ImageProfileLegacy\Exception\ImageProfileException;

final class PdoTableIdentifier
{
    private function __construct() {}

    public static function assertValid(string $table): void
    {
        if (preg_match('/^[a-zA-Z0-9_]+$/', $table) !== 1) {
            throw new class("Invalid table identifier: {$table}") extends ImageProfileException {};
        }
    }
}