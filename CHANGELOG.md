# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

## [v2.1.1] - 2026-08-06

### Added

- GitHub Actions CI workflow running the test suite on PHP 8.3/8.4 × Laravel 12/13, with actions pinned to full commit SHAs
- Dependabot configuration (github-actions, composer, npm) with a 7-day update cooldown
- Security policy (SECURITY.md) with private vulnerability reporting instructions

### Changed

- Widened dev dependencies (orchestra/testbench ^10|^11, Pest ^3|^4) so the test suite installs on both Laravel 12 and 13

## [v2.1.0] - 2026-03-18

### Added

- Laravel 13 support (illuminate/contracts ^13.0)

## [v2.0.0] - 2026-03-04

### Changed

- Upgraded to v2.x targeting Filament 5, Laravel 12, Livewire 4
