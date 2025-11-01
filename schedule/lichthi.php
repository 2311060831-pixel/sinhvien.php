<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

require_once '../connect.php';

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$exams = [];

// Get current semester
$current_semester = 1;
$current_year = date('Y');

if ($connection) {
    if ($role === 'student') {
        // Get exam schedule for student based on registered courses
        $sql = "SELECT 
                    es.exam_date,
                    es.start_time,
                    es.room,
                    es.semester,
                    es.academic_year,
                    subj.subject_name,
                    subj.subject_code,
                    t.full_name as supervisor_name
                FROM exam_schedules es
                INNER JOIN subjects subj ON es.subject_id = subj.id
                LEFT JOIN teachers t ON es.supervisor_id = t.id
                INNER JOIN course_registrations cr ON cr.subject_id = es.subject_id 
                    AND cr.semester = es.semester 
                    AND cr.academic_year = es.academic_year
                INNER JOIN students st ON cr.student_id = st.id
                WHERE st.user_id = ?
                ORDER BY es.exam_date DESC, es.start_time";
        
        $stmt = mysqli_prepare($connection, $sql);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $exams[] = $row;
        }
    } elseif ($role === 'teacher') {
        // Get exam schedule for teacher (as supervisor)
        $sql = "SELECT 
                    es.exam_date,
                    es.start_time,
                    es.room,
                    subj.subject_name,
                    subj.subject_code,
                    COUNT(DISTINCT cr.student_id) as student_count
                FROM exam_schedules es
                INNER JOIN subjects subj ON es.subject_id = subj.id
                INNER JOIN teachers t ON es.supervisor_id = t.id
                LEFT JOIN course_registrations cr ON cr.subject_id = es.subject_id
                WHERE t.user_id = ? 
                AND es.semester = ? 
                AND es.academic_year = ?
                GROUP BY es.id
                ORDER BY es.exam_date, es.start_time";
        
        $stmt = mysqli_prepare($connection, $sql);
        mysqli_stmt_bind_param($stmt, "iii", $user_id, $current_semester, $current_year);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $exams[] = $row;
        }
    } else {
        // Admin: Show all exam schedules
        $sql = "SELECT 
                    es.exam_date,
                    es.start_time,
                    es.room,
                    subj.subject_name,
                    subj.subject_code,
                    t.full_name as supervisor_name,
                    COUNT(DISTINCT cr.student_id) as student_count
                FROM exam_schedules es
                INNER JOIN subjects subj ON es.subject_id = subj.id
                LEFT JOIN teachers t ON es.supervisor_id = t.id
                LEFT JOIN course_registrations cr ON cr.subject_id = es.subject_id
                WHERE es.semester = ? 
                AND es.academic_year = ?
                GROUP BY es.id
                ORDER BY es.exam_date, es.start_time";
        
        $stmt = mysqli_prepare($connection, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $current_semester, $current_year);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $exams[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Lịch thi - QLSV</title>
    <link rel="stylesheet" href="../css/chung.css?v=3">
    <style>
        .exam-header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .exam-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .exam-table {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            overflow-x: auto;
        }
        .date-badge {
            display: inline-block;
            padding: 6px 12px;
            background: #fff3cd;
            color: #856404;
            border-radius: 4px;
            font-weight: 500;
        }
        .time-badge {
            display: inline-block;
            padding: 4px 8px;
            background: #d1ecf1;
            color: #0c5460;
            border-radius: 4px;
            font-size: 14px;
        }
        .empty-message {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        .empty-message i {
            font-size: 48px;
            color: #ddd;
            margin-bottom: 15px;
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

        <?php if (in_array($role, ['admin', 'teacher'])): ?>
        <div class="nav-section">
            <div class="nav-header">Quản lý</div>
            <?php if ($role === 'admin'): ?>
            <a href="../account/list.php">Quản lý tài khoản</a>
            <a href="../department/list.php">Quản lý khoa</a>
            <a href="../classes/list.php">Quản lý lớp học</a>
            <?php endif; ?>
            <a href="../student/list.php">Quản lý sinh viên</a>
            <?php if ($role === 'admin'): ?>
            <a href="../teacher/list.php">Quản lý giảng viên</a>
            <?php endif; ?>
            <a href="../subject/list.php">Quản lý môn học</a>
        </div>
        <?php endif; ?>

        <div class="nav-section">
            <div class="nav-header">Học tập</div>
            <?php if ($role === 'student'): ?>
            <a href="../registration/index.php">Đăng ký môn học</a>
            <a href="../grades/view.php">Xem điểm</a>
            <?php endif; ?>
            <?php if ($role === 'teacher'): ?>
            <a href="../grades/input.php">Nhập điểm</a>
            <?php endif; ?>
            <a href="index.php">Thời khóa biểu</a>
            <a href="lichthi.php" class="active">Lịch thi</a>
        </div>

        <?php if ($role === 'student'): ?>
        <div class="nav-section">
            <div class="nav-header">Học phí</div>
            <a href="../tuition/status.php">Tình trạng học phí</a>
            <a href="../tuition/history.php">Lịch sử đóng học phí</a>
        </div>
        <?php endif; ?>

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
        <h1>Lịch thi</h1>

        <div class="exam-header">
            <div class="exam-info">
                <div>
                    <strong>Học kỳ:</strong> <?php echo $current_semester; ?> - 
                    <strong>Năm học:</strong> <?php echo $current_year; ?>
                </div>
                <?php if ($role === 'admin'): ?>
                <a href="manage_exam.php" class="btn btn-primary">+ Quản lý lịch thi</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="exam-table">
            <?php if (empty($exams)): ?>
            <div class="empty-message">
                <div style="font-size: 48px; margin-bottom: 15px;">📝</div>
                <h3>Chưa có lịch thi</h3>
                <p>
                    <?php if ($role === 'student'): ?>
                    Bạn chưa có lịch thi nào hoặc chưa đăng ký môn học.
                    <?php elseif ($role === 'teacher'): ?>
                    Bạn chưa được phân công coi thi môn nào trong học kỳ này.
                    <?php else: ?>
                    Chưa có lịch thi nào được tạo cho học kỳ này.
                    <?php endif; ?>
                </p>
            </div>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Môn học</th>
                        <th>Ngày thi</th>
                        <th>Giờ thi</th>
                        <th>Phòng thi</th>
                        <?php if ($role === 'student'): ?>
                        <th>Giám thị</th>
                        <?php elseif (in_array($role, ['admin', 'teacher'])): ?>
                        <th>Số SV</th>
                        <?php endif; ?>
                        <?php if ($role === 'admin'): ?>
                        <th>Giám thị</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($exams as $exam): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($exam['subject_name']); ?></strong><br>
                            <small style="color: #666;">
                                <?php echo htmlspecialchars($exam['subject_code']); ?>
                                <?php if (isset($exam['semester']) && isset($exam['academic_year'])): ?>
                                - HK<?php echo $exam['semester']; ?>/<?php echo $exam['academic_year']; ?>
                                <?php endif; ?>
                            </small>
                        </td>
                        <td>
                            <span class="date-badge">
                                <?php echo date('d/m/Y', strtotime($exam['exam_date'])); ?>
                            </span>
                        </td>
                        <td>
                            <span class="time-badge">
                                <?php echo date('H:i', strtotime($exam['start_time'])); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($exam['room']); ?></td>
                        <?php if ($role === 'student'): ?>
                        <td><?php echo htmlspecialchars($exam['supervisor_name'] ?? 'Chưa có'); ?></td>
                        <?php elseif (in_array($role, ['admin', 'teacher'])): ?>
                        <td><?php echo intval($exam['student_count'] ?? 0); ?></td>
                        <?php endif; ?>
                        <?php if ($role === 'admin'): ?>
                        <td><?php echo htmlspecialchars($exam['supervisor_name'] ?? 'Chưa phân công'); ?></td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <div style="margin-top: 20px;">
            <a href="index.php" class="btn btn-secondary">Xem thời khóa biểu</a>
            <a href="../dashboard.php" class="btn btn-secondary">Quay lại Trang chủ</a>
        </div>
    </div>
</body>
</html>
