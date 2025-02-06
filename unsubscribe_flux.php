<?php
include('/www/conf.php');
if(!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) { exit;}
//$_POST['link'] = 45;
if(!isset($_POST['link'])) {
 
  if(!isset($_GET['link'])) {
    print "Error : Pas de flux trouvé !"; 
    exit;
  }
  else $_POST['link'] = $_GET['link'];
}
if(!is_numeric($_POST['link'])) die('id error');
  $stmt = $mysqli->prepare("delete FROM reader_user_flux where id_flux=$_POST[link] and id_user=$_SESSION[user_id];") or die($mysqli->error); 
  $stmt->execute() or die("error");
  $stmt->close();
  $stmt = $mysqli->prepare("SELECT * FROM reader_user_flux where id_flux=$_POST[link];");
  $stmt->execute();
  $stmt->store_result();
  if(0 ==  $stmt->num_rows) {
    $r = $mysqli->prepare("delete FROM reader_flux where id=$_POST[link];") or die($mysqli->error);
    $r->execute();
  }
  $r->close();
  $stmt->close();
  $mysqli->close();

?>
