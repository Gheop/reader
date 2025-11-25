# Performance Optimizations TODO

Generated: 2025-11-25

## Critical Priority (Do First)

### 1. ✅ Fix Memory Leak - Event Listeners
**File**: `lib.js` lines 1029-1030
**Issue**: Event listeners added on every scroll without removal
**Impact**: 10-20% memory reduction
**Effort**: Low (30 min)
**Status**: IN PROGRESS

### 2. ⏳ Fix N+1 Database Queries in Feed Updates
**File**: `up.php` lines 174-182
**Issue**: One SQL query per feed instead of single grouped query
**Impact**: 30-50% speed improvement
**Effort**: Medium (1-2 hours)
**Status**: TODO

### 3. ⏳ Multiple IntersectionObservers
**File**: `lib.js` lines 1035-1066
**Issue**: Creating multiple observers instead of reusing one
**Impact**: 15-30% memory reduction
**Effort**: Medium (1 hour)
**Status**: TODO

## High Priority (Quick Wins)

### 4. ⏳ Add Passive Scroll Listeners
**File**: `lib.js` line 1075, 1141
**Issue**: Missing `{passive: true}` flag on scroll listeners
**Impact**: 5-10% scroll performance
**Effort**: Low (15 min)
**Status**: TODO

### 5. ⏳ Add Database Index on rss Field
**File**: `sql/schema.sql`
**Issue**: Missing index for duplicate feed detection
**Impact**: 20% faster duplicate checking
**Effort**: Low (5 min)
**Code**:
```sql
ALTER TABLE reader_flux ADD INDEX idx_rss (rss(100));
```
**Status**: TODO

### 6. ⏳ Cache Layout Calculations
**File**: `lib.js` line 1025+
**Issue**: Repeated `offsetHeight` calls trigger reflows
**Impact**: 5% scroll smoothness
**Effort**: Low (30 min)
**Status**: TODO

## Medium Priority

### 7. ⏳ Use Native JSON in API
**File**: `api.php` lines 71-173
**Issue**: String concatenation instead of json_encode()
**Impact**: 10-15% payload size reduction
**Effort**: Medium (1-2 hours)
**Status**: TODO

### 8. ⏳ Shared Shadow DOM Styles
**File**: `lib.js` lines 1279-1299
**Issue**: Creating new stylesheet per article
**Impact**: 5-10% memory per article
**Effort**: Medium (1 hour)
**Code**: Use `adoptedStyleSheets` API
**Status**: TODO

### 9. ⏳ Cache YouTube Descriptions
**File**: `up.php` lines 88-130, `add_flux.php` lines 88-127
**Issue**: Individual API calls per video without caching
**Impact**: 50% fewer API calls
**Effort**: Medium (2 hours)
**Code**:
```sql
ALTER TABLE reader_item ADD COLUMN youtube_description TEXT;
```
**Status**: TODO

## Advanced (Long Term)

### 10. ⏳ Implement Service Worker
**Files**: New files needed
**Issue**: No offline support or cache strategy
**Impact**: 40% faster on revisit
**Effort**: High (8-10 hours)
**Status**: TODO

### 11. ⏳ HTTP/2 Server Push
**File**: `.htaccess` or nginx config
**Issue**: Critical resources not pushed
**Impact**: 10-15% initial load
**Effort**: Low (30 min)
**Status**: TODO

### 12. ⏳ Progressive Image Loading
**File**: `lib.js` preloadFeedArticles
**Issue**: Images not preloaded with articles
**Impact**: Perceived 20% faster
**Effort**: Medium (2 hours)
**Status**: TODO

### 13. ⏳ Merge Database Triggers
**File**: `sql/schema.sql` lines 79-120
**Issue**: Multiple triggers per item insert
**Impact**: 5-10% insert speed
**Effort**: High (4 hours)
**Status**: TODO

### 14. ⏳ Self-host Font Awesome
**File**: `index.php` line 26
**Issue**: CDN dependency for critical resource
**Impact**: 5-10% initial load
**Effort**: Low (1 hour)
**Status**: TODO

### 15. ⏳ Add Query Performance Monitoring
**Files**: `api.php`, `read.php`, `up.php`
**Issue**: No slow query detection
**Impact**: Identifies bottlenecks
**Effort**: Low (30 min)
**Code**:
```php
$start = microtime(true);
// Execute query
$duration = (microtime(true) - $start) * 1000;
if ($duration > 100) error_log("Slow query: " . $duration . "ms");
```
**Status**: TODO

## Completed

_None yet_

---

## Estimated Total Impact
- Memory: -25% to -40%
- Load time: -30% to -50%
- Scroll performance: +15% to +25%
- API response size: -15% to -20%

## Estimated Total Effort
- Critical (1-3): ~4 hours
- High (4-6): ~2 hours
- Medium (7-9): ~6 hours
- Advanced (10-15): ~20 hours
- **Total**: ~32 hours for all optimizations
