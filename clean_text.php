<?php
function imgbase64($f) {
  $f[1] = preg_replace('/^\/\//s','https://',$f[1]);
 // $f[1] = preg_replace('/\?.*$/s','//',$f[1]);
$extension_fichier = pathinfo($f[1], PATHINFO_EXTENSION);
/*  $extension_valides = array('jpg','png','gif','jpeg','bmp');
*/
/*  if (in_array($extension_fichier, $extension_valides))
      {*/
  if($data = file_get_contents($f[1])) {
    //if(!$data) return '';
    $tmpfile='tmp/'.md5($data).'.'.$extension_fichier;
    file_put_contents($tmpfile, $data);
    list($width, $height, $t, $attr) = getimagesize("$tmpfile");
    if($width == 1 && $height == 1) {
  	 unlink($tmpfile);
  	 return '';
    }
    if($width > 1680) {
  	 exec('convert -resize 1680x '.$tmpfile.' '.$tmpfile);
    }
    if($height > 1024) {
  	 exec('convert -resize x1024 '.$tmpfile.' '.$tmpfile);
    }
    $type = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $tmpfile);
    if($type == "inode/x-empty") return "";
    if(($type == "image/jpeg" || $type == "image/png") &&  `which convert`) {
        exec('convert '.$tmpfile.' '.$tmpfile.'.webp');
        $tmpfile .= '.webp';
        /* if($type == "image/jpeg" && `which jpegoptim`) exec('jpegoptim --strip-all --all-progressive '.$tmpfile); */
        /* else if($type == "image/png" && `which pngquant`) exec('pngquant -f --output '.$tmpfile.' '.$tmpfile); */


        /* return '<picture><source class="lazy" data-srcset="'.$tmpfile.'.webp" type="image/webp"><source class="lazy" data-srcset="'.$tmpfile.'" type="'.$type.'"><img class="lazy" data-src="'.$tmpfile.'"></picture>'; */

    }
/*    if($type == "image/jpeg" && `which jpegoptim`) exec('jpegoptim --strip-all --all-progressive '.$tmpfile);
      else if($type == "image/png" && `which pngquant`) exec('pngquant -f --output '.$tmpfile.' '.$tmpfile);*/
    else if($type == "image/gif" && `which giflossy`) exec('giflossy -O3 --lossy=80 -o '.$tmpfile.' '.$tmpfile);
    else return '<img class="lazy" data-src="'.$f[1].'" '.$attr.'" />';//return "IMAGE UNKNOW $type $f[1]";
//    $base64 = "//reader.gheop.com/$tmpfile";

//    return '<img class="lazy" data-src="'.$base64.'" '.$attr.' style="max-width: 100%;" onerror="this.src=\''.$f[1].'\';this.width=\'100%\';this.height=\'\';"   />';
    //voir pour mettre le onerror dans lib.js (stocker $f1 dans src2 ou un truc comme)
    return '<img loading="lazy" class="lazy" data-src="https://reader.gheop.com/'.$tmpfile.'" '.$attr.' onerror="this.src=\''.$f[1].'\';" />';
  }
  else return '<img loading="lazy" class="lazy" src="'.$f[1].'" />';
}

function clean_txt($v) {
//  require_once 'HTMLPurifier/HTMLPurifier.auto.php';
//  $config = HTMLPurifier_Config::createDefault();
//$config->set('Filter.YouTube', true); //don't work, je l'utilise mal ?
//  $purifier = new HTMLPurifier($config);
  $v = preg_replace('/[\x00-\x1F\x7F\xA0]/u', '', $v);
  $p = array();
  $p[] = '/<img .*?src="(http:)?\/\/feeds\.feedburner\.com\/.*?".*?>/s';
  $p[] = '/<img .*?src=".*\/\/www\.gstatic\.com\/images\/icons\/.*?".*?>/s';
  $p[] = '/<img .*?src=".*\/\/a\.fsdn\.com\/sd\/.*?".*?>/s';
  $p[]='/<a *?.*?>/s';
  $p[]='/<\/a>/s';
  $p[]='/<i>/s';
  $p[]='/<i +.*?>/s';
  $p[]='/<\/i>/s';
  $p[]='/<b\s+.*?>/s';
  $p[]='/<\/b\s+>/s';
  $p[]='/<article *?.*?>/s';
  $p[]='/<\/article>/s';
  $p[]='/<section *?.*?>/s';
  $p[]='/<\/section>/s';
  $p[]='/<table *?.*?>/s';
  $p[]='/<\/table *?>/s';
  $p[]='/<td *?.*?>/s';
  $p[]='/<\/td *?>/s';
  $p[]='/<tr *?.*?>/s';
  $p[]='/<\/tr *?>/s';
  $p[]='/<tbody *?.*?>/s';
  $p[]='/<\/tbody *?>/s';
  $p[] = '/<img .*?src="\/\/www\.gstatic\.com\/.*?".*?>/s';
  $p[]='/<img .*?src="\/\/.*?\.feedsportal\.com\/.*?".*?>/s';
  $p[]='/<iframe.*?>/s';
  $p[]='/<\/iframe *>/s';
  $p[]='/<img .*?src=".*feeds\.lefigaro\.fr\/.*\/mf.gif".*?>/s';
  $p[]='/<img .*?src="\/\/.*?\.doubleclick\.net\/.*?".*?>/s';
  $p[]='/<img .*?src="\/\/.*?\.fsdn\.com\/.*?".*?>/';
  $p[]='/<div.*?>/s';
  $p[]='/<\/div>/s';
  $p[]='/<span.*?>/s';
  $p[]='/<\/span>/s';
/*  $p[]='/<p\s*?.*?>/s';
  $p[]='/<\/p>/s';*/
/*  $p[]='/<font\s*?.*?>/s';
  $p[]='/<\/font\s*?>/s';*/
  /* $p[] = '/(class|id|style|align)=".*?"/s';*/
  $p[] = '/(id|style|align)=".*?"/s';
  $p[] = '/ onmouseover=("|\').*?("|\')/s';
  $p[] = '/ style=("|\').*?("|\')/s';
  $p[] = '/ onclick=("|\').*?("|\')/s';
  $p[]='/<\s*script.*?<\/script>/s';
  $p[]='/<\s*style.*?<\/style>/s';

  $v = preg_replace($p,'', $v);
  //$v = $purifier->purify($v);

/*  $v = preg_replace('#<a .*href=["\' ]*([^&> "\']*)["\' ]*.*?>#Ssi', '<a href="$1" target="_blank">', $v);*/
  $v = preg_replace('#<object.*<embed[^>]+src=["\' ]*//www.lewistrondheim.com/blog/affiche.swf\?image=([^&> "\']*).*["\' >]*.*</object>#Ssi', "<img src=\"//www.lewistrondheim.com/blog/images/$1\" />", $v);
  $v = preg_replace('#<yt>([^<]*)</yt>#Ssi', "<iframe class=\"lazy\" width=\"560\" height=\"315\" data-src=\"https://www.youtube.com/embed/$1\" frameborder=\"0\" allowfullscreen></iframe>", $v);
  $v = @preg_replace_callback('#<\s*img[^>]+src=["\' ]*([^> "\']*)["\' ]*.*?>#Ssi', "imgbase64", $v);

/*  $q =array();
  $q[]='/<span.*?>/s';
  $q[]='/<\/span>/s';
  $v = preg_replace($q,'<br />', $v);*/
  $a = array('\\', '"', '<br>', '<br /><br />','<br><br>','<p>','<\p>','<b>','</b>');//,"\\",     "/",   "\"",  "\n",  "\r",  "\t", "\x08", "\x0c");, '[', ']'
  $b = array('\\\\', '\"', '<br />', '<br />','<br />','','<br />','','','');//"\\\\", "\\/", "\\\"", "\\n", "\\r", "\\t",  "\\f",  "\\b"); ,'\[','\]'
//  $a = array( "\\", "\n", "\t", "\r", "\f", '"', '<br>', '<br /><br />','<br><br>','\u','/','<p>','<\p>','<b>','</b>', '{', '}',"'","\\",     "/",   "\"",  "\n",  "\r",  "\t", "\x08", "\x0c");
//  $b = array('\\\\', '', '', '', '', '\"', '<br />', '<br />','<br />','','\/','<br />','','','', '{', '}','\'',"\\\\", "\\/", "\\\"", "\\n", "\\r", "\\t",  "\\f",  "\\b");
  $v = nl2br($v);
  $v = str_replace($a, $b, $v);
  return $v;
}
