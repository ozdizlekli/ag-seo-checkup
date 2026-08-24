<?php
session_start();
require_once 'db.php';

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header("Location: index.php");
    exit;
}

$error = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Default admin override for quick test
    if ($username === 'admin' && $password === 'admin123') {
        $_SESSION['loggedin'] = true;
        $_SESSION['username'] = 'admin';
        header("Location: index.php");
        exit;
    }
    
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("SELECT id, password FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['loggedin'] = true;
                $_SESSION['username'] = $username;
                header("Location: index.php");
                exit;
            } else {
                $error = "Geçersiz kullanıcı adı veya şifre.";
            }
        } catch (PDOException $e) {
            $error = "Veritabanı hatası: " . $e->getMessage();
        }
    } else {
        // No DB connection, use JSON fallback
        $users = [];
        $users_file = __DIR__ . '/users.json';
        if (file_exists($users_file)) {
            $users = json_decode(file_get_contents($users_file), true);
        }
        if (isset($users[$username]) && password_verify($password, $users[$username])) {
            $_SESSION['loggedin'] = true;
            $_SESSION['username'] = $username;
            header("Location: index.php");
            exit;
        } else {
            $error = "Geçersiz kullanıcı adı veya şifre.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş Yap - AG SEO Check Up</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            background-color: var(--bg);
            color: var(--ink);
            font-family: 'Inter', sans-serif;
            margin: 0;
        }
        .login-box {
            background: var(--paper);
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            width: 100%;
            max-width: 400px;
            border: 1px solid var(--border);
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-header svg {
            margin-bottom: 15px;
        }
        .login-header h1 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 24px;
            margin: 0;
            color: var(--ink);
        }
        .error { color: #ef4444; font-size: 13px; margin-bottom: 15px; text-align: center; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-size: 13px; font-weight: 500; }
        .form-group input { width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 6px; font-size: 14px; background: var(--bg); color: var(--ink); }
        .btn-login { width: 100%; padding: 12px; background: var(--accent); color: #fff; border: none; border-radius: 6px; font-weight: 600; font-size: 15px; cursor: pointer; margin-bottom: 15px; }
        .link { font-size: 13px; text-align: center; display: block; color: var(--accent); text-decoration: none; }
        .link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="login-box">
        <div class="login-header">
            <svg viewBox="0 0 36 36" width="48" height="48">
              <circle cx="18" cy="18" r="15" fill="none" stroke="var(--accent)" stroke-width="2"/>
              <circle cx="18" cy="18" r="10" fill="none" stroke="var(--accent)" stroke-width="2" opacity="0.5"/>
              <circle cx="18" cy="18" r="3" fill="var(--accent)"/>
            </svg>
            <h1>AG_seo_check_up</h1>
        </div>
        <?php if($error): ?><div class="error"><?php echo $error; ?></div><?php endif; ?>
        <form method="POST" action="">
            <div class="form-group">
                <label>Kullanıcı Adı</label>
                <input type="text" name="username" required>
            </div>
            <div class="form-group">
                <label>Şifre</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" class="btn-login">Giriş Yap</button>
            <a href="register.php" class="link">Hesabınız yok mu? Yeni kayıt olun.</a>
        </form>
    </div>
</body>
</html>
