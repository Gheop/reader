<?php
if(!isset($_REQUEST['f'])) exit;

$file = $_REQUEST['f'];
if($data = file_get_contents($file)) {
    $tmpfile='/ftp/Torrents/'.md5($data).'.torrent';
    file_put_contents($tmpfile, $data);
}