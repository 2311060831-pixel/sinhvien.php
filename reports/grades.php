<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

require_once '../connect.php';

// Get filter parameters
$selected_subject = $_GET['subject_id'] ?? '';
$selected_semester = $_GET['semester'] ?? 1;
$selected_year = $_GET['academic_year'] ?? 2024;

// Calculate overall statistics
$overall_sql = "SELECT 
    COUNT(DISTINCT g.student_id) as total_students_with_grades,
    COUNT(g.id) as total_grade_records,
    AVG((g.midterm_grade * 0.4 + g.final_grade * 0.6)) as overall_avg,
    AVG(g.midterm_grade) as avg_midterm,
    AVG(g.final_grade) as avg_final
FROM grades g
WHERE g.semester = ? AND g.academic_year = ?";
$stmt = mysqli_prepare($connection, $overall_sql);
mysqli_stmt_bind_param($stmt, "ii", $selected_semester, $selected_year);
mysqli_stmt_execute($stmt);
$overall_stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

// Grade distribution (A, B, C, D, F)
$distribution_sql = "SELECT 
    SUM(CASE WHEN final_score >= 8.5 THEN 1 ELSE 0 END) as grade_a,
    SUM(CASE WHEN final_score >= 7.0 AND final_score < 8.5 THEN 1 ELSE 0 END) as grade_b,
    SUM(CASE WHEN final_score >= 5.5 AND final_score < 7.0 THEN 1 ELSE 0 END) as grade_c,
    SUM(CASE WHEN final_score >= 4.0 AND final_score < 5.5 THEN 1 ELSE 0 END) as grade_d,
    SUM(CASE WHEN final_score < 4.0 THEN 1 ELSE 0 END) as grade_f
FROM (
    SELECT (midterm_grade * 0.4 + final_grade * 0.6) as final_score
    FROM grades
    WHERE semester = ? AND academic_year = ?
    AND midterm_grade IS NOT NULL AND final_grade IS NOT NULL
) as scores";
$stmt = mysqli_prepare($connection, $distribution_sql);
mysqli_stmt_bind_param($stmt, "ii", $selected_semester, $selected_year);
mysqli_stmt_execute($stmt);
$grade_dist = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

$total_graded = array_sum($grade_dist);
$pass_rate = $total_graded > 0 ? (($total_graded - $grade_dist['grade_f']) / $total_graded) * 100 : 0;

// Statistics by subject
$subject_stats_sql = "SELECT 
    subj.subject_code,
    subj.subject_name,
    COUNT(g.id) as student_count,
    AVG(g.midterm_grade) as avg_midterm,
    AVG(g.final_grade) as avg_final,
    AVG((g.midterm_grade * 0.4 + g.final_grade * 0.6)) as avg_overall,
    SUM(CASE WHEN (g.midterm_grade * 0.4 + g.final_grade * 0.6) >= 4.0 THEN 1 ELSE 0 END) as pass_count,
    SUM(CASE WHEN (g.midterm_grade * 0.4 + g.final_grade * 0.6) < 4.0 THEN 1 ELSE 0 END) as fail_count
FROM grades g
JOIN subjects subj ON g.subject_id = subj.id
WHERE g.semester = ? AND g.academic_year = ?
AND g.midterm_grade IS NOT NULL AND g.final_grade IS NOT NULL
GROUP BY subj.id
ORDER BY avg_overall DESC";
$stmt = mysqli_prepare($connection, $subject_stats_sql);
mysqli_stmt_bind_param($stmt, "ii", $selected_semester, $selected_year);
mysqli_stmt_execute($stmt);
$subject_stats = mysqli_stmt_get_result($stmt);

// Top students
$top_students_sql = "SELECT 
    s.student_code,
    s.full_name,
    c.class_name,
    d.department_name,
    COUNT(g.id) as subject_count,
    AVG((g.midterm_grade * 0.4 + g.final_grade * 0.6)) as gpa
FROM students s
JOIN grades g ON s.id = g.student_id
LEFT JOIN classes c ON s.class_id = c.id
LEFT JOIN departments d ON s.department_id = d.id
WHERE g.semester = ? AND g.academic_year = ?
AND g.midterm_grade IS NOT NULL AND g.final_grade IS NOT NULL
GROUP BY s.id
HAVING gpa >= 8.0
ORDER BY gpa DESC
LIMIT 10";
$stmt = mysqli_prepare($connection, $top_students_sql);
mysqli_stmt_bind_param($stmt, "ii", $selected_semester, $selected_year);
mysqli_stmt_execute($stmt);
$top_students = mysqli_stmt_get_result($stmt);

// Students at risk (GPA < 2.0)
$risk_students_sql = "SELECT 
    s.student_code,
    s.full_name,
    c.class_name,
    d.department_name,
    COUNT(g.id) as subject_count,
    AVG((g.midterm_grade * 0.4 + g.final_grade * 0.6)) as gpa,
    SUM(CASE WHEN (g.midterm_grade * 0.4 + g.final_grade * 0.6) < 4.0 THEN 1 ELSE 0 END) as failed_subjects
FROM students s
JOIN grades g ON s.id = g.student_id
LEFT JOIN classes c ON s.class_id = c.id
LEFT JOIN departments d ON s.department_id = d.id
WHERE g.semester = ? AND g.academic_year = ?
AND g.midterm_grade IS NOT NULL AND g.final_grade IS NOT NULL
GROUP BY s.id
HAVING gpa < 2.0
ORDER BY gpa ASC";
$stmt = mysqli_prepare($connection, $risk_students_sql);
mysqli_stmt_bind_param($stmt, "ii", $selected_semester, $selected_year);
mysqli_stmt_execute($stmt);
$risk_students = mysqli_stmt_get_result($stmt);

// Get subjects for filter
$subjects = mysqli_query($connection, "SELECT * FROM subjects ORDER BY subject_code");
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Thống kê Điểm số - QLSV</title>
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
            font-size: 0.85rem;
            color: #666;
            text-transform: uppercase;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #333;
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
        .grade-distribution {
            display: flex;
            gap: 20px;
            justify-content: space-around;
            flex-wrap: wrap;
        }
        .grade-item {
            text-align: center;
            padding: 20px;
            border-radius: 8px;
            min-width: 100px;
        }
        .grade-a { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .grade-b { background: linear-gradient(135deg, #00b4db 0%, #0083b0 100%); color: white; }
        .grade-c { background: linear-gradient(135deg, #f2994a 0%, #f2c94c 100%); color: white; }
        .grade-d { background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%); color: white; }
        .grade-f { background: linear-gradient(135deg, #434343 0%, #000000 100%); color: white; }
        .grade-letter {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .grade-count {
            font-size: 1.5rem;
            font-weight: bold;
        }
        .grade-label {
            font-size: 0.85rem;
            opacity: 0.9;
            margin-top: 5px;
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
            min-width: 200px;
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
        }
        .bar-fill.success {
            background: linear-gradient(90deg, #28a745, #20a542);
        }
        .bar-fill.warning {
            background: linear-gradient(90deg, #ffc107, #ff9800);
        }
        .bar-fill.danger {
            background: linear-gradient(90deg, #dc3545, #c82333);
        }
        .student-list {
            display: grid;
            gap: 10px;
        }
        .student-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-left: 4px solid #2b87ff;
        }
        .student-card.warning {
            border-left-color: #dc3545;
            background: #fff5f5;
        }
        .student-info {
            flex: 1;
        }
        .student-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        .student-meta {
            font-size: 0.85rem;
            color: #666;
        }
        .student-gpa {
            font-size: 1.5rem;
            font-weight: bold;
            padding: 10px 20px;
            border-radius: 6px;
            background: white;
        }
        .gpa-excellent { color: #7c3aed; }
        .gpa-good { color: #2563eb; }
        .gpa-average { color: #f59e0b; }
        .gpa-poor { color: #dc2626; }
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
            <a href="students.php">Thống kê sinh viên</a>
            <a href="grades.php" class="active">Thống kê điểm số</a>
            <a href="tuition.php">Thống kê học phí</a>
        </div>

        <div class="nav-section">
            <a href="../logout.php">Đăng xuất</a>
        </div>
    </div>

    <div class="main">
        <h1> Thống kê Điểm số</h1>

        <!-- Filter Section -->
        <div class="filter-section">
            <h3>Chọn học kỳ</h3>
            <form method="get" action="grades.php">
                <div class="filter-grid">
                    <div class="form-group">
                        <label>Học kỳ</label>
                        <select name="semester">
                            <option value="1" <?php echo $selected_semester == 1 ? 'selected' : ''; ?>>Học kỳ 1</option>
                            <option value="2" <?php echo $selected_semester == 2 ? 'selected' : ''; ?>>Học kỳ 2</option>
                            <option value="3" <?php echo $selected_semester == 3 ? 'selected' : ''; ?>>Học kỳ phụ</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Năm học</label>
                        <select name="academic_year">
                            <option value="2024" <?php echo $selected_year == 2024 ? 'selected' : ''; ?>>2024</option>
                            <option value="2023" <?php echo $selected_year == 2023 ? 'selected' : ''; ?>>2023</option>
                            <option value="2022" <?php echo $selected_year == 2022 ? 'selected' : ''; ?>>2022</option>
                        </select>
                    </div>
                    <div class="form-group" style="display: flex; align-items: flex-end;">
                        <button type="submit" class="btn btn-primary">Xem thống kê</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Overall Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Sinh viên có điểm</h3>
                <div class="stat-value"><?php echo number_format($overall_stats['total_students_with_grades']); ?></div>
            </div>
            <div class="stat-card">
                <h3>Điểm TB cuối kỳ</h3>
                <div class="stat-value"><?php echo number_format($overall_stats['avg_final'] ?? 0, 2); ?></div>
            </div>
            <div class="stat-card">
                <h3>Điểm TB tổng kết</h3>
                <div class="stat-value"><?php echo number_format($overall_stats['overall_avg'] ?? 0, 2); ?></div>
            </div>
            <div class="stat-card success">
                <h3>Tỷ lệ đậu</h3>
                <div class="stat-value"><?php echo number_format($pass_rate, 1); ?>%</div>
            </div>
        </div>

        <!-- Grade Distribution -->
        <div class="chart-container">
            <div class="chart-title">Phân bố điểm (A, B, C, D, F)</div>
            <div class="grade-distribution">
                <div class="grade-item grade-a">
                    <div class="grade-letter">A</div>
                    <div class="grade-count"><?php echo $grade_dist['grade_a']; ?></div>
                    <div class="grade-label">8.5 - 10.0 (Xuất sắc)</div>
                    <div class="grade-label"><?php echo $total_graded > 0 ? number_format(($grade_dist['grade_a']/$total_graded)*100, 1) : 0; ?>%</div>
                </div>
                <div class="grade-item grade-b">
                    <div class="grade-letter">B</div>
                    <div class="grade-count"><?php echo $grade_dist['grade_b']; ?></div>
                    <div class="grade-label">7.0 - 8.4 (Giỏi)</div>
                    <div class="grade-label"><?php echo $total_graded > 0 ? number_format(($grade_dist['grade_b']/$total_graded)*100, 1) : 0; ?>%</div>
                </div>
                <div class="grade-item grade-c">
                    <div class="grade-letter">C</div>
                    <div class="grade-count"><?php echo $grade_dist['grade_c']; ?></div>
                    <div class="grade-label">5.5 - 6.9 (Khá)</div>
                    <div class="grade-label"><?php echo $total_graded > 0 ? number_format(($grade_dist['grade_c']/$total_graded)*100, 1) : 0; ?>%</div>
                </div>
                <div class="grade-item grade-d">
                    <div class="grade-letter">D</div>
                    <div class="grade-count"><?php echo $grade_dist['grade_d']; ?></div>
                    <div class="grade-label">4.0 - 5.4 (TB)</div>
                    <div class="grade-label"><?php echo $total_graded > 0 ? number_format(($grade_dist['grade_d']/$total_graded)*100, 1) : 0; ?>%</div>
                </div>
                <div class="grade-item grade-f">
                    <div class="grade-letter">F</div>
                    <div class="grade-count"><?php echo $grade_dist['grade_f']; ?></div>
                    <div class="grade-label">< 4.0 (Rớt)</div>
                    <div class="grade-label"><?php echo $total_graded > 0 ? number_format(($grade_dist['grade_f']/$total_graded)*100, 1) : 0; ?>%</div>
                </div>
            </div>
        </div>

        <!-- Subject Statistics -->
        <div class="chart-container">
            <div class="chart-title">Thống kê theo môn học</div>
            <table>
                <thead>
                    <tr>
                        <th>Mã MH</th>
                        <th>Tên môn học</th>
                        <th>SL SV</th>
                        <th>ĐTB Cuối kỳ</th>
                        <th>ĐTB Tổng</th>
                        <th>Đậu</th>
                        <th>Rớt</th>
                        <th>Tỷ lệ đậu</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($subject = mysqli_fetch_assoc($subject_stats)): 
                        $subject_pass_rate = $subject['student_count'] > 0 
                            ? ($subject['pass_count'] / $subject['student_count']) * 100 
                            : 0;
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($subject['subject_code']); ?></strong></td>
                        <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                        <td style="text-align: center;"><?php echo $subject['student_count']; ?></td>
                        <td style="text-align: center;"><?php echo number_format($subject['avg_final'], 2); ?></td>
                        <td style="text-align: center;"><strong><?php echo number_format($subject['avg_overall'], 2); ?></strong></td>
                        <td style="text-align: center; color: #28a745; font-weight: 600;"><?php echo $subject['pass_count']; ?></td>
                        <td style="text-align: center; color: #dc3545; font-weight: 600;"><?php echo $subject['fail_count']; ?></td>
                        <td style="text-align: center;">
                            <span style="background: <?php echo $subject_pass_rate >= 80 ? '#d4edda' : ($subject_pass_rate >= 60 ? '#fff3cd' : '#f8d7da'); ?>; 
                                         color: <?php echo $subject_pass_rate >= 80 ? '#155724' : ($subject_pass_rate >= 60 ? '#856404' : '#721c24'); ?>; 
                                         padding: 4px 10px; border-radius: 12px; font-weight: 600;">
                                <?php echo number_format($subject_pass_rate, 1); ?>%
                            </span>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Top Students -->
        <div class="chart-container">
            <div class="chart-title"> Top 10 sinh viên xuất sắc (GPA ≥ 8.0)</div>
            <?php if (mysqli_num_rows($top_students) > 0): ?>
            <div class="student-list">
                <?php $rank = 1; ?>
                <?php while ($student = mysqli_fetch_assoc($top_students)): 
                    $gpa = $student['gpa'];
                    $gpa_class = $gpa >= 8.5 ? 'gpa-excellent' : ($gpa >= 8.0 ? 'gpa-good' : 'gpa-average');
                ?>
                <div class="student-card">
                    <div style="font-size: 1.5rem; font-weight: bold; color: #ffc107; min-width: 40px;">
                        <?php echo $rank++; ?>
                    </div>
                    <div class="student-info">
                        <div class="student-name">
                            <?php echo htmlspecialchars($student['full_name']); ?>
                            <span style="color: #999; font-weight: normal; font-size: 0.9rem;">
                                (<?php echo htmlspecialchars($student['student_code']); ?>)
                            </span>
                        </div>
                        <div class="student-meta">
                            <?php echo htmlspecialchars($student['class_name']); ?> - 
                            <?php echo htmlspecialchars($student['department_name']); ?> | 
                            <?php echo $student['subject_count']; ?> môn
                        </div>
                    </div>
                    <div class="student-gpa <?php echo $gpa_class; ?>">
                        <?php echo number_format($gpa, 2); ?>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <p style="text-align: center; color: #999; padding: 20px;">Chưa có sinh viên nào đạt GPA ≥ 8.0</p>
            <?php endif; ?>
        </div>

        <!-- Students at Risk -->
        <div class="chart-container">
            <div class="chart-title"> Sinh viên cần cảnh báo học vụ (GPA < 2.0)</div>
            <?php if (mysqli_num_rows($risk_students) > 0): ?>
            <div class="student-list">
                <?php while ($student = mysqli_fetch_assoc($risk_students)): ?>
                <div class="student-card warning">
                    <div class="student-info">
                        <div class="student-name">
                            <?php echo htmlspecialchars($student['full_name']); ?>
                            <span style="color: #999; font-weight: normal; font-size: 0.9rem;">
                                (<?php echo htmlspecialchars($student['student_code']); ?>)
                            </span>
                        </div>
                        <div class="student-meta">
                            <?php echo htmlspecialchars($student['class_name']); ?> - 
                            <?php echo htmlspecialchars($student['department_name']); ?> | 
                            <?php echo $student['subject_count']; ?> môn | 
                            <span style="color: #dc3545; font-weight: 600;">
                                <?php echo $student['failed_subjects']; ?> môn rớt
                            </span>
                        </div>
                    </div>
                    <div class="student-gpa gpa-poor">
                        <?php echo number_format($student['gpa'], 2); ?>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <p style="text-align: center; color: #28a745; padding: 20px;">
                 Không có sinh viên nào cần cảnh báo học vụ
            </p>
            <?php endif; ?>
        </div>

        <div style="margin-top: 20px;">
            <a href="../dashboard.php" class="btn btn-secondary">Quay lại Trang chủ</a>
        </div>
    </div>
</body>
</html>
