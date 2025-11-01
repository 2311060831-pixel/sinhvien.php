<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

require_once '../connect.php';

$id = $_GET['id'] ?? null;

if (!$id) {
    header('Location: list.php?error=' . urlencode('ID khoa không hợp lệ'));
    exit();
}

// Get department info with statistics
$sql = "SELECT 
    d.*,
    COUNT(DISTINCT c.id) as class_count,
    COUNT(DISTINCT s.id) as student_count,
    COUNT(DISTINCT t.id) as teacher_count
FROM departments d
LEFT JOIN classes c ON d.id = c.department_id
LEFT JOIN students s ON d.id = s.department_id
LEFT JOIN teachers t ON d.id = t.department_id
WHERE d.id = ?
GROUP BY d.id";

$stmt = mysqli_prepare($connection, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$department = mysqli_fetch_assoc($result);

if (!$department) {
    header('Location: list.php?error=' . urlencode('Khoa không tồn tại'));
    exit();
}

// Handle delete confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    // Check if has related data
    if ($department['class_count'] > 0 || $department['student_count'] > 0 || $department['teacher_count'] > 0) {
        header('Location: list.php?error=' . urlencode('Không thể xóa khoa vì còn ' . $department['student_count'] . ' sinh viên, ' . $department['teacher_count'] . ' giảng viên, và ' . $department['class_count'] . ' lớp học!'));
        exit();
    }
    
    try {
        $sql = "DELETE FROM departments WHERE id = ?";
        $stmt = mysqli_prepare($connection, $sql);
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        
        header('Location: list.php?success=' . urlencode('Xóa khoa thành công!'));
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
    <title>Xóa Khoa - QLSV</title>
    <link rel="stylesheet" href="../css/chung.css?v=3">
    <style>
        .confirm-container {
            max-width: 600px;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .department-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .department-info h3 {
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
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-left: 4px solid #ff9800;
            padding: 20px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .danger-zone {
            background: #fee;
            border: 2px solid #dc3545;
            border-radius: 4px;
            padding: 20px;
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
            <a href="list.php">Quản lý khoa</a>
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
            <h1> Xác nhận xóa khoa</h1>
        </div>

        <div class="confirm-container">
            <div class="department-info">
                <h3>Thông tin khoa sẽ bị xóa:</h3>
                <div class="info-item">
                    <strong>Mã khoa:</strong> <?php echo htmlspecialchars($department['department_code']); ?>
                </div>
                <div class="info-item">
                    <strong>Tên khoa:</strong> <?php echo htmlspecialchars($department['department_name']); ?>
                </div>
            </div>

            <?php if ($department['class_count'] > 0 || $department['student_count'] > 0 || $department['teacher_count'] > 0): ?>
            <div class="danger-zone">
                <h3 style="color: #dc3545; margin-top: 0;"> Không thể xóa khoa này!</h3>
                <p>Khoa này hiện đang có:</p>
                <ul>
                    <?php if ($department['class_count'] > 0): ?>
                    <li><strong><?php echo $department['class_count']; ?></strong> lớp học</li>
                    <?php endif; ?>
                    <?php if ($department['teacher_count'] > 0): ?>
                    <li><strong><?php echo $department['teacher_count']; ?></strong> giảng viên</li>
                    <?php endif; ?>
                    <?php if ($department['student_count'] > 0): ?>
                    <li><strong><?php echo $department['student_count']; ?></strong> sinh viên</li>
                    <?php endif; ?>
                </ul>
                <p><strong>Hành động cần thiết:</strong></p>
                <ol>
                    <li>Chuyển tất cả sinh viên sang khoa khác</li>
                    <li>Chuyển tất cả giảng viên sang khoa khác</li>
                    <li>Xóa hoặc chuyển tất cả lớp học</li>
                    <li>Sau đó mới có thể xóa khoa này</li>
                </ol>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <a href="list.php" class="btn btn-secondary" style="flex: 1;">← Quay lại danh sách</a>
            </div>

            <?php else: ?>
            
            <div class="warning-box">
                <strong> Cảnh báo:</strong> Hành động này không thể hoàn tác!
                <br><br>
                Khi xóa khoa này:
                <ul>
                    <li>Tất cả thông tin của khoa sẽ bị xóa vĩnh viễn</li>
                    <li>Không thể khôi phục lại dữ liệu</li>
                </ul>
            </div>

            <form method="post" action="delete.php?id=<?php echo $id; ?>"
                  onsubmit="return confirm('Bạn có CHẮC CHẮN muốn xóa khoa <?php echo htmlspecialchars($department['department_name']); ?>?\n\nHành động này KHÔNG THỂ HOÀN TÁC!');">
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
