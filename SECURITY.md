# Security Policy

[![Maatify Image Profile Legacy](https://img.shields.io/badge/Maatify-IMAGE--PROFILE--LEGACY-blue?style=for-the-badge)](https://github.com/Maatify/ImageProfileLegacy)
[![Maatify Ecosystem](https://img.shields.io/badge/Maatify-Ecosystem-9C27B0?style=for-the-badge)](https://github.com/Maatify)

## Supported Versions

**Maatify Image Profile Legacy** has not yet tagged a `1.0.0` release — only the
`main` branch exists. Security fixes are applied there.

| Version | Supported |
|---------|-----------|
| `main` (unreleased) | ✅ Yes |

Once a `1.0.0` release is tagged, this table will be updated to track
supported major versions per [Semantic Versioning](https://semver.org/).

---

## Reporting a Vulnerability

If you discover a security vulnerability in this package, please report it responsibly.

Please **do not open a public GitHub issue** for security vulnerabilities.

Instead, report it privately via email:

[support@maatify.dev](mailto:support@maatify.dev)

Please include as much detail as possible, including:

* A clear description of the vulnerability
* Affected package version or commit SHA
* Steps to reproduce the issue
* Expected and actual behavior
* Potential impact
* Any relevant logs, stack traces, schema details, or example payloads
* Suggested mitigation, if available

We will review the report and work to address confirmed security issues appropriately.

---

## Security Scope

This package is a framework-agnostic standalone Composer package for
defining and validating image-upload profiles.

Security reports are especially relevant when they involve:

* Validation rules (MIME type, extension, dimensions, size, aspect ratio,
  transparency) that can be bypassed to admit a disallowed file
* Unsafe handling of untrusted file metadata (`ImageFileInputDTO`,
  `NativeImageMetadataReader`) — e.g. trusting client-supplied MIME type
  over detected metadata
* SQL injection or unsafe query construction in `PdoImageProfileProvider`
  or `PdoImageProfileRepository`
* Path traversal via `originalName` or temporary file paths in
  `NativePhpUploadAdapter` / `SlimUploadedFileAdapter`
* Exposure of sensitive data through DTO serialization
* Unsafe handling of storage credentials or unsafe remote-path
  construction in `DoSpacesImageStorage`
* Dependency-related vulnerabilities affecting package runtime behavior

---

## Out of Scope

The following areas are generally outside the security scope of this package unless the issue is caused directly by this package:

* Host application authentication or authorization
* Host application routing, controllers, middleware, or UI
* Host application database credentials or PDO configuration
* Cloud storage account configuration (e.g. DigitalOcean Spaces / AWS
  credentials, bucket policies) — the package only consumes a client you provide
* Infrastructure, server, firewall, or deployment misconfiguration
* Incorrect permissions applied by the consuming application
* Manual modification of database tables outside the package contract
* Image processing/resizing correctness bugs with no security impact
  (report these as regular bugs, not vulnerabilities)

Host applications are responsible for securely configuring their own runtime, database connection, access control, and operational environment.

---

## Disclosure Policy

Once a vulnerability is confirmed:

1. The issue will be reviewed and triaged.
2. A fix will be prepared and tested.
3. A patched version will be released when appropriate.
4. A security advisory may be published if the vulnerability affects released versions.

We appreciate responsible disclosure that helps keep the Maatify ecosystem safe.
