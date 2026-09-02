<?php

declare(strict_types=1);

namespace Maatify\ImageProfileLegacy\tests\Integration\Infrastructure\PDO;

use Maatify\ImageProfileLegacy\Application\DTO\CreateImageProfileRequest;
use Maatify\ImageProfileLegacy\DTO\ImageProfileProcessingExtensionDTO;
use Maatify\ImageProfileLegacy\DTO\VariantDefinitionCollectionDTO;
use Maatify\ImageProfileLegacy\DTO\VariantDefinitionDTO;
use Maatify\ImageProfileLegacy\Infrastructure\Persistence\PDO\PdoImageProfileProvider;
use Maatify\ImageProfileLegacy\Infrastructure\Repository\PDO\PdoImageProfileRepository;
use Maatify\ImageProfileLegacy\ValueObject\AllowedExtensionCollection;
use Maatify\ImageProfileLegacy\ValueObject\AllowedMimeTypeCollection;
use PDO;
use PHPUnit\Framework\TestCase;

final class PdoImageProfileProviderVariantTest extends TestCase
{
    private PDO $pdo;
    private PdoImageProfileRepository $repository;
    private PdoImageProfileProvider $provider;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec('
            CREATE TABLE image_profiles (
                id                  INTEGER PRIMARY KEY AUTOINCREMENT,
                code                TEXT    NOT NULL UNIQUE,
                display_name        TEXT    DEFAULT NULL,
                min_width           INTEGER DEFAULT NULL,
                min_height          INTEGER DEFAULT NULL,
                max_width           INTEGER DEFAULT NULL,
                max_height          INTEGER DEFAULT NULL,
                max_size_bytes      INTEGER DEFAULT NULL,
                allowed_extensions  TEXT    DEFAULT NULL,
                allowed_mime_types  TEXT    DEFAULT NULL,
                is_active           INTEGER NOT NULL DEFAULT 1,
                notes               TEXT    DEFAULT NULL,
                min_aspect_ratio    REAL    DEFAULT NULL,
                max_aspect_ratio    REAL    DEFAULT NULL,
                requires_transparency INTEGER NOT NULL DEFAULT 0,
                preferred_format    TEXT    DEFAULT NULL,
                preferred_quality   INTEGER DEFAULT NULL,
                variants            TEXT    DEFAULT NULL,
                created_at          TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at          TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $this->repository = new PdoImageProfileRepository($this->pdo);
        $this->provider = new PdoImageProfileProvider($this->pdo);
    }

    public function test_can_round_trip_variants(): void
    {
        $variants = new VariantDefinitionCollectionDTO(
            VariantDefinitionDTO::thumbnail(),
            VariantDefinitionDTO::medium()
        );

        $request = new CreateImageProfileRequest(
            code: 'with_variants',
            displayName: 'Test',
            minWidth: null,
            minHeight: null,
            maxWidth: null,
            maxHeight: null,
            maxSizeBytes: null,
            allowedExtensions: new AllowedExtensionCollection(),
            allowedMimeTypes: new AllowedMimeTypeCollection(),
            isActive: true,
            notes: null,
            minAspectRatio: null,
            maxAspectRatio: null,
            requiresTransparency: false,
            processing: new ImageProfileProcessingExtensionDTO(null, null, $variants)
        );

        $this->repository->save($request);

        $profile = $this->provider->findByCode('with_variants');
        self::assertNotNull($profile);
        self::assertNotNull($profile->processing);
        self::assertNotNull($profile->processing->variants);
        self::assertSame(2, $profile->processing->variants->count());
        self::assertTrue($profile->processing->variants->hasName('thumbnail'));
        self::assertTrue($profile->processing->variants->hasName('medium'));
    }

    public function test_can_read_legacy_flat_variants(): void
    {
        $sql = "INSERT INTO image_profiles (code, variants) VALUES ('legacy', '[{\"name\":\"small\",\"width\":100,\"height\":100,\"mode\":\"fit\",\"quality\":80,\"outputFormat\":\"webp\"}]')";
        $this->pdo->exec($sql);

        $profile = $this->provider->findByCode('legacy');
        self::assertNotNull($profile);
        self::assertNotNull($profile->processing);
        self::assertNotNull($profile->processing->variants);
        self::assertSame(1, $profile->processing->variants->count());
        $variant = $profile->processing->variants->findByName('small');
        self::assertNotNull($variant);
        self::assertSame(100, $variant->options->width);
        self::assertSame(100, $variant->options->height);
    }
}
