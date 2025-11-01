<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Check if user has admin permission
if ($_SESSION['role'] !== 'admin') {
    header('Location: list.php');
    exit();
}

require_once '../connect.php';

$id = $_GET['id'] ?? null;
$error = '';
$success = '';

if ($id && $connection) {
    try {
        mysqli_begin_transaction($connection);

        // Get student and user IDs
        $sql = "SELECT user_id FROM students WHERE id = ?";
        $stmt = mysqli_prepare($connection, $sql);
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $student = mysqli_fetch_assoc($result);

        if ($student) {
            // Delete related records first
            $tables = [
                'course_registrations',
                'grades',
                'tuition_fees'
            ];

            foreach ($tables as $table) {
                $sql = "DELETE FROM $table WHERE student_id = ?";
                $stmt = mysqli_prepare($connection, $sql);
                mysqli_stmt_bind_param($stmt, "i", $id);
                mysqli_stmt_execute($stmt);
            }

            // Delete student record
            $sql = "DELETE FROM students WHERE id = ?";
            $stmt = mysqli_prepare($connection, $sql);
            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);

            // Delete user account
            if ($student['user_id']) {
                $sql = "DELETE FROM users WHERE id = ?";
                $stmt = mysqli_prepare($connection, $sql);
                mysqli_stmt_bind_param($stmt, "i", $student['user_id']);
                mysqli_stmt_execute($stmt);
            }

            mysqli_commit($connection);
            $success = 'Sinh viên đã được xóa thành công';
        } else {
            $error = 'Không tìm thấy sinh viên';
        }
    } catch (Exception $e) {
        mysqli_rollback($connection);
        $error = 'Có lỗi xảy ra khi xóa sinh viên: ' . $e->getMessage();
    }
}

header('Location: list.php?' . ($error ? 'error=' . urlencode($error) : 'success=' . urlencode($success)));
exit();
?>
