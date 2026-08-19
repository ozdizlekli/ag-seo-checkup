<?php
session_start();
require_once 'db.php';

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header("Location: index.php");
    exit;
}

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = "Lütfen kullanıcı adı ve şifre girin.";
    } else {
        if ($pdo) {
            try {
                // Check if user exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->rowCount() > 0) {
                    $error = "Bu kullanıcı adı zaten alınmış.";
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
                    if ($stmt->execute([$username, $hashed_password])) {
                        $success = "Kayıt başarılı! Şimdi giriş yapabilirsiniz.";
                    } else {
                        $error = "Kayıt sırasında bir hata oluştu.";
                    }
                }
            } catch (PDOException $e) {
                // Fallback for demo if table doesn't exist
                $error = "Veritabanı hatası: Tablolar oluşturulmamış olabilir. (" . $e->getMessage() . ")";
            }
        } else {
            // No DB connection, use JSON fallback for demo
            $users = [];
            if (file_exists('users.json')) {
                $users = json_decode(file_get_contents('users.json'), true);
            }
            if (isset($users[$username])) {
                $error = "Bu kullanıcı adı zaten alınmış. (Demo Modu)";
            } else {
                $users[$username] = password_hash($password, PASSWORD_DEFAULT);
                file_put_contents('users.json', json_encode($users));
                $success = "Kayıt başarılı! Şimdi giriş yapabilirsiniz. (Demo Modu)";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kayıt Ol - AG SEO Check Up</title>
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
        .success { color: #10b981; font-size: 13px; margin-bottom: 15px; text-align: center; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-size: 13px; font-weight: 500; }
        .form-group input { width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 6px; font-size: 14px; background: var(--bg); color: var(--ink); }
        .btn-login { width: 100%; padding: 12px; background: var(--accent); color: #fff; border: none; border-radius: 6px; font-weight: 600; font-size: 15px; cursor: pointer; margin-bottom: 15px;}
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
        <?php if($success): ?><div class="success"><?php echo $success; ?></div><?php endif; ?>
        <form method="POST" action="">
            <div class="form-group">
                <label>Kullanıcı Adı Seçin</label>
                <input type="text" name="username" required>
            </div>
            <div class="form-group">
                <label>Şifre Belirleyin</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" class="btn-login">Kayıt Ol</button>
            <a href="login.php" class="link">Zaten hesabınız var mı? Giriş yapın.</a>
        </form>
    </div>
</body>
</html>
