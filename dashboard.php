<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
require_once 'connect.php';

$role = $_SESSION['role'];
$username = $_SESSION['username'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard - QLSV</title>
    <link rel="stylesheet" href="css/chung.css?v=3">
    <style>
        .nav-footer {
            margin-top: 20px;
            padding: 20px;
            color: #777;
            font-size: 13px;
            border-top: 1px solid #e6e9ee;
        }
        .nav-section {
            margin-bottom: 15px;
            border-bottom: 1px solid #e6e9ee;
            padding-bottom: 15px;
        }
        .nav-section:last-child {
            border-bottom: none;
        }
        .nav-header {
            padding: 0 20px;
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 10px 0;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .stat-card {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .stat-card h4 {
            margin: 0 0 10px;
            color: #666;
        }
        .stat-card .number {
            font-size: 24px;
            font-weight: bold;
            color: #2b87ff;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="brand">QLSV</div>
        
        <div class="nav-section">
            <div class="nav-header">Chung</div>
            <a href="dashboard.php">Trang chủ</a>
            <a href="profile.php">Thông tin cá nhân</a>
        </div>

        <?php if (in_array($role, ['admin', 'teacher'])): ?>
        <div class="nav-section">
            <div class="nav-header">Quản lý</div>
            <?php if ($role === 'admin'): ?>
            <a href="account/list.php">Quản lý tài khoản</a>
            <a href="department/list.php">Quản lý khoa</a>
            <a href="classes/list.php">Quản lý lớp học</a>
            <?php endif; ?>
            <a href="student/list.php">Quản lý sinh viên</a>
            <?php if ($role === 'admin'): ?>
            <a href="teacher/list.php">Quản lý giảng viên</a>
            <?php endif; ?>
            <a href="subject/list.php">Quản lý môn học</a>
        </div>
        <?php endif; ?>

        <div class="nav-section">
            <div class="nav-header">Học tập</div>
            <?php if ($role === 'student'): ?>
            <a href="registration/index.php">Đăng ký môn học</a>
            <a href="grades/view.php">Xem điểm</a>
            <?php endif; ?>
            <?php if ($role === 'teacher'): ?>
            <a href="grades/input.php">Nhập điểm</a>
            <?php endif; ?>
            <a href="schedule/index.php">Thời khóa biểu</a>
            <a href="schedule/lichthi.php">Lịch thi</a>
        </div>

        <?php if ($role === 'student'): ?>
        <div class="nav-section">
            <div class="nav-header">Học tập</div>
            <a href="grades/view.php">Xem điểm</a>
        </div>
        
        <div class="nav-section">
            <div class="nav-header">Học phí</div>
            <a href="tuition/status.php">Tình trạng học phí</a>
            <a href="tuition/history.php">Lịch sử đóng học phí</a>
        </div>
        <?php endif; ?>

        <?php if ($role === 'admin'): ?>
        <div class="nav-section">
            <div class="nav-header">Báo cáo</div>
            <a href="reports/students.php">Thống kê sinh viên</a>
            <a href="reports/grades.php">Thống kê điểm số</a>
            <a href="reports/tuition.php">Thống kê học phí</a>
        </div>
        <?php endif; ?>

        <div class="nav-section">
            <a href="logout.php">Đăng xuất</a>
        </div>

        <div class="nav-footer">
            Đăng nhập với: <?php echo htmlspecialchars($username); ?><br>
            Quyền hạn: <?php echo htmlspecialchars($role); ?>
        </div>
    </div>

    <div class="main">
        <div class="card">
            <h2>Chào mừng, <?php echo htmlspecialchars($username); ?></h2>
            <p>Chọn chức năng từ menu bên trái để bắt đầu.</p>
        </div>

        <?php if ($role === 'admin'): ?>
        <h3>Thống kê nhanh</h3>
        <div class="stats-grid">
            <?php
            // Get quick statistics
            $stats = [
                'students' => mysqli_query($connection, "SELECT COUNT(*) as count FROM students")->fetch_assoc()['count'] ?? 0,
                'teachers' => mysqli_query($connection, "SELECT COUNT(*) as count FROM teachers")->fetch_assoc()['count'] ?? 0,
                'subjects' => mysqli_query($connection, "SELECT COUNT(*) as count FROM subjects")->fetch_assoc()['count'] ?? 0,
                'classes' => mysqli_query($connection, "SELECT COUNT(*) as count FROM classes")->fetch_assoc()['count'] ?? 0
            ];
            ?>
            <div class="stat-card">
                <h4>Tổng số sinh viên</h4>
                <div class="number"><?php echo $stats['students']; ?></div>
            </div>
            <div class="stat-card">
                <h4>Tổng số giảng viên</h4>
                <div class="number"><?php echo $stats['teachers']; ?></div>
            </div>
            <div class="stat-card">
                <h4>Tổng số môn học</h4>
                <div class="number"><?php echo $stats['subjects']; ?></div>
            </div>
            <div class="stat-card">
                <h4>Tổng số lớp</h4>
                <div class="number"><?php echo $stats['classes']; ?></div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($role === 'student'): ?>
        <h3>Thông tin học tập</h3>
        <div class="stats-grid">
            <?php
            $student_id = mysqli_query($connection, 
                "SELECT s.id FROM students s 
                JOIN users u ON s.user_id = u.id 
                WHERE u.id = " . intval($_SESSION['user_id'])
            )->fetch_assoc()['id'] ?? 0;

            $stats = [
                'registered_subjects' => mysqli_query($connection, 
                    "SELECT COUNT(*) as count FROM course_registrations 
                    WHERE student_id = $student_id")->fetch_assoc()['count'] ?? 0,
                'completed_subjects' => mysqli_query($connection,
                    "SELECT COUNT(*) as count FROM grades 
                    WHERE student_id = $student_id AND final_grade IS NOT NULL")->fetch_assoc()['count'] ?? 0
            ];
            ?>
            <div class="stat-card">
                <h4>Môn học đã đăng ký</h4>
                <div class="number"><?php echo $stats['registered_subjects']; ?></div>
            </div>
            <div class="stat-card">
                <h4>Môn học đã hoàn thành</h4>
                <div class="number"><?php echo $stats['completed_subjects']; ?></div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($role === 'teacher'): ?>
        <h3>Thông tin giảng dạy</h3>
        <div class="stats-grid">
            <?php
            $teacher_id = mysqli_query($connection, 
                "SELECT t.id FROM teachers t 
                JOIN users u ON t.user_id = u.id 
                WHERE u.id = " . intval($_SESSION['user_id'])
            )->fetch_assoc()['id'] ?? 0;

            $stats = [
                'teaching_subjects' => mysqli_query($connection, 
                    "SELECT COUNT(*) as count FROM subjects 
                    WHERE teacher_id = $teacher_id")->fetch_assoc()['count'] ?? 0,
                'classes_taught' => mysqli_query($connection,
                    "SELECT COUNT(DISTINCT class_id) as count FROM schedules 
                    WHERE teacher_id = $teacher_id")->fetch_assoc()['count'] ?? 0
            ];
            ?>
            <div class="stat-card">
                <h4>Môn học phụ trách</h4>
                <div class="number"><?php echo $stats['teaching_subjects']; ?></div>
            </div>
            <div class="stat-card">
                <h4>Lớp học phụ trách</h4>
                <div class="number"><?php echo $stats['classes_taught']; ?></div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
