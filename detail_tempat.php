<?php

session_start();

define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'rate_it_up');

$mysqli = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

if ($mysqli->connect_error) {
    die("ERROR: Tidak dapat terhubung ke database. " . $mysqli->connect_error);
}

$loggedIn = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
$username = $_SESSION['username'] ?? 'Tamu';
$userRole = $_SESSION['role'] ?? 'user'; 

function getPlaceById($id, $mysqli) {
    $sql = "SELECT p.*, u.username as created_by_username FROM places p JOIN users u ON p.created_by = u.id WHERE p.id = ?";
    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $place = $result->fetch_assoc();
        $stmt->close();
        return $place;
    }
    return null;
}

function getReviewsByPlaceId($place_id, $mysqli) {
    $reviews = [];
    $sql = "SELECT r.id, r.user_id, u.username, r.rating, r.review_text, r.created_at
            FROM reviews r JOIN users u ON r.user_id = u.id
            WHERE r.place_id = ? ORDER BY r.created_at DESC";
    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param("i", $place_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $reviews[] = $row;
        }
        $stmt->close();
    }
    return $reviews;
}

function addReview($user_id, $place_id, $rating, $review_text, $mysqli) {
    if (empty($user_id) || empty($place_id) || empty($rating)) {
        return ['success' => false, 'message' => 'Rating dan ID tidak boleh kosong.'];
    }
    if ($rating < 1 || $rating > 5) {
        return ['success' => false, 'message' => 'Rating harus antara 1 dan 5.'];
    }

    $sql = "INSERT INTO reviews (user_id, place_id, rating, review_text) VALUES (?, ?, ?, ?)";
    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param("iiis", $user_id, $place_id, $rating, $review_text);
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Ulasan berhasil ditambahkan!'];
        } else {
            return ['success' => false, 'message' => 'Gagal menambahkan ulasan: ' . $stmt->error];
        }
    }
    return ['success' => false, 'message' => 'Kesalahan database.'];
}

function addComment($user_id, $review_id, $comment_text, $mysqli) {
    if (empty($user_id) || empty($review_id) || empty($comment_text)) {
        return ['success' => false, 'message' => 'Komentar tidak boleh kosong.'];
    }

    $check_review_sql = "SELECT id FROM reviews WHERE id = ?";
    if ($stmt_check = $mysqli->prepare($check_review_sql)) {
        $stmt_check->bind_param("i", $review_id);
        $stmt_check->execute();
        $stmt_check->store_result();
        if ($stmt_check->num_rows === 0) {
            return ['success' => false, 'message' => 'Ulasan yang dikomentari tidak ditemukan.'];
        }
        $stmt_check->close();
    } else {
         return ['success' => false, 'message' => 'Kesalahan database saat validasi ulasan.'];
    }

    $sql = "INSERT INTO comments (user_id, review_id, comment_text) VALUES (?, ?, ?)";
    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param("iis", $user_id, $review_id, $comment_text);
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Komentar berhasil ditambahkan dan akan dimoderasi.'];
        } else {
            return ['success' => false, 'message' => 'Gagal menambahkan komentar: ' . $stmt->error];
        }
    }
    return ['success' => false, 'message' => 'Kesalahan database.'];
}

function getCommentsByReviewId($review_id, $mysqli) {
    $comments = [];
    $sql = "SELECT c.id, c.comment_text, u.username, c.created_at, c.is_moderated
            FROM comments c
            JOIN users u ON c.user_id = u.id
            WHERE c.review_id = ? AND c.is_moderated = TRUE 
            ORDER BY c.created_at ASC";
    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param("i", $review_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $comments[] = $row;
        }
        $stmt->close();
    }
    return $comments;
}

function addCheckin($user_id, $place_id, $mysqli) {
    if (empty($user_id) || empty($place_id)) {
        return ['success' => false, 'message' => 'ID pengguna atau tempat tidak valid untuk check-in.'];
    }

    $sql_check_recent = "SELECT COUNT(*) FROM checkins WHERE user_id = ? AND place_id = ? AND checkin_time >= NOW() - INTERVAL 1 DAY";
    if ($stmt_check = $mysqli->prepare($sql_check_recent)) {
        $stmt_check->bind_param("ii", $user_id, $place_id);
        $stmt_check->execute();
        $stmt_check->bind_result($count);
        $stmt_check->fetch();
        $stmt_check->close();

        if ($count > 0) {
            return ['success' => false, 'message' => 'Anda sudah check-in di tempat ini dalam 24 jam terakhir.'];
        }
    } else {
        error_log("Failed to prepare checkin duplicate check: " . $mysqli->error);
    }

    $sql = "INSERT INTO checkins (user_id, place_id) VALUES (?, ?)";
    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param("ii", $user_id, $place_id);
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Check-in berhasil!'];
        } else {
            return ['success' => false, 'message' => 'Gagal check-in: ' . $stmt->error];
        }
    }
    return ['success' => false, 'message' => 'Kesalahan database saat check-in.'];
}

function getCheckinsByPlaceId($place_id, $mysqli, $limit = 5) { 
    $checkins = [];
    $sql = "SELECT c.id, c.user_id, u.username, c.checkin_time
            FROM checkins c JOIN users u ON c.user_id = u.id
            WHERE c.place_id = ?
            ORDER BY c.checkin_time DESC
            LIMIT ?";
    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param("ii", $place_id, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $checkins[] = $row;
        }
        $stmt->close();
    }
    return $checkins;
}

$place_id = $_GET['id'] ?? null;
if (!$place_id) {
    header('Location: index.php'); 
    exit();
}

$place = getPlaceById($place_id, $mysqli);
if (!$place) {
    echo "<h1>Tempat tidak ditemukan.</h1>";
    exit();
}

$message = '';
$messageType = '';

if (isset($_GET['status']) && isset($_GET['msg'])) {
    $messageType = $_GET['status'];
    $message = urldecode($_GET['msg']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_review') {
        if ($loggedIn) {
            $user_id = $_SESSION['id'];
            $rating = $_POST['rating'] ?? null;
            $review_text = $_POST['review_text'] ?? '';

            $result = addReview($user_id, $place_id, $rating, $review_text, $mysqli);
            $message = $result['message'];
            $messageType = $result['success'] ? 'success' : 'danger';
            header('Location: detail_tempat.php?id=' . $place_id . '&status=' . $messageType . '&msg=' . urlencode($message));
            exit();
        } else {
            $message = 'Anda harus login untuk memberikan ulasan.';
            $messageType = 'danger';
        }
    } elseif ($_POST['action'] === 'add_comment') {
        if ($loggedIn) {
            $user_id = $_SESSION['id'];
            $review_id = $_POST['review_id'] ?? null;
            $comment_text = $_POST['comment_text'] ?? '';

            if ($review_id) {
                $result = addComment($user_id, $review_id, $comment_text, $mysqli);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'danger';
                header('Location: detail_tempat.php?id=' . $place_id . '&status=' . $messageType . '&msg=' . urlencode($message));
                exit();
            } else {
                 $message = 'Ulasan yang akan dikomentari tidak valid.';
                 $messageType = 'danger';
            }
        } else {
            $message = 'Anda harus login untuk berkomentar.';
            $messageType = 'danger';
        }
    } elseif ($_POST['action'] === 'add_checkin') {
        if ($loggedIn) {
            $user_id = $_SESSION['id'];
            $result = addCheckin($user_id, $place_id, $mysqli);
            $message = $result['message'];
            $messageType = $result['success'] ? 'success' : 'danger';
            header('Location: detail_tempat.php?id=' . $place_id . '&status=' . $messageType . '&msg=' . urlencode($message));
            exit();
        } else {
            $message = 'Anda harus login untuk check-in.';
            $messageType = 'danger';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nama Halaman Anda</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="homepage-background">
    <nav class="navbar navbar-expand-lg navbar-light navbar-custom-skyblue">
        <div class="container-fluid">
            <a class="navbar-brand custom-text-transform" href="index.php">Rate It Up</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link custom-text-transform" href="index.php">
                            <i class="fas fa-home"></i>  </a>
                    </li>
                    <?php if ($loggedIn): ?>
                        <li class="nav-item">
                            <a class="nav-link custom-text-transform" href="index.php?page=add_place">Tambah Tempat</a>
                        </li>
                        <?php if ($userRole === 'admin'): ?>
                            <li class="nav-item">
                                <a class="nav-link custom-text-transform" href="admin_dashboard.php">Dashboard Admin</a>
                            </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <span class="navbar-text me-2 custom-text-transform">Halo, <?php echo htmlspecialchars($username); ?>!</span>
                        </li>
                        <li class="nav-item">
                            <form action="index.php" method="POST" class="d-inline">
                                <input type="hidden" name="action" value="logout">
                                <button type="submit" class="btn btn-outline-danger btn-sm">Logout</button>
                            </form>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link custom-text-transform" href="index.php?page=login">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link custom-text-transform" href="index.php?page=register">Daftar</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-8">
                <h1><?php echo htmlspecialchars($place['name']); ?></h1>
                <p><strong>Alamat:</strong> <?php echo htmlspecialchars($place['address']); ?></p>
                <?php if (!empty($place['description'])): ?>
                    <p><strong>Deskripsi:</strong> <?php echo nl2br(htmlspecialchars($place['description'])); ?></p>
                <?php endif; ?>
                <?php if (!empty($place['image_url'])): ?>
                    <img src="<?php echo htmlspecialchars($place['image_url']); ?>" class="img-fluid rounded mb-3" alt="<?php echo htmlspecialchars($place['name']); ?>">
                <?php endif; ?>
                <p><small class="text-muted">Ditambahkan oleh: <?php echo htmlspecialchars($place['created_by_username']); ?> pada <?php echo date('d M Y H:i', strtotime($place['created_at'])); ?></small></p>

                <?php if ($loggedIn): ?>
                <form action="detail_tempat.php?id=<?php echo $place_id; ?>" method="POST" class="d-inline mb-3">
                    <input type="hidden" name="action" value="add_checkin">
                    <button type="submit" class="btn btn-success" onclick="return confirm('Anda yakin ingin check-in di tempat ini?');">
                        Check-in Sekarang!
                    </button>
                </form>
                <?php endif; ?>


                <h2 class="mt-4">Ulasan</h2>
                <?php if ($loggedIn): ?>
                    <div class="card mb-3">
                        <div class="card-header">
                            Berikan Ulasan Anda
                        </div>
                        <div class="card-body">
                            <form action="detail_tempat.php?id=<?php echo $place_id; ?>" method="POST">
                                <input type="hidden" name="action" value="add_review">
                                <div class="mb-3">
                                    <label for="rating" class="form-label">Rating (1-5):</label>
                                    <input type="number" class="form-control" id="rating" name="rating" min="1" max="5" required>
                                </div>
                                <div class="mb-3">
                                    <label for="review_text" class="form-label">Komentar Ulasan:</label>
                                    <textarea class="form-control" id="review_text" name="review_text" rows="3"></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary">Kirim Ulasan</button>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">Login untuk memberikan ulasan.</div>
                <?php endif; ?>

                <?php
                $reviews = getReviewsByPlaceId($place_id, $mysqli);
                if (!empty($reviews)):
                    foreach ($reviews as $review):
                    ?>
                    <div class="card mb-3">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($review['username']); ?>
                                <span class="badge bg-info float-end"><?php echo htmlspecialchars($review['rating']); ?>/5 Bintang</span>
                            </h5>
                            <p class="card-text"><?php echo nl2br(htmlspecialchars($review['review_text'])); ?></p>
                            <p class="card-text"><small class="text-muted">Pada <?php echo date('d M Y H:i', strtotime($review['created_at'])); ?></small></p>

                            <?php if ($loggedIn): ?>
                            <div class="mt-3 border-top pt-2">
                                <h6>Tulis Komentar:</h6>
                                <form action="detail_tempat.php?id=<?php echo $place_id; ?>" method="POST">
                                    <input type="hidden" name="action" value="add_comment">
                                    <input type="hidden" name="review_id" value="<?php echo $review['id']; ?>">
                                    <div class="input-group mb-2">
                                        <textarea class="form-control form-control-sm" name="comment_text" placeholder="Komentar Anda..." required></textarea>
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">Kirim</button>
                                    </div>
                                </form>
                            </div>
                            <?php endif; ?>
                            <?php
                            $comments_for_review = getCommentsByReviewId($review['id'], $mysqli);
                            if (!empty($comments_for_review)):
                            ?>
                                <div class="mt-2 ps-3 border-start">
                                    <h6 class="mb-2">Komentar:</h6>
                                    <?php foreach($comments_for_review as $comment_item): ?>
                                        <p class="mb-1"><small><strong><?php echo htmlspecialchars($comment_item['username']); ?>:</strong> <?php echo nl2br(htmlspecialchars($comment_item['comment_text'])); ?></small></p>
                                        <p class="mb-1 text-end"><small class="text-muted"><?php echo date('d M Y H:i', strtotime($comment_item['created_at'])); ?></small></p>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            </div>
                    </div>
                    <?php
                    endforeach;
                else:
                    echo '<p>Belum ada ulasan untuk tempat ini. Jadilah yang pertama!</p>';
                endif;
                ?>

                <h2 class="mt-4">Check-in Terbaru</h2>
                <?php
                $checkins = getCheckinsByPlaceId($place_id, $mysqli, 5); 
                if (!empty($checkins)):
                ?>
                    <ul class="list-group mb-4">
                        <?php foreach ($checkins as $checkin): ?>
                            <li class="list-group-item">
                                <span class="fw-bold"><?php echo htmlspecialchars($checkin['username']); ?></span>
                                telah check-in pada
                                <small class="text-muted"><?php echo date('d M Y H:i', strtotime($checkin['checkin_time'])); ?></small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="alert alert-info">Belum ada check-in untuk tempat ini.</div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="js/script.js"></script>
</body>
</html>
<?php

$mysqli->close();
?>