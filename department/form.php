<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

require_once '../connect.php';

$id = $_GET['id'] ?? null;
$department = null;

// Get department data for edit
if ($id) {
    $sql = "SELECT * FROM departments WHERE id = ?";
    $stmt = mysqli_prepare($connection, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $department = mysqli_fetch_assoc($result);
    
    if (!$department) {
        header('Location: list.php?error=' . urlencode('Khoa không tồn tại'));
        exit();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $department_code = trim($_POST['department_code']);
    $department_name = trim($_POST['department_name']);
    
    // Validation
    if (empty($department_code) || empty($department_name)) {
        $error = 'Vui lòng điền đầy đủ mã khoa và tên khoa';
    } else {
        // Check duplicate code
        $check_sql = "SELECT id FROM departments WHERE department_code = ? AND id != ?";
        $stmt = mysqli_prepare($connection, $check_sql);
        $check_id = $id ?? 0;
        mysqli_stmt_bind_param($stmt, "si", $department_code, $check_id);
        mysqli_stmt_execute($stmt);
        $existing = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($existing) > 0) {
            $error = 'Mã khoa đã tồn tại!';
        } else {
            try {
                if ($id) {
                    // Update
                    $sql = "UPDATE departments SET 
                            department_code = ?, 
                            department_name = ?
                            WHERE id = ?";
                    $stmt = mysqli_prepare($connection, $sql);
                    mysqli_stmt_bind_param($stmt, "ssi", $department_code, $department_name, $id);
                    mysqli_stmt_execute($stmt);
                    
                    header('Location: list.php?success=' . urlencode('Cập nhật khoa thành công!'));
                    exit();
                } else {
                    // Insert
                    $sql = "INSERT INTO departments (department_code, department_name) 
                            VALUES (?, ?)";
                    $stmt = mysqli_prepare($connection, $sql);
                    mysqli_stmt_bind_param($stmt, "ss", $department_code, $department_name);
                    mysqli_stmt_execute($stmt);
                    
                    header('Location: list.php?success=' . urlencode('Thêm khoa mới thành công!'));
                    exit();
                }
            } catch (Exception $e) {
                $error = 'Có lỗi xảy ra: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $id ? 'Sửa' : 'Thêm'; ?> Khoa - QLSV</title>
    <link rel="stylesheet" href="../css/chung.css?v=3">
    <style>
        .form-container {
            max-width: 800px;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .form-header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e9ecef;
        }
        .form-header h2 {
            margin: 0;
            color: #2c3e50;
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
            <a href="list.php" class="active">Quản lý khoa</a>
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
            <h1><?php echo $id ? ' Sửa' : ' Thêm'; ?> Khoa</h1>
            <a href="list.php" class="btn btn-secondary">← Quay lại danh sách</a>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="form-container">
            <div class="form-header">
                <h2><?php echo $id ? 'Cập nhật thông tin khoa' : 'Thêm khoa mới'; ?></h2>
            </div>

            <form method="post" action="form.php<?php echo $id ? '?id=' . $id : ''; ?>">
                <div class="form-group">
                    <label>Mã khoa: <span style="color: red;">*</span></label>
                    <input type="text" name="department_code" class="form-control" 
                           placeholder="VD: CNTT, KTĐT, QTKD..."
                           value="<?php echo htmlspecialchars($department['department_code'] ?? ''); ?>"
                           required
                           <?php echo $id ? '' : 'autofocus'; ?>>
                    <small style="color: #6c757d;">Mã viết tắt của khoa (không dấu, chữ hoa)</small>
                </div>

                <div class="form-group">
                    <label>Tên khoa: <span style="color: red;">*</span></label>
                    <input type="text" name="department_name" class="form-control" 
                           placeholder="VD: Công nghệ thông tin"
                           value="<?php echo htmlspecialchars($department['department_name'] ?? ''); ?>"
                           required>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e9ecef;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">
                        <?php echo $id ? ' Cập nhật' : ' Thêm khoa'; ?>
                    </button>
                    <a href="list.php" class="btn btn-secondary"> Hủy</a>
                </div>
            </form>
        </div>

        <?php if ($id && $department): ?>
        <div class="card" style="margin-top: 30px;">
            <div class="card-body">
                <h3> Thông tin bổ sung</h3>
                <p style="color: #6c757d;">
                    Khoa này hiện có:
                    <?php
                    $stats_sql = "SELECT 
                        COUNT(DISTINCT c.id) as class_count,
                        COUNT(DISTINCT s.id) as student_count,
                        COUNT(DISTINCT t.id) as teacher_count
                    FROM departments d
                    LEFT JOIN classes c ON d.id = c.department_id
                    LEFT JOIN students s ON d.id = s.department_id
                    LEFT JOIN teachers t ON d.id = t.department_id
                    WHERE d.id = ?";
                    $stmt = mysqli_prepare($connection, $stats_sql);
                    mysqli_stmt_bind_param($stmt, "i", $id);
                    mysqli_stmt_execute($stmt);
                    $stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
                    ?>
                </p>
                <ul>
                    <li><strong><?php echo $stats['class_count']; ?></strong> lớp học</li>
                    <li><strong><?php echo $stats['teacher_count']; ?></strong> giảng viên</li>
                    <li><strong><?php echo $stats['student_count']; ?></strong> sinh viên</li>
                </ul>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
