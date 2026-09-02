<?php

/**
 * @copyright   ©2026 Maatify.dev
 * @Library     maatify/image-profile-legacy
 * @author      Mohamed Abdulalim (megyptm) <mohamed@maatify.dev>
 * @since       2026-04-17
 */

declare(strict_types=1);

namespace Maatify\ImageProfileLegacy\tests\Unit\Processor;

use Maatify\ImageProfileLegacy\tests\Fixtures\TestImageFactory;
use Maatify\ImageProfileLegacy\DTO\OptimizationOptionsDTO;
use Maatify\ImageProfileLegacy\DTO\ProcessedImageDTO;
use Maatify\ImageProfileLegacy\DTO\ResizeOptionsDTO;
use Maatify\ImageProfileLegacy\Enum\ResizeModeEnum;
use Maatify\ImageProfileLegacy\Exception\ImageProfileException;
use Maatify\ImageProfileLegacy\Processor\NativeImageProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @requires extension gd
 */
#[CoversClass(\Maatify\ImageProfileLegacy\Processor\NativeImageProcessor::class)]
final class NativeImageProcessorTest extends TestCase
{
    private NativeImageProcessor $processor;
    private string $outputDir;

    protected function setUp(): void
    {
        $this->processor = new NativeImageProcessor();
        $this->outputDir = sys_get_temp_dir() . '/maatify_processor_test_' . uniqid('', true);
        mkdir($this->outputDir, 0777, true);
    }

    protected function tearDown(): void
    {
        TestImageFactory::cleanup();
        $this->removeDir($this->outputDir);
    }

    private function removeDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (glob($dir . '/*') ?: [] as $file) {
            is_dir($file) ? $this->removeDir($file) : unlink($file);
        }
        rmdir($dir);
    }

    // -------------------------------------------------------------------------
    // resize — return type
    // -------------------------------------------------------------------------

    public function test_resize_returns_processed_image_dto(): void
    {
        $source = TestImageFactory::jpeg();
        $target = $this->outputDir . '/resized.jpg';
        $opts   = ResizeOptionsDTO::fit(50, 50);

        $result = $this->processor->resize($source, $target, $opts);

        self::assertInstanceOf(ProcessedImageDTO::class, $result);
    }

    // -------------------------------------------------------------------------
    // resize — Fit mode: output fits within bounding box
    // -------------------------------------------------------------------------

    public function test_resize_fit_produces_file_on_disk(): void
    {
        $source = TestImageFactory::jpeg();
        $target = $this->outputDir . '/fit.jpg';

        $this->processor->resize($source, $target, ResizeOptionsDTO::fit(50, 50));

        self::assertFileExists($target);
    }

    public function test_resize_fit_dimensions_do_not_exceed_bounding_box(): void
    {
        $source = TestImageFactory::jpeg(); // 100×100
        $target = $this->outputDir . '/fit_small.jpg';

        $result = $this->processor->resize($source, $target, ResizeOptionsDTO::fit(40, 40));

        self::assertLessThanOrEqual(40, $result->width);
        self::assertLessThanOrEqual(40, $result->height);
    }

    // -------------------------------------------------------------------------
    // resize — Fill mode: output is exactly the requested size
    // -------------------------------------------------------------------------

    public function test_resize_fill_produces_exact_dimensions(): void
    {
        $source = TestImageFactory::jpeg();
        $target = $this->outputDir . '/fill.jpg';
        $opts   = new ResizeOptionsDTO(60, 40, ResizeModeEnum::Fill);

        $result = $this->processor->resize($source, $target, $opts);

        self::assertSame(60, $result->width);
        self::assertSame(40, $result->height);
    }

    public function test_resize_fill_centers_crop_from_landscape(): void
    {
        // Create a 200x100 landscape image
        $source = sys_get_temp_dir() . '/landscape.jpg';
        $img = imagecreatetruecolor(200, 100);
        $red = imagecolorallocate($img, 255, 0, 0);
        $green = imagecolorallocate($img, 0, 255, 0);
        $blue = imagecolorallocate($img, 0, 0, 255);

        // Fill red on left (50px), green in center (100px), blue on right (50px)
        imagefilledrectangle($img, 0, 0, 49, 99, $red);
        imagefilledrectangle($img, 50, 0, 149, 99, $green);
        imagefilledrectangle($img, 150, 0, 199, 99, $blue);
        imagejpeg($img, $source, 100);
        imagedestroy($img);
        (new \ReflectionProperty(TestImageFactory::class, 'tempFiles'))->setValue(null, array_merge((new \ReflectionProperty(TestImageFactory::class, 'tempFiles'))->getValue(), [$source])); // For cleanup

        $target = $this->outputDir . '/fill_square.jpg';
        // Resize to 50x50 square. Since source is 2:1, it must be scaled down by 2
        // to match height, making intermediate 100x50, then centered crop to 50x50.
        // It should capture the center green portion.
        $opts = new ResizeOptionsDTO(50, 50, ResizeModeEnum::Fill, 100);
        $result = $this->processor->resize($source, $target, $opts);

        self::assertSame(50, $result->width);
        self::assertSame(50, $result->height);

        $outImg = imagecreatefromjpeg($target);

        // Check center pixel
        $rgb = imagecolorat($outImg, 25, 25);
        $colors = imagecolorsforindex($outImg, $rgb);
        self::assertGreaterThan(200, $colors['green']);
        self::assertLessThan(50, $colors['red']);
        self::assertLessThan(50, $colors['blue']);
        imagedestroy($outImg);
    }

    public function test_resize_fill_centers_crop_from_portrait(): void
    {
        // Create a 100x200 portrait image
        $source = sys_get_temp_dir() . '/portrait.jpg';
        $img = imagecreatetruecolor(100, 200);
        $red = imagecolorallocate($img, 255, 0, 0);
        $green = imagecolorallocate($img, 0, 255, 0);
        $blue = imagecolorallocate($img, 0, 0, 255);

        // Fill red on top (50px), green in center (100px), blue on bottom (50px)
        imagefilledrectangle($img, 0, 0, 99, 49, $red);
        imagefilledrectangle($img, 0, 50, 99, 149, $green);
        imagefilledrectangle($img, 0, 150, 99, 199, $blue);
        imagejpeg($img, $source, 100);
        imagedestroy($img);
        (new \ReflectionProperty(TestImageFactory::class, 'tempFiles'))->setValue(null, array_merge((new \ReflectionProperty(TestImageFactory::class, 'tempFiles'))->getValue(), [$source])); // For cleanup

        $target = $this->outputDir . '/fill_square_portrait.jpg';
        $opts = new ResizeOptionsDTO(50, 50, ResizeModeEnum::Fill, 100);
        $result = $this->processor->resize($source, $target, $opts);

        self::assertSame(50, $result->width);
        self::assertSame(50, $result->height);

        $outImg = imagecreatefromjpeg($target);

        $rgb = imagecolorat($outImg, 25, 25);
        $colors = imagecolorsforindex($outImg, $rgb);
        self::assertGreaterThan(200, $colors['green']);
        self::assertLessThan(50, $colors['red']);
        self::assertLessThan(50, $colors['blue']);
        imagedestroy($outImg);
    }

    // -------------------------------------------------------------------------
    // resize — Stretch mode: output is exactly the requested size
    // -------------------------------------------------------------------------

    public function test_resize_stretch_produces_exact_dimensions(): void
    {
        $source = TestImageFactory::jpeg();
        $target = $this->outputDir . '/stretch.jpg';
        $opts   = new ResizeOptionsDTO(80, 30, ResizeModeEnum::Stretch);

        $result = $this->processor->resize($source, $target, $opts);

        self::assertSame(80, $result->width);
        self::assertSame(30, $result->height);
    }

    // -------------------------------------------------------------------------
    // resize — format conversion via outputFormat
    // -------------------------------------------------------------------------

    public function test_resize_can_convert_jpeg_to_webp(): void
    {
        $source = TestImageFactory::jpeg();
        $target = $this->outputDir . '/converted.webp';
        $opts   = ResizeOptionsDTO::webpThumbnail(50, 50);

        $result = $this->processor->resize($source, $target, $opts);

        self::assertSame('webp', $result->format);
        self::assertSame('image/webp', $result->mimeType);
        self::assertFileExists($target);
    }

    // -------------------------------------------------------------------------
    // resize — result metadata
    // -------------------------------------------------------------------------

    public function test_resize_result_has_positive_size_bytes(): void
    {
        $source = TestImageFactory::jpeg();
        $target = $this->outputDir . '/sized.jpg';

        $result = $this->processor->resize($source, $target, ResizeOptionsDTO::fit(50, 50));

        self::assertGreaterThan(0, $result->sizeBytes);
    }

    public function test_resize_result_records_processing_time(): void
    {
        $source = TestImageFactory::jpeg();
        $target = $this->outputDir . '/timed.jpg';

        $result = $this->processor->resize($source, $target, ResizeOptionsDTO::fit(50, 50));

        self::assertGreaterThanOrEqual(0, $result->processingTimeMs);
    }

    public function test_resize_result_output_path_matches_target(): void
    {
        $source = TestImageFactory::jpeg();
        $target = $this->outputDir . '/path_check.jpg';

        $result = $this->processor->resize($source, $target, ResizeOptionsDTO::fit(50, 50));

        self::assertSame($target, $result->outputPath);
    }

    // -------------------------------------------------------------------------
    // resize — different source formats
    // -------------------------------------------------------------------------

    public function test_resize_works_with_png_source(): void
    {
        $source = TestImageFactory::png();
        $target = $this->outputDir . '/from_png.png';

        $result = $this->processor->resize($source, $target, ResizeOptionsDTO::fit(50, 50));

        self::assertFileExists($target);
        self::assertGreaterThan(0, $result->width);
    }

    public function test_resize_works_with_webp_source(): void
    {
        $source = TestImageFactory::webp();
        $target = $this->outputDir . '/from_webp.webp';

        $result = $this->processor->resize($source, $target, ResizeOptionsDTO::fit(50, 50));

        self::assertFileExists($target);
        self::assertGreaterThan(0, $result->width);
    }

    // -------------------------------------------------------------------------
    // resize — invalid source path throws
    // -------------------------------------------------------------------------

    public function test_resize_throws_on_missing_source(): void
    {
        $source = '/tmp/no_such_image_' . uniqid('', true) . '.jpg';
        $target = $this->outputDir . '/should_not_exist.jpg';

        $this->expectException(ImageProfileException::class);

        $this->processor->resize($source, $target, ResizeOptionsDTO::fit(50, 50));
    }

    public function test_resize_throws_on_non_image_source(): void
    {
        $source = TestImageFactory::notAnImage();
        $target = $this->outputDir . '/should_not_exist.jpg';

        $this->expectException(ImageProfileException::class);

        $this->processor->resize($source, $target, ResizeOptionsDTO::fit(50, 50));
    }

    // -------------------------------------------------------------------------
    // optimize — JPEG recompression
    // -------------------------------------------------------------------------

    public function test_optimize_recompress_produces_file(): void
    {
        $source = TestImageFactory::jpeg();
        $target = $this->outputDir . '/optimized.jpg';

        $result = $this->processor->optimize($source, $target, OptimizationOptionsDTO::recompress(70));

        self::assertFileExists($target);
        self::assertGreaterThan(0, $result->sizeBytes);
    }

    public function test_optimize_returns_processed_image_dto(): void
    {
        $source = TestImageFactory::jpeg();
        $target = $this->outputDir . '/opt_dto.jpg';

        $result = $this->processor->optimize($source, $target, OptimizationOptionsDTO::recompress());

        self::assertInstanceOf(ProcessedImageDTO::class, $result);
    }

    // -------------------------------------------------------------------------
    // optimize — format conversion to WebP
    // -------------------------------------------------------------------------

    public function test_optimize_converts_to_webp(): void
    {
        $source = TestImageFactory::jpeg();
        $target = $this->outputDir . '/opt_webp.webp';

        $result = $this->processor->optimize($source, $target, OptimizationOptionsDTO::toWebp(80));

        self::assertSame('webp', $result->format);
        self::assertSame('image/webp', $result->mimeType);
        self::assertFileExists($target);
    }

    // -------------------------------------------------------------------------
    // convertToWebp — convenience method
    // -------------------------------------------------------------------------

    public function test_convert_to_webp_produces_webp_file(): void
    {
        $source = TestImageFactory::jpeg();
        $target = $this->outputDir . '/conv.webp';

        $result = $this->processor->convertToWebp($source, $target);

        self::assertSame('image/webp', $result->mimeType);
        self::assertFileExists($target);
    }

    public function test_convert_to_webp_from_png(): void
    {
        $source = TestImageFactory::png();
        $target = $this->outputDir . '/png_to_webp.webp';

        $result = $this->processor->convertToWebp($source, $target, 75);

        self::assertSame('image/webp', $result->mimeType);
        self::assertGreaterThan(0, $result->sizeBytes);
    }

    // -------------------------------------------------------------------------
    // JSON serialization of result
    // -------------------------------------------------------------------------

    public function test_processed_image_dto_is_json_serializable(): void
    {
        $source = TestImageFactory::jpeg();
        $target = $this->outputDir . '/json.jpg';

        $result  = $this->processor->resize($source, $target, ResizeOptionsDTO::fit(50, 50));
        $encoded = json_encode($result, JSON_THROW_ON_ERROR);
        $decoded = json_decode($encoded, true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($decoded);
        self::assertArrayHasKey('outputPath', $decoded);
        self::assertArrayHasKey('width', $decoded);
        self::assertArrayHasKey('height', $decoded);
        self::assertArrayHasKey('sizeBytes', $decoded);
        self::assertArrayHasKey('mimeType', $decoded);
        self::assertArrayHasKey('format', $decoded);
        self::assertArrayHasKey('processingTimeMs', $decoded);
    }

    public function test_resize_png_with_low_quality_clamps_compression(): void
    {
        // Quality 1-4 would previously convert to compression 10: round((100 - 4)/10) = 10
        // imagepng only accepts 0-9. On PHP 8.4+ this throws ValueError, so we must clamp it to 9.
        $source = TestImageFactory::png();
        $target = $this->outputDir . '/png_low_quality.png';

        $opts = ResizeOptionsDTO::fit(50, 50, 4); // quality = 4

        $result = $this->processor->resize($source, $target, $opts);

        self::assertFileExists($target);
        self::assertSame('image/png', $result->mimeType);
    }
}
