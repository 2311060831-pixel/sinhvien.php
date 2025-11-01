<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

require_once '../connect.php';

// Get filter parameters
$selected_department = $_GET['department_id'] ?? '';
$selected_class = $_GET['class_id'] ?? '';
$selected_year = $_GET['academic_year'] ?? '';

// Get statistics
$stats = [];

// Total students
$sql = "SELECT COUNT(*) as total FROM students";
$result = mysqli_query($connection, $sql);
$stats['total_students'] = mysqli_fetch_assoc($result)['total'];

// Students by gender
$sql = "SELECT 
    SUM(CASE WHEN gender = 'M' THEN 1 ELSE 0 END) as male,
    SUM(CASE WHEN gender = 'F' THEN 1 ELSE 0 END) as female,
    SUM(CASE WHEN gender = 'O' THEN 1 ELSE 0 END) as other
FROM students";
$result = mysqli_query($connection, $sql);
$gender_stats = mysqli_fetch_assoc($result);
$stats['male'] = $gender_stats['male'];
$stats['female'] = $gender_stats['female'];
$stats['other'] = $gender_stats['other'];

// Students by department
$sql = "SELECT d.department_name, COUNT(s.id) as count
FROM departments d
LEFT JOIN students s ON d.id = s.department_id
GROUP BY d.id
ORDER BY count DESC";
$dept_result = mysqli_query($connection, $sql);
$dept_stats = [];
while ($row = mysqli_fetch_assoc($dept_result)) {
    $dept_stats[] = $row;
}

// Students by academic year
$sql = "SELECT academic_year, COUNT(*) as count
FROM students
GROUP BY academic_year
ORDER BY academic_year DESC";
$year_result = mysqli_query($connection, $sql);
$year_stats = [];
while ($row = mysqli_fetch_assoc($year_result)) {
    $year_stats[] = $row;
}

// Students by class
$sql = "SELECT c.class_name, d.department_name, COUNT(s.id) as count
FROM classes c
LEFT JOIN departments d ON c.department_id = d.id
LEFT JOIN students s ON c.id = s.class_id
GROUP BY c.id
ORDER BY count DESC
LIMIT 10";
$class_result = mysqli_query($connection, $sql);
$class_stats = [];
while ($row = mysqli_fetch_assoc($class_result)) {
    $class_stats[] = $row;
}

// Get registration statistics
$sql = "SELECT 
    COUNT(DISTINCT cr.student_id) as students_registered,
    COUNT(cr.id) as total_registrations,
    AVG(subj.credits) as avg_credits_per_subject
FROM course_registrations cr
JOIN subjects subj ON cr.subject_id = subj.id
WHERE cr.semester = 1 AND cr.academic_year = 2024";
$result = mysqli_query($connection, $sql);
$reg_stats = mysqli_fetch_assoc($result);

// Average credits per student
$sql = "SELECT AVG(total_credits) as avg_credits
FROM (
    SELECT s.id, SUM(subj.credits) as total_credits
    FROM students s
    LEFT JOIN course_registrations cr ON s.id = cr.student_id AND cr.semester = 1 AND cr.academic_year = 2024
    LEFT JOIN subjects subj ON cr.subject_id = subj.id
    GROUP BY s.id
) as student_credits";
$result = mysqli_query($connection, $sql);
$avg_credits = mysqli_fetch_assoc($result)['avg_credits'] ?? 0;

// Get departments for filter
$departments = mysqli_query($connection, "SELECT * FROM departments ORDER BY department_name");

// Get classes for filter
$classes = mysqli_query($connection, "SELECT c.*, d.department_name 
                                      FROM classes c 
                                      LEFT JOIN departments d ON c.department_id = d.id 
                                      ORDER BY c.class_name");

// Get academic years
$years_query = mysqli_query($connection, "SELECT DISTINCT academic_year FROM students ORDER BY academic_year DESC");

// Build filtered query
$where_conditions = [];
$params = [];
$types = "";

if ($selected_department) {
    $where_conditions[] = "s.department_id = ?";
    $params[] = $selected_department;
    $types .= "i";
}
if ($selected_class) {
    $where_conditions[] = "s.class_id = ?";
    $params[] = $selected_class;
    $types .= "i";
}
if ($selected_year) {
    $where_conditions[] = "s.academic_year = ?";
    $params[] = $selected_year;
    $types .= "i";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get detailed student list
$detail_sql = "SELECT 
    s.student_code,
    s.full_name,
    s.gender,
    s.date_of_birth,
    c.class_name,
    d.department_name,
    s.academic_year,
    COUNT(DISTINCT cr.id) as registered_courses,
    COALESCE(SUM(subj.credits), 0) as total_credits
FROM students s
LEFT JOIN classes c ON s.class_id = c.id
LEFT JOIN departments d ON s.department_id = d.id
LEFT JOIN course_registrations cr ON s.id = cr.student_id AND cr.semester = 1 AND cr.academic_year = 2024
LEFT JOIN subjects subj ON cr.subject_id = subj.id
$where_clause
GROUP BY s.id
ORDER BY s.student_code";

if (!empty($params)) {
    $stmt = mysqli_prepare($connection, $detail_sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $students = mysqli_stmt_get_result($stmt);
} else {
    $students = mysqli_query($connection, $detail_sql);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Thống kê Sinh viên - QLSV</title>
    <link rel="stylesheet" href="../css/chung.css?v=3">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
        .stat-card.success {
            border-left-color: #28a745;
        }
        .stat-card.warning {
            border-left-color: #ffc107;
        }
        .stat-card.danger {
            border-left-color: #dc3545;
        }
        .stat-card h3 {
            margin: 0 0 10px 0;
            font-size: 0.9rem;
            color: #666;
            text-transform: uppercase;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #333;
        }
        .stat-label {
            font-size: 0.85rem;
            color: #999;
            margin-top: 5px;
        }
        .chart-container {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        .chart-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 20px;
            color: #333;
        }
        .bar-chart {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .bar-item {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .bar-label {
            min-width: 150px;
            font-size: 0.9rem;
            color: #666;
        }
        .bar-track {
            flex: 1;
            height: 30px;
            background: #f0f0f0;
            border-radius: 4px;
            position: relative;
            overflow: hidden;
        }
        .bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #2b87ff, #1976d2);
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 10px;
            color: white;
            font-weight: 600;
            font-size: 0.85rem;
            transition: width 0.5s ease;
        }
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .gender-stats {
            display: flex;
            gap: 20px;
            justify-content: space-around;
            margin-top: 20px;
        }
        .gender-item {
            text-align: center;
        }
        .gender-icon {
            font-size: 3rem;
            margin-bottom: 10px;
        }
        .gender-count {
            font-size: 1.5rem;
            font-weight: bold;
            color: #333;
        }
        .gender-label {
            font-size: 0.9rem;
            color: #666;
        }
        .export-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
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
            <a href="students.php" class="active">Thống kê sinh viên</a>
            <a href="grades.php">Thống kê điểm số</a>
            <a href="tuition.php">Thống kê học phí</a>
        </div>

        <div class="nav-section">
            <a href="../logout.php">Đăng xuất</a>
        </div>
    </div>

    <div class="main">
        <h1>Thống kê Sinh viên</h1>

        <!-- Main Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Tổng số sinh viên</h3>
                <div class="stat-value"><?php echo number_format($stats['total_students']); ?></div>
                <div class="stat-label">Đang học</div>
            </div>
            <div class="stat-card success">
                <h3>Sinh viên đã đăng ký môn</h3>
                <div class="stat-value"><?php echo number_format($reg_stats['students_registered']); ?></div>
                <div class="stat-label">Học kỳ 1 - 2025</div>
            </div>
            <div class="stat-card warning">
                <h3>Tổng lượt đăng ký</h3>
                <div class="stat-value"><?php echo number_format($reg_stats['total_registrations']); ?></div>
                <div class="stat-label">Tất cả các môn</div>
            </div>
            <div class="stat-card">
                <h3>Trung bình tín chỉ/SV</h3>
                <div class="stat-value"><?php echo number_format($avg_credits, 1); ?></div>
                <div class="stat-label">Học kỳ hiện tại</div>
            </div>
        </div>

        <!-- Gender Statistics -->
        <div class="chart-container">
            <div class="chart-title">Thống kê theo giới tính</div>
            <div class="gender-stats">
                <div class="gender-item">
                  
                    <div class="gender-count"><?php echo $stats['male']; ?></div>
                    <div class="gender-label">Nam</div>
                </div>
                <div class="gender-item">
                   
                    <div class="gender-count"><?php echo $stats['female']; ?></div>
                    <div class="gender-label">Nữ</div>
                </div>
                <?php if ($stats['other'] > 0): ?>
                <div class="gender-item">
                  
                    <div class="gender-count"><?php echo $stats['other']; ?></div>
                    <div class="gender-label">Khác</div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Department Statistics -->
        <div class="chart-container">
            <div class="chart-title">Sinh viên theo Khoa</div>
            <div class="bar-chart">
                <?php 
                $max_dept = max(array_column($dept_stats, 'count')) ?: 1;
                foreach ($dept_stats as $dept): 
                    $percentage = ($dept['count'] / $max_dept) * 100;
                ?>
                <div class="bar-item">
                    <div class="bar-label"><?php echo htmlspecialchars($dept['department_name']); ?></div>
                    <div class="bar-track">
                        <div class="bar-fill" style="width: <?php echo $percentage; ?>%;">
                            <?php echo $dept['count']; ?> SV
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Class Statistics -->
        <div class="chart-container">
            <div class="chart-title">Top 10 Lớp có nhiều sinh viên nhất</div>
            <div class="bar-chart">
                <?php 
                $max_class = max(array_column($class_stats, 'count')) ?: 1;
                foreach ($class_stats as $class): 
                    $percentage = ($class['count'] / $max_class) * 100;
                ?>
                <div class="bar-item">
                    <div class="bar-label">
                        <?php echo htmlspecialchars($class['class_name']); ?>
                        <small style="color: #999;">(<?php echo htmlspecialchars($class['department_name']); ?>)</small>
                    </div>
                    <div class="bar-track">
                        <div class="bar-fill" style="width: <?php echo $percentage; ?>%;">
                            <?php echo $class['count']; ?> SV
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Academic Year Statistics -->
        <div class="chart-container">
            <div class="chart-title">Sinh viên theo Năm học</div>
            <div class="bar-chart">
                <?php 
                $max_year = max(array_column($year_stats, 'count')) ?: 1;
                foreach ($year_stats as $year): 
                    $percentage = ($year['count'] / $max_year) * 100;
                ?>
                <div class="bar-item">
                    <div class="bar-label">Khóa <?php echo $year['academic_year']; ?></div>
                    <div class="bar-track">
                        <div class="bar-fill" style="width: <?php echo $percentage; ?>%;">
                            <?php echo $year['count']; ?> SV
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <h3>Lọc danh sách chi tiết</h3>
            <form method="get" action="students.php">
                <div class="filter-grid">
                    <div class="form-group">
                        <label>Khoa</label>
                        <select name="department_id">
                            <option value="">Tất cả</option>
                            <?php mysqli_data_seek($departments, 0); ?>
                            <?php while ($dept = mysqli_fetch_assoc($departments)): ?>
                            <option value="<?php echo $dept['id']; ?>" 
                                    <?php echo $selected_department == $dept['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept['department_name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Lớp</label>
                        <select name="class_id">
                            <option value="">Tất cả</option>
                            <?php mysqli_data_seek($classes, 0); ?>
                            <?php while ($class = mysqli_fetch_assoc($classes)): ?>
                            <option value="<?php echo $class['id']; ?>" 
                                    <?php echo $selected_class == $class['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class['class_name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Năm học</label>
                        <select name="academic_year">
                            <option value="">Tất cả</option>
                            <?php while ($year = mysqli_fetch_assoc($years_query)): ?>
                            <option value="<?php echo $year['academic_year']; ?>" 
                                    <?php echo $selected_year == $year['academic_year'] ? 'selected' : ''; ?>>
                                <?php echo $year['academic_year']; ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group" style="display: flex; align-items: flex-end;">
                        <button type="submit" class="btn btn-primary">Lọc</button>
                        <a href="students.php" class="btn btn-secondary" style="margin-left: 10px;">Reset</a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Detailed Student List -->
        <div class="chart-container">
            <div class="chart-title">Danh sách chi tiết sinh viên</div>
            <table>
                <thead>
                    <tr>
                        <th>MSSV</th>
                        <th>Họ tên</th>
                        <th>Giới tính</th>
                        <th>Ngày sinh</th>
                        <th>Lớp</th>
                        <th>Khoa</th>
                        <th>Năm học</th>
                        <th>Số môn ĐK</th>
                        <th>Tổng TC</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($student = mysqli_fetch_assoc($students)): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($student['student_code']); ?></td>
                        <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                        <td>
                            <?php 
                            $gender_map = ['M' => ' Nam', 'F' => ' Nữ', 'O' => ' Khác'];
                            echo $gender_map[$student['gender']] ?? $student['gender']; 
                            ?>
                        </td>
                        <td><?php echo date('d/m/Y', strtotime($student['date_of_birth'])); ?></td>
                        <td><?php echo htmlspecialchars($student['class_name'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($student['department_name'] ?? 'N/A'); ?></td>
                        <td><?php echo $student['academic_year']; ?></td>
                        <td style="text-align: center;">
                            <span style="background: #e3f2fd; color: #1976d2; padding: 4px 10px; border-radius: 12px; font-weight: 600;">
                                <?php echo $student['registered_courses']; ?>
                            </span>
                        </td>
                        <td style="text-align: center;">
                            <span style="background: #e8f5e9; color: #2e7d32; padding: 4px 10px; border-radius: 12px; font-weight: 600;">
                                <?php echo $student['total_credits']; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <div style="margin-top: 20px;">
            <a href="../dashboard.php" class="btn btn-secondary">Quay lại Trang chủ</a>
        </div>
    </div>
</body>
</html>
