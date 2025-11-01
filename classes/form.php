<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

require_once '../connect.php';

$id = $_GET['id'] ?? null;
$class = null;

// Get class data for edit
if ($id) {
    $sql = "SELECT * FROM classes WHERE id = ?";
    $stmt = mysqli_prepare($connection, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $class = mysqli_fetch_assoc($result);
    
    if (!$class) {
        header('Location: list.php?error=' . urlencode('Lớp học không tồn tại'));
        exit();
    }
}

// Get departments
$dept_sql = "SELECT id, department_code, department_name FROM departments ORDER BY department_code";
$departments = mysqli_query($connection, $dept_sql);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $class_code = trim($_POST['class_code']);
    $class_name = trim($_POST['class_name']);
    $department_id = intval($_POST['department_id']);
    $academic_year = trim($_POST['academic_year']);
    
    // Validation
    if (empty($class_code) || empty($class_name) || !$department_id) {
        $error = 'Vui lòng điền đầy đủ thông tin bắt buộc';
    } else {
        // Check duplicate code
        $check_sql = "SELECT id FROM classes WHERE class_code = ? AND id != ?";
        $stmt = mysqli_prepare($connection, $check_sql);
        $check_id = $id ?? 0;
        mysqli_stmt_bind_param($stmt, "si", $class_code, $check_id);
        mysqli_stmt_execute($stmt);
        $existing = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($existing) > 0) {
            $error = 'Mã lớp đã tồn tại!';
        } else {
            try {
                if ($id) {
                    // Update
                    $sql = "UPDATE classes SET 
                            class_code = ?, 
                            class_name = ?,
                            department_id = ?,
                            academic_year = ?
                            WHERE id = ?";
                    $stmt = mysqli_prepare($connection, $sql);
                    mysqli_stmt_bind_param($stmt, "ssisi", $class_code, $class_name, $department_id, $academic_year, $id);
                    mysqli_stmt_execute($stmt);
                    
                    header('Location: list.php?success=' . urlencode('Cập nhật lớp học thành công!'));
                    exit();
                } else {
                    // Insert
                    $sql = "INSERT INTO classes (class_code, class_name, department_id, academic_year) 
                            VALUES (?, ?, ?, ?)";
                    $stmt = mysqli_prepare($connection, $sql);
                    mysqli_stmt_bind_param($stmt, "ssis", $class_code, $class_name, $department_id, $academic_year);
                    mysqli_stmt_execute($stmt);
                    
                    header('Location: list.php?success=' . urlencode('Thêm lớp học mới thành công!'));
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
    <title><?php echo $id ? 'Sửa' : 'Thêm'; ?> Lớp học - QLSV</title>
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
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2b87ff;
            padding: 15px;
            margin-bottom: 20px;
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
            <a href="../account/list.php">Quản lý tài khoản</a>
            <a href="../department/list.php">Quản lý khoa</a>
            <a href="list.php" class="active">Quản lý lớp học</a>
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
            <h1><?php echo $id ? ' Sửa' : ' Thêm'; ?> Lớp học</h1>
            <a href="list.php" class="btn btn-secondary">← Quay lại danh sách</a>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (!$id): ?>
        <div class="info-box">
            <strong> Lưu ý:</strong> Trước khi thêm lớp học, hãy đảm bảo đã tạo khoa tương ứng trong phần <a href="../department/list.php">Quản lý khoa</a>.
        </div>
        <?php endif; ?>

        <div class="form-container">
            <div class="form-header">
                <h2><?php echo $id ? 'Cập nhật thông tin lớp học' : 'Thêm lớp học mới'; ?></h2>
            </div>

            <form method="post" action="form.php<?php echo $id ? '?id=' . $id : ''; ?>">
                <div class="form-group">
                    <label>Mã lớp: <span style="color: red;">*</span></label>
                    <input type="text" name="class_code" class="form-control" 
                           placeholder="VD: CNTT01, KTDT01..."
                           value="<?php echo htmlspecialchars($class['class_code'] ?? ''); ?>"
                           required
                           <?php echo $id ? '' : 'autofocus'; ?>>
                    <small style="color: #6c757d;">Mã định danh duy nhất của lớp</small>
                </div>

                <div class="form-group">
                    <label>Tên lớp: <span style="color: red;">*</span></label>
                    <input type="text" name="class_name" class="form-control" 
                           placeholder="VD: Công nghệ thông tin 01"
                           value="<?php echo htmlspecialchars($class['class_name'] ?? ''); ?>"
                           required>
                </div>

                <div class="form-group">
                    <label>Khoa: <span style="color: red;">*</span></label>
                    <select name="department_id" class="form-control" required>
                        <option value="">-- Chọn khoa --</option>
                        <?php 
                        mysqli_data_seek($departments, 0);
                        while ($dept = mysqli_fetch_assoc($departments)): 
                        ?>
                            <option value="<?php echo $dept['id']; ?>"
                                    <?php echo (isset($class['department_id']) && $class['department_id'] == $dept['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept['department_code'] . ' - ' . $dept['department_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Khóa học:</label>
                    <input type="text" name="academic_year" class="form-control" 
                           placeholder="VD: 2024, K19, 2020-2024..."
                           value="<?php echo htmlspecialchars($class['academic_year'] ?? ''); ?>">
                    <small style="color: #6c757d;">Năm nhập học hoặc khóa học (không bắt buộc)</small>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e9ecef;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">
                        <?php echo $id ? ' Cập nhật' : ' Thêm lớp'; ?>
                    </button>
                    <a href="list.php" class="btn btn-secondary"> Hủy</a>
                </div>
            </form>
        </div>

        <?php if ($id && $class): ?>
        <div class="card" style="margin-top: 30px;">
            <div class="card-body">
                <h3> Thông tin bổ sung</h3>
                <p style="color: #6c757d;">
                    <?php
                    $stats_sql = "SELECT COUNT(*) as student_count FROM students WHERE class_id = ?";
                    $stmt = mysqli_prepare($connection, $stats_sql);
                    mysqli_stmt_bind_param($stmt, "i", $id);
                    mysqli_stmt_execute($stmt);
                    $student_count = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['student_count'];
                    ?>
                    Lớp này hiện có <strong><?php echo $student_count; ?></strong> sinh viên.
                </p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
