<?php
session_start();

define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root'); 
define('DB_PASSWORD', '');     
define('DB_NAME', 'rate_it_up');

$mysqli = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($mysqli === false) {
    die("ERROR: Tidak dapat terhubung ke database. " . $mysqli->connect_error);
}


function registerUser($username, $email, $password, $mysqli) {
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $sql = "INSERT INTO users (username, email, password) VALUES (?, ?, ?)";
    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param("sss", $username, $email, $hashed_password);
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Pendaftaran berhasil!'];
        } else {
            if ($mysqli->errno == 1062) {
                return ['success' => false, 'message' => 'Username atau email sudah digunakan.'];
            }
            return ['success' => false, 'message' => 'Gagal mendaftar: ' . $stmt->error];
        }
    }
    return ['success' => false, 'message' => 'Kesalahan database.'];
}

function loginUser($username, $password, $mysqli) {
    $sql = "SELECT id, username, password, role FROM users WHERE username = ?";
    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['loggedin'] = true;
                $_SESSION['id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                return ['success' => true, 'message' => 'Login berhasil!'];
            }
        }
    }
    return ['success' => false, 'message' => 'Username atau password salah.'];
}

function logoutUser() {
    $_SESSION = array();
    session_destroy();
    return ['success' => true, 'message' => 'Berhasil logout.'];
}

function getAllPlaces($mysqli) {
    $places = [];
    $sql = "SELECT id, name, address, description, image_url FROM places ORDER BY created_at DESC";
    if ($result = $mysqli->query($sql)) {
        while ($row = $result->fetch_assoc()) {
            $places[] = $row;
        }
        $result->free();
    }
    return $places;
}

function addPlace($name, $address, $description, $image_url, $created_by, $mysqli) {
    if (empty($name) || empty($address) || empty($created_by)) {
        return ['success' => false, 'message' => 'Nama dan alamat tempat wajib diisi.'];
    }
    $sql = "INSERT INTO places (name, address, description, image_url, created_by) VALUES (?, ?, ?, ?, ?)";
    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param("ssssi", $name, $address, $description, $image_url, $created_by);
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Tempat berhasil ditambahkan.'];
        } else {
            return ['success' => false, 'message' => 'Gagal menambahkan tempat: ' . $stmt->error];
        }
    }
    return ['success' => false, 'message' => 'Kesalahan database.'];
}


$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'register') {
        $result = registerUser($_POST['username'] ?? '', $_POST['email'] ?? '', $_POST['password'] ?? '', $mysqli);
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'danger';
        if ($result['success']) $_GET['page'] = 'login'; // Arahkan ke form login setelah daftar
    } elseif ($action === 'login') {
        $result = loginUser($_POST['username'] ?? '', $_POST['password'] ?? '', $mysqli);
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'danger';
        if ($result['success']) {
            header('Location: index.php'); 
            exit();
        }
    } elseif ($action === 'logout') {
        $result = logoutUser();
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'danger';
        header('Location: index.php');
        exit();
    } elseif ($action === 'add_place') {
        if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
            $result = addPlace(
                $_POST['name'] ?? '',
                $_POST['address'] ?? '',
                $_POST['description'] ?? '',
                $_POST['image_url'] ?? '',
                $_SESSION['id'],
                $mysqli
            );
            $message = $result['message'];
            $messageType = $result['success'] ? 'success' : 'danger';
            if ($result['success']) {
                 header('Location: index.php');
                 exit();
            }
        } else {
            $message = 'Anda harus login untuk menambah tempat.';
            $messageType = 'danger';
        }
    }
}

//HTML//
$pageTitle = 'Rate It Up';
$loggedIn = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
$username = $loggedIn ? $_SESSION['username'] : '';
$userRole = $loggedIn ? $_SESSION['role'] : 'guest';
$currentPage = $_GET['page'] ?? 'home';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
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
                    <?php if ($loggedIn): ?>
                        <li class="nav-item">
                            <a class="nav-link custom-text-transform" href="index.php?page=add_place">Tambah Tempat</a>
                        </li>
                        <?php if ($userRole === 'admin'):?>
                            <li class="nav-item">
                                <a class="nav-link custom-text-transform" href="admin_dashboard.php">Dashboard Admin</a>
                            </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <span class="navbar-text me-2 custom-text-transform">Halo, <?php echo htmlspecialchars($_SESSION['username']); ?>!</span>
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
                            <a class="nav-link" href="index.php?page=register">Daftar</a>
                        </li>
                    <?php endif; ?>
                </ul>
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

        <?php
       
        switch ($currentPage) {
            case 'login':
                ?>
                <h1 class="mb-4">Login</h1>
                <div class="row justify-content-center">
                    <div class="col-md-6">
                        <form action="index.php" method="POST">
                            <input type="hidden" name="action" value="login">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Login</button>
                        </form>
                        <p class="mt-3 text-center">Belum punya akun? <a href="index.php?page=register">Daftar di sini</a></p>
                    </div>
                </div>
                <?php
                break;

            case 'register':
                ?>
                <h1 class="mb-4">Daftar Akun</h1>
                <div class="row justify-content-center">
                    <div class="col-md-6">
                        <form action="index.php" method="POST">
                            <input type="hidden" name="action" value="register">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <button type="submit" class="btn btn-success w-100">Daftar</button>
                        </form>
                        <p class="mt-3 text-center">Sudah punya akun? <a href="index.php?page=login">Login di sini</a></p>
                    </div>
                </div>
                <?php
                break;

            case 'add_place':
                if (!$loggedIn) {
                    echo '<div class="alert alert-warning">Anda harus login untuk menambah tempat.</div>';
                    break;
                }
                ?>
                <h1 class="mb-4">Tambah Tempat Kuliner Baru</h1>
                <form action="index.php" method="POST">
                    <input type="hidden" name="action" value="add_place">
                    <div class="mb-3">
                        <label for="name" class="form-label">Nama Tempat</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="address" class="form-label">Alamat</label>
                        <input type="text" class="form-control" id="address" name="address" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Deskripsi</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="image_url" class="form-label">URL Gambar (Opsional)</label>
                        <input type="url" class="form-control" id="image_url" name="image_url">
                    </div>
                    <button type="submit" class="btn btn-primary">Tambah Tempat</button>
                    <a href="index.php" class="btn btn-secondary">Batal</a>
                </form>
                <?php
                break;

            case 'home':
            default:
                echo '<h1>Selamat datang di Rate It Up!</h1>';
                echo '<p>Temukan dan ulas tempat-tempat kuliner di Indonesia.</p>';

                $places = getAllPlaces($mysqli);

                if (!empty($places)) {
                    echo '<div class="row">';
                    foreach ($places as $place) {
                        ?>
                        <div class="col-md-4 mb-4">
                            <div class="card">
                                <?php if (!empty($place['image_url'])): ?>
                                    <img src="<?php echo htmlspecialchars($place['image_url']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($place['name']); ?>">
                                <?php endif; ?>
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($place['name']); ?></h5>
                                    <p class="card-text"><?php echo htmlspecialchars(substr($place['description'] ?? '', 0, 100)) . (strlen($place['description'] ?? '') > 100 ? '...' : ''); ?></p>
                                    <p class="card-text"><small class="text-muted"><?php echo htmlspecialchars($place['address']); ?></small></p>
                                    <a href="detail_tempat.php?id=<?php echo $place['id']; ?>" class="btn btn-primary">Lihat Detail</a>
                                </div>
                            </div>
                        </div>
                        <?php
                    }
                    echo '</div>';
                } else {
                    echo '<p>Belum ada tempat makan yang ditemukan. Jadilah yang pertama menambahkannya!</p>';
                }
                break;
        }
        ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="js/script.js"></script>
</body>
</html>
<?php $mysqli->close(); ?>