# Changelog

All notable changes to this project will be documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/) and this project adheres to [Semantic Versioning](https://semver.org/).

## [1.1.0] - 2026-08-11

### Added
- Added custom database table `tdem_error_log` for grouped exception logging.
- Added high-priority `ExceptionSubscriber` to intercept unhandled PHP exceptions before they render customer-facing error screens.
- Added `ErrorLoggerService` with integrated spike threshold evaluation and cooldown guards.
- Added `SendDailySummaryTask` Scheduled Task (cron) executing every 24 hours to dispatch grouped HTML error reports.
- Added self-cleaning database routine to delete records older than 30 days automatically.
- Added validation CLI developer utility `topdata:error-monitor:test` to test error reporting flow natively.

## [1.0.0] - 2026-08-11

### Added
- Initial release