var kona = 0,
search_value = '',
search_active = 0,
search_focus = 0,
requestTimer = false,
loadmore,
nb_title = 0,
id = 'all',
unr = 0,
d,
m,
//M = [],
varscroll = 0,
loadinprogress = 0,
zhr,
totalItems = 0,
readItems =0;
online = true;
notif = false;
var Now;
var favicon_badge;
var rtf;
var syncInterval = null;
var cacheVersion = 'v2';
var locallyModifiedArticles = {}; // Track articles manually marked as unread
var eventSource = null; // SSE connection
const hasSupportLoading = 'loading' in HTMLImageElement.prototype;

// ============================================================================
// SERVICE WORKER REGISTRATION
// ============================================================================
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js')
      .then(registration => {
        console.log('[App] Service Worker registered:', registration.scope);

        // Check for updates every hour
        setInterval(() => {
          registration.update();
        }, 60 * 60 * 1000);

        // Handle updates
        registration.addEventListener('updatefound', () => {
          const newWorker = registration.installing;
          console.log('[App] New Service Worker found');

          newWorker.addEventListener('statechange', () => {
            if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
              console.log('[App] New Service Worker installed, update available');

              // Show update notification to user
              showUpdateNotification(newWorker);
            }
          });
        });
      })
      .catch(err => {
        console.error('[App] Service Worker registration failed:', err);
      });

    // Listen for controller change (new SW activated)
    navigator.serviceWorker.addEventListener('controllerchange', () => {
      console.log('[App] Service Worker controller changed, reloading page');
      window.location.reload();
    });
  });
}

// Show update notification
function showUpdateNotification(worker) {
  if (!confirm('Une nouvelle version de Gheop Reader est disponible. Voulez-vous la charger maintenant ?')) {
    return;
  }

  // Tell the new SW to skip waiting
  worker.postMessage({ type: 'SKIP_WAITING' });
}

// Utility: Clear all caches (for debugging)
window.clearServiceWorkerCache = async function() {
  if (!navigator.serviceWorker || !navigator.serviceWorker.controller) {
    console.log('[App] No active Service Worker');
    return;
  }

  const messageChannel = new MessageChannel();

  return new Promise((resolve, reject) => {
    messageChannel.port1.onmessage = (event) => {
      if (event.data.success) {
        console.log('[App] Service Worker cache cleared');
        resolve();
      } else {
        reject(new Error('Failed to clear cache'));
      }
    };

    navigator.serviceWorker.controller.postMessage(
      { type: 'CLEAR_CACHE' },
      [messageChannel.port2]
    );
  });
};

// ============================================================================
// ONLINE/OFFLINE STATUS INDICATOR
// ============================================================================
function updateOnlineStatus() {
  const indicator = document.getElementById('offline-indicator');
  if (!indicator) return;

  if (navigator.onLine) {
    indicator.style.display = 'none';
    console.log('[App] Online');
  } else {
    indicator.style.display = 'inline-block';
    console.log('[App] Offline');
  }
}

// Update status on page load
window.addEventListener('load', updateOnlineStatus);

// Listen for online/offline events
window.addEventListener('online', updateOnlineStatus);
window.addEventListener('offline', updateOnlineStatus);

if(! /iPad|iPhone|iPod/.test(navigator.platform)) {
	const locale = navigator.language;
 	rtf = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' });
}
const D = document;
let DM = null;  // Will be initialized in i() when DOM is ready
var cptReadArticle = 0;
var imageObserver;

// Inactivity reload removed - SSE handles reconnections automatically

function $(i) {
  return D.getElementById(i);
}

// ============================================================================
// LOCALSTORAGE CACHE MANAGEMENT
// ============================================================================

function saveToCache(data) {
  try {
    localStorage.setItem('reader_cache_' + cacheVersion, JSON.stringify(data));
    localStorage.setItem('reader_cache_timestamp', Date.now());
  } catch (e) {
    console.error('Failed to save to localStorage:', e);
  }
}

function loadFromCache() {
  try {
    const cached = localStorage.getItem('reader_cache_' + cacheVersion);
    if (cached) {
      return JSON.parse(cached);
    }
  } catch (e) {
    console.error('Failed to load from localStorage:', e);
  }
  return null;
}

function getCacheAge() {
  const timestamp = localStorage.getItem('reader_cache_timestamp');
  if (!timestamp) return Infinity;
  return Date.now() - parseInt(timestamp);
}

function clearCache() {
  try {
    localStorage.removeItem('reader_cache_' + cacheVersion);
    localStorage.removeItem('reader_cache_timestamp');
  } catch (e) {
    console.error('Failed to clear cache:', e);
  }
}

// ============================================================================
// API COMBINED LOADING (menu + articles)
// ============================================================================

function loadData(feedId, useCache = true) {
  // Early return if DM doesn't exist (e.g., on login page)
  if (!DM) {
    console.log('loadData: DM not found, skipping');
    return;
  }

  // Clear display before loading to prevent flash of old content
  DM.innerHTML = '';

  if (useCache) {
    const cached = loadFromCache();
    if (cached && cached.menu && cached.articles) {
      console.log('Loading from cache (age: ' + Math.round(getCacheAge()/1000) + 's)');
      renderMenu(cached.menu);
      // Use current global 'id' if feedId is 'all' (reload scenario)
      // This maintains the current feed view when reloading all data
      var displayFeed = (feedId === 'all' && id !== 'all') ? id : (feedId || 'all');
      renderArticles(cached.articles, displayFeed);
      setTimeout(() => fetchAndUpdateData(feedId), 100);
      return;
    }
  }
  console.log('No cache available, loading from server');
  fetchAndUpdateData(feedId);
}

function fetchAndUpdateData(feedId) {
  const url = feedId && feedId !== 'all' ? 'api.php?id=' + feedId : 'api.php';
  console.log('Fetching data from:', url);

  fetch(url)
    .then(response => {
      console.log('Response status:', response.status);
      if (!response.ok) {
        throw new Error('HTTP error ' + response.status);
      }
      return response.json();
    })
    .then(data => {
      console.log('Data received:', data);
      if (data.menu && data.articles) {
        console.log('Menu items:', Object.keys(data.menu).length);
        console.log('Articles:', Object.keys(data.articles).length);

        // Only save to cache if we loaded ALL articles (not filtered by feed)
        // Otherwise we'd overwrite the cache with incomplete data
        if (!feedId || feedId === 'all') {
          saveToCache(data);
        } else {
          console.log('Skipping cache save for filtered feed', feedId);
        }

        renderMenu(data.menu);
        // Use current global 'id' if feedId is 'all' (reload scenario)
        var displayFeed = (feedId === 'all' && id !== 'all') ? id : (feedId || 'all');
        renderArticles(data.articles, displayFeed, true); // true = from API
      } else {
        console.error('Invalid data structure:', data);
      }
    })
    .catch(err => {
      console.error('Failed to fetch data:', err);
      if (typeof affError !== 'undefined') {
        affError('Erreur de chargement des données');
      }
    });
}

// Background preloading of feed-specific articles to avoid pagination during scrolling
let preloadedFeeds = {}; // Track which feeds have been preloaded
let isPreloading = false; // Prevent multiple concurrent preloads

function preloadFeedArticles(feedId) {
  // Don't preload if already done or in progress
  if (preloadedFeeds[feedId] || isPreloading) {
    console.log('Preload skipped for feed', feedId, '- already preloaded:', !!preloadedFeeds[feedId], 'isPreloading:', isPreloading);
    return;
  }

  // Don't preload if this feed has no articles
  if (!m || !m[feedId] || m[feedId].n === 0) {
    console.log('Preload skipped for feed', feedId, '- no unread articles');
    return;
  }

  console.log('=== PRELOADING feed', feedId, 'with', m[feedId].n, 'articles ===');
  isPreloading = true;

  // Request up to 200 articles for this feed (API max)
  const url = 'api.php?id=' + feedId + '&nb=200';

  fetch(url, {
    credentials: 'same-origin'
  })
    .then(response => {
      if (!response.ok) {
        throw new Error('HTTP error ' + response.status);
      }
      return response.json();
    })
    .then(data => {
      if (data.articles) {
        console.log('PRELOAD: Received', Object.keys(data.articles).length, 'articles for feed', feedId);

        // Merge new articles into existing data
        if (!d) d = {};

        let newCount = 0;
        for (let articleId in data.articles) {
          if (!d[articleId]) {
            // New article not in memory yet
            d[articleId] = data.articles[articleId];
            d[articleId].r = 1; // Unread
            d[articleId].readblock = 0;
            newCount++;
          }
        }

        console.log('PRELOAD: Added', newCount, 'new articles to memory for feed', feedId);
        preloadedFeeds[feedId] = true;

        // Update cache with expanded data
        if (m) {
          saveToCache({menu: m, articles: d, timestamp: Date.now()});
        }
      }
    })
    .catch(err => {
      console.error('Preload failed for feed', feedId, ':', err);
    })
    .finally(() => {
      isPreloading = false;
    });
}

function renderMenu(menuData) {
  // Save old counters for comparison
  var oldCounters = {};
  var oldFeedsVisible = {};
  var locallyModified = {};
  if (m) {
    for(var i in m) {
      oldCounters[i] = m[i].n || 0;
      oldFeedsVisible[i] = m[i].n > 0;
      // Marquer les compteurs modifiés localement (batch en attente)
      if (readBatchQueue.length > 0 && oldCounters[i] !== undefined) {
        locallyModified[i] = true;
      }
    }
  }

  nb_title = 0;

  // Preserve currently selected feed info if it's not in new menu data
  // (happens when all articles are read but feed is still selected)
  var currentFeedData = null;
  if (id !== 'all' && m && m[id] && !menuData[id]) {
    currentFeedData = m[id];
    console.log('Preserving currently selected feed', id, 'in menu');
  }

  // Pour les flux modifiés localement, garder les compteurs locaux si plus petits
  // IMPORTANT: Only keep local counters if there's an ACTIVE batch queue (not based on stale cache)
  for(var i in menuData) {
    if (m && m[i] && locallyModified[i] && m[i].n < menuData[i].n) {
      console.log('Keeping local counter for feed', i, '- Local:', m[i].n, 'Server:', menuData[i].n);
      menuData[i].n = m[i].n;
      menuData[i].locallyModified = true; // Garder le flag
    }
  }

  m = menuData;

  // Restore preserved feed
  if (currentFeedData) {
    m[id] = currentFeedData;
    m[id].n = 0; // Ensure counter is 0
  }

  var menu = '\t<li id="fsearch" class="flux" title="Recherche" onclick="return false;">Résultats de la recherche</li>\n';
  var changedFeeds = [];
  var newFeeds = [];
  var feedsToShow = {};

  for(var i in m) {
    // Show feed if: has unread articles OR is currently selected (even if empty)
    var isCurrentFeed = (id == i);
    var shouldShow = (m[i].n > 0) || isCurrentFeed;

    if (shouldShow) {
      feedsToShow[i] = true;

      // Use different class for empty but selected feed
      var feedClass = (m[i].n > 0) ? 'fluxnew' : 'flux';
      var counterDisplay = (m[i].n > 0) ? '<span class="nb_flux"> ' + m[i].n + '</span>' : '';

      menu += '\t<li id="f' + i + '" class="' + feedClass + '" title="' + m[i].d + '" onclick="view(' + i + ');">'  + m[i].t + counterDisplay + ' <span class="icon"><a title="Tout marquer comme lu" onclick="markallread(' + i + ')"></a> <a title="Se désabonner" onclick="unsubscribe(\'' + m[i].t.replace(/'/g, "\\\'") + '\', ' + i + ')"></a></span></li>\n';
      nb_title += m[i].n || 0;

      // Check if counter changed
      if (oldCounters[i] !== undefined && oldCounters[i] !== m[i].n) {
        changedFeeds.push(i);
      }

      // Check if this is a new feed (was hidden, now visible)
      // BUT don't treat preserved current feed as new (it was already visible)
      var isPreservedCurrentFeed = (currentFeedData && i == id);
      if ((oldFeedsVisible[i] === false || oldFeedsVisible[i] === undefined) && !isPreservedCurrentFeed) {
        newFeeds.push(i);
      }
    }
  }

  // Check if there are structural changes (new feeds or feeds disappeared)
  var structuralChanges = newFeeds.length > 0;
  if (!structuralChanges && oldFeedsVisible) {
    // Check if any old visible feed is now hidden
    for(var i in oldFeedsVisible) {
      if (oldFeedsVisible[i] && !feedsToShow[i]) {
        structuralChanges = true;
        break;
      }
    }
  }

  const menuEl = $('menu');

  // Only rebuild menu if there are structural changes or it's the first render
  if (structuralChanges || menuEl.children.length <= 1) {
    while (menuEl.children.length > 1) {
      menuEl.removeChild(menuEl.lastChild);
    }
    menuEl.insertAdjacentHTML('beforeend', menu);
  } else {
    // Just update counters for existing feeds (no rebuild)
    for(var i in changedFeeds) {
      var feedId = changedFeeds[i];
      var feedEl = $('f' + feedId);
      if (feedEl) {
        var counterSpan = feedEl.querySelector('.nb_flux');
        if (m[feedId].n > 0) {
          if (counterSpan) {
            counterSpan.textContent = ' ' + m[feedId].n;
          } else {
            // Counter didn't exist, add it
            var titleText = feedEl.firstChild;
            feedEl.insertAdjacentHTML('afterbegin', feedEl.textContent.split('<span')[0] + '<span class="nb_flux"> ' + m[feedId].n + '</span>');
          }
          feedEl.className = 'fluxnew';
        } else {
          // Remove counter if it exists
          if (counterSpan) {
            counterSpan.remove();
          }
          feedEl.className = 'flux';
        }
      }
    }
  }

  // Apply fade-in animation to new feeds
  newFeeds.forEach(function(feedId) {
    if($('f' + feedId)) {
      $('f' + feedId).classList.add('fade-in-new');
      // Remove class after animation
      setTimeout(() => {
        if($('f' + feedId)) $('f' + feedId).classList.remove('fade-in-new');
      }, 600);
    }
  });

  // Apply blink effect to changed feeds (but not new ones)
  changedFeeds.forEach(function(feedId) {
    if($('f' + feedId) && !newFeeds.includes(feedId)) {
      setTimeout(() => light('f' + feedId), 100);
    }
  });

  // Restore the 'show' class on currently selected feed
  if (id === 'all' && $('fall')) {
    $('fall').classList.add('show');
  } else if (id !== 'all' && $('f' + id)) {
    $('f' + id).classList.add('show');
  }

  D.title = 'Gheop Reader' + ((nb_title > 0) ? ' (' + nb_title + ')' : '');
  favicon(nb_title);
  totalItems = nb_title;
  readItems = 0;
  progressBar();
}

function renderArticles(articlesData, feedId, fromAPI = false) {
  // Early return if DM (main element) doesn't exist (e.g., on login page)
  if (!DM) {
    console.log('renderArticles: DM not found, skipping');
    return;
  }

  console.log('renderArticles called with feedId:', feedId, 'fromAPI:', fromAPI);
  console.log('articlesData keys:', Object.keys(articlesData).length);
  let page = '';
  cptReadArticle = 0;
  varscroll = 0;
  loadmore = 0;

  // If data comes from API for a specific feed, replace global data completely
  // API already filtered the results, so we don't need old cached data
  if (fromAPI && feedId && feedId !== 'all') {
    d = articlesData; // Replace completely
  } else {
    // Merge with existing data (for 'all' view or from cache)
    d = articlesData;
  }

  // Initialize read state for articles
  // If data comes fresh from API (reader_unread_cache), all articles are unread
  // If data comes from cache, preserve existing read states
  for(let i in d) {
    if (fromAPI) {
      d[i].r = 1; // Articles from API are always unread (from reader_unread_cache)
      d[i].readblock = 0;
    } else if (d[i].r === undefined) {
      d[i].r = 1; // Default to unread if not set
      d[i].readblock = 0;
    }
  }

  Now = new Date();
  for(let i in d) {
    // Only filter by feed if data doesn't come from API (which already filtered)
    // When fromAPI=true for a specific feed, API already filtered, so all articles in d belong to that feed
    if (!fromAPI && feedId && feedId !== 'all' && d[i].f != feedId) {
      console.log('Skipping article', i, 'feed:', d[i].f, 'looking for:', feedId);
      continue;
    }
    // Only display unread articles (skip read articles preserved from previous sync)
    if (d[i].r === 0) {
      console.log('Skipping read article', i);
      continue;
    }
    loadmore++;
    page += generateArticle(i);
  }
  console.log('Generated', loadmore, 'articles');
  if(loadmore == 0) {
    page = '<article class="item1">\n\t<header>\n\t\t<h1 class="headline"><a class="title" target="_blank">Flux vide</a></h1>\n\t\t<div class="byline vcard">\n\t\t\t<address class="author"><a class="website">Gheop Reader</a></address>\n\t\t\t<time>Maintenant</time>\n\t\t</div>\n\t</header>\n\t<div class="article-content">Pas de nouveaux articles.</div>\n\t<div class="action">&nbsp;&nbsp;</div>\n</article>';
  }
  page += '<div id="addblank">&nbsp;</div>';
  DM.innerHTML = page;
  DM.scrollTop = 0;
  DM.addEventListener('DOMMouseScroll', scroll, false);
  DM.onscroll = scroll;
  DM.onmousewheel = scroll;
  DM.scrollTop = 0;
  if(loadmore > 0) {
    if(loadmore) $('addblank').style.height = (DM.offsetHeight - 60) + 'px';
    scroll();
  }
}

// ============================================================================
// SERVER-SENT EVENTS (SSE) FOR REAL-TIME PUSH
// ============================================================================

function startSSEConnection() {
  // Close existing connection if any
  if (eventSource) {
    eventSource.close();
    eventSource = null;
  }

  // Check if browser supports EventSource
  if (typeof EventSource === 'undefined') {
    console.warn('SSE not supported, falling back to polling');
    startBackgroundSync(30);
    return;
  }

  console.log('Establishing SSE connection...');
  eventSource = new EventSource('sse.php');

  eventSource.addEventListener('connected', function(e) {
    const data = JSON.parse(e.data);
    console.log('SSE connected:', data);
  });

  eventSource.addEventListener('update', function(e) {
    const data = JSON.parse(e.data);
    console.log('SSE update received:', data);
    // Data has changed, refresh in background
    fetchAndUpdateDataBackground();
  });

  eventSource.addEventListener('heartbeat', function(e) {
    const data = JSON.parse(e.data);
    console.log('SSE heartbeat:', data.timestamp);
  });

  eventSource.addEventListener('timeout', function(e) {
    console.log('SSE timeout, reconnecting...');
    // Connection timed out, reconnect
    setTimeout(() => startSSEConnection(), 1000);
  });

  eventSource.onerror = function(e) {
    console.error('SSE error:', e);
    eventSource.close();
    eventSource = null;
    // Try to reconnect after 5 seconds
    setTimeout(() => startSSEConnection(), 5000);
  };
}

function stopSSEConnection() {
  if (eventSource) {
    console.log('Closing SSE connection');
    eventSource.close();
    eventSource = null;
  }
}

// Fallback to polling if SSE not available or fails
function startBackgroundSync(intervalSeconds = 30) {
  if (syncInterval) {
    clearInterval(syncInterval);
  }
  syncInterval = setInterval(() => {
    console.log('Background sync (polling)...');
    // Always fetch all data, but keep current view
    fetchAndUpdateDataBackground();
  }, intervalSeconds * 1000);
}

async function fetchAndUpdateDataBackground() {
  console.log('=== SSE BACKGROUND SYNC START ===');

  // Wait for read activity to stop (2 seconds of inactivity)
  const timeSinceLastRead = Date.now() - lastReadActivity;
  if (timeSinceLastRead < 2000) {
    console.log('SSE: Recent read activity detected (', timeSinceLastRead, 'ms ago), delaying sync for 2 seconds...');
    // Retry after 2 seconds
    setTimeout(() => fetchAndUpdateDataBackground(), 2000);
    return;
  }

  // Flush any pending read requests BEFORE fetching new data
  // This ensures articles marked as read locally are saved server-side
  // before we check for new articles
  console.log('SSE: No recent read activity, flushing pending read requests...');
  await flushReadBatch();
  console.log('SSE: Flush complete, now fetching data...');

  // Fetch data for the currently displayed feed (stored in global 'id' variable)
  // If viewing 'all', fetch all articles. If viewing specific feed, fetch that feed's articles.
  const url = (id === 'all') ? 'api.php' : 'api.php?id=' + id;
  console.log('Background fetching data from:', url, '(current feed:', id, ')');

  fetch(url, {
    credentials: 'same-origin' // Include cookies for authentication
  })
    .then(response => {
      if (!response.ok) {
        throw new Error('HTTP error ' + response.status);
      }
      return response.json();
    })
    .then(data => {
      if (data.menu && data.articles) {
        console.log('Background sync: Menu items:', Object.keys(data.menu).length, 'Articles:', Object.keys(data.articles).length);

        // Only save to cache if fetching 'all' data (not filtered by feed)
        // Otherwise we'd overwrite the full cache with partial data
        if (id === 'all') {
          saveToCache(data);
        } else {
          console.log('SSE: Skipping cache save for filtered feed', id);
        }

        // Update menu (with blink effects)
        renderMenu(data.menu);

        // Handle article updates
        appendNewArticles(data.articles);
      }
    })
    .catch(err => {
      console.error('Background sync failed:', err);
    });
}

function appendNewArticles(newArticlesData) {
  // Find new articles that weren't in the previous data
  var newArticles = [];
  var removedArticles = [];
  var reactivatedArticles = []; // Articles marked as unread elsewhere

  // 1. Find NEW articles and REACTIVATED articles (marked as unread elsewhere)
  for(var i in newArticlesData) {
    // Check if it should be displayed based on current filter
    if (id === 'all' || newArticlesData[i].f == id) {
      // Check if it exists in DOM as a read article (item0)
      if ($(i) && $(i).className === 'item0') {
        // Article exists in DOM and is displayed as read, but it's in newArticlesData
        // which only contains unread articles, so it was marked as unread elsewhere
        reactivatedArticles.push(i);
      } else if (!d || !d[i]) {
        // This article is not in the current data object, so it's new
        // But only add if it's not already in the DOM
        if (!$(i)) {
          newArticles.push(i);
        }
      }
    }
  }

  // 2. Find REMOVED articles (marked as read elsewhere)
  if (d) {
    for(var i in d) {
      if (!newArticlesData[i]) {
        // SAFETY CHECK: Only consider as "removed" if article belongs to current view
        // This prevents marking articles from other feeds as read when context changes
        var articleBelongsToCurrentView = (id === 'all') || (d[i] && d[i].f == id);

        if (articleBelongsToCurrentView && $(i)) {
          // This article was in old data but not in new data = marked as read elsewhere
          removedArticles.push(i);
        }
      }
    }
  }

  console.log('SSE: Found', newArticles.length, 'new articles,', reactivatedArticles.length, 'reactivated articles, and', removedArticles.length, 'removed articles for current view');

  // Count how many articles are in newArticlesData
  var newDataCount = Object.keys(newArticlesData).length;
  var oldDataCount = d ? Object.keys(d).length : 0;
  console.log('SSE: Old data had', oldDataCount, 'articles, new data has', newDataCount, 'articles');

  // SAFETY CHECK: Detect abnormal data size changes that might indicate context mismatch
  // If we're viewing a specific feed and suddenly get way more articles, something is wrong
  if (id !== 'all' && oldDataCount > 0 && newDataCount > oldDataCount * 3) {
    console.error('SSE SAFETY: Abnormal data size change detected!',
                  'Old:', oldDataCount, 'New:', newDataCount, 'Feed:', id,
                  '- This might indicate a context mismatch. Aborting update to prevent corruption.');
    return; // Abort the update
  }

  // SAFETY CHECK: If too many articles would be marked as read (>50% of current view), warn
  if (removedArticles.length > 0 && oldDataCount > 0 && removedArticles.length > oldDataCount * 0.5) {
    console.warn('SSE SAFETY: Would mark', removedArticles.length, 'articles as read out of', oldDataCount,
                 '(>' + Math.round(removedArticles.length/oldDataCount*100) + '%) - this seems excessive!',
                 'Current feed:', id);
    // Still proceed but log warning for debugging
  }

  // Initialize read state for all articles from API (they are all unread)
  for(var i in newArticlesData) {
    newArticlesData[i].r = 1; // All articles from API are unread
    newArticlesData[i].readblock = 0;
  }

  // Preserve locally modified articles FIRST (they have priority)
  for(var i in locallyModifiedArticles) {
    if (d && d[i]) {
      newArticlesData[i] = d[i];
      console.log('SSE: Preserving locally modified article', i);
    }
  }

  // Then preserve read articles that are in DOM (but not locally modified or in newArticlesData)
  // This allows users to still interact with them (mark as unread, etc.)
  if (d) {
    var preservedCount = 0;
    for(var i in d) {
      // Only preserve if:
      // 1. Article is NOT in new data (it's read according to server)
      // 2. Article is in DOM
      // 3. Article was NOT locally modified (already handled above)
      if (!newArticlesData[i] && $(i) && !locallyModifiedArticles[i]) {
        newArticlesData[i] = d[i];
        preservedCount++;
      }
    }
    console.log('SSE: Preserved', preservedCount, 'read articles from DOM');
  }

  d = newArticlesData;
  console.log('SSE: Updated global data object to', Object.keys(d).length, 'articles');

  // Handle removed articles (mark as read, don't remove from DOM to preserve scroll)
  if (removedArticles.length > 0) {
    removedArticles.forEach(function(articleId) {
      // Skip articles that were manually modified locally
      if (locallyModifiedArticles[articleId]) {
        console.log('Skipping article', articleId, '- locally modified');
        return;
      }

      var article = $(articleId);
      if (article) {
        // Just mark as read visually (item1 -> item0)
        // Don't remove from DOM to avoid scroll position jump
        article.className = 'item0';

        // Update data state
        if (d[articleId]) {
          d[articleId].r = 0;
        }
      }
    });
    console.log('Marked', removedArticles.length, 'articles as read (from other device)');

    // Check if current feed is now empty and we're on a specific feed (not "all")
    if (id !== 'all') {
      var hasUnreadArticlesForCurrentFeed = false;
      for(var i in d) {
        if (d[i].f == id) {
          hasUnreadArticlesForCurrentFeed = true;
          break;
        }
      }

      console.log('Checking if feed', id, 'has unread articles:', hasUnreadArticlesForCurrentFeed);

      if (!hasUnreadArticlesForCurrentFeed) {
        console.log('Current feed has no more unread articles, switching to "All"');
        // Update UI state
        var oldFeed = $('f' + id);
        if (oldFeed) {
          oldFeed.classList.remove('show');
        }
        id = 'all';
        if ($('fall')) {
          $('fall').classList.add('show');
        }
        // Force re-render with all articles
        renderArticles(d, 'all');
      }
    }
  }

  // Handle reactivated articles (marked as unread elsewhere)
  if (reactivatedArticles.length > 0) {
    console.log('SSE: Processing', reactivatedArticles.length, 'reactivated articles:', reactivatedArticles.join(', '));

    // Remove "Flux vide" message if it exists
    var articles = DM.querySelectorAll('article');
    articles.forEach(function(article) {
      var titleLink = article.querySelector('.title');
      if (titleLink && titleLink.textContent === 'Flux vide') {
        console.log('SSE: Removing "Flux vide" message (reactivated articles)');
        article.remove();
      }
    });

    reactivatedArticles.forEach(function(articleId) {
      var article = $(articleId);
      if (article && article.className === 'item0') {
        // Change from read (item0) to unread (item1)
        article.className = 'item1';
        console.log('SSE: Reactivated article', articleId, 'from read to unread (feed:', d[articleId].f, ')');

        // Add fade-in animation
        article.classList.add('fade-in-new');
        setTimeout(() => {
          if($(articleId)) $(articleId).classList.remove('fade-in-new');
        }, 600);
      }
    });
    console.log('SSE: Reactivated', reactivatedArticles.length, 'articles');
  }

  // Handle new articles (fade in and add to DOM)
  if (newArticles.length > 0) {
    Now = new Date();

    // Remove "Flux vide" message if it exists
    var articles = DM.querySelectorAll('article');
    articles.forEach(function(article) {
      var titleLink = article.querySelector('.title');
      if (titleLink && titleLink.textContent === 'Flux vide') {
        console.log('Removing "Flux vide" message');
        article.remove();
      }
    });

    // Append new articles to DOM
    var addBlank = $('addblank');
    if (addBlank) {
      var newPage = '';
      newArticles.forEach(function(articleId) {
        loadmore++;
        newPage += generateArticle(articleId);
      });

      // Insert before addblank
      addBlank.insertAdjacentHTML('beforebegin', newPage);

      // Apply fade-in animation to new articles
      newArticles.forEach(function(articleId) {
        if($(articleId)) {
          $(articleId).classList.add('fade-in-new');
          // Mark as protected from auto-read temporarily
          $(articleId).dataset.newArticle = 'true';
          // Remove class after animation
          setTimeout(() => {
            if($(articleId)) $(articleId).classList.remove('fade-in-new');
          }, 600);
        }
      });

      // Re-setup scroll observers for new articles
      scroll();

      // Remove protection from auto-read after 2 seconds
      // This gives user time to see the new articles before they're auto-marked as read
      setTimeout(() => {
        newArticles.forEach(function(articleId) {
          if($(articleId)) {
            delete $(articleId).dataset.newArticle;
          }
        });
      }, 2000);

      console.log('Appended', newArticles.length, 'new articles to DOM');
    }
  }
}

function stopBackgroundSync() {
  if (syncInterval) {
    clearInterval(syncInterval);
    syncInterval = null;
  }
}

// Batch read requests to avoid flooding server
let readBatchQueue = [];
let readBatchTimeout = null;
let lastReadActivity = 0; // Timestamp of last read activity

function flushReadBatch() {
	if (readBatchQueue.length === 0) return Promise.resolve();

	const idsToMark = [...readBatchQueue];
	readBatchQueue = [];

	console.log('FLUSHING BATCH: Marking', idsToMark.length, 'articles as read on server');

	// Send batch request and return the promise
	const data = 'ids=' + idsToMark.join(',');
	return myFetch('read.php', data, 1)
		.then(() => {
			console.log('BATCH SUCCESS: Marked', idsToMark.length, 'articles as read');
			// Nettoyer les flags locallyModified après succès
			for (var feedId in m) {
				if (m[feedId].locallyModified) {
					m[feedId].locallyModified = false;
				}
			}
		})
		.catch(err => {
			console.error('Batch read failed, retrying individually:', err);
			// Retry failed items individually
			idsToMark.forEach(id => myFetch('read.php', 'id=' + id, 1));
		});
}

function queueReadRequest(articleId) {
	readBatchQueue.push(articleId);
	console.log('QUEUE: Article', articleId, 'added to batch queue (', readBatchQueue.length, 'in queue)');

	// Update last read activity timestamp
	lastReadActivity = Date.now();

	// Clear existing timeout
	clearTimeout(readBatchTimeout);

	// Flush immediately if queue is large, otherwise wait 500ms
	if (readBatchQueue.length >= 20) {
		console.log('QUEUE: Auto-flushing (20 items reached)');
		flushReadBatch();
	} else {
		readBatchTimeout = setTimeout(flushReadBatch, 500);
	}
}

// Debounced favicon update to prevent "Too many badges requests" error
let faviconTimeout;
let lastFaviconUpdate = 0;
function favicon(nb) {
	if(nb < 0) return;

	// Debounce: only update favicon max once per 100ms
	const now = Date.now();
	clearTimeout(faviconTimeout);

	faviconTimeout = setTimeout(() => {
		try {
			if(favicon_badge) favicon_badge.badge(nb);
		} catch(e) {
			// Ignore favicon errors - they shouldn't block read marking
			console.warn('Favicon update failed:', e.message);
		}
		lastFaviconUpdate = now;
	}, 100);
  //  $('favico').href = "https://reader.gheop.com/favicon"+nb+".png";
}

function changeTheme(style) {
  if(imageObserver) imageObserver.disconnect();
  const currentTheme = localStorage.getItem('theme') || 'auto';

  let nextTheme;

  // Cycle: light → dark → adaptive → smooth → light
  if ($('stylesheet').href.includes('light.css')) {
    nextTheme = 'dark';
  } else if ($('stylesheet').href.includes('dark.css')) {
    nextTheme = 'adaptive';
  } else if ($('stylesheet').href.includes('adaptive.css')) {
    nextTheme = 'smooth';
  } else {
    nextTheme = 'light';
  }

  // Appliquer le thème
  if (nextTheme === 'light') {
    $('stylesheet').href = 'themes/light.min.css';
    localStorage.setItem('theme', 'light');
  } else if (nextTheme === 'dark') {
    $('stylesheet').href = 'themes/dark.min.css';
    localStorage.setItem('theme', 'dark');
  } else if (nextTheme === 'adaptive') {
    $('stylesheet').href = 'themes/adaptive.min.css';
    localStorage.setItem('theme', 'adaptive');
    // Démarrer le thème adaptatif après un court délai
    setTimeout(() => {
      if (window.startAdaptiveTheme) {
        startAdaptiveTheme();
      }
    }, 100);
  } else if (nextTheme === 'smooth') {
    $('stylesheet').href = 'themes/adaptive-smooth.min.css';
    localStorage.setItem('theme', 'smooth');
    // Démarrer le thème smooth après un court délai
    setTimeout(() => {
      if (window.startSmoothAdaptiveTheme) {
        startSmoothAdaptiveTheme();
      }
    }, 100);
  }

  updateThemeIcon();
  setTimeout(scroll, 2000);
}

function toggleThemeDropdown() {
  const dropdown = $('theme-dropdown');
  if (dropdown) {
    dropdown.classList.toggle('theme-dropdown-hidden');
  }
}

function getSystemTheme() {
  // Detect system color scheme preference
  if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
    return 'dark';
  }
  return 'light';
}

function applyAutoTheme() {
  // Apply theme based on system preference
  const systemTheme = getSystemTheme();
  const stylesheet = $('stylesheet');

  if (systemTheme === 'dark') {
    stylesheet.href = 'themes/dark.min.css';
  } else {
    stylesheet.href = 'themes/light.min.css';
  }

  // Remove integrity attribute when dynamically changing themes
  stylesheet.removeAttribute('integrity');
  stylesheet.removeAttribute('crossorigin');

  updateThemeIcon();
  setTimeout(scroll, 2000);
}

function selectTheme(themeName) {
  const dropdown = $('theme-dropdown');
  if (dropdown) {
    dropdown.classList.add('theme-dropdown-hidden');
  }

  if(imageObserver) imageObserver.disconnect();

  const stylesheet = $('stylesheet');

  // Appliquer le thème sélectionné
  if (themeName === 'auto') {
    localStorage.setItem('theme', 'auto');
    applyAutoTheme();
  } else if (themeName === 'light') {
    stylesheet.href = 'themes/light.min.css';
    localStorage.setItem('theme', 'light');
  } else if (themeName === 'dark') {
    stylesheet.href = 'themes/dark.min.css';
    localStorage.setItem('theme', 'dark');
  } else if (themeName === 'adaptive') {
    stylesheet.href = 'themes/adaptive.min.css';
    localStorage.setItem('theme', 'adaptive');
    // Démarrer le thème adaptatif après un court délai
    setTimeout(() => {
      if (window.startAdaptiveTheme) {
        startAdaptiveTheme();
      }
    }, 100);
  } else if (themeName === 'smooth') {
    stylesheet.href = 'themes/adaptive-smooth.min.css';
    localStorage.setItem('theme', 'smooth');
    // Démarrer le thème smooth après un court délai
    setTimeout(() => {
      if (window.startSmoothAdaptiveTheme) {
        startSmoothAdaptiveTheme();
      }
    }, 100);
  } else if (themeName === 'modern') {
    stylesheet.href = 'themes/modern.min.css';
    localStorage.setItem('theme', 'modern');
  }

  // Remove integrity attribute when dynamically changing themes
  // (we trust our own server, and updating integrity dynamically is complex)
  stylesheet.removeAttribute('integrity');
  stylesheet.removeAttribute('crossorigin');

  updateThemeIcon();
  setTimeout(scroll, 2000);
}

function updateThemeIcon() {
  const themeCurrent = $('theme-current');
  if (!themeCurrent) {
    console.warn('updateThemeIcon: #theme-current element not found');
    return;
  }

  const stylesheet = $('stylesheet');
  if (!stylesheet) {
    console.warn('updateThemeIcon: #stylesheet element not found');
    return;
  }

  let iconClass = '';
  const savedTheme = localStorage.getItem('theme');

  // If auto mode, show auto icon regardless of current stylesheet
  if (savedTheme === 'auto') {
    iconClass = 'theme-icon-auto';
  } else {
    // Otherwise, detect from current stylesheet
    const href = stylesheet.href;

    // Check for theme names (works with both .css and .min.css)
    if (href.includes('/light')) {
      iconClass = 'theme-icon-light';
    } else if (href.includes('/dark')) {
      iconClass = 'theme-icon-dark';
    } else if (href.includes('/adaptive-smooth')) {
      iconClass = 'theme-icon-smooth';
    } else if (href.includes('/adaptive')) {
      iconClass = 'theme-icon-adaptive';
    } else if (href.includes('/modern')) {
      iconClass = 'theme-icon-modern';
    }
  }

  console.log('updateThemeIcon: Setting icon class:', iconClass, 'for saved theme:', savedTheme);
  themeCurrent.innerHTML = '<i class="' + iconClass + '"></i>';
}

// Fermer le dropdown si on clique ailleurs
document.addEventListener('click', function(event) {
  const themeSelector = $('theme-selector');
  const dropdown = $('theme-dropdown');

  if (themeSelector && dropdown && !themeSelector.contains(event.target)) {
    dropdown.classList.add('theme-dropdown-hidden');
  }
});

function handleConnectionChange(event){
    if(event.type == "offline"){
      online = false;
      $('g').style.textDecoration='line-through';
      // Close SSE connection when offline
      stopSSEConnection();
    }
    if(event.type == "online"){
      online = true;
      $('g').style.textDecoration='none';
      // Restart SSE connection when back online (only if user is logged in)
      if (DM) {
        startSSEConnection();
      }
    }
}


function getSelectedText() {
  if (typeof window.getSelection !== 'undefined') {
    return window.getSelection().trim();
  }
  else if (typeof document.selection !== 'undefined') {
    return document.selection.createRange().text.trim();
  }
  else {
    return undefined;
  }
}

function search(t) {
  if (t) {
    search_value = t;
    $('bs').style.background = "white url(assets/images/loading.gif) no-repeat 4px center";
    var xhr = getHTTPObject('search');
    xhr.open("POST", 'https://reader.gheop.com/search.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.send('xhr=1&s=' + t);
    requestTimer = setTimeout((function() {
      if (xhr) {
        xhr.abort();
      }
    }), 4000);
    xhr = undefined;
    $('s').blur();
  }
  t = undefined;
  return false;
}

// Cache last rootHeight to avoid recreating observer unnecessarily
let lastRootHeight = 0;

function scroll() {
    // Early return if DM doesn't exist (e.g., on login page)
    if (!DM) return;

    //var unreadArticles = document.querySelectorAll(".item1");
    //plus rapide, voir support, retourne un HTMLCollections au lieu d'un NodeList d'ou le array.from devant
    let unreadArticles = Array.from(document.getElementsByClassName("item1"));
    // Cache offsetHeight to avoid multiple reflows
    const dmHeight = DM.offsetHeight;
    let rootHeight = dmHeight - 5;
    // Utiliser IntersectionObserver sauf pour Safari (bug avec root)
    const isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);
    if ("IntersectionObserver" in window && !isSafari) {
        if($('addblank')) $('addblank').style.height = (dmHeight - 60) + 'px';

        // Only recreate observer if rootHeight changed (e.g., window resize)
        if(!imageObserver || lastRootHeight !== rootHeight) {
            if(imageObserver) imageObserver.disconnect();
            lastRootHeight = rootHeight;

            imageObserver = new IntersectionObserver(function(entries, observer) {
                    entries.forEach(function(entry) {
                            // Marquer comme lu si l'article est visible OU s'il vient de sortir par le haut
                            if (entry.isIntersecting || (entry.boundingClientRect.top < 0 && entry.rootBounds)) {
                                let art = entry.target;
                                // Skip if this is a newly added article (protected for 2 seconds)
                                if (art.dataset.newArticle === 'true') {
                                    return;
                                }
                                // Vérifier que l'article n'est pas déjà lu
                                if (d[art.id] && d[art.id].r !== 0) {
                                    // Marquer immédiatement comme traité avant d'appeler read()
                                    imageObserver.unobserve(art);
                                    read(art.id);
                                    cptReadArticle++;
                                    // Disabled: automatic "load more" no longer needed with API loading all articles
                                    // if (!loadinprogress && cptReadArticle + 5 >= loadmore) {
                                    //     loadinprogress = 1;
                                    //     more();
                                    // }
                                }
                            }
                        });
                }, {
                root: DM,
                        rootMargin: "0px 0px -"+rootHeight+"px 0px",
                        threshold: [0, 0.01, 1]
                        });
        } else {
            // Observer already exists with correct rootHeight, just disconnect to re-observe new articles
            imageObserver.disconnect();
        }

        unreadArticles.forEach(function(art) {
                imageObserver.observe(art);
            });
    }
    else {
        DM.onscroll = oldScroll;
        DM.onmousewheel = oldScroll;
    }
}

function oldScroll() {
    let count = 0;
    for(let i in d) {
        // Skip if element not in DOM (filtered out)
        if(!$(i)) continue;
        count++;
        if($(i).offsetTop <= DM.scrollTop) {
            if (d[i].r != 0) read(i);
            // Disabled: automatic "load more" no longer needed with API loading all articles
            // if (!loadinprogress && count + 5 >= loadmore) {
            //     loadinprogress = 1;
            //     more();
            // }
        } else return;
    }
}

// Initialize scroll fallback and bounce handlers (once only)
// These handlers are added ONCE when the page loads, not on every scroll() call
function initScrollHandlers() {
    // Early return if DM doesn't exist (e.g., on login page)
    if (!DM) return;

    const isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);
    if (!("IntersectionObserver" in window) || isSafari) return; // Only for IntersectionObserver mode

    let scrollTimeout;
    let lastScrollTop = 0;
    let bounceTimeout;
    let lastBounceTime = 0;

    // Add scroll handler ONCE
    DM.addEventListener('scroll', function() {
        clearTimeout(scrollTimeout);
        scrollTimeout = setTimeout(function() {
            // After scroll stops, check for any unread articles above current position
            const currentScrollTop = DM.scrollTop;
            if (currentScrollTop > lastScrollTop) { // Scrolling down
                let unread = Array.from(document.getElementsByClassName("item1"));
                var fallbackMarked = 0;
                unread.forEach(function(art) {
                    if (art.offsetTop < currentScrollTop && d[art.id] && d[art.id].r !== 0) {
                        // Article is above viewport and still marked unread - mark it read
                        if (imageObserver) imageObserver.unobserve(art);
                        read(art.id);
                        cptReadArticle++;
                        fallbackMarked++;
                    }
                });
                if (fallbackMarked > 0) {
                    console.log('FALLBACK: Marked', fallbackMarked, 'missed articles as read');
                }
            }
            lastScrollTop = currentScrollTop;

            // Bounce effect when reaching bottom of last article
            clearTimeout(bounceTimeout);
            bounceTimeout = setTimeout(function() {
                const now = Date.now();
                // Throttle to max once every 2 seconds
                if (now - lastBounceTime < 2000) return;

                const scrollBottom = DM.scrollTop + DM.clientHeight;
                const scrollHeight = DM.scrollHeight;
                const addBlank = $('addblank');

                // Check if we're at the bottom (within 50px threshold)
                if (scrollBottom >= scrollHeight - 50 && addBlank) {
                    // Find the last article
                    const articles = Array.from(document.querySelectorAll('.item1, .item0'));
                    if (articles.length > 0) {
                        const lastArticle = articles[articles.length - 1];
                        const lastArticleTop = lastArticle.offsetTop;
                        const lastArticleHeight = lastArticle.offsetHeight;
                        const lastArticleBottom = lastArticleTop + lastArticleHeight;

                        // Check if we can see less than 40% of the last article
                        const visibleTop = Math.max(lastArticleTop, DM.scrollTop);
                        const visibleBottom = Math.min(lastArticleBottom, scrollBottom);
                        const visibleHeight = visibleBottom - visibleTop;
                        const visiblePercentage = (visibleHeight / lastArticleHeight) * 100;

                        if (visiblePercentage > 0 && visiblePercentage < 40) {
                            // Bounce back to show the full article
                            lastBounceTime = now;
                            const targetScroll = lastArticleTop - 10;

                            // Smooth scroll with bounce effect
                            DM.style.scrollBehavior = 'smooth';
                            DM.scrollTop = targetScroll;
                            setTimeout(() => {
                                DM.style.scrollBehavior = '';
                            }, 500);
                        }
                    }
                }
            }, 150); // Check 150ms after scroll stops
        }, 100); // Check 100ms after scroll stops
    }, {passive: true});

    // Add resize/orientation handlers ONCE
    window.addEventListener("resize", scroll, {passive: true});
    window.addEventListener("orientationchange", scroll, {passive: true});
}

function goUp() {
  DM.scrollTop -= 20;
}

function goDown() {
  DM.scrollTop += 20;
}

function goPrev() {
  if (kona == 1 || !d) return;
  var previous = undefined;
	for(var i in d) {
		// Skip if element not in DOM (filtered out)
		if(!$(i)) continue;
		if($(i).offsetTop > DM.scrollTop - 10) {
			if(previous) {DM.scrollTop = $(previous).offsetTop - 10;}
			return;
		}
		previous = i;
	}
	if(previous) {DM.scrollTop = $(previous).offsetTop - 10;}
}

function goNext() {
	if (kona == 1 || !d) return;
	for(let i in d) {
		// Skip if element not in DOM (filtered out)
		if(!$(i)) continue;
		if($(i).offsetTop > DM.scrollTop + 10) {
			DM.scrollTop = $(i).offsetTop - 10;
			return;
		}
	}
	if($('addblank')) DM.scrollTop = $('addblank').offsetTop - 20;
	return;
}

function goPrevPage() {
  DM.scrollTop -= DM.offsetHeight;
}

function goNextPage() {
  DM.scrollTop += DM.offsetHeight;
}

function delError() {
  $('error').style.display = "none";
}

function likedArticle(i) {
	var xhr = getHTTPObject('likedArticle');
	xhr.open('POST', 'likedArticle.php', true);
	xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
	xhr.send('xhr=1&f=' + i);
	requestTimer = setTimeout((function() {
		if (xhr) xhr.abort();
	}), 4000);
	xhr = undefined;
}

function markallread(i) {
	var r = confirm("Tout marquer comme lu pour \"" + m[i].t + "\" ?");
	if (r) {
		var xhr = getHTTPObject('markallread');
		xhr.open("POST", 'markallread.php', true);
		xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
		xhr.send('xhr=1&f=' + i);
		requestTimer = setTimeout((function() {
			if (xhr) xhr.abort();
		}), 4000);
		xhr = undefined;
	}
	r = undefined;
	return false;
}

function menu() {
  myFetch('menu.php').then(result => {
      var menu = '\t<li id="fsearch" class="flux" title="Recherche" onclick="return false;">Résultats de la recherche</li>\n';
      m = result;
      for(var i in m) {
        if (m[i].n > 0) {
          /* voir pour faire un event à la place des onclick and co */
          /*<li class=""><data value="'+i+'">... */
          //
          menu += '\t<li id="f' + i + '" class="fluxnew" title="' + m[i].d + '" onclick="view(' + i + ');">'  + m[i].t + '<span class="nb_flux"> ' + m[i].n + '</span> <span class="icon"><a title="Tout marquer comme lu" onclick="markallread(' + i + ')"></a> <a title="Se désabonner" onclick="unsubscribe(\'' + m[i].t.replace(/'/g, "\\\'") + '\', ' + i + ')"></a></span></li>\n';
          nb_title += m[i].n || 0;
          //$('m'+i).addEventListener("click", vieww); // on peut pas, pas encore créé sur la page ?
        }
      }
      $('menu').insertAdjacentHTML('beforeend', menu);
      D.title = 'Gheop Reader' + ((nb_title > 0) ? ' (' + nb_title + ')' : '');
      favicon(nb_title);
      totalItems = nb_title;
      readItems = 0;
      progressBar();
      return;
  })
}



// Shared stylesheet for all article-content shadow DOMs
// This is created ONCE and reused by all articles, saving memory
const articleStyleSheet = new CSSStyleSheet();
articleStyleSheet.replaceSync(`
    .article-content {
  text-align: justify;
  padding: 10px 10px 0 10px !important;
}
  .article-content > :last-child {
  margin-bottom: 0 !important;
}
  /* Prevent images, videos from overflowing */
  .article-content img,
  .article-content video,
  .article-content picture,
  .article-content source {
  max-width: 100% !important;
  height: auto !important;
}
  /* YouTube iframes - maintain 16:9 aspect ratio */
  .article-content iframe {
  width: 100% !important;
  max-width: 100% !important;
  height: auto !important;
  aspect-ratio: 16 / 9 !important;
  border: none;
}
  /* Ensure pre/code blocks also don't overflow */
  .article-content pre {
  max-width: 100%;
  overflow-x: auto;
}
  .spinner {
  margin: 20px auto;
  width: 20px;
  height: 20px;
  border: 4px solid #111;
  border-top-color: #ff8b8b;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}
`);

// Custom element for article content with shared stylesheet
customElements.define('article-content', class extends HTMLElement {
  connectedCallback() {
    const shadow = this.attachShadow({mode: 'open'});
    // Use shared stylesheet instead of creating new one per article
    shadow.adoptedStyleSheets = [articleStyleSheet];
    shadow.innerHTML = this.innerHTML;
  }
});

function view(i) {
  console.log('=== VIEW FEED', i, '===');
  console.log('Menu counter for feed:', m && m[i] ? m[i].n : 'N/A');

  // Update UI state - remove 'show' from old feed if it exists
  var oldFeed = $('f' + id);
  if (oldFeed) {
    oldFeed.classList.remove('show');
  }

  id = i;

  var newFeed = $('f' + id);
  if (newFeed) {
    newFeed.classList.add('show');
  }

  if ($('fsearch')) $('fsearch').className = "flux";
  search_active = 0;

  // Check if we have articles for this feed
  var hasArticlesForFeed = false;
  var unreadCount = 0;
  var readCount = 0;
  if (d) {
    for(var articleId in d) {
      if (i === 'all' || d[articleId].f == i) {
        if (d[articleId].r === 1) { // Only count unread articles
          hasArticlesForFeed = true;
          unreadCount++;
        } else if (d[articleId].r === 0) {
          readCount++;
        }
      }
    }
  }
  console.log('Articles in memory for feed', i, '- Unread:', unreadCount, 'Read:', readCount);

  // For specific feeds (not 'all'), always load from API to get fresh data
  // This avoids displaying articles that were marked as read in the 'all' view
  // but are still unread in the database
  if (i !== 'all') {
    console.log('Loading feed', i, 'from API to get fresh unread status');
    // Clear main display before loading to avoid flashing old content
    DM.innerHTML = '';
    loadData(i, false); // Load THIS feed's articles (no cache, direct from API)
    return;
  }

  // Clear main display before rendering to avoid flashing old content
  DM.innerHTML = '';

  // For 'all' view, use cached data if available
  if (d && Object.keys(d).length > 0) {
    renderArticles(d, i);

    // Trigger background preload if this is a specific feed (not 'all')
    // This will load ALL articles for this feed to avoid pagination delays
    if (i !== 'all' && m && m[i] && m[i].n > unreadCount) {
      console.log('Feed', i, 'has more articles in menu (', m[i].n, ') than in memory (', unreadCount, '), triggering preload after 1 second...');
      setTimeout(() => preloadFeedArticles(i), 1000);
    }
  } else {
    // No data in memory, try cache
    const cached = loadFromCache();
    if (cached && cached.articles) {
      renderArticles(cached.articles, i);

      // Trigger background preload for specific feeds
      if (i !== 'all' && m && m[i] && m[i].n > 0) {
        console.log('Feed', i, 'loaded from cache, triggering preload after 1 second...');
        setTimeout(() => preloadFeedArticles(i), 1000);
      }
    }
  }
}

function removelight(i) {
  $(i).classList.remove('blink');
}

function light(i) {
  $(i).classList.add('blink');
  setTimeout('removelight("' + i + '");', 250);
}

function addflux() {
  var val = window.prompt("Ajouter le flux :", "http(s)://");
  affError("Récupération du flux '" + val + "' en cours ...", 10);
  var xhr = getHTTPObject('addflux');
  xhr.open("POST", 'https://reader.gheop.com/add_flux.php', true);
  xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
  xhr.send('xhr=1&link=' + encodeURIComponent(val));
  requestTimer = setTimeout((function() {
    if (xhr) xhr.abort();
  }), 10000);
  val = xhr = undefined;
}

function up() {
  var xhr = getHTTPObject('up');
  $('up').style.display="inline-block";
  $('up').style.animation='spin 4s infinite linear';
  $('up').style.color = "red";
  //affError("Mise à jour des flux en cours!", 10);
    xhr.open("POST", 'up_parallel.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.send('xhr=1');
    requestTimer = setTimeout((function() {
      if (xhr) xhr.abort();
    }), 10000);
    xhr = undefined;
    return false;
}

function unsubscribe(t, f) {
  var r = confirm("Se désinscrire de \"" + t + "\" ?");
  if (r) {
    myFetch('unsubscribe_flux.php', 'link='+f, 1);
    for(var i in d) {
      if(d[i].f == f) {
        d[i].r = 0;
        if($(i)) $(i).style.display='none';
      }
    }
    if($('f' + f).className == 'fluxnew show') view('all'); //problème si la désincription mets trop de temps, on peut retrouver des articles pas encore enlevés ... voir pour passer par generateArticleS le jour ou ça sera fait.
    $('f' + f).className = "flux";
  }
  return false;
}

function editFluxName(idFlux) {
  $('f'+idFlux).innerHTML = "<input id=\"focus"+idFlux+"\" type=\"text\" value=\""+m[idFlux].t+"\" />";
  $('focus'+idFlux).focus();

}

function dateArticle(articleDate) {
	 //log(d[i]);


  //if (typeof Intl.RelativeTimeFormat !== 'undefined') {
 // if(!!navigator.platform && /iPad|iPhone|iPod/.test(navigator.platform)) {
 if(rtf == undefined) {
    return articleDate;
    //return new Intl.DateTimeFormat(locale).format(mydate);
  }
  else {
  	var mydate = new Date(articleDate);
    var diffDates = mydate - Now;
  //  localeFormat = new Intl.DateTimeFormat(locale).format;
    var diffDays = Math.round(diffDates / 86400000);
    var diffHours = Math.round(diffDates / 3600000);
    var diffMinutes = Math.round(diffDates / 60000);
    if(diffMinutes > -46) {
      return rtf.format(diffMinutes, 'minute');
    }
    else if(diffHours > -24) {
      return rtf.format(diffHours, 'hour');
    }
    else if(diffDays > -32) {
      return rtf.format(diffDays, 'day');
    }
    else {
      return articleDate;//new Intl.DateTimeFormat(locale).format(mydate);
    }
    // prévoir mois/année ? notamment pour la recherche ?
  }

}

function summarize(k) {
  console.log('get summarize for article '+k);
     //faire disparaitre l'icone
  //marche pas ici ???
   $('sum'+k).style.display = 'none'; 
  $('ac'+k).shadowRoot.innerHTML += '<hr /><div id="loader'+k+'" style="display: block;"><div class="spinner"></div></div>';
      updateStyle($('ac'+k));
 // Prépare les données à envoyer en POST
 let formData = new FormData();
 // Ajoute la variable contenant ton HTML
formData.append('article', $('ac'+k).shadowRoot.children[0].innerText);
//  formData.append('article', $('ac'+k).shadowRoot.innerText);
 //console.log(formData);

 fetch('https://reader.gheop.com/summarize.php', {
   method: 'POST',
   body: formData
 })
 .then(response => {
   // Quand la page est chargée, on la convertit en texte
   return response.text();
 })
 .then(html => {
   const parser = new DOMParser();
   const doc = parser.parseFromString(html, "text/html");
   const shadow = $('ac'+k).shadowRoot;
   shadow.querySelector('#loader'+k).innerHTML = doc.querySelector('html').innerHTML;
  // updateStyle($('ac'+k));

     //faire disparaitre l'icone
   //fait plus haut poru le faire dès le début ?!
   $('sum'+k).style.display = 'none'; 

   //ajouter le style pour avoir les marges, comme sur l'article, et ça sera nickel ;)
})
 .catch(error => {
   console.error('Failed to fetch page: ', error);
 });
}

function readability(k) {
  console.log('fetch https://reader.gheop.com/readability/?output&url='+d[k].l);
  fetch('https://reader.gheop.com/readability/?output&url='+d[k].l)
  .then(response => {
    // When the page is loaded convert it to text
    return response.text()
  })
  .then(html => {
    // Initialize the DOM parser
    const parser = new DOMParser()

    // Parse the text
    const doc = parser.parseFromString(html, "text/html")
/*    console.log(doc)
    console.log($(k));*/
    $('ac'+k).shadowRoot.children[0].innerHTML += doc.querySelector('html').innerHTML;
   // updateStyle($('ac'+k)); // plus besoin ?
    //hide icon
    countWords($('ac'+k));
   $('full'+k).style.display = 'none'; 
  })
  .catch(error => {
     console.error('Failed to fetch page: ', error)
  })

}

function tagIt(k) {
   let formData = new FormData();
 // Ajoute la variable contenant ton HTML
formData.append('article', $('ac'+k).shadowRoot.children[0].innerText);

  console.log('fetch https://reader.gheop.com/tagIt.php?text='+formData);
  fetch('https://reader.gheop.com/tagIt.php?text='+formData)
  .then(response => {
    return response.text()
  })
  .then(html => {
    const parser = new DOMParser()
    const doc = parser.parseFromString(html, "text/html")
    $('tag'+k).innerHTML = doc.querySelector('html').innerHTML;
  })
  .catch(error => {
     console.error('Failed to fetch page: ', error)
  })

}
function countWords(elem) {
    // Récupérer le texte de l'élément
 // console.log(elem.id.substring(2));
    const text = elem.shadowRoot.innerText || elem.shadowRoot.textContent;

    // Nettoyer le texte : supprimer les espaces en trop et les sauts de ligne
    const cleanedText = text.trim();

    // Vérifier si le texte n'est pas vide
    if (cleanedText === '') return 0;

    // Séparer les mots en utilisant une expression régulière pour gérer les espaces multiples
    const words = cleanedText.split(/\s+/);
    const nbWords = words.length;
    if(nbWords < 100) {
      //$('sum'+elem.id.substring(2)).style.visibility = 'hidden';
      $('sum'+elem.id.substring(2)).style.display = 'none';
    }
    else {
      $('sum'+elem.id.substring(2)).style.display = 'inline';
    }

    // Retourner le nombre de mots
    return ;
}


//faire une fonction qui génère TOUS les articles d'un coup et qui n'execute qu'une seule fois le calcul des dates...
//ne pas vider la 'page' pour more...
function generateArticle(i) {
 	let datepub = dateArticle(d[i].p);
//voir http://microformats.org/wiki/hcard
return '<article id="' + i + '" class="item1" onclick="read(this.id, 1)">\n\t<header>\n\t\t<h1 class="headline"><a href="' + d[i].l + '" class="title" target="_blank" title="' + d[i].t + '">' + d[i].t + '</a></h1>\n\t\t<div class="byline vcard">\n\t\t\t<address class="author">From <a href="' + d[i].o + '" title="' + d[i].n + '" class="website">' + d[i].n + '</a>' +((d[i].a) ? (' <a rel="author" class="nickname">' + d[i].a + '</a>') : '') + '</address>\n\t\t\t<time pubdate datetime="'+d[i].p+'" title="'+datepub+'">' + datepub+ '</time>\n\t\t</div>\n\t</header>\n\t<article-content id="ac'+i+'"><div class="article-content">' + d[i].d + '</div></article-content>\n\t<div class="action"><a class="lu" onclick="verif(' + i + ', 1);return true;" title="Lu"></a> <a id="full'+i+'" class="readability" onclick="readability('+i+')"></a> <a id="sum'+i+'" class="summarize" onclick="summarize('+i+')"></a> <a class="sendTo" onclick="sendTo('+i+')"></a><a class="print" onclick="printIt('+i+')"></a><span id="tag'+i+'" class="tags icon"><a onclick="tagIt('+i+')"></a></span></div>\n</article>';
//<!--href="https://gheop.com/readability/?url=' + d[i].l + '" -->
//return '<article id="' + i + '" class="item1" onclick="read(this.id)">\n\t<header>\n\t\t<h1 class="headline"><a href="' + d[i].l + '" class="title" target="_blank" title="' + d[i].t + '">' + d[i].t + '</a>\n\t\t\t<time pubdate datetime="'+d[i].p+'" title="'+datepub+'">' + datepub+ '</time></h1>\n\t\t<div class="byline vcard">\n\t\t\t<address class="author"><a href="' + d[i].o + '" title="' + d[i].n + '" class="website">' + d[i].n + '</a>' +((d[i].a) ? (' <a rel="author" class="nickname">' + d[i].a + '</a>') : '') + '</address>\n\t\t</div>\n\t</header>\n\t<div class="article-content">' + d[i].d + '</div>\n\t<div class="action"><a class="lu" onclick="verif(' + i + ');return true;" title="Lu"></a></div>\n</article>';
}

function sendToKindle(k) {
  const article = $('ac'+k).shadowRoot.children[0].innerHTML;
  if (!article) {
    alert("Article introuvable");
    return;
  }

  const data = new FormData();
  data.append('html', article.outerHTML);
  data.append('title', d[k].t || "Article RSS");

  fetch('/send-to-kindle.php', {
    method: 'POST',
    body: data
  })
  .then(response => response.text())
  .then(result => {
    alert("Article envoyé sur le Kindle !");
    console.log(result);
  })
  .catch(error => {
    alert("Erreur lors de l'envoi");
    console.error(error);
  });
}


function printIt(k) {
     let formData = $('ac'+k).shadowRoot.children[0].innerHTML;
  if (!formData) return;


  let titleData = d[k].t;
  const iframe = document.createElement('iframe');
  iframe.style.position = 'absolute';
  iframe.style.left = '-9999px';
  document.body.appendChild(iframe);

  const doc = iframe.contentWindow.document;
  doc.open();
  doc.write(`
    <html>
      <head>
        <title>${titleData}</title>
        <style>
          body { font-family: sans-serif; padding: 2em; }
          article { max-width: 800px; margin: auto; }
        </style>
      </head>
      <body>${formData}</body>
    </html>
  `);
  doc.close();

  iframe.onload = () => {
    iframe.contentWindow.focus();
    iframe.contentWindow.print();
    setTimeout(() => document.body.removeChild(iframe), 1000);
  };
}

// voir https://developer.mozilla.org/fr/docs/Web/API/Fetch_API/Using_Fetch si on reçoit bien du json<
async function myFetch(url, data, noreturn) {
  try {
    const response = await fetch(url, {
    method: "POST",
    body: data,
    headers: {
        "Content-Type": "application/x-www-form-urlencoded"
    }
  });
  if(!noreturn) {
    const json = await response.json();
    return json;
  } else {
    // Even if we don't need the return value, wait for the request to complete
    // This ensures pending batch tracking works correctly
    await response.text(); // Consume the response body
    return;
  }
  }
  catch (err) {
    console.log('fetch failed', err);
  }
}

function getHTTPObject(action) {
	let xhr = new XMLHttpRequest();
	xhr.onreadystatechange = function() {
		if (xhr.readyState === 4 && xhr.status && xhr.status === 200) {
			var page = '';
	    if (action === 'addflux') {
        return xhr.responseText ? affError(xhr.responseText) : location.reload();
      } else if (action === 'unsubscribeflux') {
        return xhr.responseText ? affError(xhr.responseText) : location.reload();
      } else if (action === 'up') {
        location.reload();
//        return xhr.responseText ? affError(xhr.responseText) : location.reload();
      } else if (action === 'more') {
      	if(!xhr.responseText || xhr.responseText == '{}') return 0;
        var p = JSON.parse(xhr.responseText);
        if (!p.i || p.i.length === 0) return 0;
        DM.removeChild($('addblank'));
        cptReadArticle = 0;
        Now = new Date();
        for(let i in p) {
            loadmore++;
            d[i] = p[i];
            page += generateArticle(i);
        }
        page += '<div id="addblank">&nbsp;</div>';
        DM.insertAdjacentHTML('beforeend', page);
        $('addblank').style.height = (DM.offsetHeight - 60) + 'px';
        //lazyLoadImg();
        loadinprogress = 0;
        p = undefined;
      } else if (action === 'markallread') {
        location.reload();
      } else if (action === 'search') {
        search_active = 1;
        $('bs').style.background = "white url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABQAAAARCAYAAADdRIy+AAAAAXNSR0IArs4c6QAAAAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAAOxAAADsQBlSsOGwAAAAd0SU1FB9kCBxQkAPQ1ZpQAAAGrSURBVDjLjdRNiE5xFAbw372v14zvkEGsjLD20UghKTay1uykyEIWSpY2Stmp2dhMoWQ1USYbScrKhpKyGkVDNDaGfM21ed5ct/cOp/7dc+89/+c8z/k/9xYoUPkTu7EXm7EQM3iO+3jfp/6v6NTyJbiEY1iE13iXfB+O4BdeoAxo0Q+0yLqGCRxFt1GzChfDctR/xDncxY5ak7KW9+I8JjE8H9iagJ36R9Nek5u43FZUYicGcCv3ZQtYhTk8xFYsbgPcgGnM1jb1A+vFMwxifRtgmdOba1FR1WrhR8MhRRPwA9ZFdtlgVo9ewy34mn0a9inK+KrC4TA9EZvUZe7BweQHAvipoaKABXiVNYoHmeV1PMZbbMcmnIlHh/JuPHabCc7PurS1MfU4VmN5vozjYSdgE7iBFdlzG/u1zGlbvoaleIKnkTaEQ9iIjznhQVyNoiuYwli/wXdwEiOxU5W5vsQdPMLpzLMbT47F7NO4UPRhW9V+FgP4jO+NxiM4G6azebYMk50W+WWG/C12KRpefBO2u7AyDbuY6sxj5uYqatcCX3AvhzgcX479BrxnZh85IOwcAAAAAElFTkSuQmCC) no-repeat 4px center";
        $('fsearch').className = "fluxnew";
        $('fsearch').innerHTML = 'Résultats de la recherche "' + search_value + '"';
        $('f' + id).classList.remove('show');
        id = 'search';
        $('f' + id).classList.add('show');
        loadmore = 0;
        if (xhr.responseText) {
          d = JSON.parse(xhr.responseText);
          // for(var i in d) {
          //   loadmore++;
          //   //voir pour charger le corps du texte en shadow DOM https://developer.mozilla.org/fr/docs/Web/Web_Components/Shadow_DOM (ne fonctionne pas encore dans Firefox)
          //   page += generateArticle(i);
          for (let y = 0, tlen = d.i.length; y < tlen; y++) {
            loadmore++;
            page += '<div id="' + d.i[y].i + '" class="item1">\n\t<a class="date" title="date">' + d.i[y].p + '</a>\n\t<a id="a' + d.i[y].i + '" href="' + d.i[y].l + '" class="title icon" target="_blank" title="' + d.i[y].t + '"> ' + d.i[y].t + '</a>\n\t<div class="author">From <a href="//gheop.com" title="' + d.i[y].n + '">' + d.i[y].n + '</a>' + ((d.i[y].a) ? (' by ' + d.i[y].a) : '') + '</div>\n\t<div class="descr">' + d.i[y].d + '</div>\n\t<div class="action"><a class="search icon" onclick="verif(' + y + ');return true;" title="Lu"></a><!-- <span class="tags icon"> tag1, tag2, tag3, tagsuperlongdelamortquitue</span> ☺ ☻ ♡ ♥--></div>\n</div>\n';
          //voir pour foutre les tags en ul,li
          }
        }
        else {
          DM.innerHTML = '<div id="0" class="item1">\n\t<a class="date" title="date">Maintenant</a>\n\t<a id="a0" href="#" class="title icon" target="_blank" title="Pas de résultat."> Recherche "' + search_value + '"</a>\n\t<div class="author">From <a href="//gheop.com" title="reader gheop">Reader</a></div>\n\t<div class="descr">Pas de résultat trouvé!</div>\n\t<div class="action"><a class="search icon" onclick="return true;" title="Lu"></a><!-- <span class="tags icon"> tag1, tag2, tag3, tagsuperlongdelamortquitue</span> ☺ ☻ ♡ ♥--></div>\n</div>\n';
          return false;
        }


        page += '<div id="addblank">&nbsp;</div>';
        DM.innerHTML = page;
        $('addblank').style.height = (DM.offsetHeight - 60) + 'px';
        DM.addEventListener('DOMMouseScroll', scroll, false);
        DM.onscroll = scroll;
        DM.onmousewheel = scroll;
        DM.scrollTop = 0;
      }
      if (requestTimer) clearTimeout(requestTimer);
      page = undefined;
    } else if (xhr.readyState === 4) affError('Le serveur n\'a pas répondu assez rapidement. Veuillez réessayer ultérieurement.');
    return 0;
  };
  return xhr;
}

function verif(k, v=0) {
  // Check if article still exists in data object
  if (!d || !d[k]) {
    console.warn('Article ' + k + ' not found in data object, skipping');
    return;
  }
  if(v==1) (d[k].readblock == 0) ? d[k].readblock = 1 : d[k].readblock = 0;
  return (d[k].r == 0) ? unread(k) : read(k);
}

function unread(k, v=0) {
	// Check if article still exists in data object
	if (!d || !d[k]) {
		console.warn('Article ' + k + ' not found in data object, skipping unread');
		return;
	}
	unr = 1;
    if(v===0) d[k].readblock = 1;
  if(v===1) d[k].readblock = 0;
	if (d[k].r == 1 || $(k).className == 'item1') return;
	d[k].r = 1;
	$(k).className = 'item1';

	// Track that this article was manually marked as unread
	locallyModifiedArticles[k] = {state: 'unread', timestamp: Date.now()};

	if (nb_title < 0) nb_title = 0;
	D.title = 'Gheop Reader' + ((++nb_title > 0) ? ' (' + nb_title + ')' : '');

	// Send request to server to mark as unread
	myFetch('unread.php', 'id='+k, 1);

	// Check if feed exists in menu (it might not if it had 0 unread articles)
	if (!m[d[k].f]) {
		// Feed doesn't exist in menu, wait for database to update then reload
		console.log('Feed', d[k].f, 'not in menu, triggering delayed update');

		// Wait 300ms to let the server update the database, then fetch updated data
		// Don't create temporary entry - let the real data appear with proper animation
		setTimeout(() => {
			console.log('Fetching updated data after unread action on missing feed');
			fetchAndUpdateDataBackground();
		}, 300);

		favicon(nb_title);
		readItems--;
		progressBar();
		return;
	}

	m[d[k].f].n++;
        $('f' + d[k].f).children[0].innerHTML = m[d[k].f].n;
        $('f' + d[k].f).className = "fluxnew";
	if (id == d[k].f) $('f' + d[k].f).className = "fluxnew show";
	light('f' + d[k].f);
	favicon(nb_title);
	readItems--;
	progressBar();
}

function read(k, v=0) {
  // Check if article still exists in data object
  if (!d || !d[k]) {
    console.warn('Article ' + k + ' not found in data object, skipping read');
    return;
  }
  if (search_active == 1) return;
  //obligé sinon 2 read après un verif() ou un unread-read à la suite ... mais why ?
  if (d[k].readblock == 1) return;
  if (d[k].r === 0) return;
  if (unr === 1) {
    unr = 0;
    return;
  }

  // SAFETY CHECK: When viewing a specific feed, only allow marking articles from that feed as read
  // This prevents accidentally marking articles from other feeds when data gets mixed
  if (id !== 'all' && d[k].f != id) {
    console.error('SAFETY: Attempted to mark article', k, 'from feed', d[k].f, 'as read while viewing feed', id, '- Blocking!');
    return;
  }

  // Marquer immédiatement comme traité pour éviter les doubles appels
  d[k].r = 0;
  $(k).className = 'item0';

  console.log('READ article', k, 'feed:', d[k].f, '- Menu counter before:', m[d[k].f] ? m[d[k].f].n : 'N/A');

  // Queue read request for batch processing (reduces server load)
  queueReadRequest(k);

  // Update title counter
  D.title = 'Gheop Reader' + ((--nb_title > 0) ? ' (' + nb_title + ')' : '');
  if (nb_title < 0) nb_title = 0;
  favicon(nb_title);
  xhr = undefined;
  readItems++;
  progressBar();

  // Check if feed exists in menu (it might not if it had 0 unread articles)
  if (!m[d[k].f]) {
    console.log('Feed', d[k].f, 'not in menu when marking as read, skipping menu update');
    return;
  }

  // Update menu counter
  m[d[k].f].n--;
  m[d[k].f].locallyModified = true; // Marquer comme modifié localement
  console.log('Menu counter after:', m[d[k].f].n, '- Total in title:', nb_title);
  if (m[d[k].f].n > 0) {
     $('f' + d[k].f).children[0].innerHTML = m[d[k].f].n;
      light('f' + d[k].f);
  } else {
      $('f' + d[k].f).children[0].innerHTML = '';
      if (id == d[k].f) $('f' + d[k].f).className = "flux show";
      else $('f' + d[k].f).className = "flux";
  }
}

function progressBar() {
  const footer = document.getElementsByTagName("footer")[0];
  if (!footer) return;

  // Gérer le cas où totalItems est 0 ou undefined
  if (!totalItems || totalItems === 0) {
    footer.setAttribute('style', 'background: rgba(0,0,0,0.1) !important; position: fixed !important; bottom: 0 !important; left: 0 !important; right: 0 !important; height: 4px !important; z-index: 1001 !important; display: block !important; visibility: visible !important; opacity: 1 !important;');
    return;
  }

  let perc = ((readItems || 0) / totalItems) * 100;
  if (perc > 100) perc = 100;
  if (perc < 0) perc = 0;

  // Utiliser setAttribute pour forcer tous les styles avec !important
  const gradient = 'linear-gradient(to right, #aaa 0%, #aaa '+perc+'%, rgba(0,0,0,0.1) '+perc+'%, rgba(0,0,0,0.1) 100%)';
  footer.setAttribute('style', 'background: '+gradient+' !important; position: fixed !important; bottom: 0 !important; left: 0 !important; right: 0 !important; height: 4px !important; z-index: 1001 !important; display: block !important; visibility: visible !important; opacity: 1 !important;');
}

var pressedKeys = [],
  konamiCode = [38, 38, 40, 40, 37, 39, 37, 39, 66, 65]; //, konamized = false;
//var timerkona;

var konamiGameLoop;
function konami() {
  DM.innerHTML = '<div id="konami" class="item1"><div class="date">Now!</div><a id="game" class="title">Easter Egg - Dragon Fly - Hold any key to fly - Press ESC to exit</a><div class="author">From <a href="https://js1k.com/2014-dragons/demo/1955" target="_blank">js1k.com</a> by mouminoux</div><div class="descr"><canvas id="c" style="border: 2px solid #333; display: block; margin: 20px auto;"></canvas></div><div class="action">&nbsp;&nbsp;☺ ☻ ☺ ☻ </div></div>'+DM.innerHTML;
  $('konami').style.display='block';
  DM.scrollTop = 0;
  kona = 1;

  // Dragon Fly game from js1k.com/2014-dragons/demo/1955
  var a = $('c');
  var c = a.getContext('2d');

  // Setup canvas size
  a.style.width = (a.width = 1e3) + "px";
  a.style.height = (a.height = 500) + "px";

  // Create canvas method shortcuts
  for(p in c) {
    c[p[0] + (p[6] || "")] = c[p];
  }

  // Game variables
  var J, K, C, B, u, D, E, F, G, H, I, L, M, N, O, t, U, K2, Q, R, i;
  J = K = C = B = 0;
  u = 50;
  D = 250;
  E = F = G = H = 1;
  I = 250;

  // Game loop
  konamiGameLoop = setInterval(function() {
    L = 300;
    M = 500;
    N = 400;

    with(c) {
      A = function(S, x, y, T) {
        T && a(x, y, T, 0, 7, 0);
        fillStyle = S.P ? S : "#" + "ceff99aaffff333f99ff7".substr(S * 3, 3);
        fill();
        ba();
      };

      A(0, 0, 0, 10000);
      A(6, 800, 300, 140);
      O = G * u % 400;
      l(0 - O, N + 100);
      l(0 - O, N);

      for(i = 0; i < 7; i++) {
        qt(i * 200 + 100 - O, i % 2 ? L : M, i * 200 + 200 - O, N);
      }

      l(i * 200 - O, N + 100);
      ca();
      fillStyle = "#5c1";
      fill();
      ba();

      t = (G * (u + E) + I) % 200 / 200;
      U = 1 - t;
      K2 = U * U * N + 2 * t * U * ((G * (u + E) + I) % 400 > 200 ? L : M) + t * t * N;
      D += F;

      if(D >= K) {
        if(K2 < K) {
          if(F > 0 && (K2 - K) < 0 && !J) {
            E = E / 3;
            K2 = K;
          }
          E -= E < 2 ? 0 : 0.1;
        } else {
          E += 0.1 * H * H;
        }
        J = 1;
        D = K;
        if(K2 < K && F > (K2 - K)) {
          F = (K2 - K);
        }
      } else {
        J = 0;
      }

      F += 0.4 * H;
      K = K2;
      A(3 - H, I, D - 10, 10);
      A(3, I + 3, D - 12, 4);
      A(4, I + 4, D - 12, 1);
      Q = 200 + Math.pow(B, 1.3) - u;
      R = 50 + Math.sin(B / 2) * 2;
      A(1, Q, R, 30);
      A(3, Q + 20, R + 5, 7);
      A(4, Q + 22, R + 7, 2);
      A(6, 0, 0, 0);
      fx(++B + " ☆", 5, 10);
      fx((C < B ? C = B : C) + " ★", 40, 10);

      if(Q > 4 * I) {
        clearInterval(konamiGameLoop);
        setTimeout(function() {
          if(confirm("Game Over! Score: " + B + "\nBest: " + C + "\n\nPlay again?")) {
            E = F = u = B = K = 2;
            konamiGameLoop = setInterval(arguments.callee, 30);
          }
        }, 100);
      }
    }
    u += E;
  }, 30);

  // Key handler for dragon fly
  window.konamiKeyHandler = function(evt) {
    var b = evt;
    H = b.type[5] ? 2 : 1;
  };
}

function konamistop() {
  kona = 0;
  if(konamiGameLoop) clearInterval(konamiGameLoop);
  if($('konami')) $('konami').style.display = 'none';
  window.konamiKeyHandler = null;
}


/*function lazyLoadImg() {
//	var lazyloadImages = document.querySelectorAll("img.lazy");

    //plus rapide, voir support, retourne un HTMLCollections au lieu d'un NodeList d'ou le array.from devant
    let lazyloadImages = Array.from(document.getElementsByClassName("lazy"));
//firefox75 le supporte en natif !
	if(hasSupportLoading) {
		lazyloadImages.forEach(function(img) {
			img.src = img.dataset.src;
      img.classList.remove('lazy');
		});
		return 0;
	}


    if ("IntersectionObserver" in window) {
        var imageObserver = new IntersectionObserver(function(entries, observer) {
                entries.forEach(function(entry) {
                        if (entry.isIntersecting) {
                            var image = entry.target;
                            image.src = image.dataset.src;
                            //pour lancer la détection des webp pour safari et ie
                            //         WebpHero();
                            image.classList.remove("lazy");
                            imageObserver.unobserve(image);
                        }
                    });
            }, {
            root: DM,
                    rootMargin: "0px 0px 500px 0px"
                    });

        lazyloadImages.forEach(function(image) {
                imageObserver.observe(image);
            });
    } else {
        var lazyloadThrottleTimeout;
        function lazyload () {
            if(lazyloadThrottleTimeout) {
                clearTimeout(lazyloadThrottleTimeout);
            }
            lazyloadThrottleTimeout = setTimeout(function() {
                    var scrollTop = window.pageYOffset;
                    lazyloadImages.forEach(function(img) {
                            if(img.offsetTop < (window.innerHeight + scrollTop + 500)) {
                                img.src = img.dataset.src;
                                img.classList.remove('lazy');
                            }
                        });
                    if(lazyloadImages.length == 0) {
                        document.removeEventListener("scroll", lazyload);
                        window.removeEventListener("resize", lazyload);
                        window.removeEventListener("orientationChange", lazyload);
                    }
                }, 20);
        }
        document.addEventListener("scroll", lazyload);
        window.addEventListener("resize", lazyload);
        window.addEventListener("orientationChange", lazyload);
    }
}
*/

function i() {
  // Initialize DM now that DOM is ready
  DM = document.getElementsByTagName("main")[0];

  // Initialize scroll handlers (bounce effect, fallback marking)
  initScrollHandlers();

  // Initialize favicon_badge first (needed by renderMenu)
  favicon_badge=new Favico({
    animation:'none'
   // animation:'slide'
  });

  // Set "All" as selected by default
  if ($('fall')) {
    $('fall').classList.add('show');
  }

  // Initialize theme selector icon
  updateThemeIcon();

  // Then load data
  loadData('all');

  // Start SSE connection for real-time updates (only if user is logged in)
  if (DM) {
    startSSEConnection();
  }

  window.addEventListener('online', handleConnectionChange);
  window.addEventListener('offline', handleConnectionChange);
  window.addEventListener('beforeunload', stopSSEConnection);
  window.onresize = scroll;

  // Only set up search input handlers if element exists (not on login page)
  if ($('s')) {
    $('s').onfocus = function() {
      search_focus = 1;
      //log("Focus sur l'input de recherche.");
    };
    $('s').onblur = function() {
      search_focus = 0;
      //log("Perte du focus sur l'input de recherche.");
    };
  }
  D.onkeydown = function(evt) {
    if (search_focus === 1) return;
    var k = (evt.which) ? evt.which : evt.keyCode;
    if (kona == 1) {
      if (k == 27) {
        konamistop();
        return;
      }
      if (window.konamiKeyHandler) {
        window.konamiKeyHandler(evt);
      }
      return;
    }
    if (pressedKeys.length == konamiCode.length) pressedKeys.shift();
    pressedKeys.push(k);
    if (pressedKeys.toString() == konamiCode.toString()) konami();
    switch (k) {
      case 33:
        goPrevPage();
        break;
      case 34:
        goNextPage();
        break;
      case 78:
        goNext();
        break;
      case 39:
        goNext();
        break;
      case 80:
        goPrev();
        break;
      case 37:
        goPrev();
        break;
      case 38:
        goUp();
        break;
      case 40:
        goDown();
        break;
      case 71:
        goGoogle();
        break;
      case 87:
        goWikipedia();
        break;
      case 79:
        openActif();
        break;
      default:
        //log("Touche non implémentée. Code : " + k);
        break;
    }
    k = undefined;
  };

  const resizer = document.getElementById('menu-resizer');
const nav = document.querySelector('nav');
const main = document.querySelector('main');

// Only set up menu resizer if elements exist (not on login page)
if (resizer && nav && main) {
  let isResizing = false;

  resizer.addEventListener('mousedown', (e) => {
  isResizing = true;
  document.body.classList.add('resizing');

  const onMouseMove = (e) => {
    if (!isResizing) return;
    let newWidth = e.clientX;
    if (newWidth < 20) {
      newWidth = 0;
      resizer.innerHTML ='⇹';
      resizer.style.height='auto';
    }
    else {
      resizer.innerHTML ='';
      resizer.style.height='100%';
    }
    if (newWidth > window.innerWidth * 0.6) newWidth = window.innerWidth * 0.6;

    // Appliquer la nouvelle largeur
    nav.style.width = newWidth + 'px';
    resizer.style.left = newWidth + 'px';
 //   nav.style.left = (newWidth + 6) + 'px';
    main.style.left = newWidth + 'px';
    // Optionnel : sauvegarde
    localStorage.setItem('menuWidth', newWidth);
  };

  const onMouseUp = () => {
    isResizing = false;
    document.body.classList.remove('resizing');
    document.removeEventListener('mousemove', onMouseMove);
    document.removeEventListener('mouseup', onMouseUp);
  };

  document.addEventListener('mousemove', onMouseMove);
  document.addEventListener('mouseup', onMouseUp);
  });

  // Charger la largeur sauvegardée au démarrage
  window.addEventListener('DOMContentLoaded', () => {
    const savedWidth = localStorage.getItem('menuWidth');
    if (savedWidth) {
      nav.style.width = (parseInt(savedWidth)) + 'px';
      resizer.style.left = (parseInt(savedWidth)) + 'px';
      if(savedWidth < 20) {
        resizer.innerHTML ='⇹';
        resizer.style.height='auto';
      }
      main.style.left = (parseInt(savedWidth)) + 'px';
    }
  });
}

}

function openActif() {
  if (kona == 1) return;
  if (!d) return;
  for (var k = 0, len = d.i.length; k <= len; k++) {
    if (!(d.i[k])) {
      return;
    } else if ($(d.i[k].i).offsetTop >= DM.scrollTop) {
      //log("Fenêtre active : " + d.i[k].l);
      window.focus();
      window.open(d.i[k].l).blur();
      window.self.focus();
      goNext();
      return;
    }
  }
  k = len = undefined;
  log("Fenêtre active non trouvée!");
}

function goWikipedia() {
  var t = getSelectedText();
  if (t && t !== '')
    window.open("//fr.wikipedia.org/wiki/Special:Search?search=" + t);
  return false;
}

function goGoogle() {
  var t = getSelectedText();
  if (t && t !== '')
    window.open("https://www.google.fr/search?q=" + t + "&ie=utf-8&oe=utf-8&lr=lang_fr");
  return false;
}

function more() {
  var xhr = getHTTPObject('more');
  xhr.open('POST', 'view.php', true);
  xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
  if (id && id !== 'all') xhr.send('xhr=1&nb=10&id=' + id);
  else xhr.send('xhr=1&nb=10');
  requestTimer = setTimeout((function() {
    if (xhr) xhr.abort();
  }), 4000);
  xhr = undefined;
}

function log(t) {
  if (typeof console !== 'undefined') {
    if(typeof t == 'object')
      console.table(t);
    else
      console.log(t);
  }
}


function affError(text,n) {
	n = typeof n !== 'undefined' ? n : 4;
	if('Notification' in window){
		Notification.requestPermission(function(permission){
			if (permission === 'granted') {
				var notification = new Notification('Gheop Reader',{
					body : text,
                   // icon : 'alert.png'
               });
				setTimeout(function(){ notification.close() }, n*1000);
			}
			else
				notif = true;
		});
	}
	else {
		notif = true;
	}
	if(notif) {
		$('error').innerHTML = text;
		$('error').style.display = 'block';
		setTimeout(delError, n*1000);
	}
}

/*
function showPaintTimings() {
  if (window.performance) {
    let performance = window.performance;
    let performanceEntries = performance.getEntriesByType('paint');
    performanceEntries.forEach( (performanceEntry, i, entries) => {
      console.log("The time to " + performanceEntry.name + " was " + performanceEntry.startTime + " milliseconds.");
    });
  } else {
    console.log('Performance timing isn\'t supported.');
  }
}*/
//document.onload = i(); // REMOVED: was calling i() immediately instead of on load, and we already have DOMContentLoaded listener
//showPaintTimings();
//i();



// Charger le thème sauvegardé au démarrage
window.addEventListener('DOMContentLoaded', () => {
  const savedTheme = localStorage.getItem('theme');

  if (savedTheme === 'auto') {
    // Mode automatique : suivre la préférence système
    applyAutoTheme();
  } else if (savedTheme === 'dark') {
    // Utilisateur a choisi le thème sombre
    $('stylesheet').href = 'themes/dark.min.css';
  } else if (savedTheme === 'light') {
    // Utilisateur a choisi le thème clair
    $('stylesheet').href = 'themes/light.min.css';
  } else if (savedTheme === 'adaptive') {
    // Utilisateur a choisi le thème adaptatif
    $('stylesheet').href = 'themes/adaptive.min.css';
    // Démarrer le thème adaptatif après un court délai
    setTimeout(() => {
      if (window.startAdaptiveTheme) {
        startAdaptiveTheme();
      }
    }, 100);
  } else if (savedTheme === 'smooth') {
    // Utilisateur a choisi le thème smooth progressif
    $('stylesheet').href = 'themes/adaptive-smooth.min.css';
    // Démarrer le thème smooth après un court délai
    setTimeout(() => {
      if (window.startSmoothAdaptiveTheme) {
        startSmoothAdaptiveTheme();
      }
    }, 100);
  } else if (savedTheme === 'modern') {
    // Utilisateur a choisi le thème moderne
    $('stylesheet').href = 'themes/modern.min.css';
  } else {
    // Pas de préférence sauvegardée : utiliser prefers-color-scheme
    if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
      $('stylesheet').href = 'themes/dark.min.css';
    }
    // Sinon on garde light.min.css qui est déjà chargé par défaut
  }

  // Mettre à jour l'icône du thème après un court délai pour s'assurer que le DOM est prêt
  setTimeout(() => {
    if (window.updateThemeIcon) {
      updateThemeIcon();
    }
  }, 100);

  // Écouter les changements de préférence système (uniquement en mode auto)
  if (window.matchMedia) {
    const darkModeQuery = window.matchMedia('(prefers-color-scheme: dark)');
    darkModeQuery.addEventListener('change', (e) => {
      const savedTheme = localStorage.getItem('theme');
      // Appliquer le changement seulement si en mode auto
      if (savedTheme === 'auto') {
        console.log('System theme changed to:', e.matches ? 'dark' : 'light');
        applyAutoTheme();
      }
    });
  }
});

// ============================================================================
// ADAPTIVE THEME - Color interpolation and time-based transitions
// ============================================================================

// Couleurs du thème clair (6h-10h → clair, 10h-16h → clair)
const lightTheme = {
  bgBody: '#ffffff',
  bgMain: '#eeeeee',
  bgItem: '#ffffff',
  bgShow: '#e8e8e8',
  bgInput: '#ffffff',
  textBody: '#1a1a1a',      // Plus foncé pour meilleur contraste
  textLink: '#2a2a2a',      // Plus foncé pour meilleur contraste
  textLight: '#666666',     // Plus foncé pour meilleur contraste
  textWhite: '#333333',     // Gris foncé au lieu de blanc pur (pour .show)
  shadowItem: 'rgba(0, 0, 0, 0.15)',
  border: '#dddddd'         // Bordures plus visibles
};

// Couleurs du thème sombre (16h-20h → sombre, 20h-6h → sombre)
const darkTheme = {
  bgBody: '#1a1a1a',
  bgMain: '#2b2b2b',
  bgItem: '#3a3a3a',
  bgShow: '#2b2b2b',
  bgInput: '#333333',
  textBody: '#e0e0e0',      // Plus clair pour meilleur contraste
  textLink: '#b8b8b8',      // Plus clair pour meilleur contraste
  textLight: '#999999',     // Plus clair pour meilleur contraste
  textWhite: '#f5f5f5',     // Blanc cassé au lieu de blanc pur
  shadowItem: 'rgba(0, 0, 0, 0.5)',
  border: '#4a4a4a'
};

// Interpoler entre deux valeurs hex ou rgba
function interpolateColor(color1, color2, factor) {
  // Si c'est rgba
  if (color1.startsWith('rgba')) {
    const rgba1 = color1.match(/[\d.]+/g).map(Number);
    const rgba2 = color2.match(/[\d.]+/g).map(Number);
    const r = Math.round(rgba1[0] + (rgba2[0] - rgba1[0]) * factor);
    const g = Math.round(rgba1[1] + (rgba2[1] - rgba1[1]) * factor);
    const b = Math.round(rgba1[2] + (rgba2[2] - rgba1[2]) * factor);
    const a = rgba1[3] + (rgba2[3] - rgba1[3]) * factor;
    return `rgba(${r}, ${g}, ${b}, ${a})`;
  }

  // Si c'est hex
  const hex1 = color1.replace('#', '');
  const hex2 = color2.replace('#', '');

  const r1 = parseInt(hex1.substr(0, 2), 16);
  const g1 = parseInt(hex1.substr(2, 2), 16);
  const b1 = parseInt(hex1.substr(4, 2), 16);

  const r2 = parseInt(hex2.substr(0, 2), 16);
  const g2 = parseInt(hex2.substr(2, 2), 16);
  const b2 = parseInt(hex2.substr(4, 2), 16);

  const r = Math.round(r1 + (r2 - r1) * factor);
  const g = Math.round(g1 + (g2 - g1) * factor);
  const b = Math.round(b1 + (b2 - b1) * factor);

  return `rgb(${r}, ${g}, ${b})`;
}

// Calculer l'intensité du thème selon l'heure
function calculateThemeIntensity() {
  const now = new Date();
  const hours = now.getHours();
  const minutes = now.getMinutes();
  const totalMinutes = hours * 60 + minutes;

  // 6h-10h : transition de sombre (1) à clair (0)
  const morningStart = 6 * 60;  // 6h00 = 360 min
  const morningEnd = 10 * 60;   // 10h00 = 600 min

  // 16h-20h : transition de clair (0) à sombre (1)
  const eveningStart = 16 * 60; // 16h00 = 960 min
  const eveningEnd = 20 * 60;   // 20h00 = 1200 min

  let intensity;

  if (totalMinutes >= morningStart && totalMinutes < morningEnd) {
    // 6h-10h : transition progressive de 1 (sombre) à 0 (clair)
    const progress = (totalMinutes - morningStart) / (morningEnd - morningStart);
    intensity = 1 - progress; // Diminue de 1 à 0
  } else if (totalMinutes >= morningEnd && totalMinutes < eveningStart) {
    // 10h-16h : reste clair
    intensity = 0;
  } else if (totalMinutes >= eveningStart && totalMinutes < eveningEnd) {
    // 16h-20h : transition progressive de 0 (clair) à 1 (sombre)
    const progress = (totalMinutes - eveningStart) / (eveningEnd - eveningStart);
    intensity = progress; // Augmente de 0 à 1
  } else {
    // 20h-6h : reste sombre
    intensity = 1;
  }

  return intensity;
}

// Appliquer le thème adaptatif
function applyAdaptiveTheme() {
  const rawIntensity = calculateThemeIntensity();
  const root = document.documentElement;

  // Utiliser un seuil strict pour éviter les zones de faible contraste
  // Changement brusque mais CSS transitions (0.5s) le rendent fluide visuellement
  // - Si rawIntensity <= 0.5 → thème clair complet (intensity = 0)
  // - Si rawIntensity > 0.5 → thème sombre complet (intensity = 1)
  // Cela garantit toujours un bon contraste
  let intensity = rawIntensity <= 0.5 ? 0 : 1;

  // Interpoler toutes les couleurs
  root.style.setProperty('--adaptive-bg-body', interpolateColor(lightTheme.bgBody, darkTheme.bgBody, intensity));
  root.style.setProperty('--adaptive-bg-main', interpolateColor(lightTheme.bgMain, darkTheme.bgMain, intensity));
  root.style.setProperty('--adaptive-bg-item', interpolateColor(lightTheme.bgItem, darkTheme.bgItem, intensity));
  root.style.setProperty('--adaptive-bg-show', interpolateColor(lightTheme.bgShow, darkTheme.bgShow, intensity));
  root.style.setProperty('--adaptive-bg-input', interpolateColor(lightTheme.bgInput, darkTheme.bgInput, intensity));
  root.style.setProperty('--adaptive-text-body', interpolateColor(lightTheme.textBody, darkTheme.textBody, intensity));
  root.style.setProperty('--adaptive-text-link', interpolateColor(lightTheme.textLink, darkTheme.textLink, intensity));
  root.style.setProperty('--adaptive-text-light', interpolateColor(lightTheme.textLight, darkTheme.textLight, intensity));
  root.style.setProperty('--adaptive-text-white', interpolateColor(lightTheme.textWhite, darkTheme.textWhite, intensity));
  root.style.setProperty('--adaptive-shadow-item', interpolateColor(lightTheme.shadowItem, darkTheme.shadowItem, intensity));
  root.style.setProperty('--adaptive-border', interpolateColor(lightTheme.border, darkTheme.border, intensity));

  // Mettre à jour l'intensité
  root.style.setProperty('--theme-intensity', intensity);

  // Log pour debug
  const now = new Date();
  console.log(`Adaptive theme: ${now.getHours()}:${String(now.getMinutes()).padStart(2, '0')} - raw: ${rawIntensity.toFixed(2)}, adjusted: ${intensity.toFixed(2)}`);
}

// Démarrer le thème adaptatif
function startAdaptiveTheme() {
  // Appliquer immédiatement
  applyAdaptiveTheme();

  // Mettre à jour toutes les 5 minutes
  setInterval(applyAdaptiveTheme, 5 * 60 * 1000);
}

// Exporter pour utilisation globale
window.startAdaptiveTheme = startAdaptiveTheme;
window.applyAdaptiveTheme = applyAdaptiveTheme;

// ============================================================================
// ADAPTIVE SMOOTH THEME - Continuous smooth transitions with warm colors
// ============================================================================

// Palette "chaude" avec GRAND contraste à tous les stades
// Astuce : On garde les extrêmes très éloignés pour que le milieu reste lisible
const smoothLightTheme = {
  bgBody: '#faf7ef',        // Beige très clair (même que bgMain)
  bgMain: '#faf7ef',        // Beige très clair
  bgItem: '#ffffff',        // Blanc pur pour les cartes
  bgShow: '#eae4d5',        // Beige moyen
  bgInput: '#ffffff',       // Blanc
  textBody: '#1a0f08',      // Brun presque noir (TRÈS foncé)
  textLink: '#7a3e0a',      // Brun orangé foncé
  textLight: '#5c4a3a',     // Brun moyen
  shadowItem: 'rgba(101, 67, 33, 0.15)',
  border: '#d4caba',        // Beige
  accent: '#c65d1f'         // Orange vif
};

const smoothDarkTheme = {
  bgBody: '#0f0b08',        // Brun presque noir (TRÈS sombre)
  bgMain: '#1f1812',        // Brun très sombre
  bgItem: '#2f2519',        // Brun sombre
  bgShow: '#1f1812',        // Brun très sombre
  bgInput: '#292016',       // Brun sombre
  textBody: '#fff9ed',      // Beige presque blanc (TRÈS clair)
  textLink: '#ffb366',      // Orange clair
  textLight: '#d4c4ae',     // Beige clair
  shadowItem: 'rgba(0, 0, 0, 0.5)',
  border: '#4a3f2f',        // Brun moyen
  accent: '#ff9966'         // Orange corail
};

// Fonction d'application pour smooth theme (avec interpolation continue)
function applySmoothAdaptiveTheme() {
  const rawIntensity = calculateThemeIntensity();
  const root = document.documentElement;

  // Pour smooth : on garde l'interpolation progressive (pas de seuil)
  // L'astuce est dans la palette de couleurs qui garde toujours du contraste
  const intensity = rawIntensity;

  // Interpoler toutes les couleurs
  root.style.setProperty('--adaptive-bg-body', interpolateColor(smoothLightTheme.bgBody, smoothDarkTheme.bgBody, intensity));
  root.style.setProperty('--adaptive-bg-main', interpolateColor(smoothLightTheme.bgMain, smoothDarkTheme.bgMain, intensity));
  root.style.setProperty('--adaptive-bg-item', interpolateColor(smoothLightTheme.bgItem, smoothDarkTheme.bgItem, intensity));
  root.style.setProperty('--adaptive-bg-show', interpolateColor(smoothLightTheme.bgShow, smoothDarkTheme.bgShow, intensity));
  root.style.setProperty('--adaptive-bg-input', interpolateColor(smoothLightTheme.bgInput, smoothDarkTheme.bgInput, intensity));
  root.style.setProperty('--adaptive-text-body', interpolateColor(smoothLightTheme.textBody, smoothDarkTheme.textBody, intensity));
  root.style.setProperty('--adaptive-text-link', interpolateColor(smoothLightTheme.textLink, smoothDarkTheme.textLink, intensity));
  root.style.setProperty('--adaptive-text-light', interpolateColor(smoothLightTheme.textLight, smoothDarkTheme.textLight, intensity));
  root.style.setProperty('--adaptive-shadow-item', interpolateColor(smoothLightTheme.shadowItem, smoothDarkTheme.shadowItem, intensity));
  root.style.setProperty('--adaptive-border', interpolateColor(smoothLightTheme.border, smoothDarkTheme.border, intensity));
  root.style.setProperty('--adaptive-accent', interpolateColor(smoothLightTheme.accent, smoothDarkTheme.accent, intensity));

  root.style.setProperty('--theme-intensity', intensity);

  const now = new Date();
  console.log(`Smooth adaptive theme: ${now.getHours()}:${String(now.getMinutes()).padStart(2, '0')} - intensity: ${intensity.toFixed(2)} (progressive)`);
}

function startSmoothAdaptiveTheme() {
  applySmoothAdaptiveTheme();
  setInterval(applySmoothAdaptiveTheme, 5 * 60 * 1000);
}

window.startSmoothAdaptiveTheme = startSmoothAdaptiveTheme;
window.applySmoothAdaptiveTheme = applySmoothAdaptiveTheme;

// ============================================================================
// DEBUG FUNCTIONS - Test adaptive theme at different times
// ============================================================================

// Fonction de test pour simuler une heure spécifique
window.testAdaptiveThemeAt = function(hours, minutes = 0) {
  if (typeof hours !== 'number' || hours < 0 || hours > 23) {
    console.error('❌ Heure invalide. Utilisez un nombre entre 0 et 23.');
    console.log('💡 Exemple: testAdaptiveThemeAt(8, 30) pour tester à 8h30');
    return;
  }

  if (typeof minutes !== 'number' || minutes < 0 || minutes > 59) {
    console.error('❌ Minutes invalides. Utilisez un nombre entre 0 et 59.');
    return;
  }

  // Vérifier qu'on est bien en mode adaptatif
  if (!$('stylesheet').href.includes('adaptive.css')) {
    console.warn('⚠️  Le thème adaptatif n\'est pas activé. Changez de thème d\'abord !');
    console.log('💡 Cliquez sur l\'icône de thème pour cycler jusqu\'au mode adaptatif (icône horloge)');
    return;
  }

  const totalMinutes = hours * 60 + minutes;
  const morningStart = 6 * 60;
  const morningEnd = 10 * 60;
  const eveningStart = 16 * 60;
  const eveningEnd = 20 * 60;

  let rawIntensity;
  let phase;

  if (totalMinutes >= morningStart && totalMinutes < morningEnd) {
    const progress = (totalMinutes - morningStart) / (morningEnd - morningStart);
    rawIntensity = 1 - progress;
    phase = '🌅 Transition matin (sombre → clair)';
  } else if (totalMinutes >= morningEnd && totalMinutes < eveningStart) {
    rawIntensity = 0;
    phase = '☀️  Jour (clair)';
  } else if (totalMinutes >= eveningStart && totalMinutes < eveningEnd) {
    const progress = (totalMinutes - eveningStart) / (eveningEnd - eveningStart);
    rawIntensity = progress;
    phase = '🌆 Transition soir (clair → sombre)';
  } else {
    rawIntensity = 1;
    phase = '🌙 Nuit (sombre)';
  }

  // Appliquer le même seuil strict que dans applyAdaptiveTheme()
  let intensity = rawIntensity <= 0.5 ? 0 : 1;

  console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
  console.log(`🕐 Test à ${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`);
  console.log(`📊 Intensité: ${intensity.toFixed(2)} (0=clair, 1=sombre)`);
  console.log(`🎨 Phase: ${phase}`);
  console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

  // Appliquer les couleurs
  const root = document.documentElement;
  root.style.setProperty('--adaptive-bg-body', interpolateColor(lightTheme.bgBody, darkTheme.bgBody, intensity));
  root.style.setProperty('--adaptive-bg-main', interpolateColor(lightTheme.bgMain, darkTheme.bgMain, intensity));
  root.style.setProperty('--adaptive-bg-item', interpolateColor(lightTheme.bgItem, darkTheme.bgItem, intensity));
  root.style.setProperty('--adaptive-bg-show', interpolateColor(lightTheme.bgShow, darkTheme.bgShow, intensity));
  root.style.setProperty('--adaptive-bg-input', interpolateColor(lightTheme.bgInput, darkTheme.bgInput, intensity));
  root.style.setProperty('--adaptive-text-body', interpolateColor(lightTheme.textBody, darkTheme.textBody, intensity));
  root.style.setProperty('--adaptive-text-link', interpolateColor(lightTheme.textLink, darkTheme.textLink, intensity));
  root.style.setProperty('--adaptive-text-light', interpolateColor(lightTheme.textLight, darkTheme.textLight, intensity));
  root.style.setProperty('--adaptive-text-white', interpolateColor(lightTheme.textWhite, darkTheme.textWhite, intensity));
  root.style.setProperty('--adaptive-shadow-item', interpolateColor(lightTheme.shadowItem, darkTheme.shadowItem, intensity));
  root.style.setProperty('--adaptive-border', interpolateColor(lightTheme.border, darkTheme.border, intensity));
  root.style.setProperty('--theme-intensity', intensity);

  console.log('✅ Thème appliqué ! Regardez les changements dans l\'interface.');
  console.log('');
  console.log('💡 Astuce: Testez rapidement plusieurs heures:');
  console.log('   testAllHours()  - Affiche l\'intensité pour toutes les heures');
  console.log('   testAdaptiveThemeAt(6)   - Matin (début transition)');
  console.log('   testAdaptiveThemeAt(8)   - Matin (milieu)');
  console.log('   testAdaptiveThemeAt(14)  - Jour');
  console.log('   testAdaptiveThemeAt(18)  - Soir (milieu transition)');
  console.log('   testAdaptiveThemeAt(22)  - Nuit');
};

// Fonction pour tester toutes les heures de la journée
window.testAllHours = function() {
  console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
  console.log('📊 INTENSITÉ DU THÈME ADAPTATIF SUR 24 HEURES');
  console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
  console.log('');

  const morningStart = 6 * 60;
  const morningEnd = 10 * 60;
  const eveningStart = 16 * 60;
  const eveningEnd = 20 * 60;

  for (let h = 0; h < 24; h++) {
    const totalMinutes = h * 60;
    let intensity;
    let bar = '';
    let phase = '';

    if (totalMinutes >= morningStart && totalMinutes < morningEnd) {
      const progress = (totalMinutes - morningStart) / (morningEnd - morningStart);
      intensity = 1 - progress;
      phase = '🌅';
    } else if (totalMinutes >= morningEnd && totalMinutes < eveningStart) {
      intensity = 0;
      phase = '☀️ ';
    } else if (totalMinutes >= eveningStart && totalMinutes < eveningEnd) {
      const progress = (totalMinutes - eveningStart) / (eveningEnd - eveningStart);
      intensity = progress;
      phase = '🌆';
    } else {
      intensity = 1;
      phase = '🌙';
    }

    // Créer une barre visuelle
    const barLength = Math.round(intensity * 20);
    const lightLength = 20 - barLength;
    bar = '█'.repeat(barLength) + '░'.repeat(lightLength);

    console.log(`${phase} ${String(h).padStart(2, '0')}h  [${bar}] ${intensity.toFixed(2)}`);
  }

  console.log('');
  console.log('Légende: 0.00 = clair maximum, 1.00 = sombre maximum');
  console.log('🌙 Nuit  |  🌅 Transition matin  |  ☀️  Jour  |  🌆 Transition soir');
  console.log('');
  console.log('💡 Pour tester une heure: testAdaptiveThemeAt(14) ou testAdaptiveThemeAt(18, 30)');
  console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
};

// Message d'aide au démarrage (seulement en mode adaptatif)
window.addEventListener('DOMContentLoaded', () => {
  setTimeout(() => {
    if ($('stylesheet') && $('stylesheet').href.includes('adaptive.css')) {
      console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
      console.log('🎨 THÈME ADAPTATIF ACTIF');
      console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
      console.log('');
      console.log('📝 Commandes disponibles dans la console:');
      console.log('');
      console.log('  testAdaptiveThemeAt(14)      - Tester le thème à 14h');
      console.log('  testAdaptiveThemeAt(18, 30)  - Tester le thème à 18h30');
      console.log('  testAllHours()               - Voir l\'intensité sur 24h');
      console.log('');
      console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    }

    // Initialiser le footer dès le chargement de la page
    if (window.progressBar) {
      progressBar();
    }
  }, 500);
});

// Auto-initialize when user is logged in (menu exists)
// Always wait for DOMContentLoaded to ensure DOM is fully ready
document.addEventListener('DOMContentLoaded', () => {
  if (document.getElementById('menu')) {
    i();
  }
});
