<?php

/**
 * @copyright   ©2026 Maatify.dev
 * @Library     maatify/image-profile-legacy
 * @author      Mohamed Abdulalim (megyptm) <mohamed@maatify.dev>
 * @since       2026-04-17
 */

declare(strict_types=1);

namespace Maatify\ImageProfileLegacy\DTO;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Maatify\ImageProfileLegacy\Enum\ImageFormatEnum;
use Maatify\ImageProfileLegacy\Enum\ResizeModeEnum;
use Traversable;

/**
 * Immutable, typed collection of VariantDefinitionDTO objects.
 *
 * @implements IteratorAggregate<int, VariantDefinitionDTO>
 */
final class VariantDefinitionCollectionDTO implements IteratorAggregate, Countable, JsonSerializable
{
    /** @var list<VariantDefinitionDTO> */
    private array $items;

    public function __construct(VariantDefinitionDTO ...$variants)
    {
        $this->items = array_values($variants);
    }

    // -------------------------------------------------------------------------
    // Factory
    // -------------------------------------------------------------------------

    public static function empty(): self
    {
        return new self();
    }

    /**
     * Deserialise from a JSON-decoded array (as stored in the database).
     *
     * Expected per-element shape:
     * {
     *   "name": string,
     *   "width": int,
     *   "height": int,
     *   "mode": string  (fit|fill|stretch),
     *   "quality": int,
     *   "outputFormat": string|null
     * }
     *
     * Malformed or missing elements are silently skipped to prevent a bad DB
     * row from taking down the entire provider.
     *
     * @param array<int, mixed> $rows
     */
    public static function fromJsonArray(array $rows): self
    {
        $variants = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $name   = isset($row['name']) && is_string($row['name']) ? $row['name'] : null;

            // Handle nested options structure (canonical) or fallback to flat structure (legacy)
            $optionsSource = isset($row['options']) && is_array($row['options']) ? $row['options'] : $row;

            $width  = isset($optionsSource['width'])  && is_numeric($optionsSource['width']) ? (int) $optionsSource['width']  : null;
            $height = isset($optionsSource['height']) && is_numeric($optionsSource['height'])? (int) $optionsSource['height'] : null;

            if ($name === null || $name === '' || $width === null || $height === null) {
                continue;
            }

            $modeValue    = isset($optionsSource['mode']) && is_string($optionsSource['mode']) ? $optionsSource['mode'] : ResizeModeEnum::Fit->value;
            $mode         = ResizeModeEnum::tryFrom($modeValue) ?? ResizeModeEnum::Fit;
            $quality      = isset($optionsSource['quality']) && is_numeric($optionsSource['quality']) ? (int) $optionsSource['quality'] : 85;
            $formatValue  = isset($optionsSource['outputFormat']) && is_string($optionsSource['outputFormat']) ? $optionsSource['outputFormat'] : null;
            $outputFormat = $formatValue !== null ? ImageFormatEnum::tryFrom($formatValue) : null;

            try {
                $options    = new ResizeOptionsDTO($width, $height, $mode, $quality, $outputFormat);
                $variants[] = new VariantDefinitionDTO($name, $options);
            } catch (\Throwable) {
                // Skip invalid entries
            }
        }

        return new self(...$variants);
    }

    /**
     * Deserialise from a JSON string (as read directly from a database TEXT column).
     * Returns an empty collection on null, empty string, or invalid JSON.
     */
    public static function fromJsonString(?string $json): self
    {
        if ($json === null || $json === '') {
            return self::empty();
        }

        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            return self::empty();
        }

        return self::fromJsonArray(array_values($decoded));
    }

    /**
     * Returns a new instance with $variant appended.
     * The receiver is not mutated.
     */
    public function with(VariantDefinitionDTO $variant): self
    {
        return new self(...$this->items, ...[$variant]);
    }

    // -------------------------------------------------------------------------
    // Lookups
    // -------------------------------------------------------------------------

    public function findByName(string $name): ?VariantDefinitionDTO
    {
        foreach ($this->items as $item) {
            if ($item->name === $name) {
                return $item;
            }
        }

        return null;
    }

    public function hasName(string $name): bool
    {
        return $this->findByName($name) !== null;
    }

    // -------------------------------------------------------------------------
    // IteratorAggregate
    // -------------------------------------------------------------------------

    /** @return Traversable<int, VariantDefinitionDTO> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    // -------------------------------------------------------------------------
    // Countable
    // -------------------------------------------------------------------------

    public function count(): int
    {
        return count($this->items);
    }

    // -------------------------------------------------------------------------
    // JsonSerializable
    // -------------------------------------------------------------------------

    /** @return list<array<string, mixed>> */
    public function jsonSerialize(): array
    {
        return array_map(
            static fn(VariantDefinitionDTO $v): array => $v->jsonSerialize(),
            $this->items
        );
    }
}
