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

// Get payment history
$history_query = "SELECT 
    ph.id,
    ph.amount,
    ph.payment_date,
    ph.payment_method,
    tf.semester,
    tf.academic_year
FROM payment_history ph
JOIN tuition_fees tf ON ph.tuition_fee_id = tf.id
WHERE tf.student_id = ?
ORDER BY ph.payment_date DESC";
$stmt = mysqli_prepare($connection, $history_query);
mysqli_stmt_bind_param($stmt, "i", $student['id']);
mysqli_stmt_execute($stmt);
$payment_history = mysqli_stmt_get_result($stmt);

// Get paid tuition fees
$paid_fees_query = "SELECT 
    tf.id,
    tf.semester,
    tf.academic_year,
    tf.amount,
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
WHERE tf.student_id = ? AND tf.status = 'paid'
ORDER BY tf.academic_year DESC, tf.semester DESC";
$stmt = mysqli_prepare($connection, $paid_fees_query);
mysqli_stmt_bind_param($stmt, "iii", $student['id'], $student['id'], $student['id']);
mysqli_stmt_execute($stmt);
$paid_fees = mysqli_stmt_get_result($stmt);

// Calculate statistics
$total_paid = 0;
$payment_count = mysqli_num_rows($payment_history);

mysqli_data_seek($payment_history, 0);
while ($payment = mysqli_fetch_assoc($payment_history)) {
    $total_paid += $payment['amount'];
}

// Group payments by year
$payments_by_year = [];
mysqli_data_seek($payment_history, 0);
while ($payment = mysqli_fetch_assoc($payment_history)) {
    $year = date('Y', strtotime($payment['payment_date']));
    if (!isset($payments_by_year[$year])) {
        $payments_by_year[$year] = [];
    }
    $payments_by_year[$year][] = $payment;
}
krsort($payments_by_year);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Lịch sử đóng học phí - QLSV</title>
    <link rel="stylesheet" href="../css/chung.css?v=3">
    <style>
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-box {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            border-left: 4px solid #28a745;
        }
        .stat-label {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #28a745;
        }
        .timeline {
            position: relative;
            padding-left: 40px;
        }
        .timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e0e0e0;
        }
        .year-group {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            overflow: hidden;
        }
        .year-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            font-size: 1.3rem;
            font-weight: bold;
        }
        .payment-item {
            padding: 20px;
            border-bottom: 1px solid #f0f0f0;
            position: relative;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.2s;
        }
        .payment-item:hover {
            background: #f8f9fa;
        }
        .payment-item:last-child {
            border-bottom: none;
        }
        .payment-item::before {
            content: '✓';
            position: absolute;
            left: -33px;
            width: 30px;
            height: 30px;
            background: #28a745;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            box-shadow: 0 0 0 4px white;
        }
        .payment-info {
            flex: 1;
        }
        .payment-date {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        .payment-details {
            color: #666;
            font-size: 0.9rem;
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        .payment-amount {
            font-size: 1.5rem;
            font-weight: bold;
            color: #28a745;
        }
        .payment-method {
            display: inline-block;
            background: #e3f2fd;
            color: #1976d2;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .semester-badge {
            display: inline-block;
            background: #fff3e0;
            color: #e65100;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
            background: white;
            border-radius: 8px;
        }
        .empty-icon {
            font-size: 4rem;
            margin-bottom: 20px;
        }
        .receipt-section {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            overflow: hidden;
        }
        .receipt-header {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 2px solid #e0e0e0;
        }
        .receipt-table {
            width: 100%;
        }
        .receipt-table td {
            padding: 15px 20px;
            border-bottom: 1px solid #f0f0f0;
        }
        .receipt-table tr:last-child td {
            border-bottom: none;
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
            <a href="status.php">Tình trạng học phí</a>
            <a href="history.php" class="active">Lịch sử đóng học phí</a>
        </div>

        <div class="nav-section">
            <a href="../logout.php">Đăng xuất</a>
        </div>
    </div>

    <div class="main">
        <h1>Lịch sử đóng học phí</h1>

        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-label">Tổng số tiền đã đóng</div>
                <div class="stat-value"><?php echo number_format($total_paid); ?> ₫</div>
            </div>
            <div class="stat-box" style="border-left-color: #2b87ff;">
                <div class="stat-label">Số lần đóng học phí</div>
                <div class="stat-value" style="color: #2b87ff;">
                    <?php echo $payment_count; ?>
                </div>
            </div>
        </div>

        <!-- Paid Fees Summary -->
        <?php if (mysqli_num_rows($paid_fees) > 0): ?>
        <div class="receipt-section">
            <div class="receipt-header">
                <h3 style="margin: 0;">Tổng hợp học phí đã đóng</h3>
            </div>
            <table class="receipt-table">
                <thead style="background: #f8f9fa;">
                    <tr>
                        <th style="padding: 15px 20px; text-align: left;">Học kỳ</th>
                        <th style="padding: 15px 20px; text-align: center;">Số môn</th>
                        <th style="padding: 15px 20px; text-align: center;">Số tín chỉ</th>
                        <th style="padding: 15px 20px; text-align: right;">Số tiền</th>
                        <th style="padding: 15px 20px; text-align: center;">Ngày đóng</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($fee = mysqli_fetch_assoc($paid_fees)): ?>
                    <tr>
                        <td>
                            <span class="semester-badge">
                                HK <?php echo $fee['semester']; ?> - <?php echo $fee['academic_year']; ?>
                            </span>
                        </td>
                        <td style="text-align: center;"><?php echo $fee['registered_courses']; ?></td>
                        <td style="text-align: center;"><?php echo $fee['total_credits'] ?? 0; ?></td>
                        <td style="text-align: right; font-weight: 600; color: #28a745;">
                            <?php echo number_format($fee['amount']); ?> ₫
                        </td>
                        <td style="text-align: center;">
                            <?php echo date('d/m/Y', strtotime($fee['payment_date'])); ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Payment Timeline -->
        <?php if (!empty($payments_by_year)): ?>
        <h3 style="margin-bottom: 20px;">Timeline thanh toán</h3>
        <div class="timeline">
            <?php foreach ($payments_by_year as $year => $payments): ?>
            <div class="year-group">
                <div class="year-header">
                    Năm <?php echo $year; ?>
                </div>
                <?php foreach ($payments as $payment): ?>
                <div class="payment-item">
                    <div class="payment-info">
                        <div class="payment-date">
                            <?php echo date('d/m/Y - H:i', strtotime($payment['payment_date'])); ?>
                        </div>
                        <div class="payment-details">
                            <span class="semester-badge">
                                Học kỳ <?php echo $payment['semester']; ?> - <?php echo $payment['academic_year']; ?>
                            </span>
                            <?php if ($payment['payment_method']): ?>
                            <span class="payment-method">
                                <?php 
                                $methods = [
                                    'cash' => 'Tiền mặt',
                                    'bank_transfer' => 'Chuyển khoản',
                                    'credit_card' => 'Thẻ tín dụng',
                                    'e_wallet' => 'Ví điện tử'
                                ];
                                echo $methods[$payment['payment_method']] ?? $payment['payment_method'];
                                ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="payment-amount">
                        <?php echo number_format($payment['amount']); ?> ₫
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">📭</div>
            <h3>Chưa có lịch sử đóng học phí</h3>
            <p>Bạn chưa thực hiện thanh toán học phí nào.</p>
            <a href="status.php" class="btn btn-primary" style="margin-top: 20px;">
                Xem tình trạng học phí
            </a>
        </div>
        <?php endif; ?>

        <div style="margin-top: 30px;">
            <a href="status.php" class="btn btn-secondary">Xem tình trạng học phí</a>
            <a href="../dashboard.php" class="btn btn-secondary">Quay lại Trang chủ</a>
        </div>
    </div>
</body>
</html>
