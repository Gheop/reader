<?php
//$image = new Imagick('favicon.gif') or die("msg error");
$image = new Imagick('i/ico.gif') or die("msg error");

if(isset($_GET['n']) && is_numeric($_GET['n']) && $_GET['n'] > 0) {
	$draw = new ImagickDraw();
	$draw->setFillColor('black');
  //  $draw->setFont('fontawesome-webfont.ttf');
	if($_GET['n'] > 999 && $_GET['n'] = "∞") $draw->setFontSize(70);
	else if($_GET['n'] <100) $draw->setFontSize(50);
	else $draw->setFontSize(35);
	$draw->setGravity (Imagick::GRAVITY_CENTER);

  $text_layer = new Imagick('trans.png'); # empty transparent png of the same size
  $text_layer->annotateImage($draw, 0, 0, 0, $_GET['n']);

  /* create drop shadow on it's own layer */
//$shadow_layer = $text_layer->clone(); 
  $shadow_layer = clone $text_layer; 

  $shadow_layer->setImageBackgroundColor( new ImagickPixel( 'white' ) ); 
  $shadow_layer->shadowImage( 75, 5, 5, 5 ); 

  /* composite original text_layer onto shadow_layer */
  $shadow_layer->compositeImage( $text_layer, Imagick::COMPOSITE_OVER, 0, 0 ); 
  $image->compositeImage( $shadow_layer, Imagick::COMPOSITE_OVER, 0, 0 );
}

$image->setImageFormat('gif');
header('Content-type: image/gif');
echo $image;
