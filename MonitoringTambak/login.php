<?php
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Proses Login
    if (isset($_POST['login'])) {
        $username = $_POST['username'];
        $password = $_POST['password'];
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
            
            header('Location: dashboard.php');
            exit;
        } else {
            $error = "Username atau password salah!";
        }
    }
    
    // Proses Sign Up
    if (isset($_POST['signup'])) {
        $username = $_POST['new_username'];
        $password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        $nama_lengkap = $_POST['nama_lengkap'];
        
        // Validasi
        if ($password !== $confirm_password) {
            $signup_error = "Password dan konfirmasi password tidak sama!";
        } else {
            // Cek apakah username sudah ada
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $existing_user = $stmt->fetch();
            
            if ($existing_user) {
                $signup_error = "Username sudah digunakan!";
            } else {
                // Hash password dan simpan user baru
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password, nama_lengkap, role) VALUES (?, ?, ?, 'user')");
                
                if ($stmt->execute([$username, $hashed_password, $nama_lengkap])) {
                    $signup_success = "Pendaftaran berhasil! Silakan login.";
                } else {
                    $signup_error = "Terjadi kesalahan saat mendaftar.";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Monitoring Tambak</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .login-body {
            padding: 30px;
        }
        .nav-tabs .nav-link {
            border: none;
            color: #6c757d;
            font-weight: 500;
        }
        .nav-tabs .nav-link.active {
            color: #667eea;
            border-bottom: 2px solid #667eea;
            background: transparent;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="login-card">
                    <div class="login-header">
                        <h2><i class="fas fa-fish me-2"></i>Monitoring Tambak</h2>
                        <p class="mb-0">Sistem Informasi Kualitas Air</p>
                    </div>
                    <div class="login-body">
                        <ul class="nav nav-tabs nav-justified mb-4" id="authTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="login-tab" data-bs-toggle="tab" data-bs-target="#login" type="button" role="tab">
                                    <i class="fas fa-sign-in-alt me-2"></i>Login
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="signup-tab" data-bs-toggle="tab" data-bs-target="#signup" type="button" role="tab">
                                    <i class="fas fa-user-plus me-2"></i>Daftar
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content" id="authTabsContent">
                            <!-- Tab Login -->
                            <div class="tab-pane fade show active" id="login" role="tabpanel">
                                <?php if (isset($error)): ?>
                                    <div class="alert alert-danger"><?php echo $error; ?></div>
                                <?php endif; ?>
                                
                                <?php if (isset($signup_success)): ?>
                                    <div class="alert alert-success"><?php echo $signup_success; ?></div>
                                <?php endif; ?>
                                
                                <form method="POST">
                                    <div class="mb-3">
                                        <label class="form-label">Username</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                                            <input type="text" class="form-control" name="username" required>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Password</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                            <input type="password" class="form-control" name="password" required>
                                        </div>
                                    </div>
                                    <button type="submit" name="login" class="btn btn-primary w-100">
                                        <i class="fas fa-sign-in-alt me-2"></i>Login
                                    </button>
                                </form>
                                
                                <div class="text-center mt-3">
                                    <small>Default admin: admin / password</small>
                                </div>
                            </div>

                            <!-- Tab Sign Up -->
                            <div class="tab-pane fade" id="signup" role="tabpanel">
                                <?php if (isset($signup_error)): ?>
                                    <div class="alert alert-danger"><?php echo $signup_error; ?></div>
                                <?php endif; ?>
                                
                                <form method="POST">
                                    <div class="mb-3">
                                        <label class="form-label">Nama Lengkap</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                            <input type="text" class="form-control" name="nama_lengkap" required>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Username</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                                            <input type="text" class="form-control" name="new_username" required>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Password</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                            <input type="password" class="form-control" name="new_password" required>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Konfirmasi Password</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                            <input type="password" class="form-control" name="confirm_password" required>
                                        </div>
                                    </div>
                                    <button type="submit" name="signup" class="btn btn-success w-100">
                                        <i class="fas fa-user-plus me-2"></i>Daftar
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>