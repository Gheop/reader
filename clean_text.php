<?php
/**
 * clean_text_v2.php - Version SANS pré-échappement JSON
 *
 * Changement principal (ligne 180-181):
 * - SUPPRIMÉ: Échappement de \ et " pour JSON
 * - GARDÉ: Nettoyage HTML et normalisation
 *
 * Avec cette version, les descriptions sont stockées en HTML propre,
 * permettant à view.php d'utiliser json_encode() nativement.
 */

function cutString($string, $start, $length, $endStr = '…') { //[&hellip]'){
  if( mb_strlen( $string ) <= $length ) return $string;
  $str = mb_substr( $string, $start, $length - mb_strlen( $endStr ) + 1, 'UTF-8');
  return substr( $str, 0, strrpos( $str,' ') ).$endStr;
}

function imgbase64($f) {
  $f[1] = preg_replace('/^\/\//s','https://',$f[1]);

//un petit log pour aider
 // file_put_contents('logimg', urldecode($f[1])."\n", FILE_APPEND | LOCK_EX);
 // $f[1] = preg_replace('/\?.*$/s','//',$f[1]);
//$extension_fichier = pathinfo($f[1], PATHINFO_EXTENSION);
//file_put_contents('log_image.txt', 'Fichier:'.$f[1]."\n", FILE_APPEND | LOCK_EX);
//file_put_contents('log_image.txt', $extension_fichier.'\n', FILE_APPEND | LOCK_EX);

/*  $extension_valides = array('jpg','png','gif','jpeg','bmp');
*/
/*  if (in_array($extension_fichier, $extension_valides))
      {*/
  if($data = file_get_contents(urldecode($f[1]))) {
    //if(!$data) return '';
    $tmpfile='/www/reader/tmp/'.md5($data); //.'.'.$extension_fichier;
    file_put_contents($tmpfile, $data);
    list($width, $height, $t, $attr) = getimagesize("$tmpfile");
    if($width == 1 && $height == 1) {
  	 unlink($tmpfile);
  	 return '';
    }
    if($width > 1680) {
      //really more speed then imagick
      if (`which vipsthumbnail`) {
        exec('vipsthumbnail '.$tmpfile.' --size 1680x');
      }
      else {
        exec('convert -resize 1680x '.$tmpfile.' '.$tmpfile);
      }
     $attr='width="1680px"';
    }
    if($height > 1024) {
      if (`which vipsthumbnail`) {
        exec('vipsthumbnail '.$tmpfile.' --size x1024');
      }
      else {
  	   exec('convert -resize x1024 '.$tmpfile.' '.$tmpfile);
     }
     $attr='height="1024px"';
    }
    $type = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $tmpfile);
    if($type == "inode/x-empty") return "";

    //apt install webp on debian

    if(($type == "image/jpeg" || $type == "image/png" || $type == "image/tiff") &&  `which cwebp`) {
        //exec('convert '.$tmpfile.' '.$tmpfile.'.webp');
        exec('cwebp -m 6 -mt "'.$tmpfile.'" -o "'.$tmpfile.'.webp"');
        unlink($tmpfile);
        $tmpfile .= '.webp';
        /* if($type == "image/jpeg" && `which jpegoptim`) exec('jpegoptim --strip-all --all-progressive '.$tmpfile); */
        /* else if($type == "image/png" && `which pngquant`) exec('pngquant -f --output '.$tmpfile.' '.$tmpfile); */


        /* return '<picture><source class="lazy" data-srcset="'.$tmpfile.'.webp" type="image/webp"><source class="lazy" data-srcset="'.$tmpfile.'" type="'.$type.'"><img class="lazy" data-src="'.$tmpfile.'"></picture>'; */

    }
/*    if($type == "image/jpeg" && `which jpegoptim`) exec('jpegoptim --strip-all --all-progressive '.$tmpfile);
      else if($type == "image/png" && `which pngquant`) exec('pngquant -f --output '.$tmpfile.' '.$tmpfile);*/
    else if($type == "image/gif" && `which gif2webp`) {
      //exec('giflossy -O3 --lossy=80 -o '.$tmpfile.'.gif '.$tmpfile);
      //$tmpfile .= '.gif';
      exec('gif2webp -m 6 -mt "'.$tmpfile.'" -o "'.$tmpfile.'.webp"');
      $tmpfile .= '.webp';
    }
    else return '<img class="lazy" data-src="'.$f[1].'" '.$attr.'" />';//return "IMAGE UNKNOW $type $f[1]";
//    $base64 = "//reader.gheop.com/$tmpfile";

//    return '<img class="lazy" data-src="'.$base64.'" '.$attr.' style="max-width: 100%;" onerror="this.src=\''.$f[1].'\';this.width=\'100%\';this.height=\'\';"   />';
    //voir pour mettre le onerror dans lib.js (stocker $f1 dans src2 ou un truc comme)
    $tmpfile = str_replace("/www/reader/", "", $tmpfile);
    return '<img loading="lazy" decoding="async" src="https://reader.gheop.com/'.$tmpfile.'" '.$attr.' onerror="this.src=\''.$f[1].'\';" />';
//    return '<img loading="lazy" decoding="async" class="lazy" data-src="https://reader.gheop.com/'.$tmpfile.'" '.$attr.' onerror="this.src=\''.$f[1].'\';" />';
  }
  else return '<img loading="lazy" decoding="async" class="lazy" src="'.$f[1].'" />';
}

function clean_txt($v) {
//  require_once 'HTMLPurifier/HTMLPurifier.auto.php';
//  $config = HTMLPurifier_Config::createDefault();
//$config->set('Filter.YouTube', true); //don't work, je l'utilise mal ?
//  $purifier = new HTMLPurifier($config);

//a retester
//  $allowed_tags = '<p><a><h1><h2><h3>';
//       $v = strip_tags($v, $allowed_tags);

 // $v = strip_tags($v, ['img', 'object', 'yt', 'pre', 'code', 'u', 'b', 'i']); //supprime le contenu !

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



  //ajout pour test
 /*$p[]='/<html.*?>/s';
  $p[]='/<\/html>/s';
    $p[]='/<body.*?>/s';
  $p[]='/<\/body>/s';
    $p[]='/<head.*?>/s';
  $p[]='/<\/head>/s';
      $p[]='/<title.*?>/s';
  $p[]='/<\/title>/s';*/
//fin ajout test

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
//  $v = preg_replace('#<yt>([^<]*)</yt>#Ssi', "<iframe class=\"lazy\" width=\"560\" height=\"315\" data-src=\"https://www.youtube.com/embed/$1\" frameborder=\"0\" allowfullscreen>$1</iframe>", $v);

  $v = preg_replace('#<yt>([^<]*)</yt>#Ssi', "<iframe loading=\"lazy\" width=\"560\" height=\"315\" src=\"https://www.youtube.com/embed/$1\" frameborder=\"0\" allowfullscreen>$1</iframe>", $v);
  //test
//$v = tidy_repair_string($v, ['show-body-only' => true], 'UTF8');
//finTest
 // $v = preg_replace('#<yt>([^<]*)</yt>#Ssi', "<iframe class=\"lazy\" width=\"560\" height=\"315\" data-src=\"https://www.youtube.com/embed/$1\" frameborder=\"0\" allowfullscreen></iframe>", $v);
  /*$v = @preg_replace_callback('#<\s*img[^>]+src=["\' ]*([^> "\']*)["\' ]*.*?>#Ssi', "imgbase64", $v); */

  // Convert lazy-loaded images (data-src) to regular src before processing
  // This handles feeds that use lazy loading in their HTML
  $v = preg_replace('/<img([^>]*)\s+data-src=["\']([^"\']+)["\']([^>]*)>/i', '<img$1 src="$2"$3>', $v);

  $v = @preg_replace_callback('#<\s*img[^>]+?src=["\' ]*([^> "\']*)["\' ]*.*?>#Ssi', "imgbase64", $v);

/*  $q =array();
  $q[]='/<span.*?>/s';
  $q[]='/<\/span>/s';
  $v = preg_replace($q,'<br />', $v);*/

  // ============================================
  // CHANGEMENT PRINCIPAL: Suppression de l'échappement JSON
  // ============================================
  // ANCIEN (clean_text.php):
  // $a = array('\\', '"', '<br>', '<br /><br />','<br><br>','<p>','<\p>','<b>','</b>');
  // $b = array('\\\\', '\"', '<br />', '<br />','<br />','','<br />','','','');
  //
  // NOUVEAU: On ne pré-échappe PLUS pour JSON
  // Les \ et " restent tels quels dans le HTML
  // json_encode() fera l'échappement automatiquement

  $a = array('<br>', '<br /><br />','<br><br>','<p>','<\p>','<b>','</b>');
  $b = array('<br />', '<br />','<br />','','<br />','','');

  $v = nl2br($v);
  $v = str_replace($a, $b, $v);

  return $v;
}
?>
