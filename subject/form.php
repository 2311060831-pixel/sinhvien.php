<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

require_once '../connect.php';

$error = '';
$success = '';
$id = $_GET['id'] ?? null;

// Get teachers for dropdown
$teachers = mysqli_query($connection, "SELECT t.*, d.department_name 
                                       FROM teachers t 
                                       LEFT JOIN departments d ON t.department_id = d.id 
                                       ORDER BY t.full_name");

// Default values
$subject = [
    'subject_code' => '',
    'subject_name' => '',
    'credits' => 3,
    'teacher_id' => ''
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = [
        'subject_code' => strtoupper(mysqli_real_escape_string($connection, $_POST['subject_code'])),
        'subject_name' => mysqli_real_escape_string($connection, $_POST['subject_name']),
        'credits' => intval($_POST['credits']),
        'teacher_id' => !empty($_POST['teacher_id']) ? intval($_POST['teacher_id']) : null
    ];

    try {
        if (!empty($_POST['id'])) {
            // Update existing subject
            if ($subject['teacher_id']) {
                $sql = "UPDATE subjects SET 
                        subject_code = ?, 
                        subject_name = ?, 
                        credits = ?, 
                        teacher_id = ?
                        WHERE id = ?";
                $stmt = mysqli_prepare($connection, $sql);
                mysqli_stmt_bind_param($stmt, "ssiii", 
                    $subject['subject_code'],
                    $subject['subject_name'],
                    $subject['credits'],
                    $subject['teacher_id'],
                    $id
                );
            } else {
                $sql = "UPDATE subjects SET 
                        subject_code = ?, 
                        subject_name = ?, 
                        credits = ?, 
                        teacher_id = NULL
                        WHERE id = ?";
                $stmt = mysqli_prepare($connection, $sql);
                mysqli_stmt_bind_param($stmt, "ssii", 
                    $subject['subject_code'],
                    $subject['subject_name'],
                    $subject['credits'],
                    $id
                );
            }
            mysqli_stmt_execute($stmt);
            $success = 'Môn học đã được cập nhật thành công!';
        } else {
            // Insert new subject
            if ($subject['teacher_id']) {
                $sql = "INSERT INTO subjects (subject_code, subject_name, credits, teacher_id) 
                        VALUES (?, ?, ?, ?)";
                $stmt = mysqli_prepare($connection, $sql);
                mysqli_stmt_bind_param($stmt, "ssii", 
                    $subject['subject_code'],
                    $subject['subject_name'],
                    $subject['credits'],
                    $subject['teacher_id']
                );
            } else {
                $sql = "INSERT INTO subjects (subject_code, subject_name, credits) 
                        VALUES (?, ?, ?)";
                $stmt = mysqli_prepare($connection, $sql);
                mysqli_stmt_bind_param($stmt, "ssi", 
                    $subject['subject_code'],
                    $subject['subject_name'],
                    $subject['credits']
                );
            }
            
            if (mysqli_stmt_execute($stmt)) {
                header('Location: list.php?success=' . urlencode('Môn học đã được thêm thành công!'));
                exit();
            }
        }
    } catch (Exception $e) {
        $error = 'Có lỗi xảy ra: ' . $e->getMessage();
    }
}

// Get subject data for editing
if ($id) {
    $sql = "SELECT * FROM subjects WHERE id = ?";
    $stmt = mysqli_prepare($connection, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($result)) {
        $subject = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $id ? 'Sửa môn học' : 'Thêm môn học mới'; ?> - QLSV</title>
    <link rel="stylesheet" href="../css/chung.css?v=3">
    <style>
        .form-container {
            max-width: 800px;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        .form-full {
            grid-column: 1 / -1;
        }
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
        .form-actions {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .info-box {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 4px solid #2b87ff;
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
            <a href="list.php">Quản lý môn học</a>
        </div>

        <div class="nav-section">
            <div class="nav-header">Học tập</div>
            <a href="../grades/input.php">Nhập điểm</a>
            <a href="../schedule/index.php">Thời khóa biểu</a>
            <a href="../schedule/lichthi.php">Lịch thi</a>
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
        <div class="actions">
            <h1><?php echo $id ? 'Sửa môn học' : 'Thêm môn học mới'; ?></h1>
        </div>

        <div class="form-container">
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

            <?php if (!$id): ?>
            <div class="info-box">
                <strong>Lưu ý:</strong> Mã môn học nên theo định dạng: CTDL001, CSDL001, LTW001...
            </div>
            <?php endif; ?>

            <form method="post" action="form.php<?php echo $id ? '?id='.$id : ''; ?>">
                <?php if ($id): ?>
                <input type="hidden" name="id" value="<?php echo $id; ?>">
                <?php endif; ?>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="subject_code">Mã môn học *</label>
                        <input type="text" id="subject_code" name="subject_code" 
                               value="<?php echo htmlspecialchars($subject['subject_code']); ?>"
                               required 
                               placeholder="VD: CTDL001"
                               style="text-transform: uppercase;">
                        <small style="color: #666;">Mã môn sẽ tự động viết hoa</small>
                    </div>

                    <div class="form-group">
                        <label for="credits">Số tín chỉ *</label>
                        <input type="number" id="credits" name="credits" 
                               value="<?php echo htmlspecialchars($subject['credits']); ?>"
                               min="1" max="10" required>
                    </div>

                    <div class="form-group form-full">
                        <label for="subject_name">Tên môn học *</label>
                        <input type="text" id="subject_name" name="subject_name" 
                               value="<?php echo htmlspecialchars($subject['subject_name']); ?>"
                               required
                               placeholder="VD: Cấu trúc dữ liệu và giải thuật">
                    </div>

                    <div class="form-group form-full">
                        <label for="teacher_id">Giảng viên phụ trách</label>
                        <select id="teacher_id" name="teacher_id">
                            <option value="">Chưa phân công</option>
                            <?php while ($teacher = mysqli_fetch_assoc($teachers)): ?>
                            <option value="<?php echo $teacher['id']; ?>"
                                    <?php echo ($subject['teacher_id'] ?? '') == $teacher['id'] ? 'selected' : ''; ?>>
                                <?php 
                                echo htmlspecialchars($teacher['full_name']); 
                                if ($teacher['department_name']) {
                                    echo ' - ' . htmlspecialchars($teacher['department_name']);
                                }
                                ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                        <small style="color: #666;">Có thể chọn sau</small>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="list.php" class="btn btn-secondary">Hủy</a>
                    <button type="submit" class="btn btn-primary">
                        <?php echo $id ? 'Cập nhật' : 'Thêm môn học'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
