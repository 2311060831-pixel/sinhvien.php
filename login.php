<?php
session_start();
include 'connect.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($connection) {
        $stmt = mysqli_prepare($connection, "SELECT id, username, password, role FROM users WHERE username = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($user = mysqli_fetch_assoc($result)) {
            // Check if password is hashed or plain text
            $password_valid = false;
            
            // First try with password_verify (for hashed passwords)
            if (password_verify($password, $user['password'])) {
                $password_valid = true;
            }
            // If that fails, try direct comparison (for plain text passwords - only for development)
            elseif ($password === $user['password']) {
                $password_valid = true;
                
                // Auto-hash the password for security
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $update_sql = "UPDATE users SET password = ? WHERE id = ?";
                $update_stmt = mysqli_prepare($connection, $update_sql);
                mysqli_stmt_bind_param($update_stmt, "si", $hashed, $user['id']);
                mysqli_stmt_execute($update_stmt);
                mysqli_stmt_close($update_stmt);
            }
            
            if ($password_valid) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Sai mật khẩu';
            }
        } else {
            $error = 'Tài khoản không tồn tại';
        }
        mysqli_stmt_close($stmt);
    } else {
        $error = 'Lỗi kết nối cơ sở dữ liệu';
    }
}
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Đăng nhập - QLSV</title>
<style>
body{font-family:Arial,Helvetica,sans-serif;background:#f5f7fa;margin:0;}
.login-wrap{max-width:420px;margin:80px auto;background:#fff;border:1px solid #e2e6ea;padding:28px;border-radius:8px;box-shadow:0 6px 18px rgba(0,0,0,0.06);}
h2{margin:0 0 15px;color:#333;}
label{display:block;margin:10px 0 6px;color:#555;}
input[type=text],input[type=password]{width:100%;padding:10px;border:1px solid #ccd1d9;border-radius:6px;}
button{margin-top:15px;padding:10px 14px;background:#2b87ff;color:#fff;border:none;border-radius:6px;cursor:pointer;}
.note{color:#d9534f;margin-top:10px;}
.small{font-size:13px;color:#777;margin-top:8px;}
</style>
</head>
<body>
<div class="login-wrap">
  <h2>Đăng nhập - QLSV</h2>
  <?php if($error) echo '<div class="note">'.htmlspecialchars($error).'</div>'; ?>
  <form method="post" action="login.php">
    <label>Username</label>
    <input type="text" name="username" required>
    <label>Password</label>
    <input type="password" name="password" required>
    <button type="submit">Login</button>
    <div class="small"></div>
  </form>
</div>
</body>
</html>
