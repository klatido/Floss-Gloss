<?php
$host = "127.0.0.1";
$user = "root";
$password = "";
$database = "floss_gloss_dental";
$port = 3307;

$conn = mysqli_connect($host, $user, $password, $database, $port);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>