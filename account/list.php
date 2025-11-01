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
$role_filter = $_GET['role'] ?? '';

// Build query
$where_conditions = [];
$params = [];
$types = "";

if ($search) {
    $where_conditions[] = "(u.username LIKE ? OR u.email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

if ($role_filter) {
    $where_conditions[] = "u.role = ?";
    $params[] = $role_filter;
    $types .= "s";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get accounts
$sql = "SELECT 
    u.id,
    u.username,
    u.email,
    u.role,
    u.created_at,
    CASE 
        WHEN u.role = 'student' THEN s.full_name
        WHEN u.role = 'teacher' THEN t.full_name
        ELSE NULL
    END as full_name,
    CASE 
        WHEN u.role = 'student' THEN s.student_code
        WHEN u.role = 'teacher' THEN t.teacher_code
        ELSE NULL
    END as code
FROM users u
LEFT JOIN students s ON u.id = s.user_id
LEFT JOIN teachers t ON u.id = t.user_id
$where_clause
ORDER BY u.created_at DESC";

if (!empty($params)) {
    $stmt = mysqli_prepare($connection, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $accounts = mysqli_stmt_get_result($stmt);
} else {
    $accounts = mysqli_query($connection, $sql);
}

// Get statistics
$stats = [];
$role_stats_query = "SELECT 
    role,
    COUNT(*) as count
FROM users
GROUP BY role";
$result = mysqli_query($connection, $role_stats_query);
while ($row = mysqli_fetch_assoc($result)) {
    $stats[$row['role']] = $row['count'];
}

$total_accounts = array_sum($stats);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quản lý Tài khoản - QLSV</title>
    <link rel="stylesheet" href="../css/chung.css?v=3">
    <style>
        .stats-row {
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
        .stat-card.admin {
            border-left-color: #dc3545;
        }
        .stat-card.teacher {
            border-left-color: #7c3aed;
        }
        .stat-card.student {
            border-left-color: #10b981;
        }
        .stat-label {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 8px;
            text-transform: uppercase;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #333;
        }
        .account-card {
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
        .account-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .account-info {
            flex: 1;
            display: flex;
            gap: 20px;
            align-items: center;
        }
        .account-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            font-weight: bold;
        }
        .avatar-admin {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }
        .avatar-teacher {
            background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);
        }
        .avatar-student {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }
        .account-details {
            flex: 1;
        }
        .account-username {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        .account-meta {
            font-size: 0.9rem;
            color: #666;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        .role-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .role-admin {
            background: #fee;
            color: #c82333;
        }
        .role-teacher {
            background: #f3e8ff;
            color: #6d28d9;
        }
        .role-student {
            background: #d1fae5;
            color: #059669;
        }
        .account-actions {
            display: flex;
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
            <a href="list.php" class="active">Quản lý tài khoản</a>
            <a href="../department/list.php">Quản lý khoa</a>
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
            <h1> Quản lý Tài khoản</h1>
            <a href="form.php" class="btn btn-primary">+ Thêm tài khoản</a>
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
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-label">Tổng tài khoản</div>
                <div class="stat-value"><?php echo $total_accounts; ?></div>
            </div>
            <div class="stat-card admin">
                <div class="stat-label">Admin</div>
                <div class="stat-value" style="color: #dc3545;">
                    <?php echo $stats['admin'] ?? 0; ?>
                </div>
            </div>
            <div class="stat-card teacher">
                <div class="stat-label">Giảng viên</div>
                <div class="stat-value" style="color: #7c3aed;">
                    <?php echo $stats['teacher'] ?? 0; ?>
                </div>
            </div>
            <div class="stat-card student">
                <div class="stat-label">Sinh viên</div>
                <div class="stat-value" style="color: #10b981;">
                    <?php echo $stats['student'] ?? 0; ?>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-bar">
            <form method="get" action="list.php" style="display: flex; gap: 15px; align-items: flex-end;">
                <div class="form-group" style="flex: 2;">
                    <label>Tìm kiếm</label>
                    <input type="text" name="search" placeholder="Username, Email..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Vai trò</label>
                    <select name="role">
                        <option value="">Tất cả</option>
                        <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        <option value="teacher" <?php echo $role_filter === 'teacher' ? 'selected' : ''; ?>>Giảng viên</option>
                        <option value="student" <?php echo $role_filter === 'student' ? 'selected' : ''; ?>>Sinh viên</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Tìm kiếm</button>
                <a href="list.php" class="btn btn-secondary">Reset</a>
            </form>
        </div>

        <!-- Account List -->
        <div style="margin-top: 20px;">
            <?php if (mysqli_num_rows($accounts) > 0): ?>
                <?php while ($account = mysqli_fetch_assoc($accounts)): 
                    $avatar_class = 'avatar-' . $account['role'];
                    $role_class = 'role-' . $account['role'];
                    $role_name = [
                        'admin' => ' Admin',
                        'teacher' => ' Giảng viên',
                        'student' => ' Sinh viên'
                    ][$account['role']];
                    
                    $initial = $account['full_name'] 
                        ? strtoupper(substr($account['full_name'], 0, 2)) 
                        : strtoupper(substr($account['username'], 0, 2));
                ?>
                <div class="account-card">
                    <div class="account-info">
                        <div class="account-avatar <?php echo $avatar_class; ?>">
                            <?php echo $initial; ?>
                        </div>
                        <div class="account-details">
                            <div class="account-username">
                                <?php echo htmlspecialchars($account['username']); ?>
                                <?php if ($account['id'] == $_SESSION['user_id']): ?>
                                <span style="background: #fff3cd; color: #856404; padding: 2px 8px; border-radius: 10px; font-size: 0.75rem; margin-left: 8px;">Bạn</span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <span class="role-badge <?php echo $role_class; ?>">
                                    <?php echo $role_name; ?>
                                </span>
                            </div>
                            <div class="account-meta">
                                <?php if ($account['full_name']): ?>
                                <span> <?php echo htmlspecialchars($account['full_name']); ?></span>
                                <?php endif; ?>
                                <?php if ($account['code']): ?>
                                <span> <?php echo htmlspecialchars($account['code']); ?></span>
                                <?php endif; ?>
                                <?php if ($account['email']): ?>
                                <span> <?php echo htmlspecialchars($account['email']); ?></span>
                                <?php endif; ?>
                                <span> <?php echo date('d/m/Y', strtotime($account['created_at'])); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="account-actions">
                        <?php if ($account['id'] != $_SESSION['user_id']): ?>
                        <a href="delete.php?id=<?php echo $account['id']; ?>" 
                           class="btn btn-danger"
                           onclick="return confirm('Bạn có chắc muốn xóa tài khoản này?\n\nLưu ý: Nếu là tài khoản sinh viên/giảng viên, dữ liệu liên quan sẽ không bị xóa.')">
                             Xóa
                        </a>
                        <?php else: ?>
                        <button class="btn btn-secondary" disabled title="Không thể xóa chính mình">
                             Bảo vệ
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 60px 20px; background: white; border-radius: 8px;">
                    <div style="font-size: 4rem; margin-bottom: 20px;"></div>
                    <h3>Không tìm thấy tài khoản</h3>
                    <p style="color: #666;">Thử thay đổi bộ lọc hoặc thêm tài khoản mới.</p>
                    <a href="form.php" class="btn btn-primary" style="margin-top: 20px;">+ Thêm tài khoản</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
