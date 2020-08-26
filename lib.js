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

var rtf;
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
  document.onkeypress = resetTimer;
  document.onload = resetTimer;
  document.onmousemove = resetTimer;
  document.onmousedown = resetTimer; // touchscreen presses
  document.ontouchstart = resetTimer;
  document.onclick = resetTimer;     // touchpad clicks
  document.onscroll = resetTimer;    // scrolling with arrow keys
  document.onkeypress = resetTimer;
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

function favicon(nb) {
    $('favico').href = "https://reader.gheop.com/favicon.php?n=" + nb;
}

function changeTheme(style) {
  if(imageObserver) imageObserver.disconnect();
  const regTheme = RegExp('screen.css');
  if(regTheme.test($('stylesheet').href)) {
   $('stylesheet').href='dark.css';
   $('theme').innerHTML='';
  }
  else {
    $('stylesheet').href='screen.css';
    $('theme').innerHTML='';
  }
  setTimeout(scroll, 2000);
}

function handleConnectionChange(event){
    if(event.type == "offline"){
      online = false;
      $('g').style.textDecoration='line-through';
    }
    if(event.type == "online"){
      online = true;
      $('g').style.textDecoration='none';
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
    xhr = null;
    $('s').blur();
  }
  t = null;
  return false;
}

function scroll() {
    //var unreadArticles = document.querySelectorAll(".item1");
    //plus rapide, voir support, retourne un HTMLCollections au lieu d'un NodeList d'ou le array.from devant
    var unreadArticles = Array.from(document.getElementsByClassName("item1"));
    var rootHeight = DM.offsetHeight-5;
    if ("IntersectionObserver" in window && navigator.userAgent.toLowerCase().indexOf('safari/') == -1) {
        window.addEventListener("resize", scroll);
        window.addEventListener("orientationChange", scroll);
        //on vire tout en cas de resize car rootHeight change ... et on recommence
        if(imageObserver) imageObserver.disconnect();
        if($('addblank')) $('addblank').style.height = (DM.offsetHeight - 60) + 'px';
        
        imageObserver = new IntersectionObserver(function(entries, observer) {
                entries.forEach(function(entry) {
                        if (entry.isIntersecting) {
                            var art = entry.target;
                            read(art.id);
                            cptReadArticle++;
                            imageObserver.unobserve(art);
                            if (!loadinprogress && cptReadArticle + 5 >= loadmore) {
                                loadinprogress = 1;
                                more();
                            }
                        }
                    });
            }, {
            root: DM,
                    rootMargin: "1px 0px -"+rootHeight+"px 0px"
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
    var count = 0;
    for(var i in d) {
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
  var previous = null;
	for(var i in d) {
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
	for(var i in d) {
		if($(i).offsetTop > DM.scrollTop + 10) {
			DM.scrollTop = $(i).offsetTop - 10;
			return;
		}
	}
	DM.scrollTop = $('addblank').offsetTop - 20;
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
	xhr = null;
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
		xhr = null;
	}
	r = null;
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
          menu += '\t<li id="f' + i + '" class="fluxnew" title="' + m[i].d + '"><span onclick="view(' + i + ');">' + m[i].t + '</span><span class="nb_flux"> ' + m[i].n + '</span> <span class="icon"><a title="Tout marquer comme lu" onclick="markallread(' + i + ')"></a> <a title="Se désabonner" onclick="unsubscribe(\'' + m[i].t.replace(/'/g, "\\\'") + '\', ' + i + ')"></a></span></li>\n';
          nb_title += m[i].n || 0;
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

function view(i) {
	myFetch('view.php', 'id='+i).then(result => {
		var page = '';
		cptReadArticle = 0;
		varscroll = 0;
		loadmore = 0;
		d = result;
		Now = new Date();
		for(var i in d) {
			loadmore++;
        //voir pour charger le corps du texte en shadow DOM https://developer.mozilla.org/fr/docs/Web/Web_Components/Shadow_DOM (ne fonctionne pas encore dans Firefox)
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
			lazyLoadImg();
			scroll();
		}
	})
  $('f' + id).classList.remove('show');
  id = i;
  $('f' + id).classList.add('show');
  if ($('fsearch')) $('fsearch').className = "flux";
  search_active = 0;
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
  val = xhr = null;
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
    xhr = null;
    return false;
}

function unsubscribe(t, f) {
  var r = confirm("Se désinscrire de \"" + t + "\" ?");
  if (r) {
    myFetch('unsubscribe_flux.php', 'link='+f, 1);
    for(var i in d) {
      if(d[i].f == f) {
        d[i].r = 0;
        $(i).style.display='none';
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
//faire une fonction qui génère TOUS les articles d'un coup et qui n'execute qu'une seule fois le calcul des dates...
//ne pas vider la 'page' pour more...
function generateArticle(i) {
 	var datepub = dateArticle(d[i].p);
  //return '<article id="' + i + '" class="item1" onclick="read(this.id)">\n\t<a class="date" title="date">' + datepub+ '</a>\n\t<a href="' + d[i].l + '" class="title" target="_blank" title="' + d[i].t + '">' + d[i].t + '</a>\n\t<div class="author">From <a href="' + d[i].o + '" title="' + d[i].n + '">' + d[i].n + '</a>' + ((d[i].a) ? (' by ' + d[i].a) : '') + '</div>\n\t<div class="descr">' + d[i].d + '</div>\n\t<div class="action"><a class="lu" onclick="verif(' + i + ');return true;" title="Lu"></a><span class="tags"><a class="love" onclick="likedArticle(' + i + ');">♥</a>  <a>🥚</a>  </span></div>\n</article>\n';
//return '<article id="' + i + '" class="item1" onclick="read(this.id)">\n\t<time datetime="'+ d[i].p +'">' + datepub+ '</time>\n\t<a href="' + d[i].l + '" class="title" target="_blank" title="' + d[i].t + '">' + d[i].t + '</a>\n\t<div class="author">From <a href="' + d[i].o + '" title="' + d[i].n + '">' + d[i].n + '</a>' + ((d[i].a) ? (' by ' + d[i].a) : '') + '</div>\n\t<div class="descr">' + d[i].d + '</div>\n\t<div class="action"><a class="lu" onclick="verif(' + i + ');return true;" title="Lu"></a></div>\n</article>\n';

//voir http://microformats.org/wiki/hcard
// ne peux respecter totalement le format sinon time se retrouve mal affiché... on peut le remettre à la bonne place et le cacher si besoin (m), normalement corrigé maintenant

return '<article id="' + i + '" class="item1" onclick="read(this.id)">\n\t<header>\n\t\t<h1 class="headline"><a href="' + d[i].l + '" class="title" target="_blank" title="' + d[i].t + '">' + d[i].t + '</a></h1>\n\t\t<div class="byline vcard">\n\t\t\t<address class="author"><a href="' + d[i].o + '" title="' + d[i].n + '" class="website">' + d[i].n + '</a>' +((d[i].a) ? (' <a rel="author" class="nickname">' + d[i].a + '</a>') : '') + '</address>\n\t\t\t<time pubdate datetime="'+d[i].p+'" title="'+datepub+'">' + datepub+ '</time>\n\t\t</div>\n\t</header>\n\t<div class="article-content">' + d[i].d + '</div>\n\t<div class="action"><a class="lu" onclick="verif(' + i + ');return true;" title="Lu"></a></div>\n</article>';
//return '<article id="' + i + '" class="item1" onclick="read(this.id)">\n\t<header>\n\t\t<h1 class="headline"><a href="' + d[i].l + '" class="title" target="_blank" title="' + d[i].t + '">' + d[i].t + '</a>\n\t\t\t<time pubdate datetime="'+d[i].p+'" title="'+datepub+'">' + datepub+ '</time></h1>\n\t\t<div class="byline vcard">\n\t\t\t<address class="author"><a href="' + d[i].o + '" title="' + d[i].n + '" class="website">' + d[i].n + '</a>' +((d[i].a) ? (' <a rel="author" class="nickname">' + d[i].a + '</a>') : '') + '</address>\n\t\t</div>\n\t</header>\n\t<div class="article-content">' + d[i].d + '</div>\n\t<div class="action"><a class="lu" onclick="verif(' + i + ');return true;" title="Lu"></a></div>\n</article>';

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
	var xhr = new XMLHttpRequest();
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
        for(var i in p) {
            loadmore++;
            d[i] = p[i];
            page += generateArticle(i);
        }
        page += '<div id="addblank">&nbsp;</div>';
        DM.insertAdjacentHTML('beforeend', page);
        $('addblank').style.height = (DM.offsetHeight - 60) + 'px';
        lazyLoadImg();
        loadinprogress = 0;
        p = null;
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
          for (var y = 0, tlen = d.i.length; y < tlen; y++) {
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
      page = null;
    } else if (xhr.readyState === 4) affError('Le serveur n\'a pas répondu assez rapidement. Veuillez réessayer ultérieurement.');
    return 0;
  };
  return xhr;
}

function unread(k) {
	unr = 1;
	if (d[k].r == 1 || $(k).className == 'item1') return;
	d[k].r = 1;
	$(k).className = 'item1';
	if (nb_title < 0) nb_title = 0;
	D.title = 'Gheop Reader' + ((++nb_title > 0) ? ' (' + nb_title + ')' : '');
	m[d[k].f].n++;
        $('f' + d[k].f).children[1].innerHTML = m[d[k].f].n;
        $('f' + d[k].f).className = "fluxnew";
	if (id == d[k].f) $('f' + d[k].f).className = "fluxnew show";
	light('f' + d[k].f);
  myFetch('unread.php', 'id='+k, 1);
	favicon(nb_title);
	readItems--;
	progressBar();
}

function read(k) {
  if (search_active == 1) return;
  //obligé sinon 2 read après un verif() ou un unread-read à la suite ... mais why ?
  if (d[k].r === 0) return;
  if (unr === 1) {
    unr = 0;
    return;
  }

  $(k).className = 'item0';
  d[k].r = 0;
  m[d[k].f].n--;
myFetch('read.php', 'id='+k, 1);
  if (m[d[k].f].n > 0) {
     $('f' + d[k].f).children[1].innerHTML = m[d[k].f].n;
      light('f' + d[k].f);
  } else {
      $('f' + d[k].f).children[1].innerHTML = '';
      if (id == d[k].f) $('f' + d[k].f).className = "flux show";
      else $('f' + d[k].f).className = "flux";
  }

  D.title = 'Gheop Reader' + ((--nb_title > 0) ? ' (' + nb_title + ')' : '');
  if (nb_title < 0) nb_title = 0;
  favicon(nb_title);
  xhr = null;
  readItems++;
  progressBar();
}

function progressBar() {
  var perc = (readItems/totalItems)*100;
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


function lazyLoadImg() {
//	var lazyloadImages = document.querySelectorAll("img.lazy");

    //plus rapide, voir support, retourne un HTMLCollections au lieu d'un NodeList d'ou le array.from devant
    var lazyloadImages = Array.from(document.getElementsByClassName("lazy"));
//firefox75 le supporte en natif !
	if(hasSupportLoading) { 
		lazyloadImages.forEach(function(img) {
			img.src = img.dataset.src;
            img.classList.remove('lazy');
		});
		return 0;
	}
    

    if ("IntersectionObserver" in window /*&& navigator.userAgent.toLowerCase().indexOf('safari/') == -1*/) {
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


function i() {
  view('all');
  menu();
  window.addEventListener('online', handleConnectionChange);
  window.addEventListener('offline', handleConnectionChange);
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
    k = null;
  };
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
  k = len = null;
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
  xhr = null;
}

function verif(k) {
  return (d[k].r == 0) ? unread(k) : read(k);
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


document.onload = i();
//i();
