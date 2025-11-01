<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

require_once '../connect.php';

// Get filters
$search = $_GET['search'] ?? '';
$department_filter = $_GET['department'] ?? '';

// Build query
$sql = "SELECT 
    c.*,
    d.department_name,
    d.department_code,
    COUNT(DISTINCT s.id) as student_count
FROM classes c
LEFT JOIN departments d ON c.department_id = d.id
LEFT JOIN students s ON c.id = s.class_id
WHERE 1=1";

$params = [];
$types = "";

if ($search) {
    $sql .= " AND (c.class_code LIKE ? OR c.class_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = &$search_param;
    $params[] = &$search_param;
    $types .= "ss";
}

if ($department_filter) {
    $sql .= " AND c.department_id = ?";
    $params[] = &$department_filter;
    $types .= "i";
}

$sql .= " GROUP BY c.id ORDER BY d.department_code, c.class_code";

$stmt = mysqli_prepare($connection, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$classes = mysqli_stmt_get_result($stmt);

// Get departments for filter
$dept_sql = "SELECT id, department_code, department_name FROM departments ORDER BY department_code";
$departments = mysqli_query($connection, $dept_sql);

// Get statistics
$stats_sql = "SELECT 
    COUNT(DISTINCT c.id) as total_classes,
    COUNT(DISTINCT s.id) as total_students,
    COUNT(DISTINCT c.department_id) as total_departments
FROM classes c
LEFT JOIN students s ON c.id = s.class_id";
$stats_result = mysqli_query($connection, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quản lý Lớp học - QLSV</title>
    <link rel="stylesheet" href="../css/chung.css?v=3">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            border-left: 4px solid #2b87ff;
        }
        .stat-card.departments {
            border-left-color: #28a745;
        }
        .stat-card.students {
            border-left-color: #17a2b8;
        }
        .stat-label {
            font-size: 14px;
            color: #6c757d;
            margin-bottom: 8px;
        }
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #2c3e50;
        }
        
        .class-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
        }
        .class-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .class-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .class-header {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 20px;
        }
        .class-code {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        .class-name {
            font-size: 15px;
            opacity: 0.95;
        }
        .class-body {
            padding: 20px;
        }
        .class-info {
            margin-bottom: 15px;
        }
        .info-row {
            display: flex;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            font-size: 13px;
            color: #6c757d;
            width: 100px;
        }
        .info-value {
            font-weight: 600;
            color: #2c3e50;
        }
        .student-count {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 12px;
            background: #e7f3ff;
            color: #2b87ff;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
        }
        .department-badge {
            display: inline-block;
            padding: 4px 10px;
            background: #28a745;
            color: white;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        .class-actions {
            display: flex;
            gap: 10px;
            padding-top: 15px;
            border-top: 1px solid #e9ecef;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        .empty-icon {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
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
            <h1> Quản lý Lớp học</h1>
            <a href="form.php" class="btn btn-primary">+ Thêm lớp mới</a>
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

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Tổng số lớp</div>
                <div class="stat-value"><?php echo $stats['total_classes']; ?></div>
            </div>
            <div class="stat-card departments">
                <div class="stat-label">Số khoa</div>
                <div class="stat-value"><?php echo $stats['total_departments']; ?></div>
            </div>
            <div class="stat-card students">
                <div class="stat-label">Tổng sinh viên</div>
                <div class="stat-value"><?php echo $stats['total_students']; ?></div>
            </div>
        </div>

        <div class="filters">
            <form method="get" action="list.php">
                <div class="filter-row">
                    <div class="filter-group">
                        <label> Tìm kiếm:</label>
                        <input type="text" name="search" class="form-control" 
                               placeholder="Tìm theo mã lớp, tên lớp..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-group">
                        <label> Khoa:</label>
                        <select name="department" class="form-control">
                            <option value="">-- Tất cả khoa --</option>
                            <?php 
                            mysqli_data_seek($departments, 0);
                            while ($dept = mysqli_fetch_assoc($departments)): 
                            ?>
                                <option value="<?php echo $dept['id']; ?>" 
                                        <?php echo $department_filter == $dept['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['department_code'] . ' - ' . $dept['department_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Lọc</button>
                    <?php if ($search || $department_filter): ?>
                        <a href="list.php" class="btn btn-secondary">Xóa lọc</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <?php if (mysqli_num_rows($classes) > 0): ?>
        <div class="class-grid">
            <?php while ($class = mysqli_fetch_assoc($classes)): ?>
            <div class="class-card">
                <div class="class-header">
                    <div class="class-code"><?php echo htmlspecialchars($class['class_code']); ?></div>
                    <div class="class-name"><?php echo htmlspecialchars($class['class_name']); ?></div>
                </div>
                <div class="class-body">
                    <div class="class-info">
                        <div class="info-row">
                            <div class="info-label"> Khoa:</div>
                            <div class="info-value">
                                <span class="department-badge">
                                    <?php echo htmlspecialchars($class['department_code']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label"> Khóa:</div>
                            <div class="info-value"><?php echo htmlspecialchars($class['academic_year'] ?? 'Chưa có'); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label"> Sĩ số:</div>
                            <div class="info-value">
                                <span class="student-count">
                                     <?php echo $class['student_count']; ?> sinh viên
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="class-actions">
                        <a href="form.php?id=<?php echo $class['id']; ?>" 
                           class="btn btn-primary" style="flex: 1;">
                             Sửa
                        </a>
                        <a href="delete.php?id=<?php echo $class['id']; ?>" 
                           class="btn btn-danger"
                           onclick="return confirm('Xóa lớp <?php echo htmlspecialchars($class['class_name']); ?>?\n\nLưu ý: Không thể xóa nếu còn sinh viên!')">
                             Xóa
                        </a>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon"></div>
            <h3>Chưa có lớp học nào</h3>
            <p>
                <?php if ($search || $department_filter): ?>
                    Không tìm thấy lớp học phù hợp với bộ lọc
                <?php else: ?>
                    Hãy thêm lớp học đầu tiên
                <?php endif; ?>
            </p>
            <a href="form.php" class="btn btn-primary">+ Thêm lớp mới</a>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
