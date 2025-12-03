/**
 * Background Sync Helper
 * Manages offline queue for read articles
 */

const DB_NAME = 'gheop-reader';
const DB_VERSION = 1;
const STORE_NAME = 'read-queue';
const SYNC_TAG = 'sync-read-articles';

/**
 * Queue an article to be marked as read
 * If online, marks immediately. If offline, queues for background sync.
 */
async function queueReadMark(articleId) {
  if (navigator.onLine) {
    // Online: mark immediately
    try {
      await markArticleRead(articleId);
      console.log('[Sync] Article marked as read immediately:', articleId);
      return true;
    } catch (error) {
      console.warn('[Sync] Failed to mark online, queuing:', error);
      // Fall through to queue
    }
  }

  // Offline or failed: queue for later
  try {
    await addToQueue(articleId);
    await registerBackgroundSync();
    console.log('[Sync] Article queued for background sync:', articleId);
    showSyncNotification('Article mis en file d\'attente');
    return true;
  } catch (error) {
    console.error('[Sync] Failed to queue article:', error);
    return false;
  }
}

/**
 * Mark article as read immediately
 */
async function markArticleRead(articleId) {
  const formData = new FormData();
  formData.append('id', articleId);

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
 * Add article to IndexedDB queue
 */
async function addToQueue(articleId) {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open(DB_NAME, DB_VERSION);

    request.onerror = () => reject(request.error);

    request.onupgradeneeded = (event) => {
      const db = event.target.result;
      if (!db.objectStoreNames.contains(STORE_NAME)) {
        db.createObjectStore(STORE_NAME, { keyPath: 'id' });
      }
    };

    request.onsuccess = () => {
      const db = request.result;
      const tx = db.transaction(STORE_NAME, 'readwrite');
      const store = tx.objectStore(STORE_NAME);

      const item = {
        id: articleId,
        timestamp: Date.now()
      };

      store.put(item);

      tx.oncomplete = () => {
        db.close();
        resolve();
      };

      tx.onerror = () => {
        db.close();
        reject(tx.error);
      };
    };
  });
}

/**
 * Register background sync
 */
async function registerBackgroundSync() {
  if (!('serviceWorker' in navigator) || !('sync' in self.registration)) {
    console.warn('[Sync] Background Sync not supported');
    return;
  }

  try {
    const registration = await navigator.serviceWorker.ready;
    await registration.sync.register(SYNC_TAG);
    console.log('[Sync] Background sync registered');
  } catch (error) {
    console.error('[Sync] Failed to register background sync:', error);
  }
}

/**
 * Get pending sync count
 */
async function getPendingCount() {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open(DB_NAME, DB_VERSION);

    request.onsuccess = () => {
      const db = request.result;

      if (!db.objectStoreNames.contains(STORE_NAME)) {
        db.close();
        resolve(0);
        return;
      }

      const tx = db.transaction(STORE_NAME, 'readonly');
      const store = tx.objectStore(STORE_NAME);
      const countRequest = store.count();

      countRequest.onsuccess = () => {
        db.close();
        resolve(countRequest.result);
      };

      countRequest.onerror = () => {
        db.close();
        reject(countRequest.error);
      };
    };

    request.onerror = () => reject(request.error);
  });
}

/**
 * Show sync notification to user
 */
function showSyncNotification(message) {
  // Create temporary notification
  const notification = document.createElement('div');
  notification.textContent = message;
  notification.style.cssText = `
    position: fixed;
    bottom: 20px;
    right: 20px;
    background: #2c3e50;
    color: white;
    padding: 12px 20px;
    border-radius: 4px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
    z-index: 10000;
    font-size: 14px;
    animation: slideIn 0.3s ease-out;
  `;

  document.body.appendChild(notification);

  setTimeout(() => {
    notification.style.animation = 'slideOut 0.3s ease-in';
    setTimeout(() => notification.remove(), 300);
  }, 3000);
}

/**
 * Listen for sync completion from Service Worker
 */
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.addEventListener('message', event => {
    if (event.data && event.data.type === 'SYNC_COMPLETE') {
      const { synced, total } = event.data;
      console.log(`[Sync] Sync complete: ${synced}/${total} articles`);

      if (synced > 0) {
        showSyncNotification(`${synced} article(s) synchronisé(s)`);
        // Refresh UI if needed
        if (typeof updateCounts === 'function') {
          updateCounts();
        }
      }
    }
  });
}

// Export for use in lib.js
window.BackgroundSync = {
  queueReadMark,
  getPendingCount,
  registerBackgroundSync
};
