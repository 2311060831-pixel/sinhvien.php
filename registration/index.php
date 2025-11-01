<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit();
}

require_once '../connect.php';

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get student info
$student_query = "SELECT s.*, c.class_name, d.department_name 
                  FROM students s 
                  LEFT JOIN classes c ON s.class_id = c.id 
                  LEFT JOIN departments d ON s.department_id = d.id 
                  WHERE s.user_id = ?";
$stmt = mysqli_prepare($connection, $student_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$student = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$student) {
    die("Không tìm thấy thông tin sinh viên!");
}

// Current semester and year
$current_semester = 1; // Can be changed based on system settings
$current_year = 2025;

// Handle registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $subject_id = intval($_POST['subject_id']);
    
    // Check if already registered
    $check_sql = "SELECT id FROM course_registrations 
                  WHERE student_id = ? AND subject_id = ? 
                  AND semester = ? AND academic_year = ?";
    $stmt = mysqli_prepare($connection, $check_sql);
    mysqli_stmt_bind_param($stmt, "iiii", $student['id'], $subject_id, $current_semester, $current_year);
    mysqli_stmt_execute($stmt);
    $existing = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($existing) > 0) {
        $error = 'Bạn đã đăng ký môn học này rồi!';
    } else {
        // Check credit limit (max 24 credits per semester)
        $credits_sql = "SELECT SUM(subj.credits) as total_credits
                       FROM course_registrations cr
                       JOIN subjects subj ON cr.subject_id = subj.id
                       WHERE cr.student_id = ? AND cr.semester = ? AND cr.academic_year = ?";
        $stmt = mysqli_prepare($connection, $credits_sql);
        mysqli_stmt_bind_param($stmt, "iii", $student['id'], $current_semester, $current_year);
        mysqli_stmt_execute($stmt);
        $credits_result = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        $total_credits = $credits_result['total_credits'] ?? 0;
        
        // Get credits of subject to register
        $subject_credits_sql = "SELECT credits FROM subjects WHERE id = ?";
        $stmt = mysqli_prepare($connection, $subject_credits_sql);
        mysqli_stmt_bind_param($stmt, "i", $subject_id);
        mysqli_stmt_execute($stmt);
        $subject_credits_result = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        $new_credits = $subject_credits_result['credits'];
        
        if (($total_credits + $new_credits) > 24) {
            $error = "Vượt quá số tín chỉ cho phép! (Hiện tại: $total_credits, Tối đa: 24)";
        } else {
            // Register
            $register_sql = "INSERT INTO course_registrations (student_id, subject_id, semester, academic_year) 
                           VALUES (?, ?, ?, ?)";
            $stmt = mysqli_prepare($connection, $register_sql);
            mysqli_stmt_bind_param($stmt, "iiii", $student['id'], $subject_id, $current_semester, $current_year);
            
            if (mysqli_stmt_execute($stmt)) {
                $success = 'Đăng ký môn học thành công!';
            } else {
                $error = 'Có lỗi xảy ra khi đăng ký.';
            }
        }
    }
}

// Handle unregister
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unregister'])) {
    $registration_id = intval($_POST['registration_id']);
    
    $delete_sql = "DELETE FROM course_registrations 
                  WHERE id = ? AND student_id = ?";
    $stmt = mysqli_prepare($connection, $delete_sql);
    mysqli_stmt_bind_param($stmt, "ii", $registration_id, $student['id']);
    
    if (mysqli_stmt_execute($stmt)) {
        $success = 'Đã hủy đăng ký môn học!';
    } else {
        $error = 'Có lỗi xảy ra khi hủy đăng ký.';
    }
}

// Get registered subjects
$registered_query = "SELECT 
    cr.id as registration_id,
    subj.subject_code,
    subj.subject_name,
    subj.credits,
    t.full_name as teacher_name
FROM course_registrations cr
JOIN subjects subj ON cr.subject_id = subj.id
LEFT JOIN teachers t ON subj.teacher_id = t.id
WHERE cr.student_id = ? AND cr.semester = ? AND cr.academic_year = ?
ORDER BY subj.subject_code";
$stmt = mysqli_prepare($connection, $registered_query);
mysqli_stmt_bind_param($stmt, "iii", $student['id'], $current_semester, $current_year);
mysqli_stmt_execute($stmt);
$registered_subjects = mysqli_stmt_get_result($stmt);

// Calculate total credits
$total_credits_query = "SELECT SUM(subj.credits) as total
FROM course_registrations cr
JOIN subjects subj ON cr.subject_id = subj.id
WHERE cr.student_id = ? AND cr.semester = ? AND cr.academic_year = ?";
$stmt = mysqli_prepare($connection, $total_credits_query);
mysqli_stmt_bind_param($stmt, "iii", $student['id'], $current_semester, $current_year);
mysqli_stmt_execute($stmt);
$total_credits_result = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
$total_credits = $total_credits_result['total'] ?? 0;

// Get available subjects (not yet registered)
$available_query = "SELECT 
    subj.id,
    subj.subject_code,
    subj.subject_name,
    subj.credits,
    t.full_name as teacher_name,
    d.department_name
FROM subjects subj
LEFT JOIN teachers t ON subj.teacher_id = t.id
LEFT JOIN departments d ON t.department_id = d.id
WHERE subj.id NOT IN (
    SELECT subject_id FROM course_registrations 
    WHERE student_id = ? AND semester = ? AND academic_year = ?
)
ORDER BY subj.subject_code";
$stmt = mysqli_prepare($connection, $available_query);
mysqli_stmt_bind_param($stmt, "iii", $student['id'], $current_semester, $current_year);
mysqli_stmt_execute($stmt);
$available_subjects = mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đăng ký môn học - QLSV</title>
    <link rel="stylesheet" href="../css/chung.css?v=3">
    <style>
        .registration-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-top: 20px;
        }
        @media (max-width: 1024px) {
            .registration-container {
                grid-template-columns: 1fr;
            }
        }
        .section-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .section-header {
            padding: 20px;
            border-bottom: 2px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .section-header h2 {
            margin: 0;
            font-size: 1.3rem;
        }
        .credits-badge {
            background: #2b87ff;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }
        .credits-warning {
            background: #ff9800;
        }
        .credits-danger {
            background: #dc3545;
        }
        .subject-list {
            padding: 15px;
        }
        .subject-item {
            padding: 15px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s;
        }
        .subject-item:hover {
            border-color: #2b87ff;
            box-shadow: 0 2px 8px rgba(43,135,255,0.1);
        }
        .subject-info {
            flex: 1;
        }
        .subject-code {
            display: inline-block;
            background: #e3f2fd;
            color: #1976d2;
            padding: 4px 10px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 0.85rem;
            margin-bottom: 5px;
        }
        .subject-name {
            font-weight: 600;
            color: #333;
            margin: 5px 0;
        }
        .subject-meta {
            font-size: 0.9rem;
            color: #666;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        .subject-actions {
            margin-left: 15px;
        }
        .btn-register {
            background: #28a745;
            color: white;
            padding: 8px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
        }
        .btn-register:hover {
            background: #218838;
        }
        .btn-unregister {
            background: #dc3545;
            color: white;
            padding: 8px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
        }
        .btn-unregister:hover {
            background: #c82333;
        }
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }
        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: 10px;
        }
        .info-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px;
            border-radius: 4px;
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
            <div class="nav-header">Học tập</div>
            <a href="index.php" class="active">Đăng ký môn học</a>
            <a href="../grades/view.php">Xem điểm</a>
            <a href="../schedule/index.php">Thời khóa biểu</a>
            <a href="../schedule/lichthi.php">Lịch thi</a>
        </div>

        <div class="nav-section">
            <div class="nav-header">Học phí</div>
            <a href="../tuition/status.php">Tình trạng học phí</a>
            <a href="../tuition/history.php">Lịch sử đóng học phí</a>
        </div>

        <div class="nav-section">
            <a href="../logout.php">Đăng xuất</a>
        </div>
    </div>

    <div class="main">
        <div class="actions">
            <h1>Đăng ký môn học</h1>
            <div>
                <strong>Học kỳ:</strong> <?php echo $current_semester; ?> | 
                <strong>Năm học:</strong> <?php echo $current_year; ?>
            </div>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($success); ?>
        </div>
        <?php endif; ?>

        <div class="info-box">
            <strong>Lưu ý:</strong> Tối đa 24 tín chỉ mỗi học kỳ. 
            Bạn có thể hủy đăng ký trước khi học kỳ bắt đầu.
        </div>

        <div class="registration-container">
            <!-- Registered Subjects -->
            <div class="section-card">
                <div class="section-header">
                    <h2>Môn đã đăng ký</h2>
                    <span class="credits-badge <?php 
                        echo $total_credits > 20 ? 'credits-warning' : '';
                        echo $total_credits > 24 ? 'credits-danger' : '';
                    ?>">
                        <?php echo $total_credits; ?> / 24 tín chỉ
                    </span>
                </div>
                <div class="subject-list">
                    <?php if (mysqli_num_rows($registered_subjects) > 0): ?>
                        <?php while ($subject = mysqli_fetch_assoc($registered_subjects)): ?>
                        <div class="subject-item">
                            <div class="subject-info">
                                <div>
                                    <span class="subject-code">
                                        <?php echo htmlspecialchars($subject['subject_code']); ?>
                                    </span>
                                </div>
                                <div class="subject-name">
                                    <?php echo htmlspecialchars($subject['subject_name']); ?>
                                </div>
                                <div class="subject-meta">
                                    <span> <?php echo $subject['credits']; ?> tín chỉ</span>
                                    <?php if ($subject['teacher_name']): ?>
                                    <span> <?php echo htmlspecialchars($subject['teacher_name']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="subject-actions">
                                <form method="post" style="display: inline;" 
                                      onsubmit="return confirm('Bạn có chắc muốn hủy đăng ký môn này?');">
                                    <input type="hidden" name="registration_id" 
                                           value="<?php echo $subject['registration_id']; ?>">
                                    <button type="submit" name="unregister" class="btn-unregister">
                                         Hủy
                                    </button>
                                </form>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon"></div>
                            <p>Bạn chưa đăng ký môn học nào</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Available Subjects -->
            <div class="section-card">
                <div class="section-header">
                    <h2> Môn học có thể đăng ký</h2>
                </div>
                <div class="subject-list">
                    <?php if (mysqli_num_rows($available_subjects) > 0): ?>
                        <?php while ($subject = mysqli_fetch_assoc($available_subjects)): ?>
                        <div class="subject-item">
                            <div class="subject-info">
                                <div>
                                    <span class="subject-code">
                                        <?php echo htmlspecialchars($subject['subject_code']); ?>
                                    </span>
                                </div>
                                <div class="subject-name">
                                    <?php echo htmlspecialchars($subject['subject_name']); ?>
                                </div>
                                <div class="subject-meta">
                                    <span> <?php echo $subject['credits']; ?> tín chỉ</span>
                                    <?php if ($subject['teacher_name']): ?>
                                    <span> <?php echo htmlspecialchars($subject['teacher_name']); ?></span>
                                    <?php endif; ?>
                                    <?php if ($subject['department_name']): ?>
                                    <span></span> <?php echo htmlspecialchars($subject['department_name']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="subject-actions">
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="subject_id" 
                                           value="<?php echo $subject['id']; ?>">
                                    <button type="submit" name="register" class="btn-register"
                                            <?php echo ($total_credits + $subject['credits']) > 24 ? 'disabled' : ''; ?>>
                                         Đăng ký
                                    </button>
                                </form>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon"></div>
                            <p>Bạn đã đăng ký hết tất cả các môn!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
