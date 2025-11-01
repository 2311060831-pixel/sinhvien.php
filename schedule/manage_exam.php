<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

require_once '../connect.php';

$message = '';
$error = '';
$current_semester = 1;
$current_year = 2025;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        $subject_id = intval($_POST['subject_id']);
        $exam_date = trim($_POST['exam_date']);
        $start_time = trim($_POST['start_time']);
        $room = trim($_POST['room']);
        $supervisor_id = intval($_POST['supervisor_id']);
        $semester = intval($_POST['semester']);
        $academic_year = intval($_POST['academic_year']);
        
        $sql = "INSERT INTO exam_schedules (subject_id, exam_date, start_time, room, supervisor_id, semester, academic_year) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($connection, $sql);
        mysqli_stmt_bind_param($stmt, "isssiie", $subject_id, $exam_date, $start_time, $room, $supervisor_id, $semester, $academic_year);
        
        if (mysqli_stmt_execute($stmt)) {
            $message = "Đã thêm lịch thi thành công!";
        } else {
            $error = "Lỗi: " . mysqli_error($connection);
        }
    } elseif (isset($_POST['delete'])) {
        $exam_id = intval($_POST['exam_id']);
        $sql = "DELETE FROM exam_schedules WHERE id = ?";
        $stmt = mysqli_prepare($connection, $sql);
        mysqli_stmt_bind_param($stmt, "i", $exam_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $message = "Đã xóa lịch thi thành công!";
        } else {
            $error = "Lỗi: " . mysqli_error($connection);
        }
    }
}

// Get exam schedules
$sql = "SELECT es.*, s.subject_code, s.subject_name, t.full_name as supervisor_name
        FROM exam_schedules es
        JOIN subjects s ON es.subject_id = s.id
        LEFT JOIN teachers t ON es.supervisor_id = t.id
        WHERE es.semester = ? AND es.academic_year = ?
        ORDER BY es.exam_date, es.start_time";
$stmt = mysqli_prepare($connection, $sql);
mysqli_stmt_bind_param($stmt, "ii", $current_semester, $current_year);
mysqli_stmt_execute($stmt);
$exams = mysqli_stmt_get_result($stmt);

// Get dropdowns
$subjects = mysqli_query($connection, "SELECT id, subject_code, subject_name FROM subjects ORDER BY subject_code");
$teachers = mysqli_query($connection, "SELECT id, full_name FROM teachers ORDER BY full_name");
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quản lý Lịch thi</title>
    <link rel="stylesheet" href="../css/chung.css?v=3">
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
            <a href="index.php">Thời khóa biểu</a>
            <a href="lichthi.php">Lịch thi</a>
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
        <h2>Quản lý Lịch thi</h2>
        <?php if ($message): ?><div class="alert" style="background:#d4edda;color:#155724;padding:10px;margin-bottom:15px;"><?php echo $message; ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert" style="background:#f8d7da;color:#721c24;padding:10px;margin-bottom:15px;"><?php echo $error; ?></div><?php endif; ?>

        <div class="card">
            <h3>Thêm lịch thi - HK<?php echo $current_semester; ?>/<?php echo $current_year; ?></h3>
            <form method="POST">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                    <div><label>Môn học *</label>
                        <select name="subject_id" required>
                            <option value="">-- Chọn môn --</option>
                            <?php while ($s = mysqli_fetch_assoc($subjects)): ?>
                            <option value="<?php echo $s['id']; ?>"><?php echo $s['subject_code'].' - '.$s['subject_name']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div><label>Giám thị *</label>
                        <select name="supervisor_id" required>
                            <option value="">-- Chọn giám thị --</option>
                            <?php while ($t = mysqli_fetch_assoc($teachers)): ?>
                            <option value="<?php echo $t['id']; ?>"><?php echo $t['full_name']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div><label>Ngày thi *</label><input type="date" name="exam_date" required></div>
                    <div><label>Giờ thi *</label><input type="time" name="start_time" required></div>
                    <div><label>Phòng thi *</label><input type="text" name="room" required placeholder="VD: A101"></div>
                    <div><label>Học kỳ *</label>
                        <select name="semester" required>
                            <option value="1" selected>HK1</option>
                            <option value="2">HK2</option>
                            <option value="3">HK Hè</option>
                        </select>
                    </div>
                    <div><label>Năm *</label><input type="number" name="academic_year" value="2025" required></div>
                </div>
                <button type="submit" name="add" class="btn btn-primary" style="margin-top:15px;">Thêm</button>
            </form>
        </div>

        <div class="card" style="margin-top:20px;">
            <h3>Danh sách lịch thi</h3>
            <table>
                <thead><tr><th>Môn học</th><th>Ngày thi</th><th>Giờ</th><th>Phòng</th><th>Giám thị</th><th>Thao tác</th></tr></thead>
                <tbody>
                    <?php if (mysqli_num_rows($exams) > 0): ?>
                        <?php while ($ex = mysqli_fetch_assoc($exams)): ?>
                        <tr>
                            <td><?php echo $ex['subject_code'].' - '.$ex['subject_name']; ?></td>
                            <td><?php echo date('d/m/Y', strtotime($ex['exam_date'])); ?></td>
                            <td><?php echo date('H:i', strtotime($ex['start_time'])); ?></td>
                            <td><?php echo $ex['room']; ?></td>
                            <td><?php echo $ex['supervisor_name'] ?? 'N/A'; ?></td>
                            <td>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Xóa?');">
                                    <input type="hidden" name="exam_id" value="<?php echo $ex['id']; ?>">
                                    <button type="submit" name="delete" class="btn btn-danger" style="padding:5px 10px;">Xóa</button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align:center;color:#999;">Chưa có lịch thi</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div style="margin-top:20px;"><a href="lichthi.php" class="btn btn-primary">← Quay lại</a></div>
    </div>
</body>
</html>
