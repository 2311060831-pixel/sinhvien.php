<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit();
}

require_once '../connect.php';

$user_id = $_SESSION['user_id'];

// Get student info
$student_query = "SELECT s.*, c.class_name, d.department_name 
                  FROM students s 
                  LEFT JOIN classes c ON s.class_id = c.id
                  LEFT JOIN departments d ON c.department_id = d.id
                  WHERE s.user_id = ?";
$stmt = mysqli_prepare($connection, $student_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$student = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$student) {
    die("Không tìm thấy thông tin sinh viên!");
}

// Get filter values
$filter_semester = $_GET['semester'] ?? '';
$filter_year = $_GET['year'] ?? '';

// Get all academic years and semesters from grades
$years_query = "SELECT DISTINCT g.academic_year, g.semester
                FROM grades g
                WHERE g.student_id = ?
                ORDER BY g.academic_year DESC, g.semester DESC";
$stmt = mysqli_prepare($connection, $years_query);
mysqli_stmt_bind_param($stmt, "i", $student['id']);
mysqli_stmt_execute($stmt);
$years_result = mysqli_stmt_get_result($stmt);
$available_periods = [];
while ($row = mysqli_fetch_assoc($years_result)) {
    $available_periods[] = $row;
}

// Set default filter to most recent if not specified
if (empty($filter_semester) && empty($filter_year) && !empty($available_periods)) {
    $filter_semester = $available_periods[0]['semester'];
    $filter_year = $available_periods[0]['academic_year'];
}

// Get grades with subject info
$grades_query = "SELECT 
    g.*,
    s.subject_code,
    s.subject_name,
    s.credits,
    t.full_name as teacher_name
FROM grades g
INNER JOIN subjects s ON g.subject_id = s.id
LEFT JOIN teachers t ON s.teacher_id = t.id
WHERE g.student_id = ?";

$params = [$student['id']];
$types = "i";

if ($filter_semester && $filter_year) {
    $grades_query .= " AND g.semester = ? AND g.academic_year = ?";
    $params[] = $filter_semester;
    $params[] = $filter_year;
    $types .= "ii";
}

$grades_query .= " ORDER BY g.academic_year DESC, g.semester DESC, s.subject_code";

$stmt = mysqli_prepare($connection, $grades_query);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$grades = mysqli_stmt_get_result($stmt);

// Calculate GPA and statistics
$total_credits = 0;
$total_grade_points = 0;
$passed_credits = 0;
$failed_count = 0;
$grade_distribution = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'F' => 0];

$grades_array = [];
while ($row = mysqli_fetch_assoc($grades)) {
    $grades_array[] = $row;
    
    if ($row['final_score'] !== null) {
        $total_credits += $row['credits'];
        $total_grade_points += $row['final_score'] * $row['credits'];
        
        if ($row['final_score'] >= 5.0) {
            $passed_credits += $row['credits'];
        } else {
            $failed_count++;
        }
        
        // Grade distribution
        if ($row['final_score'] >= 8.5) $grade_distribution['A']++;
        elseif ($row['final_score'] >= 7.0) $grade_distribution['B']++;
        elseif ($row['final_score'] >= 5.5) $grade_distribution['C']++;
        elseif ($row['final_score'] >= 4.0) $grade_distribution['D']++;
        else $grade_distribution['F']++;
    }
}

$gpa = $total_credits > 0 ? round($total_grade_points / $total_credits, 2) : 0;

// Get letter grade
function getLetterGrade($score) {
    if ($score === null) return '-';
    if ($score >= 8.5) return 'A';
    if ($score >= 7.0) return 'B';
    if ($score >= 5.5) return 'C';
    if ($score >= 4.0) return 'D';
    return 'F';
}

// Get grade color
function getGradeColor($score) {
    if ($score === null) return '#999';
    if ($score >= 8.5) return '#28a745';
    if ($score >= 7.0) return '#17a2b8';
    if ($score >= 5.5) return '#ffc107';
    if ($score >= 4.0) return '#fd7e14';
    return '#dc3545';
}

// Get academic rank
function getAcademicRank($gpa) {
    if ($gpa >= 8.5) return ['Xuất sắc', '#28a745'];
    if ($gpa >= 8.0) return ['Giỏi', '#17a2b8'];
    if ($gpa >= 7.0) return ['Khá', '#20c997'];
    if ($gpa >= 5.5) return ['Trung bình', '#ffc107'];
    if ($gpa >= 4.0) return ['Yếu', '#fd7e14'];
    return ['Kém', '#dc3545'];
}

$rank = getAcademicRank($gpa);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Xem điểm - QLSV</title>
    <link rel="stylesheet" href="../css/chung.css?v=3">
    <style>
        .student-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 8px;
            margin-bottom: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .student-header h2 {
            margin: 0 0 10px 0;
            font-size: 24px;
        }
        .student-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .info-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .info-label {
            opacity: 0.9;
            font-size: 14px;
        }
        .info-value {
            font-weight: 600;
        }
        
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
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 4px solid;
        }
        .stat-card.gpa {
            border-left-color: #667eea;
        }
        .stat-card.credits {
            border-left-color: #28a745;
        }
        .stat-card.passed {
            border-left-color: #17a2b8;
        }
        .stat-card.failed {
            border-left-color: #dc3545;
        }
        .stat-label {
            font-size: 14px;
            color: #6c757d;
            margin-bottom: 8px;
        }
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #2c3e50;
        }
        .stat-rank {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            margin-top: 8px;
            color: white;
        }
        
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .filter-row {
            display: flex;
            gap: 15px;
            align-items: end;
            flex-wrap: wrap;
        }
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        
        .grades-table {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .grades-table table {
            width: 100%;
            border-collapse: collapse;
        }
        .grades-table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #dee2e6;
        }
        .grades-table td {
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
        }
        .grades-table tr:hover {
            background: #f8f9fa;
        }
        .score-cell {
            font-weight: 600;
            font-size: 16px;
        }
        .letter-grade {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-weight: 700;
            font-size: 14px;
            color: white;
            min-width: 35px;
            text-align: center;
        }
        
        .chart-container {
            background: white;
            padding: 25px;
            border-radius: 8px;
            margin-top: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .chart-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #2c3e50;
        }
        .chart-bars {
            display: flex;
            gap: 15px;
            align-items: flex-end;
            height: 200px;
            padding: 0 20px;
        }
        .chart-bar {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }
        .bar {
            width: 100%;
            background: linear-gradient(to top, #667eea, #764ba2);
            border-radius: 4px 4px 0 0;
            position: relative;
            transition: all 0.3s;
        }
        .bar:hover {
            opacity: 0.8;
            transform: translateY(-5px);
        }
        .bar-value {
            position: absolute;
            top: -25px;
            width: 100%;
            text-align: center;
            font-weight: 700;
            color: #2c3e50;
        }
        .bar-label {
            font-weight: 600;
            color: #495057;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .grades-table {
                overflow-x: auto;
            }
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
            <a href="../registration/index.php">Đăng ký môn học</a>
            <a href="view.php" class="active">Xem điểm</a>
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
        <div class="student-header">
            <h2> Kết quả học tập</h2>
            <div class="student-info">
                <div class="info-item">
                    <span class="info-label">MSSV:</span>
                    <span class="info-value"><?php echo htmlspecialchars($student['student_code']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Họ tên:</span>
                    <span class="info-value"><?php echo htmlspecialchars($student['full_name']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Lớp:</span>
                    <span class="info-value"><?php echo htmlspecialchars($student['class_name'] ?? 'Chưa có'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Khoa:</span>
                    <span class="info-value"><?php echo htmlspecialchars($student['department_name'] ?? 'Chưa có'); ?></span>
                </div>
            </div>
        </div>

        <div class="filter-section">
            <form method="get" action="view.php">
                <div class="filter-row">
                    <div class="filter-group">
                        <label>Học kỳ:</label>
                        <select name="semester" class="form-control">
                            <option value="">-- Tất cả học kỳ --</option>
                            <?php
                            $semesters = [];
                            foreach ($available_periods as $period) {
                                $key = $period['semester'];
                                if (!in_array($key, $semesters)) {
                                    $semesters[] = $key;
                                    $selected = ($filter_semester == $key) ? 'selected' : '';
                                    echo "<option value='$key' $selected>Học kỳ $key</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Năm học:</label>
                        <select name="year" class="form-control">
                            <option value="">-- Tất cả năm --</option>
                            <?php
                            $years = [];
                            foreach ($available_periods as $period) {
                                $key = $period['academic_year'];
                                if (!in_array($key, $years)) {
                                    $years[] = $key;
                                    $selected = ($filter_year == $key) ? 'selected' : '';
                                    $year_label = $key . '-' . ($key + 1);
                                    echo "<option value='$key' $selected>$year_label</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary"> Lọc</button>
                    <a href="view.php" class="btn btn-secondary">↺ Đặt lại</a>
                </div>
            </form>
        </div>

        <?php if (!empty($grades_array)): ?>
        <div class="stats-grid">
            <div class="stat-card gpa">
                <div class="stat-label">Điểm trung bình (GPA)</div>
                <div class="stat-value"><?php echo number_format($gpa, 2); ?></div>
                <span class="stat-rank" style="background: <?php echo $rank[1]; ?>">
                    <?php echo $rank[0]; ?>
                </span>
            </div>
            <div class="stat-card credits">
                <div class="stat-label">Tổng tín chỉ tích lũy</div>
                <div class="stat-value"><?php echo $passed_credits; ?></div>
                <small style="color: #6c757d;">/ <?php echo $total_credits; ?> tín chỉ đã học</small>
            </div>
            <div class="stat-card passed">
                <div class="stat-label">Môn đạt</div>
                <div class="stat-value"><?php echo count($grades_array) - $failed_count; ?></div>
                <small style="color: #6c757d;">môn học</small>
            </div>
            <div class="stat-card failed">
                <div class="stat-label">Môn chưa đạt</div>
                <div class="stat-value"><?php echo $failed_count; ?></div>
                <small style="color: #6c757d;">môn học</small>
            </div>
        </div>

        <div class="grades-table">
            <table>
                <thead>
                    <tr>
                        <th style="width: 100px;">Mã môn</th>
                        <th>Tên môn học</th>
                        <th style="width: 80px; text-align: center;">Tín chỉ</th>
                        <th style="width: 150px;">Giảng viên</th>
                        <th style="width: 100px; text-align: center;">Điểm giữa kì</th>
                        <th style="width: 100px; text-align: center;">Điểm cuối kì</th>
                        <th style="width: 100px; text-align: center;">Điểm tổng kết</th>
                        <th style="width: 80px; text-align: center;">Xếp loại</th>
                        <th style="width: 120px;">Học kỳ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($grades_array as $grade): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($grade['subject_code']); ?></strong></td>
                        <td><?php echo htmlspecialchars($grade['subject_name']); ?></td>
                        <td style="text-align: center;"><?php echo $grade['credits']; ?></td>
                        <td><?php echo htmlspecialchars($grade['teacher_name'] ?? 'Chưa phân công'); ?></td>
                        <td style="text-align: center;" class="score-cell">
                            <?php echo $grade['midterm_score'] !== null ? number_format($grade['midterm_score'], 1) : '-'; ?>
                        </td>
                        <td style="text-align: center;" class="score-cell">
                            <?php echo $grade['final_exam_score'] !== null ? number_format($grade['final_exam_score'], 1) : '-'; ?>
                        </td>
                        <td style="text-align: center;" class="score-cell" 
                            style="color: <?php echo getGradeColor($grade['final_score']); ?>">
                            <strong>
                                <?php echo $grade['final_score'] !== null ? number_format($grade['final_score'], 1) : '-'; ?>
                            </strong>
                        </td>
                        <td style="text-align: center;">
                            <span class="letter-grade" 
                                  style="background: <?php echo getGradeColor($grade['final_score']); ?>">
                                <?php echo getLetterGrade($grade['final_score']); ?>
                            </span>
                        </td>
                        <td>
                            HK<?php echo $grade['semester']; ?> / <?php echo $grade['academic_year']; ?>-<?php echo $grade['academic_year'] + 1; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="chart-container">
            <div class="chart-title"> Phân bố xếp loại</div>
            <div class="chart-bars">
                <?php
                $max_count = max($grade_distribution);
                $grade_labels = ['A' => 'Xuất sắc', 'B' => 'Giỏi', 'C' => 'Khá', 'D' => 'Trung bình', 'F' => 'Yếu/Kém'];
                foreach ($grade_distribution as $letter => $count):
                    $height = $max_count > 0 ? ($count / $max_count * 100) : 0;
                ?>
                <div class="chart-bar">
                    <div class="bar" style="height: <?php echo $height; ?>%;">
                        <?php if ($count > 0): ?>
                        <div class="bar-value"><?php echo $count; ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="bar-label"><?php echo $letter; ?></div>
                    <small style="color: #6c757d; font-size: 11px;">
                        <?php echo $grade_labels[$letter]; ?>
                    </small>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon"></div>
            <h3>Chưa có điểm</h3>
            <p>
                <?php if ($filter_semester || $filter_year): ?>
                    Không tìm thấy điểm cho học kỳ đã chọn.
                <?php else: ?>
                    Bạn chưa có điểm nào được nhập vào hệ thống.<br>
                    Vui lòng liên hệ giảng viên hoặc phòng đào tạo để biết thêm chi tiết.
                <?php endif; ?>
            </p>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
