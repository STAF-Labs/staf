# ADR 0001: Content Interaction Events

## Status

Accepted

## Context

STAF needs to track streaming content interactions that do not have their own long-lived state or business workflow. This ADR covers only:

- `view`
- `share`
- `download`

These actions need reliable history for analytics and deduplication, while the product UI also needs fast counters for sorting and display.

Writing only counters loses the event history. Reading counters directly from a raw event log is too expensive for common UI queries. Therefore, raw events and read-optimized counters must be separate.

## Decision

Use raw interaction events as the source of history and store aggregate counters as projections.

Raw events are the source of truth for `view`, `share`, and `download`. Aggregate counters are not the source of truth; they are derived values optimized for reads.

Core content tables must not be the source of truth for views, shares, or downloads. If a future performance need appears, hot counters may be denormalized into content tables, but only as cached projection values that can be rebuilt from events.

Use these tables:

`content_interaction_events`

- `id`
- `subject_type`
- `subject_id`
- `action`: `view`, `share`, `download`
- `user_id` nullable
- `session_id` nullable
- `ip_hash` nullable
- `user_agent_hash` nullable
- `metadata` JSON nullable
- `occurred_at`
- `created_at`

`content_interaction_counters`

- `subject_type`
- `subject_id`
- `action`
- `value`
- `updated_at`

Add a unique index to counters:

```sql
unique(subject_type, subject_id, action)
```

Use Laravel morph map values for `subject_type`. Do not store full PHP class names in the database.

## Deduplication

Deduplication rules are action-specific.

- `view`: count one view per user or session and content subject within a time window, for example 30 minutes.
- `share`: count according to product rules. If the client provides a client id or idempotency key, do not write the same action twice.
- `download`: count an authorized or completed download request. If downloads use signed URLs or redirects to object storage, the application records the intent before issuing the URL. CDN or storage logs may be used later for exact reconciliation.

Use database unique constraints for absolute rules. Use Redis/cache locks, TTL keys, or idempotency keys for time-windowed rules.

## Projection and consistency

Counters should be updated asynchronously through Laravel queues when eventual consistency is acceptable.

The UI must treat `content_interaction_counters` as eventually consistent. A raw event may exist before the counter reflects it.

Projection handlers must be idempotent. Reprocessing the same event must not increment the counter twice. Store enough projection state or use a deterministic event processing key so retries, queue redelivery, or manual replays are safe.

The system must support full counter rebuilds from `content_interaction_events`. Rebuild jobs should truncate or replace projection rows and recalculate counters from the event log or rollup tables.

## Implementation Notes

The event write should happen at the application boundary where STAF has already authorized or accepted the action.

For `view`, write the event after the application decides that the content is visible to the visitor. Bot filtering may happen before event creation or during projection, depending on how much raw traffic history is useful.

For `download`, write the event before returning a signed URL or object storage redirect. This records application-level intent. Exact transfer completion can be reconciled separately from CDN or storage logs if the product needs that accuracy.

For `share`, write the event when the application accepts the share action or generates the share target. If the frontend may retry the request, require an idempotency key.

The `metadata` column is not a free-form dump. Its structure must be limited by `action` and validated through DTOs, value objects, or dedicated event classes. Examples:

- `view`: referrer category, viewport context, source surface.
- `share`: share target, client id, idempotency key.
- `download`: media id, release id, file variant.

## Privacy

Do not store raw IP addresses.

Store IP fingerprints with HMAC, for example:

```php
hash_hmac('sha256', $ip, $secret)
```

Do not use a plain hash for IP values. The HMAC secret must come from configuration, not from request data.

Store user-agent only as a hash. Do not store full user-agent strings unless a separate security or legal requirement explicitly requires it.

Retention rules must be defined separately for raw events and aggregate counters. Aggregate counters can live longer than raw events that contain identifying fingerprints.

## Retention

Recommended default retention:

- `view` events: limited retention, for example 90 days.
- `download` and `share` events: longer retention when analytics or audit needs it.
- Aggregate counters: indefinite retention.

Old events may be compacted into daily or monthly rollup tables before deletion. Rollups should preserve analytics value without keeping identifying fingerprints longer than necessary.

Retention jobs should delete or compact old raw events in batches to avoid long PostgreSQL locks and excessive queue or database load.

## Consequences

Benefits:

- Counters can be rebuilt from raw events.
- UI reads stay fast through aggregate counters.
- Analytics can evolve without changing core content tables.
- Deduplication rules remain action-specific and testable.

Costs:

- More tables and queue processing.
- Event volume can grow quickly, especially for views.
- Eventual consistency must be acceptable for counters.
- Projection idempotency and retention jobs need explicit implementation.

## Out of Scope

This ADR does not cover stateful interactions or domain workflows.

Out of scope:

- `like`
- `follow`
- `bookmark`
- `report`
- `comment`
- `rating`
- other domain entities

Likes, follows, and bookmarks must be designed as stateful flows with their own tables and unique constraints. Reports must be designed as a moderation flow with status, reason, processing, and moderator assignment. Comments, ratings, and other domain entities require their own models and ADRs or flow descriptions.
