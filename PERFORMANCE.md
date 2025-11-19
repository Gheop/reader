# Feed Update Performance Improvements

## Overview

This document explains the parallel feed update system implemented in `up_parallel.php`.

## Problem Statement

The original `up.php` had several performance bottlenecks:

1. **Memory Issues**: Loaded all feeds into memory before processing
2. **No Concurrency Control**: Could overwhelm server with too many simultaneous connections
3. **Sequential Processing**: Parsed and stored articles one feed at a time
4. **No Prioritization**: Updated feeds randomly instead of prioritizing oldest
5. **Poor Error Handling**: One failure could affect batch processing

## Solution: Batch Processing with Controlled Concurrency

### Key Features

#### 1. Batch Processing
- Processes feeds in configurable batches (default: 50 feeds)
- Reduces memory footprint
- Allows progress tracking
- Enables incremental completion

#### 2. Concurrency Control
- Limits parallel HTTP requests (default: 20 simultaneous)
- Prevents server overload
- Uses `curl_multi` with `CURLMOPT_MAX_TOTAL_CONNECTIONS`
- Balances speed and resource usage

#### 3. Priority-Based Updates
- Orders feeds by last update timestamp (`ORDER BY F.update ASC`)
- Ensures frequently-updated feeds get priority
- Distributes load evenly over time

#### 4. Bulk Database Operations
- Fetches all feed metadata in one query per batch
- Reduces database roundtrips
- Improves overall throughput

#### 5. Comprehensive Statistics
- Real-time progress reporting
- Error tracking per batch
- Performance metrics (feeds/sec)
- Total articles added

### Configuration

```php
$BATCH_SIZE = 50;           // Feeds per batch
$MAX_CONCURRENT = 20;       // Parallel downloads
$TIMEOUT = 30;              // Feed timeout (seconds)
$CONNECT_TIMEOUT = 10;      // Connection timeout (seconds)
```

### Usage

#### Update All Feeds
```bash
php up_parallel.php
```

#### Update Specific Feed
```bash
php up_parallel.php?id=123
```

#### Update Limited Number
```bash
php up_parallel.php?limit=100
```

#### CLI Mode (for cron)
```bash
php /www/reader/up_parallel.php
```

### Performance Comparison

| Metric | Original `up.php` | New `up_parallel.php` |
|--------|-------------------|----------------------|
| Memory usage | High (all feeds) | Low (batch-based) |
| Concurrency control | None | Configurable limit |
| Error resilience | Poor | Excellent |
| Progress tracking | Minimal | Detailed stats |
| Priority | Random | Oldest first |
| Blocking | Full session lock | Session released |

### Output Example

```
=== Parallel Feed Update ===
Batch size: 50 | Concurrency: 20

Total feeds to update: 321

--- Batch 1 (50 feeds) ---
Feed 777: Korben... HTTP 403
Feed 1025: Bitcoin.fr... HTTP 409
Feed 552: RaspberryPi... +2
Feed 418: HandMade... +5
...
Batch complete: 45 processed, 127 new articles, 5 errors

--- Batch 2 (50 feeds) ---
...

=== Update Complete ===
Duration: 45.32s
Total feeds: 321
Processed: 312
New articles: 1,243
Errors: 9
Batches: 7
Avg speed: 6.88 feeds/sec
```

### Error Handling

The system handles various error conditions:

- **HTTP errors** (403, 404, 500, etc.): Logged and skipped
- **Invalid XML**: Logged with debug info if enabled
- **Connection timeouts**: Configurable per-feed
- **Database errors**: Gracefully handled, continue processing
- **Collation issues**: Uses `CAST` for cross-charset matching

### Future Improvements

1. **Async YouTube API**: Non-blocking description fetching
2. **Database Connection Pooling**: Reuse connections across batches
3. **Distributed Processing**: Multi-server support
4. **Adaptive Timeouts**: Learn optimal timeouts per feed
5. **Failure Retry Logic**: Exponential backoff for transient errors
6. **Webhook Notifications**: Alert on completion/errors

### Technical Details

#### Session Management
- Releases PHP session lock immediately after auth (`session_write_close()`)
- Prevents blocking other user requests during long updates

#### Database Optimizations
- Prepared statements for all queries
- Bulk metadata fetch per batch
- Index-friendly queries (uses `id_flux` index)

#### Memory Management
- Explicit `gc_collect_cycles()` between batches
- Limits curl handles to batch size
- Cleans up resources after each feed

### Monitoring

Watch real-time progress:
```bash
watch -n 1 'ps aux | grep up_parallel'
```

Check database load:
```sql
SHOW PROCESSLIST;
```

### Migration Path

1. Test `up_parallel.php` alongside existing `up.php`
2. Compare performance and error rates
3. Gradually migrate cron jobs to new script
4. Monitor for issues
5. Eventually deprecate old `up.php`

## See Also

- `up.php` - Original sequential update script
- `sql/schema.sql` - Database schema with triggers
- `sql/fix_counters.sql` - Counter repair utility
