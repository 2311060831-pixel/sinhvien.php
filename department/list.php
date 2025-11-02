<?php
require_once '../connect.php';

// Lấy từ khóa tìm kiếm nếu có
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$sql = "SELECT 
    d.id, 
    d.department_code, 
    d.department_name, 
    COUNT(DISTINCT s.id) AS student_count, 
    COUNT(DISTINCT t.id) AS teacher_count, 
    COUNT(DISTINCT c.id) AS class_count
FROM departments d
LEFT JOIN students s ON d.id = s.department_id
LEFT JOIN teachers t ON d.id = t.department_id
LEFT JOIN classes c ON d.id = c.department_id
WHERE 1=1";

if ($search) {
    $sql .= " AND (d.department_code LIKE '%$search%' OR d.department_name LIKE '%$search%')";
}

$sql .= " GROUP BY d.id, d.department_code, d.department_name ORDER BY d.department_code";

$result = mysqli_query($connection, $sql);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý Khoa</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f7fa;
            margin: 0;
            padding: 0;
        }
        h1 {
            text-align: center;
            color: #333;
            margin: 20px 0;
        }
        .container {
            width: 90%;
            margin: 0 auto;
        }
        .search-box {
            text-align: right;
            margin-bottom: 15px;
        }
        .search-box input {
            padding: 6px;
            width: 200px;
        }
        .search-box button {
            padding: 6px 10px;
            background-color: #3498db;
            color: white;
            border: none;
            cursor: pointer;
        }
        .search-box button:hover {
            background-color: #2980b9;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        th, td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: center;
        }
        th {
            background-color: #eaeaea;
        }
        tr:hover {
            background-color: #f9f9f9;
        }
        .actions a {
            color: #3498db;
            text-decoration: none;
            margin: 0 5px;
        }
        .actions a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Danh sách Khoa</h1>

        <div class="search-box">
            <form method="get">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Tìm mã hoặc tên khoa...">
                <button type="submit">Tìm kiếm</button>
            </form>
        </div>

        <table>
            <tr>
                <th>Mã khoa</th>
                <th>Tên khoa</th>
                <th>Số sinh viên</th>
                <th>Số giảng viên</th>
                <th>Số lớp</th>
                <th>Hành động</th>
            </tr>
            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <tr>
                    <td><?= htmlspecialchars($row['department_code']) ?></td>
                    <td><?= htmlspecialchars($row['department_name']) ?></td>
                    <td><?= $row['student_count'] ?></td>
                    <td><?= $row['teacher_count'] ?></td>
                    <td><?= $row['class_count'] ?></td>
                    <td class="actions">
                        <a href="edit.php?id=<?= $row['id'] ?>">Sửa</a> |
                        <a href="delete.php?id=<?= $row['id'] ?>" onclick="return confirm('Bạn có chắc muốn xóa khoa này?')">Xóa</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>
    </div>
</body>
</html>
