var kona = 0;
var search_value = ''; //new String('');
var search_active = 0;
var search_focus = 0;
var requestTimer = false;
var loadmore;
var nb_title = 0;
var id = 'all';
var unr = 0;
//var bgsave;
var d;
//var f;
var D = document;
var M = []; //new Array();
var varscroll = 0;
var loadinprogress = 0;
var zhr;
function $(i) {
  return D.getElementById(i);
}
//var favico = D.createElement("link");
//favico.id="favico";
//favico.type = "image/gif";
//favico.rel = "shortcut icon";
$('favico').href = "//reader.gheop.com/favicon.gif";
//D.head.appendChild(favico);

window.onload = function() {
  i();
}

function getSelectedText() {
  if (window.getSelection) {
    return window.getSelection();
  }
  if (document.selection) {
    return document.selection.createRange().text;
  }
  return '';
}

function search(t) {
  if (t) {
    search_value = t;
    $('bs').style.background = "white url(loading.gif) no-repeat 4px center";
    var xhr = getHTTPObject('search');
    xhr.open("POST", '//reader.gheop.com/search.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.send('xhr=1&s=' + t);
    requestTimer = setTimeout(function() {
      if (xhr) xhr.abort();
    }, 4000);
    xhr = null;
    $('s').blur();
  }
  t = null;
  return false;
}


function scroll() {
  for (var varscroll = 0, len = d.i.length; varscroll < len; varscroll++) {
    if ($(d.i[varscroll].i).offsetTop <= $('page').scrollTop) {
      if (d.i[varscroll].r == 1) read(varscroll);
      if (!loadinprogress && varscroll + 5 >= loadmore) {
        loadinprogress = 1;
        more();
      }
    } else return;
  }
}

function goUp() {
  $('page').scrollTop -= 20;
}

function goDown() {
  $('page').scrollTop += 20;
}

function goPrev() {
  if (kona == 1) return;
  if (!d) {
    return;
  }
  for (var k = d.i.length - 1; k >= 0; k--) {
    if ($(d.i[k].i).offsetTop < $('page').scrollTop - 10) {
      $('page').scrollTop = $(d.i[k].i).offsetTop - 10;
      return;
    }
  }
  k = null;
}

function goNext() {
  if (kona == 1) return;
  if (!d) return;
  for (var k = 0, l = d.i.length; k <= l; k++) {
    if (!(d.i[k])) {
      $('page').scrollTop = $('addblank').offsetTop - 20;
      return;
    } else if ($(d.i[k].i).offsetTop > $('page').scrollTop + 10) {
      $('page').scrollTop = $(d.i[k].i).offsetTop - 10;
      return;
    }
  }
  k = l = null;
}

function goPrevPage() {
  $('page').scrollTop -= $('page').offsetHeight;
}

function goNextPage() {
  $('page').scrollTop += $('page').offsetHeight;
}

function delError() {
  $('error').style.display = "none";
}

function affError(text,n) {
  n = typeof n !== 'undefined' ? n : 4;
  $('error').innerHTML = text;
  $('error').style.display = 'block';
  setTimeout(delError, n*1000);
}

function markallread(i) {
  var xhr = getHTTPObject('markallread');
  xhr.open("POST", 'markallread.php', true);
  xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
  xhr.send('xhr=1&f=' + i);
  requestTimer = setTimeout(function() {
    if (xhr) xhr.abort();
  }, 4000);
  xhr = null;
}

function view(i) {
  if(zhr) {zhr.abort(); zhr=null;}
  zhr = getHTTPObject('view');
  zhr.open("POST", '//reader.gheop.com/view.php', true);
  zhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
  zhr.send((i == 'all') ? 'zhr=1' : 'zhr=1&id=' + i);
  //    requestTimer = setTimeout(function() {if(zhr) zhr.abort();}, 8000);
 requestTimer = setTimeout(function() {
    if (zhr) zhr.abort();
  }, 8000);
  $('f' + id).classList.remove('show');
  id = i;
  $('f' + id).classList.add('show');
  if ($('fsearch')) $('fsearch').className = "flux";
  search_active = 0;
  //zhr = null;   Les autres timeout ne marchent pas car xhr = null en fin de fonction !!!!!!!!!!!!
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
  xhr.open("POST", '//reader.gheop.com/add_flux.php', true);
  xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
  xhr.send('xhr=1&link=' + val);
  requestTimer = setTimeout(function() {
    if (xhr) xhr.abort();
  }, 10000);
  val = xhr = null;
}

function up() {
  var xhr = getHTTPObject('up');
  affError("Mise à jour des flux en cours!", 10);
    xhr.open("POST", 'up.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.send('xhr=1');
    requestTimer = setTimeout(function() {
      if (xhr) xhr.abort();
    }, 10000);
    xhr = null;
    return false;
}

function unsubscribe(t, f) {
  var r = confirm("Se désinscrire de \"" + t + "\" ?");
  if (r) {
    var xhr = getHTTPObject('unsubscribeflux');
    xhr.open("POST", 'unsubscribe_flux.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.send('xhr=1&link=' + f);
    requestTimer = setTimeout(function() {
      if (xhr) xhr.abort();
    }, 4000);
    xhr = null;
  }
  r = null;
  return false;
}

function getHTTPObject(action) {
  var xhr = new XMLHttpRequest();
  xhr.onreadystatechange = function() {
    if (xhr.readyState === 4 && xhr.status && xhr.status === 200) {
      var page = '';
      if (action === 'menu') {
        //alert('ho');
        if (!xhr.responseText) return false;
        page += '\t<li id="fsearch" class="flux" title="Recherche" onclick="return false;">Résultats de la recherche</li>\n';
        var f = JSON.parse(xhr.responseText);
        //log("JSON du menu : "+f);
        for (var k = 0, mlen = f.f.length; k < mlen; k++) {
          M[f.f[k].i] = [];
          M[f.f[k].i].d = f.f[k].d;
          M[f.f[k].i].t = f.f[k].t;
          M[f.f[k].i].n = f.f[k].n;
          M[f.f[k].i].i = f.f[k].i;
          if (f.f[k].n > 0) {
            page += '\t<li id="f' + f.f[k].i + '" class="fluxnew" title="' + f.f[k].d + '" onclick="view(' + f.f[k].i + ');">' + f.f[k].t + ' (' + f.f[k].n + ') <a title="Tout marquer comme lu" onclick="markallread(' + f.f[k].i + ')"></a> <a title="Se désabonner" onclick="unsubscribe(\'' + f.f[k].t.replace(/'/g, "\\\'") + '\', ' + f.f[k].i + ')"></a></li>\n';
            nb_title += f.f[k].n || 0;
          } else page += '\t<li href="' + f.f[k].l + '" id="f' + f.f[k].i + '" class="flux" onclick="view(' + f.f[k].i + ');" title="' + f.f[k].d + '">' + f.f[k].t + '</li>\n';
        }
        $('menu').insertAdjacentHTML('beforeend', page);
        // $('menu').innerHTML += page;
        D.title = 'Gheop Reader' + ((nb_title > 0) ? ' (' + nb_title + ')' : '');
//        $('favico').href = "//reader.gheop.com/favicon" + nb_title + ".gif";
        $('favico').href = "//reader.gheop.com/favicon.php?n=" + nb_title;

        f = null;
        return;
      } else if (action === 'addflux') {
        return xhr.responseText ? affError(xhr.responseText) : location.reload();
      } else if (action === 'unsubscribeflux') {
        return xhr.responseText ? affError(xhr.responseText) : location.reload();
      } else if (action === 'up') {
        location.reload();
//        return xhr.responseText ? affError(xhr.responseText) : location.reload();
      } else if (action === 'view') {

        varscroll = 0;
        if (xhr.responseText) {
          //                    log("JSON de view : "+xhr.responseText);
          d = JSON.parse(xhr.responseText); // d = eval('('+xhr.responseText+')');
          loadmore = 0;
          for (var z = 0, len = d.i.length; z < len; z++) {
            loadmore++;
            page += '<div id="' + d.i[z].i + '" class="item' + d.i[z].r + '" onclick="read(' + z + ')">\n\t<a class="date" title="date">' + d.i[z].p + '</a>\n\t<a id="a' + d.i[z].i + '" href="' + d.i[z].l + '" class="title" target="_blank" title="' + d.i[z].t + '">' + d.i[z].t + '</a>\n\t<div class="author">From <a href="//gheop.com" title="' + d.i[z].n + '">' + d.i[z].n + '</a>' + ((d.i[z].a) ? (' by ' + d.i[z].a) : '') + '</div>\n\t<div class="descr">' + d.i[z].d + '</div>\n\t<div class="action"><a class="nocolor lu" onclick="verif(' + z + ');return true;" title="Lu"></a><span class="tags"> tags  <a href="viewpage.php?id='+d.i[z].i+'" target="_blank"></a> ☺ ☻ ♡ ♥  </span></div>\n</div>\n';
          }
        }
        page += '<div id="addblank">&nbsp;</div>';
        $('page').innerHTML = page;
        $('addblank').style.height = ($('page').offsetHeight - 60) + 'px';
        $('page').addEventListener('DOMMouseScroll', scroll, false);
        $('page').onscroll = scroll;
        $('page').onmousewheel = scroll;
        $('page').scrollTop = 0;
      } else if (action === 'more') {
        if (!xhr.responseText) return 0;
        var p = JSON.parse(xhr.responseText);
        if (!p || p.i.length === 0) return 0;
        $('page').removeChild($('addblank'));
        for (var x = 0, plen = p.i.length; x < plen; x++) {
          //d.i.push(p.i[k]);
          d.i[d.i.length] = p.i[x]; //plus rapide que ligne précédente
          var n = d.i.length - 1;
          loadmore++;
          page += '<div id="' + d.i[n].i + '" class="item' + d.i[n].r + '" onclick="read(' + x + ')"><div class="date">' + d.i[n].p + '</div><a id="a' + d.i[n].i + '" href="' + d.i[n].l + '" class="title" target="_blank">' + d.i[n].t + '</a><a href="//reader.gheop.com/viewSrc.php?id=' + d.i[n].i + '" style="vertical-align:sub; font-size:0.8em;color:silver" target="_blank"> src</a> <div class="author">From <a>' + d.i[n].n + '</a>' + ((d.i[n].a) ? (' by ' + d.i[n].a) : '') + '</div><div class="descr">' + d.i[n].d + '</div><div class="action"><a class="nocolor lu" onclick="verif(' + x + ');return true;"></a><span class="tags"> tags</span><!--  ☺ ☻ ♡ ♥--></div></div>';
          n = null;
        }
        page += '<div id="addblank">&nbsp;</div>';
        $('page').insertAdjacentHTML('beforeend', page);
        $('addblank').style.height = ($('page').offsetHeight - 60) + 'px';
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
        if (!xhr.responseText) {
          $('page').innerHTML = '<div id="0" class="item1">\n\t<a class="date" title="date">Maintenant</a>\n\t<a id="a0" href="#" class="title" target="_blank" title="Pas de résultat."> Recherche "' + search_value + '"</a>\n\t<div class="author">From <a href="//gheop.com" title="reader gheop">Reader</a></div>\n\t<div class="descr">Pas de résultat trouvé!</div>\n\t<div class="action"><a class="nocolor search" onclick="return true;" title="Lu"></a><span class="tags"> tag1, tag2, tag3, tagsuperlongdelamortquitue</span><!--  ☺ ☻ ♡ ♥--></div>\n</div>\n';
          return false;
        }
        d = JSON.parse(xhr.responseText); // d = eval('('+xhr.responseText+')');
        loadmore = 0;
        for (var y = 0, tlen = d.i.length; y < tlen; y++) {
          loadmore++;
          page += '<div id="' + d.i[y].i + '" class="item1">\n\t<a class="date" title="date">' + d.i[y].p + '</a>\n\t<a id="a' + d.i[y].i + '" href="' + d.i[y].l + '" class="title" target="_blank" title="' + d.i[y].t + '"> ' + d.i[y].t + '</a>\n\t<div class="author">From <a href="//gheop.com" title="' + d.i[y].n + '">' + d.i[y].n + '</a>' + ((d.i[y].a) ? (' by ' + d.i[y].a) : '') + '</div>\n\t<div class="descr">' + d.i[y].d + '</div>\n\t<div class="action"><a class="nocolor search" onclick="verif(' + y + ');return true;" title="Lu"></a><span class="tags"> tag1, tag2, tag3, tagsuperlongdelamortquitue</span><!--  ☺ ☻ ♡ ♥--></div>\n</div>\n';
          //voir pour foutre les tags en ul,li
        }

        page += '<div id="addblank">&nbsp;</div>';
        $('page').innerHTML = page;
        $('addblank').style.height = ($('page').offsetHeight - 60) + 'px';
        $('page').addEventListener('DOMMouseScroll', scroll, false);
        $('page').onscroll = scroll;
        $('page').onmousewheel = scroll;
        $('page').scrollTop = 0;
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
  if (d.i[k].r == 1) return;
  d.i[k].r = 1;
  $(d.i[k].i).className = 'item1';
  if (nb_title < 0) nb_title = 0;
  D.title = 'Gheop Reader' + ((++nb_title > 0) ? ' (' + nb_title + ')' : '');
  M[d.i[k].f].n++;
  if ($('S' + d.i[k].f)) $('S' + d.i[k].f).innerHTML = M[d.i[k].f].t + ' (' + M[d.i[k].f].n + ')';
  $('f' + d.i[k].f).className = "fluxnew";
  if (id == d.i[k].f) $('f' + d.i[k].f).className = "fluxnew show";
  light('f' + d.i[k].f);
  var xhr = getHTTPObject();
  xhr.open("POST", 'unread.php', true);
  xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
  xhr.send('xhr=1&id=' + d.i[k].i);
  //    requestTimer = setTimeout(function() {xhr.abort();}, 4000);
 requestTimer = setTimeout(function() {
    if (xhr) xhr.abort();
  }, 4000); //a voir si ça marche
  xhr = null;
  $('favico').href = "//reader.gheop.com/favicon.php?n=" + nb_title;

}

function read(k) {
  if (search_active == 1) return;
  //obligé sinon 2 read après un verif() ou un unread-read à la suite ... mais why ?
  if (d.i[k].r === 0) return;
  if (unr === 1) {
    unr = 0;
    return;
  }

  $(d.i[k].i).className = 'item0';
  d.i[k].r = 0;
  M[d.i[k].f].n--;
  if (M[d.i[k].f].n > 0) {
    $('f' + d.i[k].f).innerHTML = M[d.i[k].f].t + ' (' + M[d.i[k].f].n + ') <a title="Tout marquer comme lu" onclick="markallread(' + M[d.i[k].f].i + ')"></a> <a title="Se désabonner" onclick="unsubscribe(\'' + M[d.i[k].f].t.replace(/'/g, "\\\'") + '\', ' + M[d.i[k].f].i + ')"></a>';
    light('f' + d.i[k].f);
  } else {
    $('f' + d.i[k].f).innerHTML = M[d.i[k].f].t + ' <a title="Se désabonner" onclick="unsubscribe(\'' + M[d.i[k].f].t.replace(/'/g, "\\\'") + '\',' + d.i[k].f + ')"></a>';
    if (id == d.i[k].f) $('f' + d.i[k].f).className = "flux show";
    else $('f' + d.i[k].f).className = "flux";
  }
  var xhr = getHTTPObject();
  xhr.open("POST", 'read.php', true);
  xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
  xhr.send('xhr=1&id=' + d.i[k].i);
  requestTimer = setTimeout(function() {
    if (xhr) xhr.abort();
  }, 4000);
  D.title = 'Gheop Reader' + ((--nb_title > 0) ? ' (' + nb_title + ')' : '');
  if (nb_title < 0) nb_title = 0;
  $('favico').href = "//reader.gheop.com/favicon.php?n=" + nb_title;
  xhr = null;
}



function menu() {
  //alert('menu');
  var xhr = getHTTPObject('menu');
  xhr.open("POST", '//reader.gheop.com/menu.php', true);
  xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
  xhr.send('xhr=1');
  requestTimer = setTimeout(function() {
    if (xhr) xhr.abort();
  }, 4000);
  xhr = null;
}

var pressedKeys = [],
  konamiCode = [38, 38, 40, 40, 37, 39, 37, 39, 66, 65]; //, konamized = false;
//var timerkona;

function konami() {
  //document.body.style.background = "url(stickmen.png)";
  //document.body.style.fontFamily = "LaChatteAMaman";
  $('page').innerHTML = '<div id="konami" class="item1"><div class="date">Now!</div><a id="game" class="title">Easter Egg</a><div class="author">From <a>Gheop</a> by SiB</div><div class="descr"><canvas id="c"></canvas></div><div class="action">&nbsp;&nbsp;☺ ☻ ☺ ☻ ☺ ☻ ☺ ☻ ☺ ☻ ☺ ☻ ☺ ☻ ☺ ☻ ☺ ☻ ☺ ☻ ☺ ☻ ☺ ☻ ☺ ☻ ☺ ☻ ☺ ☻ ☺ ☻ ☺ ☻ ☺ ☻ ☺ ☻ ☺ ☻ ☺ ☻ ☺ ☻ ☺ ☻ ☺ ☻ ☺ ☻ ☺ ☻ ☺ ☻ ☺ ☻ ☺ ☻ ☺ ☻ ☺ ☻ ☺ ☻ ☺ ☻ ☺ ☻ ☺ ☻ ☺ ☻ ☺ ☻ ☺ ☻ ☺ ☻ ☺ ☻ <!-- ♡ ♥ --></div></div>'+$('page').innerHTML;
  $('konami').style.display='block';

  ////js1k.com/2014-dragons/details/1955
        var a = $('c');
a.style.width=(a.width=1e3)+"px";a.style.height=(a.height=500)+"px";for(p in c){c[p[0]+(p[6]||"")]=c[p]}J=K=C=B=0;u=50;D=250;E=F=G=H=1;I=250;setInterval(function(){L=300;M=500;N=400;with(c){A=function(S,x,y,T){T&&a(x,y,T,0,7,0);fillStyle=S.P?S:"#"+"ceff99aaffff333f99ff7".substr(S*3,3);fill();ba()};A(0,0,0,10000);A(6,800,300,140);O=G*u%400;l(0-O,N+100);l(0-O,N);for(i=0;i<7;i++){qt(i*200+100-O,i%2?L:M,i*200+200-O,N)}l(i*200-O,N+100);ca();fillStyle="#5c1";fill();ba();t=(G*(u+E)+I)%200/200;U=1-t;K2=U*U*N+2*t*U*((G*(u+E)+I)%400>200?L:M)+t*t*N;D+=F;if(D>=K){if(K2<K){if(F>0&&(K2-K)<0&&!J){E=E/3;K2=K}E-=E<2?0:0.1}else{E+=0.1*H*H}J=1;D=K;if(K2<K&&F>(K2-K)){F=(K2-K)}}else{J=0}F+=0.4*H;K=K2;A(3-H,I,D-10,10);A(3,I+3,D-12,4);A(4,I+4,D-12,1);Q=200+Math.pow(B,1.3)-u;R=50+Math.sin(B/2)*2;A(1,Q,R,30);A(3,Q+20,R+5,7);A(4,Q+22,R+7,2);A(6,0,0,0);fx(++B+" ☆",5,10);fx((C<B?C=B:C)+" ★",40,10);if(Q>4*I){alert(B);E=F=u=B=K=2}}u+=E},30);onkeydown=onkeyup=function(b){H=b.type[5]?2:1}
    //c=$('kona');h=t=150;L=w=c.width=800;u=DD=50;H=[];R=Math.random;for(mavar in C=c.getContext('2d'))C[mavar[J=X=Y=0]+(mavar[6]||'')]=C[mavar];timerkona = setInterval("if(DD)for(x=405,i=y=I=0;i<1e4;)L=H[i++]=i<9|L<w&R()<.3?w:R()*u+80|0;mavar=++t%99-u;mavar=mavar*mavar/8+20;y+=Y;x+=y-H[(x+X)/u|0]>9?0:X;j=H[o=x/u|0];Y=y<j|Y<0?Y+1:(y=j,J?-10:0);with(C){A=function(c,x,y,r){r&&arc(x,y,r,0,7,0);fillStyle=c.P?c:'#'+'ceff99ff78f86eeaaffffd45333'.substr(c*3,3); f(); ba()};for(DD=Z=0;Z<21;Z++){Z<7&&A(Z%6,w/2,235,Z?250-15*Z:w);i=o-5+Z;S=x-i*u;B=S>9&S<41;ta(u-S,0);G=cL(0,T=H[i],0,T+9);T%6||(A(2,25,T-7,5),y^j||B&&(H[i]-=.1,I++));G.P=G.addColorStop;G.P(0,i%7?'#7e3':(i^o||y^T||(y=H[i]+=mavar/99),'#c7a'));G.P(1,'#ca6');i%4&&A(6,t/2%200,9,i%2?27:33);m(-6,h);qt(-6,T,3,T);l(47,T);qt(56,T,56,h);A(G);i%3?0:T<w?(A(G,33,T-15,10),fc(31,T-7,4,9)):(A(7,25,mavar,9),A(G,25,mavar,5),fc(24,mavar,2,h),DD=B&y>mavar-9?1:DD);ta(S-u,0)}A(6,u,y-9,11);A(5,M=u+X*.7,Q=y-9+Y/5,8);A(8,M,Q,5);fx(I+'c',5,15)}DD=y>h?1:DD",u);onkeydown=onkeyup=function(e){E=e.type[5]?4:0;e=e.keyCode;J=e^38?J:E;X=e^37?e^39?X:E:-E}
  $('page').scrollTop = 0;
  kona = 1;
}

function konamistop() {
  kona = 0;
  // document.body.style.background = "url(data:image/gif;base64,R0lGODdhAwADAOcAAAAAAAEBAQICAgMDAwQEBAUFBQYGBgcHBwgICAkJCQoKCgsLCwwMDA0NDQ4ODg8PDxAQEBERERISEhMTExQUFBUVFRYWFhcXFxgYGBkZGRoaGhsbGxwcHB0dHR4eHh8fHyAgICEhISIiIiMjIyQkJCUlJSYmJicnJygoKCkpKSoqKisrKywsLC0tLS4uLi8vLzAwMDExMTIyMjMzMzQ0NDU1NTY2Njc3Nzg4ODk5OTo6Ojs7Ozw8PD09PT4+Pj8/P0BAQEFBQUJCQkNDQ0REREVFRUZGRkdHR0hISElJSUpKSktLS0xMTE1NTU5OTk9PT1BQUFFRUVJSUlNTU1RUVFVVVVZWVldXV1hYWFlZWVpaWltbW1xcXF1dXV5eXl9fX2BgYGFhYWJiYmNjY2RkZGVlZWZmZmdnZ2hoaGlpaWpqamtra2xsbG1tbW5ubm9vb3BwcHFxcXJycnNzc3R0dHV1dXZ2dnd3d3h4eHl5eXp6ent7e3x8fH19fX5+fn9/f4CAgIGBgYKCgoODg4SEhIWFhYaGhoeHh4iIiImJiYqKiouLi4yMjI2NjY6Ojo+Pj5CQkJGRkZKSkpOTk5SUlJWVlZaWlpeXl5iYmJmZmZqampubm5ycnJ2dnZ6enp+fn6CgoKGhoaKioqOjo6SkpKWlpaampqenp6ioqKmpqaqqqqurq6ysrK2tra6urq+vr7CwsLGxsbKysrOzs7S0tLW1tba2tre3t7i4uLm5ubq6uru7u7y8vL29vb6+vr+/v8DAwMHBwcLCwsPDw8TExMXFxcbGxsfHx8jIyMnJycrKysvLy8zMzM3Nzc7Ozs/Pz9DQ0NHR0dLS0tPT09TU1NXV1dbW1tfX19jY2NnZ2dra2tvb29zc3N3d3d7e3t/f3+Dg4OHh4eLi4uPj4+Tk5OXl5ebm5ufn5+jo6Onp6erq6uvr6+zs7O3t7e7u7u/v7/Dw8PHx8fLy8vPz8/T09PX19fb29vf39/j4+Pn5+fr6+vv7+/z8/P39/f7+/v///ywAAAAAAwADAAAICQDb/Rv4T6DAgAA7)";
  // clearInterval(timerkona);
  $('konami').style.display = 'none';
}

function i() {
  view('all');
  menu();
  $('s').onfocus = function() {
    search_focus = 1;
    log("Focus sur l'input de recherche.");
  };
  $('s').onblur = function() {
    search_focus = 0;
    log("Perte du focus sur l'input de recherche.");
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
        log("Touche non implémentée. Code : " + k);
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
    } else if ($(d.i[k].i).offsetTop >= $('page').scrollTop) {
      log("Fenêtre active : " + d.i[k].l);
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
    window.open("//fr.wikipedia.org/wiki/Special:Search?search=" + getSelectedText());
  return false;
  // url = "//fr.wikipedia.org/wiki/Special:Search?search=
}

function goGoogle() {
  var t = getSelectedText();
  if (t && t !== '')
    window.open("https://www.google.fr/search?q=" + getSelectedText() + "&ie=utf-8&oe=utf-8&lr=lang_fr");
  return false;
}

function more() {
  var xhr = getHTTPObject('more');
  xhr.open('POST', 'view.php', true);
  xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
  if (id && id !== 'all') xhr.send('xhr=1&nb=10&id=' + id);
  else xhr.send('xhr=1&nb=10');
  requestTimer = setTimeout(function() {
    if (xhr) xhr.abort();
  }, 4000);
  xhr = null;
}

function verif(k) {
  return (d.i[k].r == 1) ? read(k) : unread(k);
}

function log(t) {
  if (typeof console !== 'undefined') console.log(t);
}
