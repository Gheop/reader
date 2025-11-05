/**
 * API Cache Manager - localStorage optimization
 * Handles caching of menu and articles data for instant loading
 */

var syncInterval = null;
var cacheVersion = 'v1';

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
// LOAD DATA FROM API (combined menu + articles)
// ============================================================================

function loadData(feedId, useCache = true) {
  // Try to load from cache first for instant display
  if (useCache) {
    const cached = loadFromCache();
    if (cached && cached.menu && cached.articles) {
      console.log('Loading from cache (age: ' + Math.round(getCacheAge()/1000) + 's)');

      // Display cached data immediately
      renderMenu(cached.menu);
      renderArticles(cached.articles, feedId || 'all');

      // Then load fresh data in background
      setTimeout(() => fetchAndUpdateData(feedId), 100);
      return;
    }
  }

  // No cache available, load from server
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

        // Save to cache
        saveToCache(data);

        // Update display
        renderMenu(data.menu);
        renderArticles(data.articles, feedId || 'all');
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

function renderMenu(menuData) {
  nb_title = 0;
  m = menuData;

  var menu = '\t<li id="fsearch" class="flux" title="Recherche" onclick="return false;">Résultats de la recherche</li>\n';

  for(var i in m) {
    if (m[i].n > 0) {
      menu += '\t<li id="f' + i + '" class="fluxnew" title="' + m[i].d + '" onclick="view(' + i + ');">'  + m[i].t + '<span class="nb_flux"> ' + m[i].n + '</span> <span class="icon"><a title="Tout marquer comme lu" onclick="markallread(' + i + ')"></a> <a title="Se désabonner" onclick="unsubscribe(\'' + m[i].t.replace(/'/g, "\\\'") + '\', ' + i + ')"></a></span></li>\n';
      nb_title += m[i].n || 0;
    }
  }

  // Clear existing menu items (except first li#fall)
  const menuEl = $('menu');
  while (menuEl.children.length > 1) {
    menuEl.removeChild(menuEl.lastChild);
  }

  menuEl.insertAdjacentHTML('beforeend', menu);
  D.title = 'Gheop Reader' + ((nb_title > 0) ? ' (' + nb_title + ')' : '');
  favicon(nb_title);
  totalItems = nb_title;
  readItems = 0;
  progressBar();
}

function renderArticles(articlesData, feedId) {
  let page = '';
  cptReadArticle = 0;
  varscroll = 0;
  loadmore = 0;
  d = articlesData;
  Now = new Date();

  for(let i in d) {
    // Filter by feed if needed
    if (feedId && feedId !== 'all' && d[i].f != feedId) {
      continue;
    }
    loadmore++;
    page += generateArticle(i);
  }

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
// BACKGROUND SYNC
// ============================================================================

function startBackgroundSync(intervalSeconds = 30) {
  if (syncInterval) {
    clearInterval(syncInterval);
  }

  syncInterval = setInterval(() => {
    console.log('Background sync...');
    fetchAndUpdateData(id);
  }, intervalSeconds * 1000);
}

function stopBackgroundSync() {
  if (syncInterval) {
    clearInterval(syncInterval);
    syncInterval = null;
  }
}

// Start sync when page is visible, stop when hidden
if (typeof document.addEventListener !== 'undefined') {
  document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
      stopBackgroundSync();
    } else {
      startBackgroundSync(30);
    }
  });
}
