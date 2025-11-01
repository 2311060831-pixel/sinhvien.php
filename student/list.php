<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Check if user has permission to access this page
if (!in_array($_SESSION['role'], ['admin', 'teacher'])) {
    header('Location: ../dashboard.php');
    exit();
}

require_once '../connect.php';

// Initialize filters
$search = isset($_GET['search']) ? mysqli_real_escape_string($connection, $_GET['search']) : '';
$department = isset($_GET['department']) ? mysqli_real_escape_string($connection, $_GET['department']) : '';
$class = isset($_GET['class']) ? mysqli_real_escape_string($connection, $_GET['class']) : '';

// Base query
$query = "
    SELECT 
        s.id,
        s.student_code,
        s.full_name,
        s.date_of_birth,
        s.gender,
        c.class_name,
        d.department_name,
        s.academic_year
    FROM students s
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN departments d ON s.department_id = d.id
    WHERE 1=1";

// Add filters
if ($search) {
    $query .= " AND (s.student_code LIKE '%$search%' OR s.full_name LIKE '%$search%')";
}
if ($department) {
    $query .= " AND d.department_code = '$department'";
}
if ($class) {
    $query .= " AND c.class_code = '$class'";
}

$query .= " ORDER BY s.id DESC";

// Get students
$result = mysqli_query($connection, $query);
$students = [];
while ($row = mysqli_fetch_assoc($result)) {
    $students[] = $row;
}

// Get departments for filter
$departments = [];
$dept_result = mysqli_query($connection, "SELECT * FROM departments ORDER BY department_name");
while ($row = mysqli_fetch_assoc($dept_result)) {
    $departments[] = $row;
}

// Get classes for filter
$classes = [];
$class_result = mysqli_query($connection, "SELECT * FROM classes ORDER BY class_name");
while ($row = mysqli_fetch_assoc($class_result)) {
    $classes[] = $row;
}
$q = htmlspecialchars($_GET['q'] ?? '');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quản lý sinh viên</title>
    <link rel="stylesheet" href="../css/chung.css?v=3">
    <style>
        .filters {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .filter-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }
        .filter-group {
            flex: 1;
        }
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            color: #666;
        }
        .filter-group select,
        .filter-group input[type="text"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .btn-group {
            display: flex;
            gap: 10px;
        }
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        .btn-primary {
            background: #2b87ff;
            color: white;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .table-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .gender-male {
            color: #2b87ff;
        }
        .gender-female {
            color: #ff4081;
        }
        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 20px;
        }
        .pagination a {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
        }
        .pagination a.active {
            background: #2b87ff;
            color: white;
            border-color: #2b87ff;
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

        <?php if ($_SESSION['role'] === 'admin'): ?>
        <div class="nav-section">
            <div class="nav-header">Quản lý</div>
            <a href="../account/list.php">Quản lý tài khoản</a>
            <a href="../department/list.php">Quản lý khoa</a>
            <a href="../classes/list.php">Quản lý lớp học</a>
            <a href="list.php" class="active">Quản lý sinh viên</a>
            <a href="../teacher/list.php">Quản lý giảng viên</a>
            <a href="../subject/list.php">Quản lý môn học</a>
        </div>
        <?php endif; ?>

        <?php if ($_SESSION['role'] === 'teacher'): ?>
        <div class="nav-section">
            <div class="nav-header">Quản lý</div>
            <a href="list.php" class="active">Quản lý sinh viên</a>
            <a href="../subject/list.php">Quản lý môn học</a>
        </div>
        <?php endif; ?>

        <div class="nav-section">
            <div class="nav-header">Học tập</div>
            <a href="../schedule/index.php">Thời khóa biểu</a>
            <a href="../schedule/lichthi.php">Lịch thi</a>
        </div>

        <?php if ($_SESSION['role'] === 'admin'): ?>
        <div class="nav-section">
            <div class="nav-header">Báo cáo</div>
            <a href="../reports/students.php">Thống kê sinh viên</a>
            <a href="../reports/grades.php">Thống kê điểm số</a>
            <a href="../reports/tuition.php">Thống kê học phí</a>
        </div>
        <?php endif; ?>

        <div class="nav-section">
            <a href="../logout.php">Đăng xuất</a>
        </div>
    </div>

    <div class="main">
        <div class="actions">
            <h1>Quản lý sinh viên</h1>
            <?php if ($_SESSION['role'] === 'admin'): ?>
            <a href="form.php" class="btn btn-primary">+ Thêm sinh viên mới</a>
            <?php endif; ?>
        </div>

        <div class="filters">
            <form method="get" action="">
                <div class="filter-row">
                    <div class="filter-group">
                        <label>Tìm kiếm</label>
                        <input type="text" name="search" placeholder="Nhập MSSV hoặc họ tên..." 
                               value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                    </div>
                    <div class="filter-group">
                        <label>Khoa</label>
                        <select name="department">
                            <option value="">Tất cả</option>
                            <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo htmlspecialchars($dept['department_code']); ?>"
                                    <?php if (($_GET['department'] ?? '') === $dept['department_code']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($dept['department_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Lớp</label>
                        <select name="class">
                            <option value="">Tất cả</option>
                            <?php foreach ($classes as $class): ?>
                            <option value="<?php echo htmlspecialchars($class['class_code']); ?>"
                                    <?php if (($_GET['class'] ?? '') === $class['class_code']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($class['class_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">Lọc</button>
                    <a href="list.php" class="btn btn-secondary">Đặt lại</a>
                </div>
            </form>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>MSSV</th>
                        <th>Họ và tên</th>
                        <th>Ngày sinh</th>
                        <th>Giới tính</th>
                        <th>Lớp</th>
                        <th>Khoa</th>
                        <th>Khóa</th>
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                        <th>Thao tác</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                    <tr>
                        <td colspan="<?php echo $_SESSION['role'] === 'admin' ? '8' : '7'; ?>" style="text-align: center;">
                            Không tìm thấy sinh viên nào
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($students as $student): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($student['student_code']); ?></td>
                        <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($student['date_of_birth'])); ?></td>
                        <td>
                            <span class="gender-<?php echo strtolower($student['gender']); ?>">
                                <?php 
                                echo $student['gender'] === 'M' ? 'Nam' : 
                                    ($student['gender'] === 'F' ? 'Nữ' : 'Khác'); 
                                ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($student['class_name']); ?></td>
                        <td><?php echo htmlspecialchars($student['department_name']); ?></td>
                        <td><?php echo htmlspecialchars($student['academic_year']); ?></td>
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                        <td>
                            <a href="form.php?id=<?php echo $student['id']; ?>" 
                               class="btn-link">Sửa</a> | 
                            <a href="delete.php?id=<?php echo $student['id']; ?>" 
                               class="btn-link text-danger"
                               onclick="return confirm('Bạn có chắc chắn muốn xóa sinh viên này?')">Xóa</a>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
