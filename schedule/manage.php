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
        $teacher_id = intval($_POST['teacher_id']);
        $class_id = intval($_POST['class_id']);
        $day_of_week = intval($_POST['day_of_week']);
        $start_period = intval($_POST['start_period']);
        $num_periods = intval($_POST['num_periods']);
        $room = trim($_POST['room']);
        $semester = intval($_POST['semester']);
        $academic_year = intval($_POST['academic_year']);
        
        $sql = "INSERT INTO schedules (subject_id, teacher_id, class_id, day_of_week, start_period, num_periods, room, semester, academic_year) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($connection, $sql);
        mysqli_stmt_bind_param($stmt, "iiiiissii", $subject_id, $teacher_id, $class_id, $day_of_week, $start_period, $num_periods, $room, $semester, $academic_year);
        
        if (mysqli_stmt_execute($stmt)) {
            $message = "Đã thêm lịch học thành công!";
        } else {
            $error = "Lỗi: " . mysqli_error($connection);
        }
    } elseif (isset($_POST['delete'])) {
        $schedule_id = intval($_POST['schedule_id']);
        $sql = "DELETE FROM schedules WHERE id = ?";
        $stmt = mysqli_prepare($connection, $sql);
        mysqli_stmt_bind_param($stmt, "i", $schedule_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $message = "Đã xóa lịch học thành công!";
        } else {
            $error = "Lỗi: " . mysqli_error($connection);
        }
    }
}

// Get schedules
$sql = "SELECT s.*, subj.subject_code, subj.subject_name, t.full_name as teacher_name, c.class_name
        FROM schedules s
        JOIN subjects subj ON s.subject_id = subj.id
        LEFT JOIN teachers t ON s.teacher_id = t.id
        LEFT JOIN classes c ON s.class_id = c.id
        WHERE s.semester = ? AND s.academic_year = ?
        ORDER BY s.day_of_week, s.start_period";
$stmt = mysqli_prepare($connection, $sql);
mysqli_stmt_bind_param($stmt, "ii", $current_semester, $current_year);
mysqli_stmt_execute($stmt);
$schedules = mysqli_stmt_get_result($stmt);

// Get dropdowns
$subjects = mysqli_query($connection, "SELECT id, subject_code, subject_name FROM subjects ORDER BY subject_code");
$teachers = mysqli_query($connection, "SELECT id, full_name FROM teachers ORDER BY full_name");
$classes = mysqli_query($connection, "SELECT id, class_name FROM classes ORDER BY class_name");

$days = ['', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7', 'CN'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quản lý TKB</title>
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
        <h2>Quản lý Thời khóa biểu</h2>
        <?php if ($message): ?><div class="alert" style="background:#d4edda;color:#155724;padding:10px;margin-bottom:15px;"><?php echo $message; ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert" style="background:#f8d7da;color:#721c24;padding:10px;margin-bottom:15px;"><?php echo $error; ?></div><?php endif; ?>

        <div class="card">
            <h3>Thêm lịch học - HK<?php echo $current_semester; ?>/<?php echo $current_year; ?></h3>
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
                    <div><label>Giảng viên *</label>
                        <select name="teacher_id" required>
                            <option value="">-- Chọn GV --</option>
                            <?php while ($t = mysqli_fetch_assoc($teachers)): ?>
                            <option value="<?php echo $t['id']; ?>"><?php echo $t['full_name']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div><label>Lớp *</label>
                        <select name="class_id" required>
                            <option value="">-- Chọn lớp --</option>
                            <?php while ($c = mysqli_fetch_assoc($classes)): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo $c['class_name']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div><label>Thứ *</label>
                        <select name="day_of_week" required>
                            <option value="">-- Chọn --</option>
                            <?php for($i=2;$i<=7;$i++): ?><option value="<?php echo $i; ?>">Thứ <?php echo $i; ?></option><?php endfor; ?>
                        </select>
                    </div>
                    <div><label>Tiết bắt đầu *</label><input type="number" name="start_period" min="1" max="12" required></div>
                    <div><label>Số tiết *</label><input type="number" name="num_periods" min="1" max="6" required></div>
                    <div><label>Phòng *</label><input type="text" name="room" required placeholder="VD: A101"></div>
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
            <h3>Danh sách lịch học</h3>
            <table>
                <thead><tr><th>Môn</th><th>GV</th><th>Lớp</th><th>Thứ</th><th>Tiết</th><th>Phòng</th><th>Thao tác</th></tr></thead>
                <tbody>
                    <?php if (mysqli_num_rows($schedules) > 0): ?>
                        <?php while ($sch = mysqli_fetch_assoc($schedules)): ?>
                        <tr>
                            <td><?php echo $sch['subject_code'].' - '.$sch['subject_name']; ?></td>
                            <td><?php echo $sch['teacher_name'] ?? 'N/A'; ?></td>
                            <td><?php echo $sch['class_name'] ?? 'N/A'; ?></td>
                            <td><?php echo $days[$sch['day_of_week']] ?? ''; ?></td>
                            <td>Tiết <?php echo $sch['start_period'].'-'.($sch['start_period']+$sch['num_periods']-1); ?></td>
                            <td><?php echo $sch['room']; ?></td>
                            <td>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Xóa?');">
                                    <input type="hidden" name="schedule_id" value="<?php echo $sch['id']; ?>">
                                    <button type="submit" name="delete" class="btn btn-danger" style="padding:5px 10px;">Xóa</button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7" style="text-align:center;color:#999;">Chưa có lịch học</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div style="margin-top:20px;"><a href="index.php" class="btn btn-primary">← Quay lại</a></div>
    </div>
</body>
</html>
