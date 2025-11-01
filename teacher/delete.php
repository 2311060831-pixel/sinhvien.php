<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

require_once '../connect.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: list.php?error=' . urlencode('ID giảng viên không hợp lệ'));
    exit();
}

// Check if teacher has related records
$checks = [
    'subjects' => 'có môn học phụ trách',
    'schedules' => 'có lịch giảng dạy',
    'exam_schedules' => 'có lịch giám thị thi'
];

$warnings = [];
foreach ($checks as $table => $message) {
    $sql = "SELECT COUNT(*) as count FROM $table WHERE teacher_id = ?";
    $stmt = mysqli_prepare($connection, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    if ($row['count'] > 0) {
        $warnings[] = $message . ' (' . $row['count'] . ')';
    }
}

// Get teacher info
$sql = "SELECT t.*, u.username, d.department_name 
        FROM teachers t 
        JOIN users u ON t.user_id = u.id
        LEFT JOIN departments d ON t.department_id = d.id
        WHERE t.id = ?";
$stmt = mysqli_prepare($connection, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$teacher = mysqli_fetch_assoc($result);

if (!$teacher) {
    header('Location: list.php?error=' . urlencode('Giảng viên không tồn tại'));
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    mysqli_begin_transaction($connection);
    
    try {
        $user_id = $teacher['user_id'];
        
        // Update related tables to remove teacher reference
        $tables = ['subjects', 'schedules', 'exam_schedules'];
        foreach ($tables as $table) {
            $sql = "UPDATE $table SET teacher_id = NULL WHERE teacher_id = ?";
            $stmt = mysqli_prepare($connection, $sql);
            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);
        }
        
        // Delete teacher
        $sql = "DELETE FROM teachers WHERE id = ?";
        $stmt = mysqli_prepare($connection, $sql);
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        
        // Delete user account
        $sql = "DELETE FROM users WHERE id = ?";
        $stmt = mysqli_prepare($connection, $sql);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        
        mysqli_commit($connection);
        header('Location: list.php?success=' . urlencode('Giảng viên đã được xóa thành công!'));
        exit();
    } catch (Exception $e) {
        mysqli_rollback($connection);
        header('Location: list.php?error=' . urlencode('Có lỗi xảy ra: ' . $e->getMessage()));
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Xóa giảng viên - QLSV</title>
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
        .warning-box h3 {
            color: #ff6b00;
            margin-top: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .teacher-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .teacher-info .item {
            display: flex;
            padding: 8px 0;
            border-bottom: 1px solid #dee2e6;
        }
        .teacher-info .item:last-child {
            border-bottom: none;
        }
        .teacher-info .label {
            font-weight: 600;
            width: 150px;
            color: #495057;
        }
        .teacher-info .value {
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
        ul.warning-list {
            margin: 10px 0;
            padding-left: 20px;
        }
        ul.warning-list li {
            padding: 5px 0;
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
            <a href="../account/list.php">Quản lý tài khoản</a>
            <a href="../department/list.php">Quản lý khoa</a>
            <a href="../classes/list.php">Quản lý lớp học</a>
            <a href="../student/list.php">Quản lý sinh viên</a>
            <a href="list.php">Quản lý giảng viên</a>
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
            <h1>Xác nhận xóa giảng viên</h1>
        </div>

        <div class="confirm-container">
            <div class="teacher-info">
                <h3>Thông tin giảng viên sẽ bị xóa:</h3>
                <div class="item">
                    <div class="label">Mã giảng viên:</div>
                    <div class="value"><strong><?php echo htmlspecialchars($teacher['teacher_code']); ?></strong></div>
                </div>
                <div class="item">
                    <div class="label">Họ và tên:</div>
                    <div class="value"><?php echo htmlspecialchars($teacher['full_name']); ?></div>
                </div>
                <div class="item">
                    <div class="label">Khoa:</div>
                    <div class="value"><?php echo htmlspecialchars($teacher['department_name'] ?? 'Chưa phân công'); ?></div>
                </div>
                <div class="item">
                    <div class="label">Tài khoản:</div>
                    <div class="value"><?php echo htmlspecialchars($teacher['username']); ?></div>
                </div>
            </div>

            <?php if (!empty($warnings)): ?>
            <div class="warning-box">
                <h3>Cảnh báo quan trọng!</h3>
                <p>Giảng viên này đang được sử dụng trong hệ thống:</p>
                <ul class="warning-list">
                    <?php foreach ($warnings as $warning): ?>
                    <li>🔸 <?php echo htmlspecialchars($warning); ?></li>
                    <?php endforeach; ?>
                </ul>
                <p><strong>Các dữ liệu liên quan sẽ bị gỡ bỏ thông tin giảng viên!</strong></p>
            </div>
            <?php endif; ?>

            <div class="danger-zone">
                <h3 style="color: #dc3545; margin-top: 0;">🗑️ Vùng nguy hiểm</h3>
                <p>Hành động này <strong>KHÔNG THỂ HOÀN TÁC</strong>. Khi xóa giảng viên:</p>
                <ul>
                    <li>Tài khoản đăng nhập sẽ bị xóa vĩnh viễn</li>
                    <li>Các môn học sẽ mất thông tin giảng viên phụ trách</li>
                    <li>Lịch giảng dạy sẽ mất thông tin giảng viên</li>
                    <li>Lịch giám thị thi sẽ mất thông tin giám thị</li>
                </ul>
            </div>

            <form method="post" action="delete.php?id=<?php echo $id; ?>" 
                  onsubmit="return confirm('Bạn có CHẮC CHẮN muốn xóa giảng viên này?\n\nHành động này KHÔNG THỂ HOÀN TÁC!');">
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
