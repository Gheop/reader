/**
 * Service Worker for Gheop Reader
 * Provides offline support and fast loading through intelligent caching
 *
 * Cache Strategy:
 * - Static assets (CSS/JS/fonts): Cache-first with version control
 * - API calls (menu/articles): Network-first with cache fallback
 * - Images: Cache-first with background update
 */

const VERSION = '1.2.0';
const CACHE_NAME = `gheop-reader-v${VERSION}`;
const CACHE_STATIC = `${CACHE_NAME}-static`;
const CACHE_API = `${CACHE_NAME}-api`;
const CACHE_IMAGES = `${CACHE_NAME}-images`;

// Assets to cache on install
const STATIC_ASSETS = [
  '/',
  '/index.php',
  '/manifest.json',
  '/lib.min.js',
  '/favico.min.js',
  '/background-sync.js',
  '/themes/common.min.css',
  '/themes/light.min.css',
  '/themes/dark.min.css',
  '/themes/adaptive.min.css',
  '/themes/adaptive-smooth.min.css',
  '/themes/modern.min.css',
  '/fontawesome/css/all.min.css',
  '/fontawesome/webfonts/fa-solid-900.woff2',
  '/favicon.png',
  '/icon-192.png',
  '/icon-512.png',
  '/apple-touch-icon.png',
  '/icon.svg'
];

// API endpoints to cache
const API_ENDPOINTS = [
  '/api.php',
  '/menu.php'
];

// Maximum cache sizes
const MAX_API_CACHE = 50; // Keep 50 API responses
const MAX_IMAGE_CACHE = 100; // Keep 100 images

/**
 * Install Event: Cache static assets
 */
self.addEventListener('install', event => {
  console.log('[SW] Installing Service Worker v' + VERSION);

  event.waitUntil(
    caches.open(CACHE_STATIC)
      .then(cache => {
        console.log('[SW] Caching static assets');
        return cache.addAll(STATIC_ASSETS);
      })
      .then(() => {
        console.log('[SW] Static assets cached, skipping waiting');
        return self.skipWaiting(); // Activate immediately
      })
      .catch(err => {
        console.error('[SW] Failed to cache static assets:', err);
      })
  );
});

/**
 * Activate Event: Clean old caches
 */
self.addEventListener('activate', event => {
  console.log('[SW] Activating Service Worker v' + VERSION);

  event.waitUntil(
    caches.keys()
      .then(cacheNames => {
        return Promise.all(
          cacheNames
            .filter(name => name.startsWith('gheop-reader-') && name !== CACHE_NAME)
            .map(name => {
              console.log('[SW] Deleting old cache:', name);
              return caches.delete(name);
            })
        );
      })
      .then(() => {
        console.log('[SW] Old caches cleaned, claiming clients');
        return self.clients.claim(); // Take control immediately
      })
  );
});

/**
 * Fetch Event: Intelligent routing based on request type
 */
self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);

  // Skip non-GET requests
  if (event.request.method !== 'GET') {
    return;
  }

  // IMPORTANT: Skip SSE (Server-Sent Events) - EventSource doesn't work through SW
  if (url.pathname.includes('sse.php')) {
    return; // Let browser handle SSE directly
  }

  // Skip read.php (POST requests for marking as read)
  if (url.pathname.includes('read.php')) {
    return; // Let browser handle POST directly
  }

  // Route based on request type
  if (isStaticAsset(url)) {
    event.respondWith(cacheFirstStrategy(event.request, CACHE_STATIC));
  } else if (isAPICall(url)) {
    event.respondWith(networkFirstStrategy(event.request, CACHE_API));
  } else if (isImage(url)) {
    event.respondWith(cacheFirstStrategy(event.request, CACHE_IMAGES));
  } else {
    // Default: try network, fallback to cache
    event.respondWith(networkFirstStrategy(event.request, CACHE_STATIC));
  }
});

/**
 * Check if URL is a static asset
 */
function isStaticAsset(url) {
  return url.pathname.match(/\.(js|css|woff2?|ttf|png|ico)$/) ||
         STATIC_ASSETS.some(asset => url.pathname === asset);
}

/**
 * Check if URL is an API call
 */
function isAPICall(url) {
  return url.pathname.match(/\/(api|menu|read|favicon)\.php/);
}

/**
 * Check if URL is an image
 */
function isImage(url) {
  return url.pathname.match(/\.(jpg|jpeg|gif|png|webp|svg)$/);
}

/**
 * Cache-First Strategy: Serve from cache, update in background
 */
async function cacheFirstStrategy(request, cacheName) {
  const cache = await caches.open(cacheName);
  const cached = await cache.match(request);

  if (cached) {
    console.log('[SW] Cache hit:', request.url);

    // Update cache in background (stale-while-revalidate)
    fetch(request)
      .then(response => {
        if (response && response.status === 200) {
          cache.put(request, response.clone());
        }
      })
      .catch(() => {}); // Ignore network errors in background

    return cached;
  }

  // Not in cache, fetch from network
  console.log('[SW] Cache miss, fetching:', request.url);
  try {
    const response = await fetch(request);
    if (response && response.status === 200) {
      cache.put(request, response.clone());
    }
    return response;
  } catch (err) {
    console.error('[SW] Fetch failed:', request.url, err);
    return new Response('Offline', { status: 503, statusText: 'Service Unavailable' });
  }
}

/**
 * Network-First Strategy: Try network, fallback to cache
 */
async function networkFirstStrategy(request, cacheName) {
  const cache = await caches.open(cacheName);

  try {
    console.log('[SW] Network first, fetching:', request.url);
    const response = await fetch(request);

    if (response && response.status === 200) {
      // Clone response before caching (can only read once)
      cache.put(request, response.clone());

      // Limit cache size
      await limitCacheSize(cacheName, MAX_API_CACHE);
    }

    return response;
  } catch (err) {
    console.log('[SW] Network failed, trying cache:', request.url);
    const cached = await cache.match(request);

    if (cached) {
      console.log('[SW] Serving stale cache:', request.url);
      return cached;
    }

    // No cache available
    console.error('[SW] No cache available for:', request.url);
    return new Response(
      JSON.stringify({ error: 'Offline', message: 'No cached data available' }),
      {
        status: 503,
        statusText: 'Service Unavailable',
        headers: { 'Content-Type': 'application/json' }
      }
    );
  }
}

/**
 * Limit cache size by removing oldest entries
 */
async function limitCacheSize(cacheName, maxItems) {
  const cache = await caches.open(cacheName);
  const keys = await cache.keys();

  if (keys.length > maxItems) {
    // Remove oldest entries (FIFO)
    const toDelete = keys.length - maxItems;
    for (let i = 0; i < toDelete; i++) {
      await cache.delete(keys[i]);
    }
    console.log('[SW] Cleaned cache, removed', toDelete, 'old entries from', cacheName);
  }
}

/**
 * Background Sync: Queue offline read marks for later sync
 */
const SYNC_TAG_READ = 'sync-read-articles';
const READ_QUEUE_STORE = 'read-queue';

self.addEventListener('sync', event => {
  console.log('[SW] Sync event:', event.tag);

  if (event.tag === SYNC_TAG_READ) {
    event.waitUntil(syncReadArticles());
  }
});

/**
 * Sync queued read marks to server
 */
async function syncReadArticles() {
  console.log('[SW] Starting background sync for read articles');

  try {
    // Get queued read marks from IndexedDB
    const queue = await getReadQueue();

    if (queue.length === 0) {
      console.log('[SW] No articles to sync');
      return;
    }

    console.log(`[SW] Syncing ${queue.length} read articles`);

    // Try to sync each queued item
    const results = await Promise.allSettled(
      queue.map(item => syncReadMark(item))
    );

    // Count successes
    const succeeded = results.filter(r => r.status === 'fulfilled').length;
    console.log(`[SW] Sync complete: ${succeeded}/${queue.length} successful`);

    // Clear successfully synced items
    if (succeeded > 0) {
      await clearSyncedItems(queue.slice(0, succeeded));
    }

    // Notify main thread
    const clients = await self.clients.matchAll();
    clients.forEach(client => {
      client.postMessage({
        type: 'SYNC_COMPLETE',
        synced: succeeded,
        total: queue.length
      });
    });

  } catch (error) {
    console.error('[SW] Background sync failed:', error);
    throw error; // Retry sync later
  }
}

/**
 * Sync a single read mark to server
 */
async function syncReadMark(item) {
  const formData = new FormData();
  formData.append('id', item.id);

  const response = await fetch('/read.php', {
    method: 'POST',
    body: formData
  });

  if (!response.ok) {
    throw new Error(`Server returned ${response.status}`);
  }

  return response;
}

/**
 * Get read queue from IndexedDB
 */
async function getReadQueue() {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open('gheop-reader', 1);

    request.onerror = () => reject(request.error);

    request.onupgradeneeded = (event) => {
      const db = event.target.result;
      if (!db.objectStoreNames.contains(READ_QUEUE_STORE)) {
        db.createObjectStore(READ_QUEUE_STORE, { keyPath: 'id' });
      }
    };

    request.onsuccess = () => {
      const db = request.result;

      if (!db.objectStoreNames.contains(READ_QUEUE_STORE)) {
        resolve([]);
        db.close();
        return;
      }

      const tx = db.transaction(READ_QUEUE_STORE, 'readonly');
      const store = tx.objectStore(READ_QUEUE_STORE);
      const getAllRequest = store.getAll();

      getAllRequest.onsuccess = () => {
        resolve(getAllRequest.result);
        db.close();
      };

      getAllRequest.onerror = () => {
        reject(getAllRequest.error);
        db.close();
      };
    };
  });
}

/**
 * Clear synced items from queue
 */
async function clearSyncedItems(items) {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open('gheop-reader', 1);

    request.onsuccess = () => {
      const db = request.result;
      const tx = db.transaction(READ_QUEUE_STORE, 'readwrite');
      const store = tx.objectStore(READ_QUEUE_STORE);

      items.forEach(item => store.delete(item.id));

      tx.oncomplete = () => {
        db.close();
        resolve();
      };

      tx.onerror = () => {
        db.close();
        reject(tx.error);
      };
    };

    request.onerror = () => reject(request.error);
  });
}

/**
 * Message handler for cache control from main thread
 */
self.addEventListener('message', event => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    console.log('[SW] Received SKIP_WAITING message');
    self.skipWaiting();
  }

  if (event.data && event.data.type === 'CLEAR_CACHE') {
    console.log('[SW] Received CLEAR_CACHE message');
    event.waitUntil(
      caches.keys()
        .then(names => Promise.all(names.map(name => caches.delete(name))))
        .then(() => {
          console.log('[SW] All caches cleared');
          event.ports[0].postMessage({ success: true });
        })
    );
  }
});
