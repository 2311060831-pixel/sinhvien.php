<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Check if user has permission
if (!in_array($_SESSION['role'], ['admin', 'teacher'])) {
    header('Location: ../dashboard.php');
    exit();
}

require_once '../connect.php';

$role = $_SESSION['role'];

// Get search parameters
$search = isset($_GET['search']) ? mysqli_real_escape_string($connection, $_GET['search']) : '';
$department = isset($_GET['department']) ? intval($_GET['department']) : 0;

// Build query
$query = "
    SELECT 
        s.id,
        s.subject_code,
        s.subject_name,
        s.credits,
        t.full_name as teacher_name,
        d.department_name
    FROM subjects s
    LEFT JOIN teachers t ON s.teacher_id = t.id
    LEFT JOIN departments d ON t.department_id = d.id
    WHERE 1=1";

if ($search) {
    $query .= " AND (s.subject_code LIKE '%$search%' OR s.subject_name LIKE '%$search%')";
}

if ($department) {
    $query .= " AND t.department_id = $department";
}

$query .= " ORDER BY s.subject_code";

$subjects = mysqli_query($connection, $query);

// Get departments for filter
$departments = mysqli_query($connection, "SELECT * FROM departments ORDER BY department_name");
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quản lý Môn học - QLSV</title>
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
            align-items: end;
        }
        .filter-group {
            flex: 1;
        }
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            color: #666;
        }
        .actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .subject-code {
            display: inline-block;
            padding: 4px 8px;
            background: #e7f3ff;
            color: #2b87ff;
            border-radius: 4px;
            font-family: monospace;
            font-size: 13px;
        }
        .credits-badge {
            display: inline-block;
            padding: 4px 8px;
            background: #fff3cd;
            color: #856404;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
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

        <?php if ($role === 'admin'): ?>
        <div class="nav-section">
            <div class="nav-header">Quản lý</div>
            <a href="../account/list.php">Quản lý tài khoản</a>
            <a href="../department/list.php">Quản lý khoa</a>
            <a href="../classes/list.php">Quản lý lớp học</a>
            <a href="../student/list.php">Quản lý sinh viên</a>
            <a href="../teacher/list.php">Quản lý giảng viên</a>
            <a href="list.php" class="active">Quản lý môn học</a>
        </div>
        <?php endif; ?>

        <?php if ($role === 'teacher'): ?>
        <div class="nav-section">
            <div class="nav-header">Quản lý</div>
            <a href="../student/list.php">Quản lý sinh viên</a>
            <a href="list.php" class="active">Quản lý môn học</a>
        </div>
        <?php endif; ?>

        <div class="nav-section">
            <div class="nav-header">Học tập</div>
            <?php if ($role === 'teacher'): ?>
            <a href="../grades/input.php">Nhập điểm</a>
            <?php endif; ?>
            <a href="../schedule/index.php">Thời khóa biểu</a>
            <a href="../schedule/lichthi.php">Lịch thi</a>
        </div>

        <?php if ($role === 'admin'): ?>
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
            <h1>Quản lý Môn học</h1>
            <?php if ($role === 'admin'): ?>
            <a href="form.php" class="btn btn-primary">+ Thêm môn học mới</a>
            <?php endif; ?>
        </div>

        <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($_GET['success']); ?>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($_GET['error']); ?>
        </div>
        <?php endif; ?>

        <div class="filters">
            <form method="get" action="">
                <div class="filter-row">
                    <div class="filter-group">
                        <label>Tìm kiếm</label>
                        <input type="text" name="search" placeholder="Mã môn hoặc tên môn..." 
                               value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                    </div>
                    <div class="filter-group">
                        <label>Khoa</label>
                        <select name="department">
                            <option value="">Tất cả</option>
                            <?php while ($dept = mysqli_fetch_assoc($departments)): ?>
                            <option value="<?php echo $dept['id']; ?>"
                                    <?php if (($_GET['department'] ?? 0) == $dept['id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($dept['department_name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Lọc</button>
                    <a href="list.php" class="btn btn-secondary">Đặt lại</a>
                </div>
            </form>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Mã môn học</th>
                        <th>Tên môn học</th>
                        <th>Số tín chỉ</th>
                        <th>Giảng viên phụ trách</th>
                        <th>Khoa</th>
                        <?php if ($role === 'admin'): ?>
                        <th>Thao tác</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($subjects) == 0): ?>
                    <tr>
                        <td colspan="<?php echo $role === 'admin' ? '6' : '5'; ?>" style="text-align: center;">
                            Không tìm thấy môn học nào
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php while ($subject = mysqli_fetch_assoc($subjects)): ?>
                    <tr>
                        <td>
                            <span class="subject-code">
                                <?php echo htmlspecialchars($subject['subject_code']); ?>
                            </span>
                        </td>
                        <td><strong><?php echo htmlspecialchars($subject['subject_name']); ?></strong></td>
                        <td>
                            <span class="credits-badge">
                                <?php echo $subject['credits']; ?> TC
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($subject['teacher_name'] ?? 'Chưa phân công'); ?></td>
                        <td><?php echo htmlspecialchars($subject['department_name'] ?? 'N/A'); ?></td>
                        <?php if ($role === 'admin'): ?>
                        <td>
                            <a href="form.php?id=<?php echo $subject['id']; ?>" class="btn-link">Sửa</a> |
                            <a href="delete.php?id=<?php echo $subject['id']; ?>" 
                               class="btn-link text-danger"
                               onclick="return confirm('Bạn có chắc chắn muốn xóa môn học này?')">Xóa</a>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
