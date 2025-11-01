<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

require_once '../connect.php';

// Get statistics
$stats = [
    'total_students' => 0,
    'paid_students' => 0,
    'partial_students' => 0,
    'unpaid_students' => 0,
    'total_tuition' => 0,
    'total_paid' => 0,
    'total_outstanding' => 0
];

// Total students
$total_query = "SELECT COUNT(*) as total_students FROM students";
$total_result = mysqli_query($connection, $total_query);
if ($total_result) {
    $stats['total_students'] = mysqli_fetch_assoc($total_result)['total_students'];
}

// Payment status statistics
$status_query = "SELECT 
    COUNT(DISTINCT CASE WHEN tf.status = 'paid' THEN s.id END) as paid_students,
    COUNT(DISTINCT CASE WHEN tf.status = 'unpaid' THEN s.id END) as unpaid_students,
    SUM(tf.amount) as total_tuition,
    SUM(CASE WHEN tf.status = 'paid' THEN tf.amount ELSE 0 END) as total_paid
FROM students s
LEFT JOIN tuition_fees tf ON s.id = tf.student_id";

$status_result = mysqli_query($connection, $status_query);
if ($status_result) {
    $status_data = mysqli_fetch_assoc($status_result);
    $stats['paid_students'] = $status_data['paid_students'] ?? 0;
    $stats['unpaid_students'] = $status_data['unpaid_students'] ?? 0;
    $stats['total_tuition'] = $status_data['total_tuition'] ?? 0;
    $stats['total_paid'] = $status_data['total_paid'] ?? 0;
    $stats['total_outstanding'] = $stats['total_tuition'] - $stats['total_paid'];
}

// Payment by department
$dept_query = "SELECT 
    d.department_name,
    d.department_code,
    COUNT(DISTINCT s.id) as student_count,
    SUM(tf.amount) as total_tuition,
    SUM(CASE WHEN tf.status = 'paid' THEN tf.amount ELSE 0 END) as total_paid,
    SUM(CASE WHEN tf.status = 'unpaid' THEN tf.amount ELSE 0 END) as outstanding
FROM departments d
LEFT JOIN students s ON d.id = s.department_id
LEFT JOIN tuition_fees tf ON s.id = tf.student_id
GROUP BY d.id, d.department_name, d.department_code
ORDER BY d.department_name";

$dept_result = mysqli_query($connection, $dept_query);
$dept_stats = [];
if ($dept_result) {
    while ($row = mysqli_fetch_assoc($dept_result)) {
        $dept_stats[] = $row;
    }
}

// Payment by class
$class_query = "SELECT 
    c.class_code,
    c.class_name,
    d.department_code,
    COUNT(DISTINCT s.id) as student_count,
    SUM(tf.amount) as total_tuition,
    SUM(CASE WHEN tf.status = 'paid' THEN tf.amount ELSE 0 END) as total_paid,
    SUM(CASE WHEN tf.status = 'unpaid' THEN tf.amount ELSE 0 END) as outstanding,
    COUNT(DISTINCT CASE WHEN tf.status = 'paid' THEN s.id END) as paid_count,
    COUNT(DISTINCT CASE WHEN tf.status = 'unpaid' THEN s.id END) as unpaid_count
FROM classes c
LEFT JOIN students s ON c.id = s.class_id
LEFT JOIN tuition_fees tf ON s.id = tf.student_id
LEFT JOIN departments d ON c.department_id = d.id
GROUP BY c.id, c.class_code, c.class_name, d.department_code
ORDER BY c.class_code";

$class_result = mysqli_query($connection, $class_query);
$class_stats = [];
if ($class_result) {
    while ($row = mysqli_fetch_assoc($class_result)) {
        $class_stats[] = $row;
    }
}

// Recent payment history
$payment_query = "SELECT 
    s.student_code,
    s.full_name,
    c.class_code,
    ph.payment_date,
    ph.amount,
    ph.payment_method
FROM payment_history ph
JOIN tuition_fees tf ON ph.tuition_fee_id = tf.id
JOIN students s ON tf.student_id = s.id
LEFT JOIN classes c ON s.class_id = c.id
ORDER BY ph.payment_date DESC
LIMIT 20";

$payment_result = mysqli_query($connection, $payment_query);
$recent_payments = [];
if ($payment_result) {
    while ($row = mysqli_fetch_assoc($payment_result)) {
        $recent_payments[] = $row;
    }
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thống kê Học phí - QLSV</title>
    <link rel="stylesheet" href="../css/chung.css?v=3">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .stat-card h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #666;
            font-weight: normal;
        }

        .stat-card .stat-value {
            font-size: 28px;
            font-weight: bold;
            color: #2b87ff;
            margin-bottom: 5px;
        }

        .stat-card .stat-label {
            font-size: 12px;
            color: #999;
        }

        .stat-card.success .stat-value {
            color: #10b981;
        }

        .stat-card.warning .stat-value {
            color: #f59e0b;
        }

        .stat-card.danger .stat-value {
            color: #ef4444;
        }

        .section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .section h2 {
            margin: 0 0 20px 0;
            font-size: 18px;
            color: #333;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table th,
        table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        table th {
            background-color: #f9fafb;
            font-weight: 600;
            color: #374151;
            font-size: 14px;
        }

        table tr:hover {
            background-color: #f9fafb;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-paid {
            background-color: #d1fae5;
            color: #065f46;
        }

        .status-partial {
            background-color: #fed7aa;
            color: #92400e;
        }

        .status-unpaid {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .progress-bar {
            width: 100%;
            height: 20px;
            background-color: #e5e7eb;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 5px;
        }

        .progress-fill {
            height: 100%;
            background-color: #10b981;
            transition: width 0.3s ease;
        }

        .export-buttons {
            margin-bottom: 20px;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #2b87ff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-secondary {
            background-color: #6b7280;
        }

        .btn:hover {
            opacity: 0.9;
        }

        @media print {
            .sidebar,
            .export-buttons,
            .btn {
                display: none;
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
            <a href="grades.php">Thống kê điểm số</a>
            <a href="tuition.php" class="active">Thống kê học phí</a>
        </div>

        <div class="nav-section">
            <a href="../logout.php">Đăng xuất</a>
        </div>
    </div>

    <div class="main">
        <h1> Thống kê Học phí</h1>

        <!-- Main Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Tổng số sinh viên</h3>
                <div class="stat-value"><?php echo number_format($stats['total_students'] ?? 0); ?></div>
                <div class="stat-label">Đang theo học</div>
            </div>
            <div class="stat-card success">
                <h3>Đã đóng đủ</h3>
                <div class="stat-value"><?php echo number_format($stats['paid_students'] ?? 0); ?></div>
                <div class="stat-label">
                    <?php 
                    $paid_percent = $stats['total_students'] > 0 ? 
                        round(($stats['paid_students'] ?? 0) / $stats['total_students'] * 100, 1) : 0;
                    echo $paid_percent . '%';
                    ?>
                </div>
            </div>
            <div class="stat-card warning">
                <h3>Có học phí</h3>
                <div class="stat-value"><?php 
                    // Students who have tuition records
                    $has_tuition = ($stats['paid_students'] ?? 0) + ($stats['unpaid_students'] ?? 0);
                    echo number_format($has_tuition); 
                ?></div>
                <div class="stat-label">
                    <?php 
                    $has_tuition_percent = $stats['total_students'] > 0 ? 
                        round($has_tuition / $stats['total_students'] * 100, 1) : 0;
                    echo $has_tuition_percent . '%';
                    ?>
                </div>
            </div>
            <div class="stat-card danger">
                <h3>Chưa đóng</h3>
                <div class="stat-value"><?php echo number_format($stats['unpaid_students'] ?? 0); ?></div>
                <div class="stat-label">
                    <?php 
                    $unpaid_percent = $stats['total_students'] > 0 ? 
                        round(($stats['unpaid_students'] ?? 0) / $stats['total_students'] * 100, 1) : 0;
                    echo $unpaid_percent . '%';
                    ?>
                </div>
            </div>
        </div>

        <!-- Financial Overview -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Tổng học phí</h3>
                <div class="stat-value"><?php echo number_format($stats['total_tuition'] ?? 0); ?> ₫</div>
                <div class="stat-label">Học kỳ hiện tại</div>
            </div>
            <div class="stat-card success">
                <h3>Đã thu</h3>
                <div class="stat-value"><?php echo number_format($stats['total_paid'] ?? 0); ?> ₫</div>
                <div class="stat-label">
                    <?php 
                    $paid_money_percent = $stats['total_tuition'] > 0 ? 
                        round(($stats['total_paid'] ?? 0) / $stats['total_tuition'] * 100, 1) : 0;
                    echo $paid_money_percent . '%';
                    ?>
                </div>
            </div>
            <div class="stat-card danger">
                <h3>Còn nợ</h3>
                <div class="stat-value"><?php echo number_format($stats['total_outstanding'] ?? 0); ?> ₫</div>
                <div class="stat-label">
                    <?php 
                    $outstanding_percent = $stats['total_tuition'] > 0 ? 
                        round(($stats['total_outstanding'] ?? 0) / $stats['total_tuition'] * 100, 1) : 0;
                    echo $outstanding_percent . '%';
                    ?>
                </div>
            </div>
        </div>

        <!-- Statistics by Department -->
        <div class="section">
            <h2> Thống kê theo Khoa</h2>
            <table>
                <thead>
                    <tr>
                        <th>Khoa</th>
                        <th>Số SV</th>
                        <th>Tổng học phí</th>
                        <th>Đã thu</th>
                        <th>Còn nợ</th>
                        <th>Tỷ lệ thu</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dept_stats as $dept): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($dept['department_code']); ?></strong><br>
                            <small style="color: #666;"><?php echo htmlspecialchars($dept['department_name']); ?></small>
                        </td>
                        <td><?php echo number_format($dept['student_count']); ?></td>
                        <td><?php echo number_format($dept['total_tuition'] ?? 0); ?> ₫</td>
                        <td style="color: #10b981;"><?php echo number_format($dept['total_paid'] ?? 0); ?> ₫</td>
                        <td style="color: #ef4444;"><?php echo number_format($dept['outstanding'] ?? 0); ?> ₫</td>
                        <td>
                            <?php 
                            $dept_percent = $dept['total_tuition'] > 0 ? 
                                round(($dept['total_paid'] ?? 0) / $dept['total_tuition'] * 100, 1) : 0;
                            ?>
                            <div><?php echo $dept_percent; ?>%</div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $dept_percent; ?>%;"></div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Statistics by Class -->
        <div class="section">
            <h2> Thống kê theo Lớp học</h2>
            <table>
                <thead>
                    <tr>
                        <th>Lớp</th>
                        <th>Khoa</th>
                        <th>Số SV</th>
                        <th>Đã đóng</th>
                        <th>Chưa đóng</th>
                        <th>Tổng học phí</th>
                        <th>Đã thu</th>
                        <th>Tỷ lệ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($class_stats as $class): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($class['class_code']); ?></strong><br>
                            <small style="color: #666;"><?php echo htmlspecialchars($class['class_name']); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($class['department_code']); ?></td>
                        <td><?php echo number_format($class['student_count']); ?></td>
                        <td style="color: #10b981;"><?php echo number_format($class['paid_count']); ?></td>
                        <td style="color: #ef4444;"><?php echo number_format($class['unpaid_count']); ?></td>
                        <td><?php echo number_format($class['total_tuition'] ?? 0); ?> ₫</td>
                        <td><?php echo number_format($class['total_paid'] ?? 0); ?> ₫</td>
                        <td>
                            <?php 
                            $class_percent = $class['total_tuition'] > 0 ? 
                                round(($class['total_paid'] ?? 0) / $class['total_tuition'] * 100, 1) : 0;
                            echo $class_percent . '%';
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Recent Payments -->
        <div class="section">
            <h2> Lịch sử đóng học phí gần đây</h2>
            <table>
                <thead>
                    <tr>
                        <th>Ngày</th>
                        <th>MSSV</th>
                        <th>Họ tên</th>
                        <th>Lớp</th>
                        <th>Số tiền</th>
                        <th>Phương thức</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent_payments)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: #999;">
                            Chưa có giao dịch nào
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($recent_payments as $payment): ?>
                    <tr>
                        <td><?php echo date('d/m/Y H:i', strtotime($payment['payment_date'])); ?></td>
                        <td><?php echo htmlspecialchars($payment['student_code']); ?></td>
                        <td><?php echo htmlspecialchars($payment['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($payment['class_code'] ?? '-'); ?></td>
                        <td style="color: #10b981; font-weight: bold;">
                            <?php echo number_format($payment['amount']); ?> ₫
                        </td>
                        <td>
                            <?php
                            $method_names = [
                                'cash' => ' Tiền mặt',
                                'bank_transfer' => ' Chuyển khoản',
                                'card' => ' Thẻ',
                                'Cash' => ' Tiền mặt',
                                'Bank Transfer' => ' Chuyển khoản',
                                'Card' => ' Thẻ'
                            ];
                            echo $method_names[$payment['payment_method']] ?? htmlspecialchars($payment['payment_method'] ?? '-');
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
