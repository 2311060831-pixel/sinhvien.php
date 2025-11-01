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

// Get departments for dropdown
$departments = mysqli_query($connection, "SELECT * FROM departments ORDER BY department_name");

// Default values
$teacher = [
    'teacher_code' => '',
    'full_name' => '',
    'department_id' => '',
    'username' => '',
    'email' => '',
    'password' => ''
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $teacher = [
        'teacher_code' => strtoupper(mysqli_real_escape_string($connection, $_POST['teacher_code'])),
        'full_name' => mysqli_real_escape_string($connection, $_POST['full_name']),
        'department_id' => !empty($_POST['department_id']) ? intval($_POST['department_id']) : null,
        'username' => mysqli_real_escape_string($connection, $_POST['username']),
        'email' => mysqli_real_escape_string($connection, $_POST['email']),
        'password' => $_POST['password'] ?? ''
    ];

    try {
        mysqli_begin_transaction($connection);
        
        if (!empty($_POST['id'])) {
            // Update existing teacher
            $teacher_id = intval($_POST['id']);
            
            // Get current user_id
            $sql = "SELECT user_id FROM teachers WHERE id = ?";
            $stmt = mysqli_prepare($connection, $sql);
            mysqli_stmt_bind_param($stmt, "i", $teacher_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $current_teacher = mysqli_fetch_assoc($result);
            $user_id = $current_teacher['user_id'];
            
            // Update user info
            if (!empty($teacher['password'])) {
                $sql = "UPDATE users SET username = ?, email = ?, password = ? WHERE id = ?";
                $stmt = mysqli_prepare($connection, $sql);
                mysqli_stmt_bind_param($stmt, "sssi", 
                    $teacher['username'],
                    $teacher['email'],
                    $teacher['password'],
                    $user_id
                );
            } else {
                $sql = "UPDATE users SET username = ?, email = ? WHERE id = ?";
                $stmt = mysqli_prepare($connection, $sql);
                mysqli_stmt_bind_param($stmt, "ssi", 
                    $teacher['username'],
                    $teacher['email'],
                    $user_id
                );
            }
            mysqli_stmt_execute($stmt);
            
            // Update teacher info
            if ($teacher['department_id']) {
                $sql = "UPDATE teachers SET 
                        teacher_code = ?, 
                        full_name = ?, 
                        department_id = ?
                        WHERE id = ?";
                $stmt = mysqli_prepare($connection, $sql);
                mysqli_stmt_bind_param($stmt, "ssii", 
                    $teacher['teacher_code'],
                    $teacher['full_name'],
                    $teacher['department_id'],
                    $teacher_id
                );
            } else {
                $sql = "UPDATE teachers SET 
                        teacher_code = ?, 
                        full_name = ?, 
                        department_id = NULL
                        WHERE id = ?";
                $stmt = mysqli_prepare($connection, $sql);
                mysqli_stmt_bind_param($stmt, "ssi", 
                    $teacher['teacher_code'],
                    $teacher['full_name'],
                    $teacher_id
                );
            }
            mysqli_stmt_execute($stmt);
            
            mysqli_commit($connection);
            header('Location: list.php?success=' . urlencode('Cập nhật giảng viên thành công!'));
            exit();
        } else {
            // Insert new teacher
            // Check if username exists
            $check_sql = "SELECT id FROM users WHERE username = ?";
            $stmt = mysqli_prepare($connection, $check_sql);
            mysqli_stmt_bind_param($stmt, "s", $teacher['username']);
            mysqli_stmt_execute($stmt);
            if (mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0) {
                throw new Exception("Tên đăng nhập đã tồn tại!");
            }
            
            // Check if teacher_code exists
            $check_sql = "SELECT id FROM teachers WHERE teacher_code = ?";
            $stmt = mysqli_prepare($connection, $check_sql);
            mysqli_stmt_bind_param($stmt, "s", $teacher['teacher_code']);
            mysqli_stmt_execute($stmt);
            if (mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0) {
                throw new Exception("Mã giảng viên đã tồn tại!");
            }
            
            // Insert user account
            $sql = "INSERT INTO users (username, password, role, email) VALUES (?, ?, 'teacher', ?)";
            $stmt = mysqli_prepare($connection, $sql);
            mysqli_stmt_bind_param($stmt, "sss", 
                $teacher['username'],
                $teacher['password'],
                $teacher['email']
            );
            mysqli_stmt_execute($stmt);
            $user_id = mysqli_insert_id($connection);
            
            // Insert teacher
            if ($teacher['department_id']) {
                $sql = "INSERT INTO teachers (teacher_code, user_id, full_name, department_id) 
                        VALUES (?, ?, ?, ?)";
                $stmt = mysqli_prepare($connection, $sql);
                mysqli_stmt_bind_param($stmt, "sisi", 
                    $teacher['teacher_code'],
                    $user_id,
                    $teacher['full_name'],
                    $teacher['department_id']
                );
            } else {
                $sql = "INSERT INTO teachers (teacher_code, user_id, full_name) 
                        VALUES (?, ?, ?)";
                $stmt = mysqli_prepare($connection, $sql);
                mysqli_stmt_bind_param($stmt, "sis", 
                    $teacher['teacher_code'],
                    $user_id,
                    $teacher['full_name']
                );
            }
            mysqli_stmt_execute($stmt);
            
            mysqli_commit($connection);
            header('Location: list.php?success=' . urlencode('Thêm giảng viên thành công!'));
            exit();
        }
    } catch (Exception $e) {
        mysqli_rollback($connection);
        $error = $e->getMessage();
    }
}

// Get teacher data for editing
if ($id) {
    $sql = "SELECT t.*, u.username, u.email 
            FROM teachers t 
            JOIN users u ON t.user_id = u.id 
            WHERE t.id = ?";
    $stmt = mysqli_prepare($connection, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($result)) {
        $teacher = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $id ? 'Sửa giảng viên' : 'Thêm giảng viên'; ?> - QLSV</title>
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
            <a href="list.php">Quản lý giảng viên</a>
            <a href="../subject/list.php">Quản lý môn học</a>
        </div>

        <div class="nav-section">
            <div class="nav-header">Học tập</div>
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
            <h1><?php echo $id ? '✏️ Sửa giảng viên' : ' Thêm giảng viên'; ?></h1>
        </div>

        <div class="form-container">
            <?php if ($error): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>

            <?php if (!$id): ?>
            <div class="info-box">
                <strong>Lưu ý:</strong> Mã giảng viên nên theo định dạng: GV001, GV002... 
                Tài khoản đăng nhập sẽ được tạo tự động.
            </div>
            <?php endif; ?>

            <form method="post" action="form.php<?php echo $id ? '?id='.$id : ''; ?>">
                <?php if ($id): ?>
                <input type="hidden" name="id" value="<?php echo $id; ?>">
                <?php endif; ?>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="teacher_code">Mã giảng viên *</label>
                        <input type="text" id="teacher_code" name="teacher_code" 
                               value="<?php echo htmlspecialchars($teacher['teacher_code']); ?>"
                               required 
                               placeholder="VD: GV001"
                               style="text-transform: uppercase;"
                               <?php echo $id ? 'readonly' : ''; ?>>
                        <small style="color: #666;">Mã GV sẽ tự động viết hoa</small>
                    </div>

                    <div class="form-group">
                        <label for="department_id">Khoa</label>
                        <select id="department_id" name="department_id">
                            <option value="">Chưa phân công</option>
                            <?php 
                            mysqli_data_seek($departments, 0);
                            while ($dept = mysqli_fetch_assoc($departments)): 
                            ?>
                            <option value="<?php echo $dept['id']; ?>"
                                    <?php echo ($teacher['department_id'] ?? '') == $dept['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept['department_name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group form-full">
                        <label for="full_name">Họ và tên *</label>
                        <input type="text" id="full_name" name="full_name" 
                               value="<?php echo htmlspecialchars($teacher['full_name']); ?>"
                               required
                               placeholder="VD: Nguyễn Văn A">
                    </div>

                    <div class="form-group">
                        <label for="username">Tên đăng nhập *</label>
                        <input type="text" id="username" name="username" 
                               value="<?php echo htmlspecialchars($teacher['username']); ?>"
                               required
                               placeholder="VD: gv001"
                               <?php echo $id ? 'readonly' : ''; ?>>
                        <?php if ($id): ?>
                        <small style="color: #666;">Không thể thay đổi tên đăng nhập</small>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" 
                               value="<?php echo htmlspecialchars($teacher['email'] ?? ''); ?>"
                               placeholder="VD: teacher@example.com">
                    </div>

                    <div class="form-group form-full">
                        <label for="password">Mật khẩu <?php echo $id ? '(Để trống nếu không đổi)' : '*'; ?></label>
                        <input type="text" id="password" name="password" 
                               placeholder="Nhập mật khẩu"
                               <?php echo !$id ? 'required' : ''; ?>>
                        <small style="color: #666;">
                            <?php if ($id): ?>
                            Chỉ nhập nếu muốn đổi mật khẩu
                            <?php else: ?>
                            Mật khẩu mặc định cho giảng viên mới
                            <?php endif; ?>
                        </small>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="list.php" class="btn btn-secondary">Hủy</a>
                    <button type="submit" class="btn btn-primary">
                        <?php echo $id ? 'Cập nhật' : 'Thêm giảng viên'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
