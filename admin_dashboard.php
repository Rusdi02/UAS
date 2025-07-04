<?php

session_start();

$db_server = 'localhost';
$db_username = 'root';
$db_password = '';
$db_name = 'rate_it_up';

$mysqli = new mysqli($db_server, $db_username, $db_password, $db_name);
if ($mysqli->connect_error) {
    die("Koneksi database gagal: " . $mysqli->connect_error);
}

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

function getUnmoderatedCommentsSimple($mysqli) {
    $comments = [];
    $sql = "SELECT c.id, c.comment_text, u.username, r.review_text, p.name AS place_name
            FROM comments c
            JOIN users u ON c.user_id = u.id
            JOIN reviews r ON c.review_id = r.id
            JOIN places p ON r.place_id = p.id
            WHERE c.is_moderated = FALSE
            ORDER BY c.created_at ASC";
    $result = $mysqli->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $comments[] = $row;
        }
        $result->free();
    }
    return $comments;
}

function moderateCommentSimple($comment_id, $mysqli) {
    $sql = "UPDATE comments SET is_moderated = TRUE WHERE id = ?";
    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param("i", $comment_id);
        return $stmt->execute();
    }
    return false;
}

$notification_message = ''; 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'moderate_comment') {
    $comment_id = $_POST['comment_id'] ?? null;
    if ($comment_id && moderateCommentSimple($comment_id, $mysqli)) {
        $notification_message = '<div class="alert alert-success">Komentar berhasil dimoderasi.</div>';
    } else {
        $notification_message = '<div class="alert alert-danger">Gagal memoderasi komentar.</div>';
    }
}

$unmoderatedComments = getUnmoderatedCommentsSimple($mysqli);

$username = $_SESSION['username'] ?? 'Admin'; 
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Rate It Up</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="homepage-background">
    <nav class="navbar navbar-expand-lg navbar-light navbar-custom-skyblue">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">Rate It Up</a>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php?page=add_place">Tambah Tempat</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="admin_dashboard.php">Dashboard Admin</a>
                    </li>
                    <li class="nav-item">
                        <span class="navbar-text me-2">Halo, <?php echo htmlspecialchars($username); ?>!</span>
                    </li>
                    <li class="nav-item">
                        <form action="index.php" method="POST" class="d-inline">
                            <input type="hidden" name="action" value="logout">
                            <button type="submit" class="btn btn-outline-danger btn-sm">Logout</button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h1>Dashboard Admin</h1>
        <p>Selamat datang di panel admin, <?php echo htmlspecialchars($username); ?>. Di sini Anda bisa memoderasi komentar.</p>

        <?php echo $notification_message; ?>

        <h2 class="mt-4">Komentar yang Belum Dimoderasi</h2>
        <?php if (!empty($unmoderatedComments)): ?>
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Pengguna</th>
                        <th>Komentar</th>
                        <th>Ulasan Asli</th>
                        <th>Tempat</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($unmoderatedComments as $comment): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($comment['id']); ?></td>
                            <td><?php echo htmlspecialchars($comment['username']); ?></td>
                            <td><?php echo nl2br(htmlspecialchars($comment['comment_text'])); ?></td>
                            <td><?php echo nl2br(htmlspecialchars(substr($comment['review_text'] ?? '', 0, 100))) . (strlen($comment['review_text'] ?? '') > 100 ? '...' : ''); ?></td>
                            <td><?php echo htmlspecialchars($comment['place_name']); ?></td>
                            <td>
                                <form action="admin_dashboard.php" method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="moderate_comment">
                                    <input type="hidden" name="comment_id" value="<?php echo $comment['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-warning" onclick="return confirm('Anda yakin ingin memoderasi komentar ini?');">Moderasi</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="alert alert-info">Tidak ada komentar yang perlu dimoderasi.</div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js"></script>
</body>
</html>
<?php $mysqli->close(); ?>