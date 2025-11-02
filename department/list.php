<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

require_once '../connect.php';

$search = $_GET['search'] ?? '';

// Get departments with statistics
$sql = "SELECT 
    d.*,
    COUNT(DISTINCT s.id) as student_count,
    COUNT(DISTINCT t.id) as teacher_count,
    COUNT(DISTINCT c.id) as class_count
FROM departments d
LEFT JOIN students s ON d.id = s.department_id
LEFT JOIN teachers t ON d.id = t.department_id
LEFT JOIN classes c ON c.department_id = d.id
WHERE d.department_name LIKE ? OR d.department_code LIKE ?
GROUP BY d.id
ORDER BY d.department_code";

$stmt = mysqli_prepare($connection, $sql);
if (!$stmt) {
    die("SQL Error: " . mysqli_error($connection));
}
$search_param = "%$search%";
mysqli_stmt_bind_param($stmt, "ss", $search_param, $search_param);
mysqli_stmt_execute($stmt);
$departments = mysqli_stmt_get_result($stmt);

// Get total statistics
$stats_sql = "SELECT 
    COUNT(*) as total_departments,
    COUNT(DISTINCT s.id) as total_students,
    COUNT(DISTINCT t.id) as total_teachers
FROM departments d
LEFT JOIN students s ON d.id = s.department_id
LEFT JOIN teachers t ON d.id = t.department_id";
$stats_result = mysqli_query($connection, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quản lý Khoa - QLSV</title>
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
        .stat-card.teachers {
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
        
        .department-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        .department-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .department-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .department-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
        }
        .department-code {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        .department-name {
            font-size: 16px;
            opacity: 0.95;
        }
        .department-body {
            padding: 20px;
        }
        .department-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 15px;
        }
        .stat-item {
            text-align: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 6px;
        }
        .stat-item-value {
            font-size: 20px;
            font-weight: 700;
            color: #2b87ff;
        }
        .stat-item-label {
            font-size: 12px;
            color: #6c757d;
            margin-top: 5px;
        }
        .department-actions {
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
            <h1> Quản lý Khoa</h1>
            <a href="form.php" class="btn btn-primary">+ Thêm khoa mới</a>
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
                <div class="stat-label">Tổng số khoa</div>
                <div class="stat-value"><?php echo $stats['total_departments']; ?></div>
            </div>
            <div class="stat-card teachers">
                <div class="stat-label">Tổng số giảng viên</div>
                <div class="stat-value"><?php echo $stats['total_teachers']; ?></div>
            </div>
            <div class="stat-card students">
                <div class="stat-label">Tổng số sinh viên</div>
                <div class="stat-value"><?php echo $stats['total_students']; ?></div>
            </div>
        </div>

        <div class="filters">
            <form method="get" action="list.php">
                <div class="filter-row">
                    <div class="filter-group">
                        <label> Tìm kiếm:</label>
                        <input type="text" name="search" class="form-control" 
                               placeholder="Tìm theo mã khoa, tên khoa..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <button type="submit" class="btn btn-primary">Tìm kiếm</button>
                    <?php if ($search): ?>
                        <a href="list.php" class="btn btn-secondary">Xóa lọc</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <?php if (mysqli_num_rows($departments) > 0): ?>
        <div class="department-grid">
            <?php while ($dept = mysqli_fetch_assoc($departments)): ?>
            <div class="department-card">
                <div class="department-header">
                    <div class="department-code"><?php echo htmlspecialchars($dept['department_code']); ?></div>
                    <div class="department-name"><?php echo htmlspecialchars($dept['department_name']); ?></div>
                </div>
                <div class="department-body">
                    <div class="department-stats">
                        <div class="stat-item">
                            <div class="stat-item-value"><?php echo $dept['class_count']; ?></div>
                            <div class="stat-item-label">Lớp học</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-item-value"><?php echo $dept['teacher_count']; ?></div>
                            <div class="stat-item-label">Giảng viên</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-item-value"><?php echo $dept['student_count']; ?></div>
                            <div class="stat-item-label">Sinh viên</div>
                        </div>
                    </div>
                    
                    <div class="department-actions">
                        <a href="form.php?id=<?php echo $dept['id']; ?>" 
                           class="btn btn-primary" style="flex: 1;">
                             Sửa
                        </a>
                        <a href="delete.php?id=<?php echo $dept['id']; ?>" 
                           class="btn btn-danger"
                           onclick="return confirm('Xóa khoa <?php echo htmlspecialchars($dept['department_name']); ?>?\n\nLưu ý: Không thể xóa nếu còn sinh viên, giảng viên hoặc lớp học!')">
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
            <h3>Chưa có khoa nào</h3>
            <p>
                <?php if ($search): ?>
                    Không tìm thấy khoa với từ khóa "<?php echo htmlspecialchars($search); ?>"
                <?php else: ?>
                    Hãy thêm khoa đầu tiên để bắt đầu quản lý
                <?php endif; ?>
            </p>
            <a href="form.php" class="btn btn-primary">+ Thêm khoa mới</a>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
