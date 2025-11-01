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
                  LEFT JOIN departments d ON s.department_id = d.id 
                  WHERE s.user_id = ?";
$stmt = mysqli_prepare($connection, $student_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$student = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$student) {
    die("Không tìm thấy thông tin sinh viên!");
}

// Get tuition fees
$tuition_query = "SELECT 
    tf.id,
    tf.semester,
    tf.academic_year,
    tf.amount,
    tf.status,
    tf.payment_date,
    (SELECT COUNT(*) FROM course_registrations cr 
     WHERE cr.student_id = ? 
     AND cr.semester = tf.semester 
     AND cr.academic_year = tf.academic_year) as registered_courses,
    (SELECT SUM(subj.credits) FROM course_registrations cr 
     JOIN subjects subj ON cr.subject_id = subj.id
     WHERE cr.student_id = ? 
     AND cr.semester = tf.semester 
     AND cr.academic_year = tf.academic_year) as total_credits
FROM tuition_fees tf
WHERE tf.student_id = ?
ORDER BY tf.academic_year DESC, tf.semester DESC";
$stmt = mysqli_prepare($connection, $tuition_query);
mysqli_stmt_bind_param($stmt, "iii", $student['id'], $student['id'], $student['id']);
mysqli_stmt_execute($stmt);
$tuition_fees = mysqli_stmt_get_result($stmt);

// Calculate totals
$total_amount = 0;
$total_paid = 0;
$total_unpaid = 0;
$fee_count = mysqli_num_rows($tuition_fees);

mysqli_data_seek($tuition_fees, 0);
while ($fee = mysqli_fetch_assoc($tuition_fees)) {
    $total_amount += $fee['amount'];
    if ($fee['status'] === 'paid') {
        $total_paid += $fee['amount'];
    } else {
        $total_unpaid += $fee['amount'];
    }
}

// Check if need to generate tuition fee for current semester
$current_semester = 1;
$current_year = 2025;

$check_current = "SELECT id FROM tuition_fees 
                  WHERE student_id = ? AND semester = ? AND academic_year = ?";
$stmt = mysqli_prepare($connection, $check_current);
mysqli_stmt_bind_param($stmt, "iii", $student['id'], $current_semester, $current_year);
mysqli_stmt_execute($stmt);
$has_current_fee = mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0;

if (!$has_current_fee) {
    // Auto-generate tuition fee based on registered courses
    $credits_query = "SELECT SUM(subj.credits) as total_credits
                     FROM course_registrations cr
                     JOIN subjects subj ON cr.subject_id = subj.id
                     WHERE cr.student_id = ? AND cr.semester = ? AND cr.academic_year = ?";
    $stmt = mysqli_prepare($connection, $credits_query);
    mysqli_stmt_bind_param($stmt, "iii", $student['id'], $current_semester, $current_year);
    mysqli_stmt_execute($stmt);
    $credits_result = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    $total_credits = $credits_result['total_credits'] ?? 0;
    
    if ($total_credits > 0) {
        $credit_price = 500000; // 500,000 VND per credit
        $amount = $total_credits * $credit_price;
        
        $insert_sql = "INSERT INTO tuition_fees (student_id, semester, academic_year, amount, status) 
                      VALUES (?, ?, ?, ?, 'unpaid')";
        $stmt = mysqli_prepare($connection, $insert_sql);
        mysqli_stmt_bind_param($stmt, "iiid", $student['id'], $current_semester, $current_year, $amount);
        mysqli_stmt_execute($stmt);
    }
}

// Reload tuition fees after potential auto-generation
mysqli_data_seek($tuition_fees, 0);
$tuition_fees = mysqli_query($connection, 
    "SELECT 
        tf.id,
        tf.semester,
        tf.academic_year,
        tf.amount,
        tf.status,
        tf.payment_date,
        (SELECT COUNT(*) FROM course_registrations cr 
         WHERE cr.student_id = {$student['id']} 
         AND cr.semester = tf.semester 
         AND cr.academic_year = tf.academic_year) as registered_courses,
        (SELECT SUM(subj.credits) FROM course_registrations cr 
         JOIN subjects subj ON cr.subject_id = subj.id
         WHERE cr.student_id = {$student['id']} 
         AND cr.semester = tf.semester 
         AND cr.academic_year = tf.academic_year) as total_credits
    FROM tuition_fees tf
    WHERE tf.student_id = {$student['id']}
    ORDER BY tf.academic_year DESC, tf.semester DESC");

// Recalculate totals
$total_amount = 0;
$total_paid = 0;
$total_unpaid = 0;

$fees_array = [];
while ($fee = mysqli_fetch_assoc($tuition_fees)) {
    $fees_array[] = $fee;
    $total_amount += $fee['amount'];
    if ($fee['status'] === 'paid') {
        $total_paid += $fee['amount'];
    } else {
        $total_unpaid += $fee['amount'];
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tình trạng học phí - QLSV</title>
    <link rel="stylesheet" href="../css/chung.css?v=3">
    <style>
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .summary-card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            border-left: 4px solid #2b87ff;
        }
        .summary-card.paid {
            border-left-color: #28a745;
        }
        .summary-card.unpaid {
            border-left-color: #dc3545;
        }
        .summary-title {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        .summary-amount {
            font-size: 2rem;
            font-weight: bold;
            color: #333;
        }
        .fee-list {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        .fee-item {
            padding: 20px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.2s;
        }
        .fee-item:hover {
            background: #f8f9fa;
        }
        .fee-item:last-child {
            border-bottom: none;
        }
        .fee-info {
            flex: 1;
        }
        .fee-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 10px;
        }
        .fee-semester {
            background: #e3f2fd;
            color: #1976d2;
            padding: 6px 14px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .fee-details {
            color: #666;
            font-size: 0.9rem;
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        .fee-amount {
            font-size: 1.5rem;
            font-weight: bold;
            color: #333;
        }
        .fee-status {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
            text-align: center;
            min-width: 120px;
        }
        .status-paid {
            background: #d4edda;
            color: #155724;
        }
        .status-unpaid {
            background: #f8d7da;
            color: #721c24;
        }
        .payment-info {
            margin-top: 8px;
            font-size: 0.85rem;
            color: #28a745;
        }
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2b87ff;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .warning-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        .empty-icon {
            font-size: 4rem;
            margin-bottom: 20px;
        }
        .btn-pay {
            background: #28a745;
            color: white;
            padding: 10px 25px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
        }
        .btn-pay:hover {
            background: #218838;
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
            <a href="../grades/view.php">Xem điểm</a>
            <a href="../schedule/index.php">Thời khóa biểu</a>
            <a href="../schedule/lichthi.php">Lịch thi</a>
        </div>

        <div class="nav-section">
            <div class="nav-header">Học phí</div>
            <a href="status.php" class="active">Tình trạng học phí</a>
            <a href="history.php">Lịch sử đóng học phí</a>
        </div>

        <div class="nav-section">
            <a href="../logout.php">Đăng xuất</a>
        </div>
    </div>

    <div class="main">
        <div class="actions">
            <h1>Tình trạng học phí</h1>
        </div>

        <div class="info-box">
            <strong>Thông tin:</strong> Học phí được tính theo số tín chỉ đăng ký. 
            Đơn giá: <strong>500,000 VNĐ/tín chỉ</strong>
        </div>

        <?php if ($total_unpaid > 0): ?>
        <div class="warning-box">
            <strong>Lưu ý:</strong> Bạn còn <strong><?php echo number_format($total_unpaid); ?> VNĐ</strong> học phí chưa đóng. 
            Vui lòng hoàn thành trước khi thi cuối kỳ!
        </div>
        <?php endif; ?>

        <!-- Summary Cards -->
        <div class="summary-cards">
            <div class="summary-card">
                <div class="summary-title">Tổng học phí</div>
                <div class="summary-amount"><?php echo number_format($total_amount); ?> ₫</div>
            </div>
            <div class="summary-card paid">
                <div class="summary-title">Đã đóng</div>
                <div class="summary-amount" style="color: #28a745;">
                    <?php echo number_format($total_paid); ?> ₫
                </div>
            </div>
            <div class="summary-card unpaid">
                <div class="summary-title">Còn nợ</div>
                <div class="summary-amount" style="color: #dc3545;">
                    <?php echo number_format($total_unpaid); ?> ₫
                </div>
            </div>
        </div>

        <!-- Fee List -->
        <div class="fee-list">
            <div style="padding: 20px; background: #f8f9fa; border-bottom: 2px solid #e0e0e0;">
                <h3 style="margin: 0;">Chi tiết học phí theo học kỳ</h3>
            </div>

            <?php if (count($fees_array) > 0): ?>
                <?php foreach ($fees_array as $fee): ?>
                <div class="fee-item">
                    <div class="fee-info">
                        <div class="fee-header">
                            <span class="fee-semester">
                                Học kỳ <?php echo $fee['semester']; ?> - <?php echo $fee['academic_year']; ?>
                            </span>
                            <span class="fee-status <?php echo $fee['status'] === 'paid' ? 'status-paid' : 'status-unpaid'; ?>">
                                <?php echo $fee['status'] === 'paid' ? '✓ Đã đóng' : '⚠ Chưa đóng'; ?>
                            </span>
                        </div>
                        <div class="fee-details">
                            <span><?php echo $fee['registered_courses']; ?> môn học</span>
                            <span><?php echo $fee['total_credits'] ?? 0; ?> tín chỉ</span>
                            <?php if ($fee['status'] === 'paid' && $fee['payment_date']): ?>
                            <span class="payment-info">
                                Đã đóng ngày <?php echo date('d/m/Y', strtotime($fee['payment_date'])); ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <div class="fee-amount"><?php echo number_format($fee['amount']); ?> ₫</div>
                        <?php if ($fee['status'] === 'unpaid'): ?>
                        <button class="btn-pay" onclick="alert('Tính năng thanh toán trực tuyến đang được phát triển!\n\nVui lòng đóng học phí tại phòng Tài vụ.')">
                           Đóng học phí
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon"></div>
                    <h3>Chưa có thông tin học phí</h3>
                    <p>Bạn chưa có học phí nào được tạo.</p>
                    <p style="color: #666;">Học phí sẽ tự động tạo khi bạn đăng ký môn học.</p>
                </div>
            <?php endif; ?>
        </div>

        <div style="margin-top: 20px;">
            <a href="history.php" class="btn btn-secondary">Xem lịch sử đóng học phí</a>
            <a href="../dashboard.php" class="btn btn-secondary">Quay lại Trang chủ</a>
        </div>
    </div>
</body>
</html>
