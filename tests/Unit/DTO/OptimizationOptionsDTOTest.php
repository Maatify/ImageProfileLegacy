<?php

declare(strict_types=1);

namespace Maatify\ImageProfileLegacy\tests\Unit\DTO;

use Maatify\ImageProfileLegacy\DTO\OptimizationOptionsDTO;
use Maatify\ImageProfileLegacy\Enum\ImageFormatEnum;
use Maatify\ImageProfileLegacy\Exception\InvalidImageInputException;
use PHPUnit\Framework\TestCase;

final class OptimizationOptionsDTOTest extends TestCase
{
    public function test_recompress_named_constructor(): void
    {
        $dto = OptimizationOptionsDTO::recompress(75);
        self::assertSame(75, $dto->quality);
        self::assertNull($dto->targetFormat);
    }

    public function test_toWebp_named_constructor(): void
    {
        $dto = OptimizationOptionsDTO::toWebp(85);
        self::assertSame(85, $dto->quality);
        self::assertSame(ImageFormatEnum::Webp, $dto->targetFormat);
    }

    public function test_quality_below_1_throws(): void
    {
        $this->expectException(InvalidImageInputException::class);
        new OptimizationOptionsDTO(0);
    }

    public function test_quality_above_100_throws(): void
    {
        $this->expectException(InvalidImageInputException::class);
        new OptimizationOptionsDTO(101);
    }
}
