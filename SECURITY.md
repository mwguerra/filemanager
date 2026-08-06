# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 2.x     | :white_check_mark: |
| < 2.0   | :x:                |

## Reporting a Vulnerability

**Please do not report security vulnerabilities through public GitHub issues.**

Report vulnerabilities privately via GitHub's private vulnerability reporting:

1. Go to the [Security tab](https://github.com/mwguerra/filemanager/security) of this repository.
2. Click **Report a vulnerability** and fill in the advisory form.

You can expect an acknowledgement within 72 hours and a status update within 7 days. Please include a description of the issue, steps to reproduce, affected versions, and any known mitigations.

## Scope

This package manages file uploads, storage, and delivery for Laravel and Filament applications. Reports are especially welcome for issues in:

- File streaming and download endpoints (signed URL generation and validation, expiry enforcement)
- Upload validation (MIME type checks, blocked extensions, filename sanitization)
- Disk access controls (`public_access_disks`) and Laravel Policy authorization paths
- Path handling when resolving files on local, S3, or MinIO storage (path traversal)
- Response headers for user-supplied content (e.g. `X-Content-Type-Options`, `Content-Disposition`)

Configuration guidance for hardening deployments is documented in the Security section of the [README](README.md).
