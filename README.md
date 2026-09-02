<div align="center">

# Maatify Image Profile Legacy

![Maatify.dev](https://www.maatify.dev/assets/img/img/maatify_logo_white.svg)

[![Latest Version](https://img.shields.io/packagist/v/maatify/image-profile-legacy.svg)](https://packagist.org/packages/maatify/image-profile-legacy)
[![PHP Version](https://img.shields.io/packagist/php-v/maatify/image-profile-legacy.svg)](https://packagist.org/packages/maatify/image-profile-legacy)
[![License](https://img.shields.io/packagist/l/maatify/image-profile-legacy.svg)](LICENSE)
[![PHPStan](https://img.shields.io/badge/PHPStan-Level%20Max-4E8CAE)](https://github.com/Maatify/ImageProfileLegacy)

[![Monthly Downloads](https://img.shields.io/packagist/dm/maatify/image-profile-legacy)](https://packagist.org/packages/maatify/image-profile-legacy)
[![Total Downloads](https://img.shields.io/packagist/dt/maatify/image-profile-legacy)](https://packagist.org/packages/maatify/image-profile-legacy)
[![Maatify Ecosystem](https://img.shields.io/badge/Maatify-Ecosystem-blueviolet)](https://github.com/Maatify)
[![Install](https://img.shields.io/badge/Install-composer%20require%20maatify%2Fimage--profile--legacy-blue)](https://packagist.org/packages/maatify/image-profile-legacy)

[![Changelog](https://img.shields.io/badge/Changelog-View-blue.svg)](CHANGELOG.md)
[![Release Checklist](https://img.shields.io/badge/Release-Checklist-blue.svg)](RELEASE_READINESS_CHECKLIST.md)
[![Security Policy](https://img.shields.io/badge/Security-Policy-blue.svg)](SECURITY.md)
[![Contributing Guide](https://img.shields.io/badge/Contributing-Guide-blue.svg)](CONTRIBUTING.md)
[![Code of Conduct](https://img.shields.io/badge/Code%20of%20Conduct-Read-blue.svg)](CODE_OF_CONDUCT.md)

`maatify/image-profile-legacy` is a framework-agnostic standalone Composer package for defining and validating named image-upload profiles within the Maatify ecosystem.

It provides reusable validation rules, a typed validation result, and pluggable profile storage — deliberately without pulling any HTTP framework, cloud SDK, or image-processing library into its core.

</div>

---

## Table of Contents

- [Key Features](#key-features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Core Concepts](#core-concepts)
- [Database Schema](#database-schema)
- [Providers](#providers)
- [Validation Flow](#validation-flow)
- [Public Entry Point](#public-entry-point)
- [Composition Helper](#composition-helper)
- [Canonical Contract Surface](#canonical-contract-surface)
- [Error Handling](#error-handling)
- [Adapters](#adapters)
- [Storage](#storage)
- [Extension Strategy](#extension-strategy)
- [No-Array Contract](#no-array-contract)
- [Design Rules](#design-rules)
- [Testing and Static Analysis](#testing-and-static-analysis)
- [Versioning](#versioning)
- [Quality Status](#quality-status)
- [License](#license)
- [Author](#author)

---

## 🚀 Key Features

* **Named, Reusable Validation Profiles**: Stable business `code` identifiers (e.g. `product_thumbnail`) carrying dimension, size, format, aspect-ratio, and transparency rules.
* **Framework-Agnostic Core**: Zero required runtime dependencies — `NativeImageMetadataReader` reads metadata via `getimagesize()`, part of PHP's core `standard` extension.
* **Typed, Never-Throw Validation Results**: `validateByCode()` always returns a typed `ImageValidationResultDTO`; business rule failures are collected, not thrown.
* **Pluggable Profile Providers**: Ships with `ArrayImageProfileProvider` (config/testing) and `PdoImageProfileProvider` (database-backed) — implement `ImageProfileProviderInterface` for anything else.
* **Optional Framework & Cloud Adapters**: `SlimUploadedFileAdapter` (PSR-7) and `NativePhpUploadAdapter` (`$_FILES`) for input, `DoSpacesImageStorage` for DigitalOcean Spaces / S3-compatible storage — all live outside the core.
* **No-Array Contract**: Every public collection (errors, warnings, profiles, allowed extensions/MIME types) is a typed, iterable, JSON-serializable DTO — never a raw array.
* **Extension-Ready Processing Primitives**: Optional resize/optimize/variant-generation APIs are decoupled from the stable v1 validation path.
* **Stable Contract Surface**: 15 validation error codes defined in `ValidationErrorCodeEnum`, guaranteed not to change within a minor version.

### Out of Scope

The canonical validation core of this package is deliberately **not** responsible for:

* framework HTTP lifecycle
* admin UI or CRUD controllers

*(Note: While the core focuses solely on validation, the package does ship optional extension primitives for image resizing/optimization, adapters for `$_FILES`/PSR-7, and a DoSpaces storage implementation to aid integration.)*

---

## 📋 Requirements

* PHP `^8.2`
* No runtime dependencies beyond PHP itself for the core validation path

**Optional, Feature-Gated Dependencies** (only needed if you use that specific class):

* `ext-gd` — `NativeImageProcessor` / `NativeImageVariantGenerator` (resize, optimize, variant generation)
* `ext-fileinfo` — `DoSpacesImageStorage` (detects MIME type via `finfo` before upload)
* `ext-pdo` — `PdoImageProfileProvider` / `PdoImageProfileRepository` (use `ArrayImageProfileProvider` to avoid this dependency)
* `psr/http-message` — `SlimUploadedFileAdapter`
* `aws/aws-sdk-php` — `DoSpacesImageStorage`

---

## 📦 Installation

```bash
composer require maatify/image-profile-legacy
```

---

## Core Concepts

### Image Profile

An `ImageProfileEntity` is a reusable, immutable rule set identified by a stable business `code` such as `category_app_image`, `product_thumbnail`, or `homepage_banner`.

```php
use Maatify\ImageProfileLegacy\Entity\ImageProfileEntity;
use Maatify\ImageProfileLegacy\ValueObject\AllowedExtensionCollection;
use Maatify\ImageProfileLegacy\ValueObject\AllowedMimeTypeCollection;

$profile = new ImageProfileEntity(
    id:                1,
    code:              'product_thumbnail',
    displayName:       'Product Thumbnail',
    minWidth:          100,
    minHeight:         100,
    maxWidth:          2000,
    maxHeight:         2000,
    maxSizeBytes:      1_048_576, // 1 MB
    allowedExtensions: new AllowedExtensionCollection('jpg', 'jpeg', 'png', 'webp'),
    allowedMimeTypes:  new AllowedMimeTypeCollection('image/jpeg', 'image/png', 'image/webp'),
    isActive:          true,
    notes:             null,
);
```

Any `null` dimension bound disables that rule. Empty extension/MIME collections disable those restrictions entirely.

### Image File Input

`ImageFileInputDTO` is a neutral, framework-agnostic carrier of the upload data.

```php
use Maatify\ImageProfileLegacy\DTO\ImageFileInputDTO;

$input = new ImageFileInputDTO(
    originalName:   'banner.webp',
    temporaryPath:  '/tmp/phpXYZ123',
    clientMimeType: 'image/webp',   // client hint — not trusted for validation
    sizeBytes:      524288,
);
```

### Validation Result

`ImageValidationResultDTO` is the typed outcome of a validation call.

```php
$result->isValid()         // bool
$result->profileCode       // string
$result->metadata          // ?ImageMetadataDTO
$result->errors            // ImageValidationErrorCollectionDTO
$result->warnings          // ImageValidationWarningCollectionDTO
```

---

## Database Schema

```sql
CREATE TABLE `image_profiles` (
    `id`                    INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `code`                  VARCHAR(64)      NOT NULL,
    `display_name`          VARCHAR(128)     DEFAULT NULL,
    `min_width`             INT UNSIGNED     DEFAULT NULL,
    `min_height`            INT UNSIGNED     DEFAULT NULL,
    `max_width`             INT UNSIGNED     DEFAULT NULL,
    `max_height`            INT UNSIGNED     DEFAULT NULL,
    `max_size_bytes`        BIGINT UNSIGNED  DEFAULT NULL,
    `allowed_extensions`    VARCHAR(255)     DEFAULT NULL,
    `allowed_mime_types`    TEXT             DEFAULT NULL,
    `is_active`             TINYINT(1)       NOT NULL DEFAULT 1,
    `notes`                 TEXT             DEFAULT NULL,
    `min_aspect_ratio`      DECIMAL(8,4)     DEFAULT NULL,
    `max_aspect_ratio`      DECIMAL(8,4)     DEFAULT NULL,
    `requires_transparency` TINYINT(1)       NOT NULL DEFAULT 0,
    `preferred_format`      VARCHAR(10)      DEFAULT NULL,
    `preferred_quality`     TINYINT UNSIGNED DEFAULT NULL,
    `variants`              JSON             DEFAULT NULL,
    `created_at`            DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_image_profiles_code` (`code`),
    KEY `idx_image_profiles_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

`allowed_extensions` and `allowed_mime_types` are stored as comma-separated strings (e.g. `"jpg,png,webp"` and `"image/jpeg,image/png,image/webp"`). They are parsed automatically by `AllowedExtensionCollection::fromDelimitedString()` and `AllowedMimeTypeCollection::fromDelimitedString()`.

The full schema file with comments is at `src/Infrastructure/Schema/image_profiles.sql`.

---

## Providers

A provider is responsible for loading an `ImageProfile` by its `code`. The core validator never touches the database directly.

### Array Provider (testing / config-based)

```php
use Maatify\ImageProfileLegacy\Provider\ArrayImageProfileProvider;

$provider = new ArrayImageProfileProvider(
    new ImageProfileEntity(code: 'product_thumbnail', /* ... */),
    new ImageProfileEntity(code: 'homepage_banner',   /* ... */),
);

$profile = $provider->findByCode('product_thumbnail'); // ?ImageProfileEntity
$all     = $provider->listAll();                       // ImageProfileCollectionDTO
$active  = $provider->listActive();                    // ImageProfileCollectionDTO
```

### PDO Provider (database-backed)

```php
use Maatify\ImageProfileLegacy\Infrastructure\Persistence\PDO\PdoImageProfileProvider;

$provider = new PdoImageProfileProvider($pdo);
// or with a custom table name:
$provider = new PdoImageProfileProvider($pdo, 'custom_image_profiles');

$profile = $provider->findByCode('product_thumbnail'); // ?ImageProfileEntity
$all     = $provider->listAll();                       // ImageProfileCollectionDTO
$active  = $provider->listActive();                    // SQL-level filter
```

`findByCode` returns `null` for a missing code — it never throws.
`findByCode` does **not** filter by `is_active` — that is the validator's responsibility.

---

## Validation Flow

```php
use Maatify\ImageProfileLegacy\Reader\NativeImageMetadataReader;
use Maatify\ImageProfileLegacy\Validator\ImageProfileValidator;

$provider  = new PdoImageProfileProvider($pdo);
$reader    = new NativeImageMetadataReader();
$validator = new ImageProfileValidator($provider, $reader);

$input = new ImageFileInputDTO(
    originalName:   'thumbnail.png',
    temporaryPath:  '/tmp/phpABC',
    clientMimeType: 'image/png',
    sizeBytes:      204800,
);

$result = $validator->validateByCode('product_thumbnail', $input);

if ($result->isValid()) {
    // proceed to storage
} else {
    foreach ($result->errors as $error) {
        echo $error->code->value . ': ' . $error->message . PHP_EOL;
    }
}
```

The validator short-circuits only on infrastructure failures (missing profile, missing/unreadable file, unreadable metadata). For rule failures (mime, extension, dimensions, size) it collects **all** errors before returning.

---

## Public Entry Point

For most consumers, use `ImageProfileValidationService` as the neutral library boundary.

```php
use Maatify\ImageProfileLegacy\Service\ImageProfileValidationService;

$service = ImageProfileValidationService::compose($provider, $reader);

$result = $service->validateByCode('product_thumbnail', $input);
```

This service intentionally exposes only profile lookup/list + validation behavior.
It does not include controller, upload, or storage orchestration.

---

## Composition Helper

Use `ImageProfileComposition` for framework-agnostic wiring guidance.

### Compose from explicit dependencies

```php
use Maatify\ImageProfileLegacy\Bootstrap\ImageProfileComposition;

$service = ImageProfileComposition::fromProvider($provider, $reader);
```

### Compose from PDO (ready-to-use path)

```php
use Maatify\ImageProfileLegacy\Bootstrap\ImageProfileComposition;

$service = ImageProfileComposition::fromPdo($pdo, 'image_profiles');
```

---

## Canonical Contract Surface

Consumers should use the library through its defined contracts/DTOs/services only.

### Validation-first read/use path

- `ImageFileInputDTO` (input)
- `ImageValidationResultDTO` (result)
- `ImageProfileValidationServiceInterface` / `ImageProfileValidationService` (entry)
- `ImageProfileProviderInterface` + `ImageMetadataReaderInterface` (replaceable dependencies)

### Canonical write path (profile management)

- `CreateImageProfileRequest` / `UpdateImageProfileRequest` (command/input DTOs)
- `ImageProfileRepositoryInterface` (write contract)
- `CreateImageProfileService`, `UpdateImageProfileService`, `ToggleImageProfileService` (write services)

### Extension processing metadata

- Validation-core profile fields remain canonical.
- Processing hints are optional extension data via `ImageProfileProcessingExtensionDTO`.
- Validation behavior does not depend on processing extension data.

Do not use loose arrays as public API and do not invent ad-hoc host-only command formats.

---

## Error Handling

### Validation errors — returned, never thrown

| Code | Meaning |
|---|---|
| `profile_not_found` | No profile exists for the given code |
| `profile_inactive` | Profile exists but is disabled |
| `file_not_found` | Temporary file path does not exist |
| `file_not_readable` | File exists but cannot be read |
| `metadata_unreadable` | Metadata extraction failed |
| `mime_not_allowed` | Detected MIME type is not in the allowed list |
| `extension_not_allowed` | Detected extension is not in the allowed list |
| `width_too_small` | Image width is below the profile minimum |
| `height_too_small` | Image height is below the profile minimum |
| `width_too_large` | Image width exceeds the profile maximum |
| `height_too_large` | Image height exceeds the profile maximum |
| `file_too_large` | File size exceeds the profile maximum |
| `aspect_ratio_too_narrow` | Image aspect ratio is below the profile minimum |
| `aspect_ratio_too_wide` | Image aspect ratio is above the profile maximum |
| `transparency_required` | Profile requires PNG/WebP transparency-capable format |

These codes are defined in `ValidationErrorCodeEnum` and will not change between minor versions.

### Exceptions — thrown for infrastructure and API misuse

| Exception | When |
|---|---|
| `ImageProfileNotFoundException` | Provider used directly and code not found |
| `InvalidImageInputException` | DTO constructed with invalid values, or upload error in adapter |
| `ImageMetadataReadException` | `finfo` / `getimagesize` failed on a file |
| `ImageProfileException` | Base class — catch this to handle all package exceptions |

---

## Adapters

Adapters convert framework-specific upload objects into `ImageFileInputDTO`. They live outside `src/` because the core validator must remain framework-agnostic. The package provides these optional adapters to help with typical workflows, but they are separate from the canonical validation path.

### Slim / PSR-7 adapter

Requires `psr/http-message` in your project.

```php
use Maatify\ImageProfileLegacy\Adapter\SlimUploadedFileAdapter;

$uploadedFile = $request->getUploadedFiles()['image'];
$input        = SlimUploadedFileAdapter::toInputDTO($uploadedFile);
```

### Native PHP `$_FILES` adapter

No external dependencies.

```php
use Maatify\ImageProfileLegacy\Adapter\NativePhpUploadAdapter;

// From a specific field name:
$input = NativePhpUploadAdapter::fromSuperGlobal('image');

// Or from an already-fetched entry:
$input = NativePhpUploadAdapter::fromFilesEntry($_FILES['image']);
```

Both adapters throw `InvalidImageInputException` for any `UPLOAD_ERR_*` code other than `UPLOAD_ERR_OK`.

---

## Storage

Storage implementations live outside `src/`. The core validator has no storage dependency. The package provides optional storage implementations to ease integration, but they operate independently of the validation core.

### DigitalOcean Spaces

Requires `aws/aws-sdk-php` in your project.

```php
use Aws\S3\S3Client;
use Maatify\ImageProfileLegacy\Storage\DoSpacesImageStorage;

$s3 = new S3Client([
    'version'     => 'latest',
    'region'      => 'fra1',
    'endpoint'    => 'https://fra1.digitaloceanspaces.com',
    'credentials' => [
        'key'    => $_ENV['DO_SPACES_KEY'],
        'secret' => $_ENV['DO_SPACES_SECRET'],
    ],
]);

$storage = new DoSpacesImageStorage(
    client:     $s3,
    bucket:     $_ENV['DO_SPACES_BUCKET'],
    cdnBaseUrl: $_ENV['DO_SPACES_CDN_URL'], // e.g. https://cdn.example.com
);
```

### Complete upload flow

```php
// 1. Adapt
$input = SlimUploadedFileAdapter::toInputDTO($request->getUploadedFiles()['image']);

// 2. Validate
$result = $validator->validateByCode('category_app_image', $input);

if (! $result->isValid()) {
    // return validation errors to the client
    return $response->withStatus(422);
}

// 3. Store
$stored = $storage->store(
    localPath:  $input->temporaryPath,
    remotePath: 'images/categories/' . uniqid() . '.webp',
);

// 4. Persist to database
// save $stored->publicUrl, $stored->remotePath, $stored->mimeType, $stored->sizeBytes
```

### StoredImageDTO fields

| Field | Type | Description |
|---|---|---|
| `publicUrl` | `string` | Full public URL (CDN or direct) |
| `remotePath` | `string` | Path inside the bucket — used for delete |
| `disk` | `string` | Backend identifier e.g. `do-spaces` |
| `sizeBytes` | `int` | File size as confirmed by the local file |
| `mimeType` | `string` | MIME type detected by `finfo` |

---

## Extension Strategy

### Processing and variants are extension scope (deferred)

Image processing primitives (resize, optimization, variant generation, preferred output hints)
are intentionally not part of the stable v1 validation entry path.
They are optional extension APIs and should not be treated as required dependencies
for core profile validation consumption.

### Adding a new provider

Implement `ImageProfileProviderInterface`:

```php
use Maatify\ImageProfileLegacy\Contract\ImageProfileProviderInterface;
use Maatify\ImageProfileLegacy\Entity\ImageProfileEntity;

final class RedisImageProfileProvider implements ImageProfileProviderInterface
{
    public function findByCode(string $code): ?ImageProfileEntity
    {
        // load from Redis, map to ImageProfileEntity
    }
}
```

### Adding a new storage backend

Implement `ImageStorageInterface`:

```php
use Maatify\ImageProfileLegacy\Storage\ImageStorageInterface;
use Maatify\ImageProfileLegacy\Storage\StoredImageDTO;

final class S3ImageStorage implements ImageStorageInterface
{
    public function store(string $localPath, string $remotePath): StoredImageDTO { /* ... */ }
    public function delete(string $remotePath): void { /* ... */ }
}
```

### Adding a new metadata reader

Implement `ImageMetadataReaderInterface` and return an `ImageMetadataDTO`.

---

## No-Array Contract

No public API method in this package returns a raw PHP array for a collection. All collections use typed, iterable DTOs:

| Collection | Type |
|---|---|
| Validation errors | `ImageValidationErrorCollectionDTO` |
| Validation warnings | `ImageValidationWarningCollectionDTO` |
| Image profiles | `ImageProfileCollectionDTO` |
| Allowed extensions | `AllowedExtensionCollection` |
| Allowed MIME types | `AllowedMimeTypeCollection` |

All collection DTOs implement `IteratorAggregate` and `JsonSerializable`.

---

## Design Rules

- The core is **profile and validation focused** — not a generic upload service.
- The database is **optional** — profiles can be loaded from arrays or any custom provider.
- The validator works with a **neutral input DTO** — no framework upload objects in `src/`.
- Validation and processing are **always separate** — no merging of concerns.
- Stable business identifiers use `code`, not fragile display labels.
- No raw arrays on the public API — all collections are typed DTOs.
- Adapters and storage live **outside** `src/` — the core validation logic has zero framework or cloud SDK dependencies.

---

## Testing and Static Analysis

```bash
composer install
./vendor/bin/phpunit                         # all suites
./vendor/bin/phpunit --testsuite Unit        # unit only
./vendor/bin/phpunit --testsuite Integration # integration only (SQLite in-memory)
./vendor/bin/phpunit --testsuite Contract    # contract only

./vendor/bin/phpstan analyse                 # level 10 (max) + strict rules
```

Integration tests require only `ext-pdo` and `ext-pdo_sqlite` — no external database needed.
Validator and Reader unit tests require `ext-gd` for creating test images.

---

## Versioning

This package follows [Semantic Versioning](https://semver.org/).

- **Major** — breaking contract changes
- **Minor** — new capabilities, non-breaking
- **Patch** — bug fixes and internal improvements

---

## ✅ Quality Status

* PHP 8.2+
* PHPStan Level 10 (max), strict rules, bleeding edge — 0 errors
* PHPUnit — 353 tests, 647 assertions (Unit, Integration, Contract)
* GitHub Actions CI validated (PHP 8.2 / 8.3 / 8.4 / 8.5, prefer-lowest and prefer-stable)
* `composer audit` — no known vulnerability advisories

---

## 🪪 License

This package is licensed under the MIT License.
See the [LICENSE](LICENSE) file for details.

---

## 👤 Author

Engineered by **Mohamed Abdulalim** ([@megyptm](https://github.com/megyptm))<br>
Backend Lead & Technical Architect<br>
[https://www.maatify.dev](https://www.maatify.dev)

---

<div align="center">

[Built with ❤️ by Maatify.dev — Unified Ecosystem for Modern PHP Libraries](https://www.maatify.dev)

</div>
