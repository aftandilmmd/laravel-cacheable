# Changelog

All notable changes to `aftandilmmd/laravel-cacheable` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-05-17

### Added

- Initial release.
- `#[Cacheable]` attribute with 19 configurable parameters.
- `CacheableAspect` with hit/miss/write/forget event dispatching.
- Stampede protection via distributed locks.
- TTL jitter to mitigate thundering herd.
- Stale-while-revalidate (`refreshAhead`) with optional async refresh.
- Per-method `forget` / `forgetTags` directives for inline invalidation.
- `when` / `unless` predicate hooks for conditional caching.
- Pluggable `KeyResolver` and `ArgumentNormalizer` contracts.
- Auto-proxy registration through config.
- `Cacheable` facade with `proxy`, `forget`, `flushTags`, `keyFor` helpers.
- Full Laravel 10 / 11 / 12 / 13 support.
- PHP 8.2, 8.3, 8.4 support.
