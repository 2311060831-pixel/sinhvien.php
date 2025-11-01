<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

require_once '../connect.php';

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

// Get filter parameters
$search = $_GET['search'] ?? '';
$department_filter = $_GET['department_id'] ?? '';

// Build query
$where_conditions = [];
$params = [];
$types = "";

if ($search) {
    $where_conditions[] = "(t.teacher_code LIKE ? OR t.full_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

if ($department_filter) {
    $where_conditions[] = "t.department_id = ?";
    $params[] = $department_filter;
    $types .= "i";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get teachers
$sql = "SELECT 
    t.id,
    t.teacher_code,
    t.full_name,
    t.user_id,
    u.username,
    u.email,
    d.department_name,
    d.id as department_id,
    COUNT(DISTINCT subj.id) as subject_count
FROM teachers t
LEFT JOIN users u ON t.user_id = u.id
LEFT JOIN departments d ON t.department_id = d.id
LEFT JOIN subjects subj ON subj.teacher_id = t.id
$where_clause
GROUP BY t.id
ORDER BY t.teacher_code";

if (!empty($params)) {
    $stmt = mysqli_prepare($connection, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $teachers = mysqli_stmt_get_result($stmt);
} else {
    $teachers = mysqli_query($connection, $sql);
}

// Get departments for filter
$departments = mysqli_query($connection, "SELECT * FROM departments ORDER BY department_name");

// Get statistics
$total_teachers = mysqli_query($connection, "SELECT COUNT(*) as count FROM teachers");
$total_count = mysqli_fetch_assoc($total_teachers)['count'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quản lý Giảng viên - QLSV</title>
    <link rel="stylesheet" href="../css/chung.css?v=3">
    <style>
        .stats-bar {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            display: flex;
            gap: 30px;
            align-items: center;
        }
        .stat-item {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .stat-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        .stat-info {
            display: flex;
            flex-direction: column;
        }
        .stat-label {
            font-size: 0.85rem;
            color: #666;
        }
        .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #333;
        }
        .teacher-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s;
        }
        .teacher-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .teacher-info {
            flex: 1;
            display: flex;
            gap: 20px;
            align-items: center;
        }
        .teacher-avatar {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            font-weight: bold;
        }
        .teacher-details {
            flex: 1;
        }
        .teacher-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        .teacher-code {
            display: inline-block;
            background: #e3f2fd;
            color: #1976d2;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-right: 10px;
        }
        .teacher-meta {
            font-size: 0.9rem;
            color: #666;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 8px;
        }
        .teacher-actions {
            display: flex;
            gap: 10px;
        }
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .badge-primary {
            background: #e8f5e9;
            color: #2e7d32;
        }
        .badge-secondary {
            background: #fff3e0;
            color: #e65100;
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
            <a href="list.php" class="active">Quản lý giảng viên</a>
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
            <h1></h1>Quản lý Giảng viên</h1>
            <a href="form.php" class="btn btn-primary">+ Thêm giảng viên</a>
        </div>

        <?php if ($success): ?>
        <div class="alert alert-success">
            ✓ <?php echo htmlspecialchars($success); ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert alert-danger">
            ✗ <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-bar">
            <div class="stat-item">
                <div class="stat-icon"></div>
                <div class="stat-info">
                    <div class="stat-label">Tổng số giảng viên</div>
                    <div class="stat-value"><?php echo $total_count; ?></div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-bar">
            <form method="get" action="list.php" style="display: flex; gap: 15px; align-items: flex-end;">
                <div class="form-group" style="flex: 2;">
                    <label>Tìm kiếm</label>
                    <input type="text" name="search" placeholder="Mã GV, Họ tên..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Khoa</label>
                    <select name="department_id">
                        <option value="">Tất cả</option>
                        <?php while ($dept = mysqli_fetch_assoc($departments)): ?>
                        <option value="<?php echo $dept['id']; ?>" 
                                <?php echo $department_filter == $dept['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dept['department_name']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Tìm kiếm</button>
                <a href="list.php" class="btn btn-secondary">Reset</a>
            </form>
        </div>

        <!-- Teacher List -->
        <div style="margin-top: 20px;">
            <?php if (mysqli_num_rows($teachers) > 0): ?>
                <?php while ($teacher = mysqli_fetch_assoc($teachers)): ?>
                <div class="teacher-card">
                    <div class="teacher-info">
                        <div class="teacher-avatar">
                            <?php echo strtoupper(substr($teacher['full_name'], 0, 2)); ?>
                        </div>
                        <div class="teacher-details">
                            <div class="teacher-name">
                                <?php echo htmlspecialchars($teacher['full_name']); ?>
                            </div>
                            <div>
                                <span class="teacher-code">
                                    <?php echo htmlspecialchars($teacher['teacher_code']); ?>
                                </span>
                                <?php if ($teacher['subject_count'] > 0): ?>
                                <span class="badge badge-primary">
                                     <?php echo $teacher['subject_count']; ?> môn giảng dạy
                                </span>
                                <?php endif; ?>
                            </div>
                            <div class="teacher-meta">
                                <?php if ($teacher['department_name']): ?>
                                <span> <?php echo htmlspecialchars($teacher['department_name']); ?></span>
                                <?php endif; ?>
                                <?php if ($teacher['email']): ?>
                                <span> <?php echo htmlspecialchars($teacher['email']); ?></span>
                                <?php endif; ?>
                                <span> <?php echo htmlspecialchars($teacher['username']); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="teacher-actions">
                        <a href="form.php?id=<?php echo $teacher['id']; ?>" class="btn btn-secondary">✏️ Sửa</a>
                        <a href="delete.php?id=<?php echo $teacher['id']; ?>" 
                           class="btn btn-danger"
                           onclick="return confirm('Bạn có chắc muốn xóa giảng viên này?')">
                             Xóa
                        </a>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 60px 20px; background: white; border-radius: 8px;">
                    <div style="font-size: 4rem; margin-bottom: 20px;"></div>
                    <h3>Không tìm thấy giảng viên</h3>
                    <p style="color: #666;">Thử thay đổi bộ lọc hoặc thêm giảng viên mới.</p>
                    <a href="form.php" class="btn btn-primary" style="margin-top: 20px;">+ Thêm giảng viên</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
