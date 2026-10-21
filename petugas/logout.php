
<?php
session_start();

// Menghapus semua data session
$_SESSION = [];

// Menghapus session
session_destroy();

// Kembali ke halaman login
header("Location: ../index.php");
exit;
?>
