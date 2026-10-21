
<?php
session_start();
include "koneksi.php";

if (isset($_POST['login'])) {

    $username = trim($_POST['username'] ?? '');
    $password = md5($_POST['password'] ?? '');

    $stmt = mysqli_prepare($koneksi, "
        SELECT user_id, username, password, user_status, status
        FROM t_user
        WHERE username = ?
        LIMIT 1
    ");

    if (!$stmt) {
        die("Query login gagal: " . mysqli_error($koneksi));
    }

    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    if ($row = mysqli_fetch_assoc($result)) {

        if (
            $row['status'] === 'aktif' &&
            $row['password'] === $password &&
            in_array((int)$row['user_status'], [1, 2, 3], true)
        ) {

            session_regenerate_id(true);

            $_SESSION['user_id'] = $row['user_id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['user_status'] = (int)$row['user_status'];

            if ((int)$row['user_status'] === 1) {
                header("Location: admin/dashboard.php");
                exit;
            }

            if ((int)$row['user_status'] === 2) {
                header("Location: petugas/dashboard.php");
                exit;
            }

            if ((int)$row['user_status'] === 3) {
                header("Location: peminjam/dashboard.php");
                exit;
            }

        }
    }

    mysqli_stmt_close($stmt);

    header("Location: index.php?error=1");
    exit;
}

header("Location: index.php");
exit;
?>
