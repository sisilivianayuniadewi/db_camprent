<?php
session_start();
include "koneksi.php";

$error = "";

if (isset($_SESSION['user_id'])) {
    if ((int) $_SESSION['user_status'] === 1) {
        header("Location: admin/dashboard.php");
        exit;
    } elseif ((int) $_SESSION['user_status'] === 2) {
        header("Location: petugas/dashboard.php");
        exit;
    } elseif ((int) $_SESSION['user_status'] === 3) {
        header("Location: peminjam/dashboard.php");
        exit;
    }
}

if (isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = "Username dan password wajib diisi.";
    } else {
        $stmt = mysqli_prepare(
            $koneksi,
            "SELECT user_id, username, password, user_status
             FROM t_user
             WHERE username = ?
             LIMIT 1"
        );

        if (!$stmt) {
            die("Query login gagal: " . mysqli_error($koneksi));
        }

        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);

        $hasil = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($hasil);

        mysqli_stmt_close($stmt);

        if ($user) {
            $password_db = $user['password'];

            // Mendukung password MD5 dan password_hash()
            $password_cocok = password_verify($password, $password_db)
                || (
                    strlen($password_db) === 32
                    && ctype_xdigit($password_db)
                    && md5($password) === strtolower($password_db)
                );

            if ($password_cocok) {
                $role = (int) $user['user_status'];

                if (in_array($role, [1, 2, 3], true)) {
                    session_regenerate_id(true);

                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['user_status'] = $role;

                    if ($role === 1) {
                        header("Location: admin/dashboard.php");
                        exit;
                    } elseif ($role === 2) {
                        header("Location: petugas/dashboard.php");
                        exit;
                    } else {
                        header("Location: peminjam/dashboard.php");
                        exit;
                    }
                } else {
                    $error = "Hak akses akun tidak valid.";
                }
            } else {
                $error = "Username atau password salah.";
            }
        } else {
            $error = "Username atau password salah.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login CAMPrent</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #102a43, #176b78);
        }

        .login-card {
            width: 100%;
            max-width: 420px;
            background: white;
            padding: 35px;
            border-radius: 20px;
            box-shadow: 0 20px 60px #071b2b55;
        }

        .logo {
            width: 75px;
            height: 75px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            border-radius: 20px;
            color: white;
            font-size: 38px;
            background: linear-gradient(135deg, #12304a, #20a6ad);
        }

        h3 {
            font-weight: bold;
            color: #12304a;
            text-align: center;
            margin-bottom: 5px;
        }

        .subtitle {
            text-align: center;
            color: #718096;
            font-size: 13px;
            margin-bottom: 28px;
        }

        .form-label {
            font-size: 13px;
            font-weight: bold;
            color: #16324f;
        }

        .form-control {
            padding: 12px;
            border-radius: 9px;
        }

        .input-group-text {
            background: #f0f5fa;
            border-radius: 9px 0 0 9px;
        }

        .btn-login {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 9px;
            background: linear-gradient(135deg, #12304a, #176b78);
            color: white;
            font-weight: bold;
        }

        .btn-login:hover {
            color: white;
            background: #102a43;
        }

        .footer {
            text-align: center;
            color: #718096;
            font-size: 12px;
            margin-top: 22px;
        }
    </style>
</head>

<body>

<div class="login-card">
    <div class="logo">
        <i class="bi bi-cone-striped"></i>
    </div>

    <h3>CAMPrent</h3>
    <div class="subtitle">Sistem Peminjaman Alat</div>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger py-2 small">
            <i class="bi bi-exclamation-circle me-1"></i>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="index.php">
        <div class="mb-3">
            <label class="form-label">Username</label>
            <div class="input-group">
                <span class="input-group-text">
                    <i class="bi bi-person"></i>
                </span>
                <input type="text"
                       name="username"
                       class="form-control"
                       placeholder="Masukkan username"
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                       autocomplete="username"
                       required>
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label">Password</label>
            <div class="input-group">
                <span class="input-group-text">
                    <i class="bi bi-lock"></i>
                </span>
                <input type="password"
                       name="password"
                       id="password"
                       class="form-control"
                       placeholder="Masukkan password"
                       autocomplete="current-password"
                       required>
                <button type="button"
                        class="btn btn-outline-secondary"
                        onclick="lihatPassword()">
                    <i class="bi bi-eye" id="ikonPassword"></i>
                </button>
            </div>
        </div>

        <button type="submit" name="login" class="btn-login">
            <i class="bi bi-box-arrow-in-right me-2"></i>
            Masuk
        </button>
    </form>

    <div class="footer">
        &copy; <?= date('Y') ?> CAMPrent — Sistem Peminjaman Alat
    </div>
</div>

<script>
function lihatPassword() {
    const input = document.getElementById('password');
    const ikon = document.getElementById('ikonPassword');

    if (input.type === 'password') {
        input.type = 'text';
        ikon.className = 'bi bi-eye-slash';
    } else {
        input.type = 'password';
        ikon.className = 'bi bi-eye';
    }
}
</script>

</body>
</html>