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
var cacheVersion = 'v1';
var locallyModifiedArticles = {}; // Track articles manually marked as unread
var eventSource = null; // SSE connection
const hasSupportLoading = 'loading' in HTMLImageElement.prototype;
if(! /iPad|iPhone|iPod/.test(navigator.platform)) {
	const locale = navigator.language;
 	rtf = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' });
}
const D = document;
const DM = document.getElementsByTagName("main")[0];
var cptReadArticle = 0;
var imageObserver;

var inactivityTime = function () {
  var t;
  window.onload = resetTimer;

  document.onmousemove = resetTimer;
  document.onkeydown = resetTimer;
  document.onload = resetTimer;
  document.onmousedown = resetTimer; // touchscreen presses
  document.ontouchstart = resetTimer;
  document.onclick = resetTimer;     // touchpad clicks
  document.onscroll = resetTimer;    // scrolling with arrow keys

  function rearm() {
  	if(online)
	    document.location.reload(true);
  }
  function resetTimer() {
    clearTimeout(t);
    t = setTimeout(rearm, 300000);
        // 1000 milisec = 1 sec
  }
};

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
        renderArticles(data.articles, displayFeed);
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
  // Save old counters for comparison
  var oldCounters = {};
  var oldFeedsVisible = {};
  if (m) {
    for(var i in m) {
      oldCounters[i] = m[i].n || 0;
      oldFeedsVisible[i] = m[i].n > 0;
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

function renderArticles(articlesData, feedId) {
  console.log('renderArticles called with feedId:', feedId);
  console.log('articlesData keys:', Object.keys(articlesData).length);
  let page = '';
  cptReadArticle = 0;
  varscroll = 0;
  loadmore = 0;
  d = articlesData;

  // Initialize read state for articles if not already set (first load from API)
  for(let i in d) {
    if (d[i].r === undefined) {
      d[i].r = 1; // Articles from API are unread
      d[i].readblock = 0;
    }
  }

  Now = new Date();
  for(let i in d) {
    if (feedId && feedId !== 'all' && d[i].f != feedId) {
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

function fetchAndUpdateDataBackground() {
  const url = 'api.php'; // Always fetch all data
  console.log('Background fetching data from:', url);

  fetch(url)
    .then(response => {
      if (!response.ok) {
        throw new Error('HTTP error ' + response.status);
      }
      return response.json();
    })
    .then(data => {
      if (data.menu && data.articles) {
        console.log('Background sync: Menu items:', Object.keys(data.menu).length, 'Articles:', Object.keys(data.articles).length);

        // Save to cache
        saveToCache(data);

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
        // This article was in old data but not in new data = marked as read elsewhere
        if ($(i)) {
          removedArticles.push(i);
        }
      }
    }
  }

  console.log('Found', newArticles.length, 'new articles,', reactivatedArticles.length, 'reactivated articles, and', removedArticles.length, 'removed articles for current view');

  // Initialize read state for all articles from API (they are all unread)
  for(var i in newArticlesData) {
    newArticlesData[i].r = 1; // All articles from API are unread
    newArticlesData[i].readblock = 0;
  }

  // Preserve locally modified articles FIRST (they have priority)
  for(var i in locallyModifiedArticles) {
    if (d && d[i]) {
      newArticlesData[i] = d[i];
      console.log('Preserving locally modified article', i);
    }
  }

  // Then preserve read articles that are in DOM (but not locally modified or in newArticlesData)
  // This allows users to still interact with them (mark as unread, etc.)
  if (d) {
    for(var i in d) {
      // Only preserve if:
      // 1. Article is NOT in new data (it's read according to server)
      // 2. Article is in DOM
      // 3. Article was NOT locally modified (already handled above)
      if (!newArticlesData[i] && $(i) && !locallyModifiedArticles[i]) {
        newArticlesData[i] = d[i];
        console.log('Preserving read article in DOM:', i);
      }
    }
  }

  d = newArticlesData;

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
    // Remove "Flux vide" message if it exists
    var articles = DM.querySelectorAll('article');
    articles.forEach(function(article) {
      var titleLink = article.querySelector('.title');
      if (titleLink && titleLink.textContent === 'Flux vide') {
        console.log('Removing "Flux vide" message (reactivated articles)');
        article.remove();
      }
    });

    reactivatedArticles.forEach(function(articleId) {
      var article = $(articleId);
      if (article && article.className === 'item0') {
        // Change from read (item0) to unread (item1)
        article.className = 'item1';
        console.log('Reactivated article', articleId, 'from read to unread');

        // Add fade-in animation
        article.classList.add('fade-in-new');
        setTimeout(() => {
          if($(articleId)) $(articleId).classList.remove('fade-in-new');
        }, 600);
      }
    });
    console.log('Reactivated', reactivatedArticles.length, 'articles');
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

function favicon(nb) {
	if(nb >= 0 && favicon_badge) favicon_badge.badge(nb);
  //  $('favico').href = "https://reader.gheop.com/favicon"+nb+".png";
}

function changeTheme(style) {
  if(imageObserver) imageObserver.disconnect();
  const regTheme = RegExp('light.css');
  const timestamp = Date.now();
  if(regTheme.test($('stylesheet').href)) {
   $('stylesheet').href='themes/dark.css?v=' + timestamp;
   $('theme').innerHTML='';
   localStorage.setItem('theme', 'dark');
  }
  else {
    $('stylesheet').href='themes/light.css?v=' + timestamp;
    $('theme').innerHTML='';
    localStorage.setItem('theme', 'light');
  }
  setTimeout(scroll, 2000);
}

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
      // Restart SSE connection when back online
      startSSEConnection();
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
    $('bs').style.background = "white url(i/loading.gif) no-repeat 4px center";
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

function scroll() {
    //var unreadArticles = document.querySelectorAll(".item1");
    //plus rapide, voir support, retourne un HTMLCollections au lieu d'un NodeList d'ou le array.from devant
    let unreadArticles = Array.from(document.getElementsByClassName("item1"));
    let rootHeight = DM.offsetHeight-5;
    // Utiliser IntersectionObserver sauf pour Safari (bug avec root)
    const isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);
    if ("IntersectionObserver" in window && !isSafari) {
        window.addEventListener("resize", scroll);
        window.addEventListener("orientationChange", scroll);
        //on vire tout en cas de resize car rootHeight change ... et on recommence
        if(imageObserver) imageObserver.disconnect();
        if($('addblank')) $('addblank').style.height = (DM.offsetHeight - 60) + 'px';

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
                                if (!loadinprogress && cptReadArticle + 5 >= loadmore) {
                                    loadinprogress = 1;
                                    more();
                                }
                            }
                        }
                    });
            }, {
            root: DM,
                    rootMargin: "0px 0px -"+rootHeight+"px 0px",
                    threshold: [0, 0.01, 1]
                    });

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
            if (!loadinprogress && count + 5 >= loadmore) {
                loadinprogress = 1;
                more();
            }
        } else return;
    }
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



customElements.define('article-content', class extends HTMLElement {
  connectedCallback() {
    const shadow = this.attachShadow({mode: 'open'});
    /*const style = document.createElement("style");
    shadow.appendChild(style);
*/    shadow.innerHTML = this.innerHTML;
    updateStyle(this);
  }
});

function updateStyle(elem) {
 // console.log(elem);
//  console.log(countWords(elem)+' words.');
  const shadow = elem.shadowRoot;
  const style = document.createElement("style");
  shadow.appendChild(style);
  shadow.querySelector("style").textContent = `
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
  `;
}

function view(i) {
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
  if (d) {
    for(var articleId in d) {
      if (i === 'all' || d[articleId].f == i) {
        if (d[articleId].r === 1) { // Only count unread articles
          hasArticlesForFeed = true;
          break;
        }
      }
    }
  }

  // If menu says there are articles but we don't have them in d, reload ALL data
  // We must load ALL articles, not just this feed, to keep d complete
  if (!hasArticlesForFeed && m && m[i] && m[i].n > 0) {
    console.log('Feed', i, 'has', m[i].n, 'articles in menu but none in d, reloading all data');
    loadData('all', true); // Load ALL articles to refresh d completely
    // After load completes, articles will be filtered by current id automatically
    return;
  }

  // Just filter and display articles from cache
  // The background sync will update automatically
  if (d && Object.keys(d).length > 0) {
    renderArticles(d, i);
  } else {
    // No data in memory, try cache
    const cached = loadFromCache();
    if (cached && cached.articles) {
      renderArticles(cached.articles, i);
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
    xhr.open("POST", 'up.php', true);
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
return '<article id="' + i + '" class="item1" onclick="read(this.id, 1)">\n\t<header>\n\t\t<h1 class="headline"><a href="' + d[i].l + '" class="title" target="_blank" title="' + d[i].t + '">' + d[i].t + '</a></h1>\n\t\t<div class="byline vcard">\n\t\t\t<address class="author"><a href="' + d[i].o + '" title="' + d[i].n + '" class="website">' + d[i].n + '</a>' +((d[i].a) ? (' <a rel="author" class="nickname">' + d[i].a + '</a>') : '') + '</address>\n\t\t\t<time pubdate datetime="'+d[i].p+'" title="'+datepub+'">' + datepub+ '</time>\n\t\t</div>\n\t</header>\n\t<article-content id="ac'+i+'"><div class="article-content">' + d[i].d + '</div></article-content>\n\t<div class="action"><a class="lu" onclick="verif(' + i + ', 1);return true;" title="Lu"></a> <a id="full'+i+'" class="readability" onclick="readability('+i+')"></a> <a id="sum'+i+'" class="summarize" onclick="summarize('+i+')"></a> <a class="sendTo" onclick="sendTo('+i+')"></a><a class="print" onclick="printIt('+i+')"></a><span id="tag'+i+'" class="tags icon"><a onclick="tagIt('+i+')"></a></span></div>\n</article>';
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
  // Marquer immédiatement comme traité pour éviter les doubles appels
  d[k].r = 0;
  $(k).className = 'item0';

  // Send request to server to mark as read
  myFetch('read.php', 'id='+k, 1);

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
  let perc = (readItems/totalItems)*100;
  document.getElementsByTagName("footer")[0].style.background = '-moz-linear-gradient(left, #aaa 0%, #aaa '+perc+'%, white '+perc+'%, white 100%';
}

var pressedKeys = [],
  konamiCode = [38, 38, 40, 40, 37, 39, 37, 39, 66, 65]; //, konamized = false;
//var timerkona;

function konami() {
  //document.body.style.background = "url(stickmen.png)";
 // document.body.style.fontFamily = "LaChatteAMaman";
  DM.innerHTML = '<div id="konami" class="item1"><div class="date">Now!</div><a id="game" class="title">Easter Egg</a><div class="author">From <a>Gheop</a> by SiB</div><div class="descr"><canvas id="c"></canvas></div><div class="action">&nbsp;&nbsp;☺ ☻ ☺ ☻ </div></div>'+DM.innerHTML;
  $('konami').style.display='block';
  ////js1k.com/2014-dragons/details/1955
  DM.scrollTop = 0;
  kona = 1;
}

function konamistop() {
  kona = 0;
  // document.body.style.background = "url(data:image/gif;base64,R0lGODdhAwADAOcAAAAAAAEBAQICAgMDAwQEBAUFBQYGBgcHBwgICAkJCQoKCgsLCwwMDA0NDQ4ODg8PDxAQEBERERISEhMTExQUFBUVFRYWFhcXFxgYGBkZGRoaGhsbGxwcHB0dHR4eHh8fHyAgICEhISIiIiMjIyQkJCUlJSYmJicnJygoKCkpKSoqKisrKywsLC0tLS4uLi8vLzAwMDExMTIyMjMzMzQ0NDU1NTY2Njc3Nzg4ODk5OTo6Ojs7Ozw8PD09PT4+Pj8/P0BAQEFBQUJCQkNDQ0REREVFRUZGRkdHR0hISElJSUpKSktLS0xMTE1NTU5OTk9PT1BQUFFRUVJSUlNTU1RUVFVVVVZWVldXV1hYWFlZWVpaWltbW1xcXF1dXV5eXl9fX2BgYGFhYWJiYmNjY2RkZGVlZWZmZmdnZ2hoaGlpaWpqamtra2xsbG1tbW5ubm9vb3BwcHFxcXJycnNzc3R0dHV1dXZ2dnd3d3h4eHl5eXp6ent7e3x8fH19fX5+fn9/f4CAgIGBgYKCgoODg4SEhIWFhYaGhoeHh4iIiImJiYqKiouLi4yMjI2NjY6Ojo+Pj5CQkJGRkZKSkpOTk5SUlJWVlZaWlpeXl5iYmJmZmZqampubm5ycnJ2dnZ6enp+fn6CgoKGhoaKioqOjo6SkpKWlpaampqenp6ioqKmpqaqqqqurq6ysrK2tra6urq+vr7CwsLGxsbKysrOzs7S0tLW1tba2tre3t7i4uLm5ubq6uru7u7y8vL29vb6+vr+/v8DAwMHBwcLCwsPDw8TExMXFxcbGxsfHx8jIyMnJycrKysvLy8zMzM3Nzc7Ozs/Pz9DQ0NHR0dLS0tPT09TU1NXV1dbW1tfX19jY2NnZ2dra2tvb29zc3N3d3d7e3t/f3+Dg4OHh4eLi4uPj4+Tk5OXl5ebm5ufn5+jo6Onp6erq6uvr6+zs7O3t7e7u7u/v7/Dw8PHx8fLy8vPz8/T09PX19fb29vf39/j4+Pn5+fr6+vv7+/z8/P39/f7+/v///ywAAAAAAwADAAAICQDb/Rv4T6DAgAA7)";
  // clearInterval(timerkona);
  $('konami').style.display = 'none';
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
  // Initialize favicon_badge first (needed by renderMenu)
  favicon_badge=new Favico({
    animation:'none'
   // animation:'slide'
  });

  // Set "All" as selected by default
  if ($('fall')) {
    $('fall').classList.add('show');
  }

  // Then load data
  loadData('all');

  // Start SSE connection for real-time updates
  startSSEConnection();

  window.addEventListener('online', handleConnectionChange);
  window.addEventListener('offline', handleConnectionChange);
  window.addEventListener('beforeunload', stopSSEConnection);
  window.onresize = scroll;

  inactivityTime();
  $('s').onfocus = function() {
    search_focus = 1;
    //log("Focus sur l'input de recherche.");
  };
  $('s').onblur = function() {
    search_focus = 0;
    //log("Perte du focus sur l'input de recherche.");
  };
  D.onkeydown = function(evt) {
    if (search_focus === 1) return;
    var k = (evt.which) ? evt.which : evt.keyCode;
    if (kona == 1) {
      if (k == 27) konamistop();
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
document.onload = i();
//showPaintTimings();
//i();



// Charger le thème sauvegardé au démarrage
window.addEventListener('DOMContentLoaded', () => {
  const savedTheme = localStorage.getItem('theme');
  if (savedTheme === 'dark') {
    const timestamp = Date.now();
    $('stylesheet').href = 'themes/dark.css?v=' + timestamp;
  }
});
