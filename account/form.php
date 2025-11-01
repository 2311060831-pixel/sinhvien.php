<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

require_once '../connect.php';

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = mysqli_real_escape_string($connection, $_POST['username']);
    $password = $_POST['password'];
    $role = mysqli_real_escape_string($connection, $_POST['role']);
    $email = mysqli_real_escape_string($connection, $_POST['email']);

    try {
        // Check if username exists
        $check_sql = "SELECT id FROM users WHERE username = ?";
        $stmt = mysqli_prepare($connection, $check_sql);
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        if (mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0) {
            throw new Exception("Tên đăng nhập đã tồn tại!");
        }
        
        // Insert user account
        $sql = "INSERT INTO users (username, password, role, email) VALUES (?, ?, ?, ?)";
        $stmt = mysqli_prepare($connection, $sql);
        mysqli_stmt_bind_param($stmt, "ssss", $username, $password, $role, $email);
        
        if (mysqli_stmt_execute($stmt)) {
            header('Location: list.php?success=' . urlencode('Thêm tài khoản thành công!'));
            exit();
        } else {
            throw new Exception("Có lỗi xảy ra khi thêm tài khoản!");
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Thêm tài khoản - QLSV</title>
    <link rel="stylesheet" href="../css/chung.css?v=3">
    <style>
        .form-container {
            max-width: 600px;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .form-actions {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .info-box {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 4px solid #2b87ff;
        }
        .warning-box {
            background: #fff3cd;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 4px solid #ffc107;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="brand">QLSV</div>
        
        <div class="nav-section">
            <div class="nav-header">Chung</div>
            <a href="../dashboard.php">Trang chủ</a>
            <a href="../profile.php">Thông tin cá nhân</a>
        </div>

        <div class="nav-section">
            <div class="nav-header">Quản lý</div>
            <a href="list.php">Quản lý tài khoản</a>
            <a href="../department/list.php">Quản lý khoa</a>
            <a href="../classes/list.php">Quản lý lớp học</a>
            <a href="../student/list.php">Quản lý sinh viên</a>
            <a href="../teacher/list.php">Quản lý giảng viên</a>
            <a href="../subject/list.php">Quản lý môn học</a>
        </div>

        <div class="nav-section">
            <div class="nav-header">Học tập</div>
            <a href="../schedule/index.php">Thời khóa biểu</a>
            <a href="../schedule/lichthi.php">Lịch thi</a>
        </div>

        <div class="nav-section">
            <div class="nav-header">Báo cáo</div>
            <a href="../reports/students.php">Thống kê sinh viên</a>
            <a href="../reports/grades.php">Thống kê điểm số</a>
            <a href="../reports/tuition.php">Thống kê học phí</a>
        </div>

        <div class="nav-section">
            <a href="../logout.php">Đăng xuất</a>
        </div>
    </div>

    <div class="main">
        <div class="actions">
            <h1> Thêm tài khoản mới</h1>
        </div>

        <div class="form-container">
            <?php if ($error): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>

            <div class="info-box">
                <strong> Lưu ý:</strong> Tài khoản này chỉ dùng để đăng nhập hệ thống. 
                Để tạo sinh viên/giảng viên đầy đủ thông tin, vui lòng sử dụng chức năng 
                "Quản lý sinh viên" hoặc "Quản lý giảng viên".
            </div>

            <div class="warning-box">
                <strong> Quan trọng:</strong> Mật khẩu lưu dưới dạng plain text. 
                Người dùng có thể đổi mật khẩu sau khi đăng nhập.
            </div>

            <form method="post" action="form.php">
                <div class="form-group">
                    <label for="username">Tên đăng nhập *</label>
                    <input type="text" id="username" name="username" 
                           required 
                           placeholder="VD: admin, gv001, sv001">
                    <small style="color: #666;">Tên đăng nhập phải là duy nhất</small>
                </div>

                <div class="form-group">
                    <label for="password">Mật khẩu *</label>
                    <input type="text" id="password" name="password" 
                           required
                           placeholder="Nhập mật khẩu">
                    <small style="color: #666;">Mật khẩu có thể thay đổi sau</small>
                </div>

                <div class="form-group">
                    <label for="role">Vai trò *</label>
                    <select id="role" name="role" required>
                        <option value="">Chọn vai trò</option>
                        <option value="admin"> Admin</option>
                        <option value="teacher"> Giảng viên</option>
                        <option value="student"> Sinh viên</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" 
                           placeholder="email@example.com">
                    <small style="color: #666;">Tùy chọn</small>
                </div>

                <div class="form-actions">
                    <a href="list.php" class="btn btn-secondary">Hủy</a>
                    <button type="submit" class="btn btn-primary">
                        Thêm tài khoản
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
