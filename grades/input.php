<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

require_once '../connect.php';

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Get teacher info
$teacher_query = "SELECT t.*, d.department_name 
                  FROM teachers t 
                  LEFT JOIN departments d ON t.department_id = d.id 
                  WHERE t.user_id = ?";
$stmt = mysqli_prepare($connection, $teacher_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$teacher = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$teacher) {
    die("Không tìm thấy thông tin giảng viên!");
}

// Current semester
$current_semester = 1;
$current_year = 2025;

// Get subjects taught by this teacher
$subjects_query = "SELECT DISTINCT
    subj.id,
    subj.subject_code,
    subj.subject_name,
    subj.credits,
    COUNT(DISTINCT cr.student_id) as student_count
FROM subjects subj
LEFT JOIN course_registrations cr ON subj.id = cr.subject_id 
    AND cr.semester = ? AND cr.academic_year = ?
WHERE subj.teacher_id = ?
GROUP BY subj.id
ORDER BY subj.subject_code";
$stmt = mysqli_prepare($connection, $subjects_query);
mysqli_stmt_bind_param($stmt, "iii", $current_semester, $current_year, $teacher['id']);
mysqli_stmt_execute($stmt);
$subjects = mysqli_stmt_get_result($stmt);

// Get selected subject
$selected_subject_id = $_GET['subject_id'] ?? '';

// Handle grade submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_grades'])) {
    $subject_id = intval($_POST['subject_id']);
    $student_grades = $_POST['grades'] ?? [];
    
    mysqli_begin_transaction($connection);
    
    try {
        foreach ($student_grades as $student_id => $grades) {
            $student_id = intval($student_id);
            $midterm = !empty($grades['midterm']) ? floatval($grades['midterm']) : null;
            $final = !empty($grades['final']) ? floatval($grades['final']) : null;
            
            // Check if grade exists
            $check_sql = "SELECT id FROM grades 
                         WHERE student_id = ? AND subject_id = ? 
                         AND semester = ? AND academic_year = ?";
            $stmt = mysqli_prepare($connection, $check_sql);
            mysqli_stmt_bind_param($stmt, "iiii", $student_id, $subject_id, $current_semester, $current_year);
            mysqli_stmt_execute($stmt);
            $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            
            if ($existing) {
                // Update
                $update_sql = "UPDATE grades SET midterm_grade = ?, final_grade = ? 
                              WHERE id = ?";
                $stmt = mysqli_prepare($connection, $update_sql);
                mysqli_stmt_bind_param($stmt, "ddi", $midterm, $final, $existing['id']);
                mysqli_stmt_execute($stmt);
            } else {
                // Insert
                if ($midterm !== null || $final !== null) {
                    $insert_sql = "INSERT INTO grades (student_id, subject_id, semester, academic_year, midterm_grade, final_grade) 
                                  VALUES (?, ?, ?, ?, ?, ?)";
                    $stmt = mysqli_prepare($connection, $insert_sql);
                    mysqli_stmt_bind_param($stmt, "iiiidd", $student_id, $subject_id, $current_semester, $current_year, $midterm, $final);
                    mysqli_stmt_execute($stmt);
                }
            }
        }
        
        mysqli_commit($connection);
        $success = 'Đã lưu điểm thành công!';
        $selected_subject_id = $subject_id;
    } catch (Exception $e) {
        mysqli_rollback($connection);
        $error = 'Có lỗi xảy ra: ' . $e->getMessage();
    }
}

// Get students for selected subject
$students = [];
if ($selected_subject_id) {
    $students_query = "SELECT 
        s.id,
        s.student_code,
        s.full_name,
        c.class_name,
        g.midterm_grade,
        g.final_grade
    FROM course_registrations cr
    JOIN students s ON cr.student_id = s.id
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN grades g ON g.student_id = s.id 
        AND g.subject_id = cr.subject_id 
        AND g.semester = cr.semester 
        AND g.academic_year = cr.academic_year
    WHERE cr.subject_id = ? 
        AND cr.semester = ? 
        AND cr.academic_year = ?
    ORDER BY s.student_code";
    $stmt = mysqli_prepare($connection, $students_query);
    mysqli_stmt_bind_param($stmt, "iii", $selected_subject_id, $current_semester, $current_year);
    mysqli_stmt_execute($stmt);
    $students = mysqli_stmt_get_result($stmt);
    
    // Get subject info
    $subject_info_query = "SELECT * FROM subjects WHERE id = ?";
    $stmt = mysqli_prepare($connection, $subject_info_query);
    mysqli_stmt_bind_param($stmt, "i", $selected_subject_id);
    mysqli_stmt_execute($stmt);
    $selected_subject_info = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nhập điểm - QLSV</title>
    <link rel="stylesheet" href="../css/chung.css?v=3">
    <style>
        .subject-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .subject-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
        }
        .subject-card:hover {
            border-color: #2b87ff;
            box-shadow: 0 4px 12px rgba(43,135,255,0.2);
            transform: translateY(-2px);
        }
        .subject-card.selected {
            border-color: #2b87ff;
            background: #f0f7ff;
        }
        .subject-code {
            background: #e3f2fd;
            color: #1976d2;
            padding: 6px 12px;
            border-radius: 4px;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 10px;
        }
        .subject-name {
            font-weight: 600;
            color: #333;
            margin: 10px 0;
        }
        .subject-meta {
            font-size: 0.85rem;
            color: #666;
            display: flex;
            gap: 15px;
        }
        .grade-form {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        .grade-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
        }
        .grade-header h2 {
            margin: 0 0 5px 0;
        }
        .grade-header p {
            margin: 0;
            opacity: 0.9;
        }
        .grade-table {
            width: 100%;
            border-collapse: collapse;
        }
        .grade-table thead {
            background: #f8f9fa;
        }
        .grade-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #666;
            border-bottom: 2px solid #e0e0e0;
        }
        .grade-table td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
        }
        .grade-table tbody tr:hover {
            background: #f8f9fa;
        }
        .grade-input {
            width: 80px;
            padding: 8px;
            border: 2px solid #e0e0e0;
            border-radius: 4px;
            text-align: center;
            font-weight: 600;
            transition: all 0.3s;
        }
        .grade-input:focus {
            border-color: #2b87ff;
            outline: none;
            box-shadow: 0 0 0 3px rgba(43,135,255,0.1);
        }
        .grade-input.has-value {
            background: #e8f5e9;
            border-color: #4caf50;
        }
        .final-grade {
            font-weight: bold;
            font-size: 1.1rem;
        }
        .final-grade.excellent {
            color: #7c3aed;
        }
        .final-grade.good {
            color: #2563eb;
        }
        .final-grade.average {
            color: #f59e0b;
        }
        .final-grade.pass {
            color: #10b981;
        }
        .final-grade.fail {
            color: #dc2626;
        }
        .action-bar {
            padding: 20px;
            background: #f8f9fa;
            border-top: 2px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2b87ff;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .stats-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        .stat-item {
            background: white;
            padding: 15px 20px;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .stat-label {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 5px;
        }
        .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #333;
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
            <a href="../student/list.php">Quản lý sinh viên</a>
            <a href="../subject/list.php">Quản lý môn học</a>
        </div>

        <div class="nav-section">
            <div class="nav-header">Học tập</div>
            <a href="input.php" class="active">Nhập điểm</a>
            <a href="../schedule/index.php">Thời khóa biểu</a>
            <a href="../schedule/lichthi.php">Lịch thi</a>
        </div>

        <div class="nav-section">
            <a href="../logout.php">Đăng xuất</a>
        </div>
    </div>

    <div class="main">
        <div class="actions">
            <h1> Nhập điểm sinh viên</h1>
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

        <div class="info-box">
            <strong> Hướng dẫn:</strong> Chọn môn học để nhập điểm. 
            Điểm giữa kỳ và cuối kỳ từ 0-10. Điểm tổng kết = Giữa kỳ × 40% + Cuối kỳ × 60%.
        </div>

        <!-- Subject Selection -->
        <h3>Chọn môn học (Học kỳ <?php echo $current_semester; ?> - <?php echo $current_year; ?>)</h3>
        <div class="subject-grid">
            <?php while ($subject = mysqli_fetch_assoc($subjects)): ?>
            <a href="?subject_id=<?php echo $subject['id']; ?>" style="text-decoration: none; color: inherit;">
                <div class="subject-card <?php echo $selected_subject_id == $subject['id'] ? 'selected' : ''; ?>">
                    <div class="subject-code"><?php echo htmlspecialchars($subject['subject_code']); ?></div>
                    <div class="subject-name"><?php echo htmlspecialchars($subject['subject_name']); ?></div>
                    <div class="subject-meta">
                        <span> <?php echo $subject['credits']; ?> tín chỉ</span>
                        <span> <?php echo $subject['student_count']; ?> sinh viên</span>
                    </div>
                </div>
            </a>
            <?php endwhile; ?>
        </div>

        <!-- Grade Input Form -->
        <?php if ($selected_subject_id && $students && mysqli_num_rows($students) > 0): ?>
        <form method="post" action="input.php" id="gradeForm">
            <input type="hidden" name="subject_id" value="<?php echo $selected_subject_id; ?>">
            
            <div class="grade-form">
                <div class="grade-header">
                    <h2><?php echo htmlspecialchars($selected_subject_info['subject_name']); ?></h2>
                    <p>Mã: <?php echo htmlspecialchars($selected_subject_info['subject_code']); ?> | 
                       <?php echo mysqli_num_rows($students); ?> sinh viên</p>
                </div>

                <table class="grade-table">
                    <thead>
                        <tr>
                            <th style="width: 80px;">STT</th>
                            <th style="width: 120px;">MSSV</th>
                            <th>Họ và tên</th>
                            <th>Lớp</th>
                            <th style="width: 120px; text-align: center;">Điểm giữa kì<br>(40%)</th>
                            <th style="width: 120px; text-align: center;">Điểm cuối kì<br>(60%)</th>
                            <th style="width: 120px; text-align: center;">Điểm tổng kết</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $stt = 1;
                        $total_midterm = 0;
                        $total_final = 0;
                        $count_graded = 0;
                        
                        mysqli_data_seek($students, 0);
                        while ($student = mysqli_fetch_assoc($students)): 
                            $final_score = null;
                            if ($student['midterm_grade'] !== null && $student['final_grade'] !== null) {
                                $final_score = $student['midterm_grade'] * 0.4 + $student['final_grade'] * 0.6;
                                $total_midterm += $student['midterm_grade'];
                                $total_final += $student['final_grade'];
                                $count_graded++;
                            }
                            
                            $grade_class = '';
                            if ($final_score !== null) {
                                if ($final_score >= 8.5) $grade_class = 'excellent';
                                elseif ($final_score >= 7.0) $grade_class = 'good';
                                elseif ($final_score >= 5.5) $grade_class = 'average';
                                elseif ($final_score >= 4.0) $grade_class = 'pass';
                                else $grade_class = 'fail';
                            }
                        ?>
                        <tr>
                            <td style="text-align: center;"><?php echo $stt++; ?></td>
                            <td><strong><?php echo htmlspecialchars($student['student_code']); ?></strong></td>
                            <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($student['class_name'] ?? 'N/A'); ?></td>
                            <td style="text-align: center;">
                                <input type="number" 
                                       name="grades[<?php echo $student['id']; ?>][midterm]" 
                                       class="grade-input <?php echo $student['midterm_grade'] !== null ? 'has-value' : ''; ?>"
                                       value="<?php echo $student['midterm_grade'] !== null ? number_format($student['midterm_grade'], 1) : ''; ?>"
                                       min="0" max="10" step="0.1"
                                       placeholder="0-10"
                                       onchange="calculateFinal(this)">
                            </td>
                            <td style="text-align: center;">
                                <input type="number" 
                                       name="grades[<?php echo $student['id']; ?>][final]" 
                                       class="grade-input <?php echo $student['final_grade'] !== null ? 'has-value' : ''; ?>"
                                       value="<?php echo $student['final_grade'] !== null ? number_format($student['final_grade'], 1) : ''; ?>"
                                       min="0" max="10" step="0.1"
                                       placeholder="0-10"
                                       onchange="calculateFinal(this)">
                            </td>
                            <td style="text-align: center;">
                                <span class="final-grade <?php echo $grade_class; ?>" data-row="<?php echo $student['id']; ?>">
                                    <?php echo $final_score !== null ? number_format($final_score, 2) : '-'; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>

                <div class="action-bar">
                    <div class="stats-row">
                        <div class="stat-item">
                            <div class="stat-label">ĐTB Cuối kỳ</div>
                            <div class="stat-value" style="color: #7c3aed;">
                                <?php echo $count_graded > 0 ? number_format($total_final / $count_graded, 2) : '-'; ?>
                            </div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">Đã nhập điểm</div>
                            <div class="stat-value" style="color: #10b981;">
                                <?php echo $count_graded; ?>/<?php echo mysqli_num_rows($students); ?>
                            </div>
                        </div>
                    </div>
                    <div>
                        <button type="submit" name="save_grades" class="btn btn-primary" style="font-size: 1.1rem; padding: 12px 30px;">
                             Lưu tất cả điểm
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <script>
        function calculateFinal(input) {
            const row = input.closest('tr');
            const midtermInput = row.querySelector('input[name*="[midterm]"]');
            const finalInput = row.querySelector('input[name*="[final]"]');
            const studentId = midtermInput.name.match(/\[(\d+)\]/)[1];
            const finalGradeSpan = row.querySelector(`span[data-row="${studentId}"]`);
            
            const midterm = parseFloat(midtermInput.value) || 0;
            const final = parseFloat(finalInput.value) || 0;
            
            if (midtermInput.value && finalInput.value) {
                const finalScore = midterm * 0.4 + final * 0.6;
                finalGradeSpan.textContent = finalScore.toFixed(2);
                
                // Update color
                finalGradeSpan.className = 'final-grade';
                if (finalScore >= 8.5) finalGradeSpan.classList.add('excellent');
                else if (finalScore >= 7.0) finalGradeSpan.classList.add('good');
                else if (finalScore >= 5.5) finalGradeSpan.classList.add('average');
                else if (finalScore >= 4.0) finalGradeSpan.classList.add('pass');
                else finalGradeSpan.classList.add('fail');
                
                // Add has-value class
                if (midtermInput.value) midtermInput.classList.add('has-value');
                if (finalInput.value) finalInput.classList.add('has-value');
            } else {
                finalGradeSpan.textContent = '-';
                finalGradeSpan.className = 'final-grade';
            }
        }

        // Auto-save warning
        window.addEventListener('beforeunload', function(e) {
            const form = document.getElementById('gradeForm');
            const inputs = form.querySelectorAll('input[type="number"]');
            let hasChanges = false;
            
            inputs.forEach(input => {
                if (input.value && input.defaultValue !== input.value) {
                    hasChanges = true;
                }
            });
            
            if (hasChanges) {
                e.preventDefault();
                e.returnValue = 'Bạn có thay đổi chưa lưu. Bạn có chắc muốn rời khỏi trang?';
            }
        });
        </script>

        <?php elseif ($selected_subject_id): ?>
        <div class="grade-form">
            <div style="text-align: center; padding: 60px 20px; color: #999;">
                <div style="font-size: 4rem; margin-bottom: 20px;">📭</div>
                <h3>Chưa có sinh viên đăng ký</h3>
                <p>Môn học này chưa có sinh viên nào đăng ký.</p>
            </div>
        </div>
        <?php endif; ?>

        <div style="margin-top: 20px;">
            <a href="../dashboard.php" class="btn btn-secondary">Quay lại Trang chủ</a>
        </div>
    </div>
</body>
</html>
