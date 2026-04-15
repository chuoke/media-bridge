# Changelog

All notable changes to `media-bridge` will be documented in this file.

## 2.0.0 - 2026-04-15

This is a full rewrite of v1. The public API, internal architecture, and Laravel integration have all changed.

### Breaking Changes

- Composer package name changed from `chuoke/unify-gallery` to `chuoke/media-bridge`.
- The package has been rebuilt around `MediaManager` and driver classes. The v1 gallery-style adapters, formatters, query param objects, and response classes have been removed.
- The core data model has changed. Results are now returned as `MediaResult` and items are returned as `MediaItem`.
- `MediaItem` fields are different from v1. The new model standardizes fields such as `source`, `source_id`, `media_type`, `url`, `thumb_url`, `variants`, `author_name`, `license`, and `display_date`.
- Media variants are now exposed explicitly through `MediaItem::$variants`. Each variant is represented as a `MediaVariant` object and serializes to an array structure.
- Pagination has changed. Results now expose `hasMore`, `page`, `perPage`, and `nextPage`. Cursor-based sources may return an opaque cursor token in `nextPage`.
- Source behavior is normalized per driver instead of sharing the old v1 query abstraction. In particular, some sources support search, some support browse only, and some expose source-specific `extras`.
- Exceptions have changed. The package now throws `MediaException`, `RequestFailedException`, and `NotImplementedException` instead of the old gallery exception types.
- Laravel integration has changed. The package now provides `Chuoke\MediaBridge\Laravel\MediaServiceProvider` and `Chuoke\MediaBridge\Laravel\Media`.
- In Laravel applications, outgoing HTTP requests now use Laravel's `Http` client automatically. Outside Laravel, the package still uses Guzzle.
- Testing has changed from API-key-driven online tests to fixture-based mocked HTTP tests. If you maintained custom tests around v1 internals, they will need to be updated.

### Migration Notes

- Update your dependency from `chuoke/unify-gallery` to `chuoke/media-bridge`.
- Replace v1 gallery manager / adapter usage with `MediaManager`.
- Replace v1 response and item handling with `MediaResult` and `MediaItem`.
- Update any direct field access to the new normalized item fields.
- If you consumed image sizes from source-specific payloads before, migrate to `MediaItem::$variants`.
- If you relied on the old pagination shape, switch to `hasMore` and `nextPage`.
- If you integrated with Laravel, update provider / facade references to the new `MediaBridge` namespace.

## 1.0.0 - 202X-XX-XX

- initial release
