<?php
include('/www/conf.php');
#if(!isset($_POST['id']) || !is_numeric($_POST['id']) || !isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) exit;
$mysqli->query('delete from reader_user_item where id_user='.$_SESSION['user_id'].' and id_item='.$_POST['id'].';');
?>