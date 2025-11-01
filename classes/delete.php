<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

require_once '../connect.php';

$id = $_GET['id'] ?? null;

if (!$id) {
    header('Location: list.php?error=' . urlencode('ID lớp học không hợp lệ'));
    exit();
}

// Get class info with statistics
$sql = "SELECT 
    c.*,
    d.department_name,
    d.department_code,
    COUNT(s.id) as student_count
FROM classes c
LEFT JOIN departments d ON c.department_id = d.id
LEFT JOIN students s ON c.id = s.class_id
WHERE c.id = ?
GROUP BY c.id";

$stmt = mysqli_prepare($connection, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$class = mysqli_fetch_assoc($result);

if (!$class) {
    header('Location: list.php?error=' . urlencode('Lớp học không tồn tại'));
    exit();
}

// Handle delete confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    // Check if has students
    if ($class['student_count'] > 0) {
        header('Location: list.php?error=' . urlencode('Không thể xóa lớp vì còn ' . $class['student_count'] . ' sinh viên!'));
        exit();
    }
    
    try {
        $sql = "DELETE FROM classes WHERE id = ?";
        $stmt = mysqli_prepare($connection, $sql);
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        
        header('Location: list.php?success=' . urlencode('Xóa lớp học thành công!'));
        exit();
    } catch (Exception $e) {
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
    <title>Xóa Lớp học - QLSV</title>
    <link rel="stylesheet" href="../css/chung.css?v=3">
    <style>
        .confirm-container {
            max-width: 600px;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .class-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .class-info h3 {
            margin-top: 0;
            color: #2c3e50;
        }
        .info-item {
            padding: 10px 0;
            border-bottom: 1px solid #dee2e6;
        }
        .info-item:last-child {
            border-bottom: none;
        }
        .danger-zone {
            background: #fee;
            border: 2px solid #dc3545;
            border-radius: 4px;
            padding: 20px;
            margin: 20px 0;
        }
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-left: 4px solid #ff9800;
            padding: 20px;
            border-radius: 4px;
            margin: 20px 0;
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
            <a href="list.php">Quản lý lớp học</a>
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
            <h1> Xác nhận xóa lớp học</h1>
        </div>

        <div class="confirm-container">
            <div class="class-info">
                <h3>Thông tin lớp học sẽ bị xóa:</h3>
                <div class="info-item">
                    <strong>Mã lớp:</strong> <?php echo htmlspecialchars($class['class_code']); ?>
                </div>
                <div class="info-item">
                    <strong>Tên lớp:</strong> <?php echo htmlspecialchars($class['class_name']); ?>
                </div>
                <div class="info-item">
                    <strong>Khoa:</strong> <?php echo htmlspecialchars($class['department_code'] . ' - ' . $class['department_name']); ?>
                </div>
                <?php if ($class['academic_year']): ?>
                <div class="info-item">
                    <strong>Khóa:</strong> <?php echo htmlspecialchars($class['academic_year']); ?>
                </div>
                <?php endif; ?>
                <div class="info-item">
                    <strong>Số sinh viên:</strong> <?php echo $class['student_count']; ?>
                </div>
            </div>

            <?php if ($class['student_count'] > 0): ?>
            <div class="danger-zone">
                <h3 style="color: #dc3545; margin-top: 0;"> Không thể xóa lớp này!</h3>
                <p>Lớp này hiện đang có <strong><?php echo $class['student_count']; ?></strong> sinh viên.</p>
                <p><strong>Hành động cần thiết:</strong></p>
                <ol>
                    <li>Chuyển tất cả sinh viên sang lớp khác</li>
                    <li>Hoặc xóa tất cả sinh viên (không khuyến khích)</li>
                    <li>Sau đó mới có thể xóa lớp này</li>
                </ol>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <a href="list.php" class="btn btn-secondary" style="flex: 1;">← Quay lại danh sách</a>
                <a href="../student/list.php?class=<?php echo $class['class_code']; ?>" 
                   class="btn btn-primary" style="flex: 1;">
                    👥 Xem sinh viên
                </a>
            </div>

            <?php else: ?>
            
            <div class="warning-box">
                <strong> Cảnh báo:</strong> Hành động này không thể hoàn tác!
                <br><br>
                Khi xóa lớp học này:
                <ul>
                    <li>Tất cả thông tin của lớp sẽ bị xóa vĩnh viễn</li>
                    <li>Không thể khôi phục lại dữ liệu</li>
                </ul>
            </div>

            <form method="post" action="delete.php?id=<?php echo $id; ?>"
                  onsubmit="return confirm('Bạn có CHẮC CHẮN muốn xóa lớp <?php echo htmlspecialchars($class['class_name']); ?>?\n\nHành động này KHÔNG THỂ HOÀN TÁC!');">
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <a href="list.php" class="btn btn-secondary" style="flex: 1;">← Hủy và quay lại</a>
                    <button type="submit" name="confirm" class="btn btn-danger" style="flex: 1;">
                         Xác nhận xóa
                    </button>
                </div>
            </form>

            <?php endif; ?>
        </div>
    </div>
</body>
</html>
