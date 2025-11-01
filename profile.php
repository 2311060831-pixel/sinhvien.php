<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'connect.php';

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$username = $_SESSION['username'];

// Get user information based on role
$user_info = [];
$error = '';
$success = '';

if ($role === 'student') {
    // Get student information
    $sql = "SELECT s.*, c.class_name, d.department_name 
            FROM students s
            LEFT JOIN classes c ON s.class_id = c.id
            LEFT JOIN departments d ON s.department_id = d.id
            WHERE s.user_id = ?";
    $stmt = mysqli_prepare($connection, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user_info = mysqli_fetch_assoc($result);
} elseif ($role === 'teacher') {
    // Get teacher information
    $sql = "SELECT t.*, d.department_name 
            FROM teachers t
            LEFT JOIN departments d ON t.department_id = d.id
            WHERE t.user_id = ?";
    $stmt = mysqli_prepare($connection, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user_info = mysqli_fetch_assoc($result);
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Get current password from database
    $sql = "SELECT password FROM users WHERE id = ?";
    $stmt = mysqli_prepare($connection, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    
    // Verify current password
    if (password_verify($current_password, $user['password']) || $current_password === $user['password']) {
        if ($new_password === $confirm_password) {
            if (strlen($new_password) >= 6) {
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $update_sql = "UPDATE users SET password = ? WHERE id = ?";
                $update_stmt = mysqli_prepare($connection, $update_sql);
                mysqli_stmt_bind_param($update_stmt, "si", $hashed, $user_id);
                
                if (mysqli_stmt_execute($update_stmt)) {
                    $success = 'Đổi mật khẩu thành công!';
                } else {
                    $error = 'Có lỗi xảy ra khi đổi mật khẩu';
                }
            } else {
                $error = 'Mật khẩu mới phải có ít nhất 6 ký tự';
            }
        } else {
            $error = 'Mật khẩu xác nhận không khớp';
        }
    } else {
        $error = 'Mật khẩu hiện tại không đúng';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Thông tin cá nhân - QLSV</title>
    <link rel="stylesheet" href="css/chung.css?v=3">
    <style>
        .profile-container {
            max-width: 900px;
        }
        .profile-card {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        .profile-header {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e6e9ee;
        }
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 36px;
            font-weight: bold;
            margin-right: 30px;
        }
        .profile-info {
            flex: 1;
        }
        .profile-info h2 {
            margin: 0 0 10px 0;
            color: #333;
        }
        .profile-info .role-badge {
            display: inline-block;
            padding: 4px 12px;
            background: #2b87ff;
            color: white;
            border-radius: 12px;
            font-size: 12px;
            text-transform: uppercase;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        .info-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 6px;
        }
        .info-item label {
            display: block;
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .info-item .value {
            font-size: 16px;
            color: #333;
            font-weight: 500;
        }
        .password-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        .password-form h3 {
            margin-top: 0;
            color: #333;
        }
        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
            .profile-avatar {
                margin-right: 0;
                margin-bottom: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="brand">QLSV</div>
        
        <div class="nav-section">
            <div class="nav-header">Chung</div>
            <a href="dashboard.php">Trang chủ</a>
            <a href="profile.php" class="active">Thông tin cá nhân</a>
        </div>

        <?php if (in_array($role, ['admin', 'teacher'])): ?>
        <div class="nav-section">
            <div class="nav-header">Quản lý</div>
            <?php if ($role === 'admin'): ?>
            <a href="account/list.php">Quản lý tài khoản</a>
            <a href="department/list.php">Quản lý khoa</a>
            <a href="classes/list.php">Quản lý lớp học</a>
            <?php endif; ?>
            <a href="student/list.php">Quản lý sinh viên</a>
            <?php if ($role === 'admin'): ?>
            <a href="teacher/list.php">Quản lý giảng viên</a>
            <?php endif; ?>
            <a href="subject/list.php">Quản lý môn học</a>
        </div>
        <?php endif; ?>

        <div class="nav-section">
            <div class="nav-header">Học tập</div>
            <?php if ($role === 'student'): ?>
            <a href="registration/index.php">Đăng ký môn học</a>
            <a href="grades/view.php">Xem điểm</a>
            <?php endif; ?>
            <?php if ($role === 'teacher'): ?>
            <a href="grades/input.php">Nhập điểm</a>
            <?php endif; ?>
            <a href="schedule/index.php">Thời khóa biểu</a>
            <a href="schedule/lichthi.php">Lịch thi</a>
        </div>

        <?php if ($role === 'student'): ?>
        <div class="nav-section">
            <div class="nav-header">Học phí</div>
            <a href="tuition/status.php">Tình trạng học phí</a>
            <a href="tuition/history.php">Lịch sử đóng học phí</a>
        </div>
        <?php endif; ?>

        <?php if ($role === 'admin'): ?>
        <div class="nav-section">
            <div class="nav-header">Báo cáo</div>
            <a href="reports/students.php">Thống kê sinh viên</a>
            <a href="reports/grades.php">Thống kê điểm số</a>
            <a href="reports/tuition.php">Thống kê học phí</a>
        </div>
        <?php endif; ?>

        <div class="nav-section">
            <a href="logout.php">Đăng xuất</a>
        </div>
    </div>

    <div class="main">
        <div class="profile-container">
            <h1>Thông tin cá nhân</h1>

            <?php if ($error): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success); ?>
            </div>
            <?php endif; ?>

            <div class="profile-card">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <?php 
                        if ($role === 'student' && !empty($user_info['full_name'])) {
                            echo strtoupper(mb_substr($user_info['full_name'], 0, 1));
                        } elseif ($role === 'teacher' && !empty($user_info['full_name'])) {
                            echo strtoupper(mb_substr($user_info['full_name'], 0, 1));
                        } else {
                            echo strtoupper(mb_substr($username, 0, 1));
                        }
                        ?>
                    </div>
                    <div class="profile-info">
                        <h2>
                            <?php 
                            if ($role === 'student' && !empty($user_info['full_name'])) {
                                echo htmlspecialchars($user_info['full_name']);
                            } elseif ($role === 'teacher' && !empty($user_info['full_name'])) {
                                echo htmlspecialchars($user_info['full_name']);
                            } else {
                                echo htmlspecialchars($username);
                            }
                            ?>
                        </h2>
                        <span class="role-badge">
                            <?php 
                            echo $role === 'admin' ? 'Quản trị viên' : 
                                ($role === 'teacher' ? 'Giảng viên' : 'Sinh viên'); 
                            ?>
                        </span>
                    </div>
                </div>

                <div class="info-grid">
                    <div class="info-item">
                        <label>Tên đăng nhập</label>
                        <div class="value"><?php echo htmlspecialchars($username); ?></div>
                    </div>

                    <?php if ($role === 'student' && $user_info): ?>
                    <div class="info-item">
                        <label>Mã sinh viên</label>
                        <div class="value"><?php echo htmlspecialchars($user_info['student_code']); ?></div>
                    </div>
                    <div class="info-item">
                        <label>Ngày sinh</label>
                        <div class="value"><?php echo date('d/m/Y', strtotime($user_info['date_of_birth'])); ?></div>
                    </div>
                    <div class="info-item">
                        <label>Giới tính</label>
                        <div class="value">
                            <?php 
                            echo $user_info['gender'] === 'M' ? 'Nam' : 
                                ($user_info['gender'] === 'F' ? 'Nữ' : 'Khác'); 
                            ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <label>Lớp</label>
                        <div class="value"><?php echo htmlspecialchars($user_info['class_name'] ?? 'Chưa có'); ?></div>
                    </div>
                    <div class="info-item">
                        <label>Khoa</label>
                        <div class="value"><?php echo htmlspecialchars($user_info['department_name'] ?? 'Chưa có'); ?></div>
                    </div>
                    <div class="info-item">
                        <label>Khóa học</label>
                        <div class="value"><?php echo htmlspecialchars($user_info['academic_year']); ?></div>
                    </div>
                    <?php elseif ($role === 'teacher' && $user_info): ?>
                    <div class="info-item">
                        <label>Mã giảng viên</label>
                        <div class="value"><?php echo htmlspecialchars($user_info['teacher_code']); ?></div>
                    </div>
                    <div class="info-item">
                        <label>Khoa</label>
                        <div class="value"><?php echo htmlspecialchars($user_info['department_name'] ?? 'Chưa có'); ?></div>
                    </div>
                    <?php endif; ?>

                    <div class="info-item">
                        <label>Vai trò</label>
                        <div class="value">
                            <?php 
                            echo $role === 'admin' ? 'Quản trị viên' : 
                                ($role === 'teacher' ? 'Giảng viên' : 'Sinh viên'); 
                            ?>
                        </div>
                    </div>
                </div>

                <div class="password-form">
                    <h3>Đổi mật khẩu</h3>
                    <form method="post" action="">
                        <input type="hidden" name="change_password" value="1">
                        
                        <div class="form-group">
                            <label for="current_password">Mật khẩu hiện tại</label>
                            <input type="password" id="current_password" name="current_password" required>
                        </div>

                        <div class="form-group">
                            <label for="new_password">Mật khẩu mới (tối thiểu 6 ký tự)</label>
                            <input type="password" id="new_password" name="new_password" required minlength="6">
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Xác nhận mật khẩu mới</label>
                            <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                        </div>

                        <button type="submit" class="btn btn-primary">Đổi mật khẩu</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
