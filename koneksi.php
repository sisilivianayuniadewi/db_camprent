<?php
$host     = "localhost";
$username = "root";
$password = "";
$database = "db_camprent";

$koneksi = new mysqli($host, $username, $password, $database);

if ($koneksi->connect_error) {
    die("Koneksi database gagal: " . $koneksi->connect_error);
}

date_default_timezone_set('Asia/Jakarta');
?>