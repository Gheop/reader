<?php
$image = new Imagick('favicon.gif');
if(isset($_GET['n']) && is_numeric($_GET['n']) && $_GET['n'] > 0) {
  $draw = new ImagickDraw();
  $draw->setFillColor('black');
  //  $draw->setFont('fontawesome-webfont.ttf');
  if($_GET['n'] > 999 && $_GET['n'] = "∞") $draw->setFontSize(70);
  else if($_GET['n'] <100) $draw->setFontSize(50);
  else $draw->setFontSize(35);
  $draw->setGravity (Imagick::GRAVITY_CENTER);
  $image->annotateImage($draw, 0, 0, 0, $_GET['n']);
 }
$image->setImageFormat('gif');
header('Content-type: image/gif');
echo $image;
