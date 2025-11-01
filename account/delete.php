<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

require_once '../connect.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: list.php?error=' . urlencode('ID tài khoản không hợp lệ'));
    exit();
}

// Prevent deleting own account
if ($id == $_SESSION['user_id']) {
    header('Location: list.php?error=' . urlencode('Không thể xóa tài khoản của chính mình!'));
    exit();
}

// Get account info
$sql = "SELECT 
    u.*,
    s.full_name as student_name,
    s.student_code,
    t.full_name as teacher_name,
    t.teacher_code
FROM users u
LEFT JOIN students s ON u.id = s.user_id
LEFT JOIN teachers t ON u.id = t.user_id
WHERE u.id = ?";
$stmt = mysqli_prepare($connection, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$account = mysqli_fetch_assoc($result);

if (!$account) {
    header('Location: list.php?error=' . urlencode('Tài khoản không tồn tại'));
    exit();
}

// Check related data
$has_student = !empty($account['student_code']);
$has_teacher = !empty($account['teacher_code']);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    try {
        mysqli_begin_transaction($connection);
        
        // Note: We only delete the user account
        // Students and teachers records will remain but with user_id = NULL
        // This preserves historical data like grades, registrations, etc.
        
        if ($has_student) {
            $sql = "UPDATE students SET user_id = NULL WHERE user_id = ?";
            $stmt = mysqli_prepare($connection, $sql);
            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);
        }
        
        if ($has_teacher) {
            $sql = "UPDATE teachers SET user_id = NULL WHERE user_id = ?";
            $stmt = mysqli_prepare($connection, $sql);
            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);
        }
        
        // Delete user account
        $sql = "DELETE FROM users WHERE id = ?";
        $stmt = mysqli_prepare($connection, $sql);
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        
        mysqli_commit($connection);
        header('Location: list.php?success=' . urlencode('Tài khoản đã được xóa thành công!'));
        exit();
    } catch (Exception $e) {
        mysqli_rollback($connection);
        header('Location: list.php?error=' . urlencode('Có lỗi xảy ra: ' . $e->getMessage()));
        exit();
    }
}

$role_names = [
    'admin' => ' Admin',
    'teacher' => ' Giảng viên',
    'student' => ' Sinh viên'
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Xóa tài khoản - QLSV</title>
    <link rel="stylesheet" href="../css/chung.css?v=3">
    <style>
        .confirm-container {
            max-width: 600px;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-left: 4px solid #ff9800;
            padding: 20px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .account-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .account-info .item {
            display: flex;
            padding: 8px 0;
            border-bottom: 1px solid #dee2e6;
        }
        .account-info .item:last-child {
            border-bottom: none;
        }
        .account-info .label {
            font-weight: 600;
            width: 150px;
            color: #495057;
        }
        .account-info .value {
            flex: 1;
        }
        .danger-zone {
            background: #fee;
            border: 2px solid #dc3545;
            border-radius: 4px;
            padding: 20px;
            margin: 20px 0;
        }
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2b87ff;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
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
            <h1> Xác nhận xóa tài khoản</h1>
        </div>

        <div class="confirm-container">
            <div class="account-info">
                <h3>Thông tin tài khoản sẽ bị xóa:</h3>
                <div class="item">
                    <div class="label">Tên đăng nhập:</div>
                    <div class="value"><strong><?php echo htmlspecialchars($account['username']); ?></strong></div>
                </div>
                <div class="item">
                    <div class="label">Vai trò:</div>
                    <div class="value"><?php echo $role_names[$account['role']]; ?></div>
                </div>
                <?php if ($account['email']): ?>
                <div class="item">
                    <div class="label">Email:</div>
                    <div class="value"><?php echo htmlspecialchars($account['email']); ?></div>
                </div>
                <?php endif; ?>
                <?php if ($has_student): ?>
                <div class="item">
                    <div class="label">Sinh viên:</div>
                    <div class="value">
                        <?php echo htmlspecialchars($account['student_name']); ?> 
                        (<?php echo htmlspecialchars($account['student_code']); ?>)
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($has_teacher): ?>
                <div class="item">
                    <div class="label">Giảng viên:</div>
                    <div class="value">
                        <?php echo htmlspecialchars($account['teacher_name']); ?> 
                        (<?php echo htmlspecialchars($account['teacher_code']); ?>)
                    </div>
                </div>
                <?php endif; ?>
                <div class="item">
                    <div class="label">Ngày tạo:</div>
                    <div class="value"><?php echo date('d/m/Y H:i', strtotime($account['created_at'])); ?></div>
                </div>
            </div>

            <?php if ($has_student || $has_teacher): ?>
            <div class="info-box">
                <strong> Lưu ý:</strong> Tài khoản này liên kết với 
                <?php echo $has_student ? 'sinh viên' : 'giảng viên'; ?> trong hệ thống.
                <br><br>
                <strong>Dữ liệu được bảo toàn:</strong>
                <ul style="margin: 10px 0 0 20px;">
                    <?php if ($has_student): ?>
                    <li>Thông tin sinh viên, điểm số, đăng ký môn học sẽ được giữ lại</li>
                    <li>Sinh viên chỉ mất quyền đăng nhập hệ thống</li>
                    <?php endif; ?>
                    <?php if ($has_teacher): ?>
                    <li>Thông tin giảng viên, môn học phụ trách sẽ được giữ lại</li>
                    <li>Giảng viên chỉ mất quyền đăng nhập hệ thống</li>
                    <?php endif; ?>
                </ul>
            </div>
            <?php endif; ?>

            <div class="danger-zone">
                <h3 style="color: #dc3545; margin-top: 0;"> Vùng nguy hiểm</h3>
                <p>Hành động này <strong>KHÔNG THỂ HOÀN TÁC</strong>. Khi xóa tài khoản:</p>
                <ul>
                    <li>Người dùng sẽ không thể đăng nhập được nữa</li>
                    <li>Tài khoản sẽ bị xóa vĩnh viễn khỏi hệ thống</li>
                    <?php if (!$has_student && !$has_teacher): ?>
                    <li>Không có dữ liệu nào khác bị ảnh hưởng</li>
                    <?php endif; ?>
                </ul>
            </div>

            <form method="post" action="delete.php?id=<?php echo $id; ?>" 
                  onsubmit="return confirm('Bạn có CHẮC CHẮN muốn xóa tài khoản này?\n\nHành động này KHÔNG THỂ HOÀN TÁC!');">
                <div class="form-actions">
                    <a href="list.php" class="btn btn-secondary">← Hủy và quay lại</a>
                    <button type="submit" name="confirm" class="btn btn-danger">
                         Xác nhận xóa
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
