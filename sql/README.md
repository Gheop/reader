# Database Schema

This directory contains the database schema and maintenance scripts for Gheop Reader.

## Files

- `schema.sql` - Complete database structure with tables, indexes, and triggers
- `fix_counters.sql` - Maintenance script to recalculate unread counters

## Database Structure

### Tables

- **reader_flux** - RSS/Atom feeds with unread counters per user
- **reader_item** - Feed articles/items
- **reader_user_flux** - User subscriptions to feeds (many-to-many)
- **reader_unread_cache** - Fast lookup cache for unread articles (MEMORY engine)
- **reader_user_item** - Articles marked as read by users
- **users** - User accounts

### Triggers

The database uses triggers to automatically maintain unread counters and cache:

#### On `reader_item` INSERT:
1. `cache_after_item_insert` - Adds item to cache for subscribed users only
2. `update_unread_count_after_item_insert` - Increments unread counters for subscribed users

#### On `reader_item` DELETE:
1. `update_unread_count_after_item_delete` - Decrements unread counters if not already read
2. `cache_after_item_delete` - Removes item from cache

#### On `reader_user_item` INSERT (mark as read):
1. `update_unread_count_after_read` - Decrements unread counter
2. `cache_after_read` - Removes item from cache

#### On `reader_user_item` DELETE (mark as unread):
1. `update_unread_count_after_unread` - Increments unread counter
2. `cache_after_unread` - Re-adds item to cache

### Key Design Decisions

1. **Subscription Check**: All triggers verify `reader_user_flux` to ensure users only receive items from subscribed feeds
2. **MEMORY Engine**: `reader_unread_cache` uses MEMORY engine for fast access
3. **Denormalized Counters**: `unread_count_user_1` and `unread_count_user_2` in `reader_flux` for performance

## Usage

### Initial Setup

```bash
mysql -u gheop -p gheop < schema.sql
```

### Maintenance

If counters become out of sync with the cache, run:

```bash
mysql -u gheop -p gheop < fix_counters.sql
```

This will:
1. Reset all counters to 0
2. Recalculate from `reader_unread_cache`
3. Clean orphaned cache entries (feeds not subscribed)

## Performance Indexes

- `reader_item`: Indexes on `pubdate`, `id_flux`, composite covering index for feed queries
- `reader_unread_cache`: Composite indexes on `(id_user, id_item)`, `(id_user, pubdate)`, `(id_user, id_flux, pubdate)`
- `reader_user_flux`: Unique composite index on `(id_user, id_flux)`
