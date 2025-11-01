<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Check if user has admin permission
if ($_SESSION['role'] !== 'admin') {
    header('Location: list.php');
    exit();
}

require_once '../connect.php';

// Get departments
$departments = [];
$dept_query = "SELECT * FROM departments ORDER BY department_name";
$dept_result = mysqli_query($connection, $dept_query);
while ($row = mysqli_fetch_assoc($dept_result)) {
    $departments[] = $row;
}

// Get classes
$classes = [];
$class_query = "SELECT c.*, d.department_name 
                FROM classes c 
                LEFT JOIN departments d ON c.department_id = d.id 
                ORDER BY c.class_name";
$class_result = mysqli_query($connection, $class_query);
while ($row = mysqli_fetch_assoc($class_result)) {
    $classes[] = $row;
}

$id = $_GET['id'] ?? null;
$error = '';
$success = '';

// Default values
$student = [
    'student_code' => '',
    'full_name' => '',
    'date_of_birth' => '',
    'gender' => '',
    'class_id' => '',
    'department_id' => '',
    'academic_year' => date('Y'),
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student = [
        'student_code' => mysqli_real_escape_string($connection, $_POST['student_code']),
        'full_name' => mysqli_real_escape_string($connection, $_POST['full_name']),
        'date_of_birth' => mysqli_real_escape_string($connection, $_POST['date_of_birth']),
        'gender' => mysqli_real_escape_string($connection, $_POST['gender']),
        'class_id' => intval($_POST['class_id']),
        'department_id' => intval($_POST['department_id']),
        'academic_year' => intval($_POST['academic_year'])
    ];

    // Create user account for student
    $username = $student['student_code'];
    $password = password_hash($student['student_code'], PASSWORD_DEFAULT); // Initially set password same as student code

    try {
        mysqli_begin_transaction($connection);

        if (!empty($_POST['id'])) {
            // Update existing student
            $sql = "UPDATE students SET 
                    student_code = ?, 
                    full_name = ?, 
                    date_of_birth = ?, 
                    gender = ?, 
                    class_id = ?, 
                    department_id = ?, 
                    academic_year = ?
                    WHERE id = ?";
            $stmt = mysqli_prepare($connection, $sql);
            mysqli_stmt_bind_param($stmt, "ssssiiii", 
                $student['student_code'],
                $student['full_name'],
                $student['date_of_birth'],
                $student['gender'],
                $student['class_id'],
                $student['department_id'],
                $student['academic_year'],
                $id
            );
            mysqli_stmt_execute($stmt);
        } else {
            // First create user account
            $sql = "INSERT INTO users (username, password, role) VALUES (?, ?, 'student')";
            $stmt = mysqli_prepare($connection, $sql);
            mysqli_stmt_bind_param($stmt, "ss", $username, $password);
            mysqli_stmt_execute($stmt);
            $user_id = mysqli_insert_id($connection);

            // Then create student record
            $sql = "INSERT INTO students (
                    student_code, 
                    full_name, 
                    date_of_birth, 
                    gender, 
                    class_id, 
                    department_id, 
                    academic_year,
                    user_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($connection, $sql);
            mysqli_stmt_bind_param($stmt, "ssssiiis", 
                $student['student_code'],
                $student['full_name'],
                $student['date_of_birth'],
                $student['gender'],
                $student['class_id'],
                $student['department_id'],
                $student['academic_year'],
                $user_id
            );
            mysqli_stmt_execute($stmt);
        }

        mysqli_commit($connection);
        $success = 'Sinh viên đã được ' . ($id ? 'cập nhật' : 'thêm') . ' thành công!';
        if (!$id) {
            header('Location: list.php?success=' . urlencode($success));
            exit();
        }
    } catch (Exception $e) {
        mysqli_rollback($connection);
        $error = 'Có lỗi xảy ra: ' . $e->getMessage();
    }
}

// Get student data for editing
if ($id && $connection) {
    $sql = "SELECT * FROM students WHERE id = ?";
    $stmt = mysqli_prepare($connection, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($result)) {
        $student = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $id ? 'Sửa sinh viên' : 'Thêm sinh viên mới'; ?> - QLSV</title>
    <link rel="stylesheet" href="../css/chung.css?v=3">
    <style>
        .form-container {
            max-width: 800px;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
        .form-actions {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
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
            <a href="list.php">Quản lý sinh viên</a>
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
            <h1><?php echo $id ? 'Sửa thông tin sinh viên' : 'Thêm sinh viên mới'; ?></h1>
        </div>

        <div class="form-container">
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

            <form method="post" action="form.php<?php echo $id ? '?id='.intval($id) : ''; ?>">
                <?php if ($id): ?>
                <input type="hidden" name="id" value="<?php echo intval($id); ?>">
                <?php endif; ?>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="student_code">Mã số sinh viên *</label>
                        <input type="text" id="student_code" name="student_code" 
                               value="<?php echo htmlspecialchars($student['student_code']); ?>"
                               required <?php echo $id ? 'readonly' : ''; ?>>
                        <?php if (!$id): ?>
                        <small class="text-muted">MSSV sẽ được sử dụng làm tên đăng nhập</small>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="full_name">Họ và tên *</label>
                        <input type="text" id="full_name" name="full_name" 
                               value="<?php echo htmlspecialchars($student['full_name']); ?>"
                               required>
                    </div>

                    <div class="form-group">
                        <label for="date_of_birth">Ngày sinh *</label>
                        <input type="date" id="date_of_birth" name="date_of_birth" 
                               value="<?php echo htmlspecialchars($student['date_of_birth']); ?>"
                               required>
                    </div>

                    <div class="form-group">
                        <label for="gender">Giới tính *</label>
                        <select id="gender" name="gender" required>
                            <option value="">Chọn giới tính</option>
                            <option value="M" <?php echo $student['gender'] === 'M' ? 'selected' : ''; ?>>Nam</option>
                            <option value="F" <?php echo $student['gender'] === 'F' ? 'selected' : ''; ?>>Nữ</option>
                            <option value="O" <?php echo $student['gender'] === 'O' ? 'selected' : ''; ?>>Khác</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="department_id">Khoa *</label>
                        <select id="department_id" name="department_id" required>
                            <option value="">Chọn khoa</option>
                            <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['id']; ?>"
                                    <?php echo $student['department_id'] == $dept['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept['department_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="class_id">Lớp *</label>
                        <select id="class_id" name="class_id" required>
                            <option value="">Chọn lớp</option>
                            <?php foreach ($classes as $class): ?>
                            <option value="<?php echo $class['id']; ?>"
                                    <?php echo $student['class_id'] == $class['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class['class_name']); ?>
                                (<?php echo htmlspecialchars($class['department_name']); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="academic_year">Khóa học *</label>
                        <input type="number" id="academic_year" name="academic_year" 
                               value="<?php echo htmlspecialchars($student['academic_year']); ?>"
                               min="2000" max="2099" required>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="list.php" class="btn btn-secondary">Hủy</a>
                    <button type="submit" class="btn btn-primary">
                        <?php echo $id ? 'Cập nhật' : 'Thêm sinh viên'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Add client-side validation and dynamic class filtering based on department
        document.getElementById('department_id').addEventListener('change', function() {
            const departmentId = this.value;
            const classSelect = document.getElementById('class_id');
            const classes = <?php echo json_encode($classes); ?>;

            // Clear current options
            classSelect.innerHTML = '<option value="">Chọn lớp</option>';

            // Add filtered options
            classes.forEach(function(classItem) {
                if (classItem.department_id == departmentId) {
                    const option = document.createElement('option');
                    option.value = classItem.id;
                    option.textContent = classItem.class_name;
                    classSelect.appendChild(option);
                }
            });
        });
    </script>
</body>
</html>
