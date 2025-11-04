<?php
include 'config.php';

// Cek apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Ambil data terbaru
$stmt = $pdo->query("SELECT * FROM sensor_data ORDER BY timestamp DESC LIMIT 1");
$latest_data = $stmt->fetch();

// Ambil data untuk grafik (24 jam terakhir)
$stmt = $pdo->query("SELECT * FROM sensor_data WHERE timestamp >= NOW() - INTERVAL 24 HOUR ORDER BY timestamp ASC");
$chart_data = $stmt->fetchAll();

// Ambil semua user (hanya untuk admin)
$users = [];
if ($_SESSION['role'] == 'admin') {
    $stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
    $users = $stmt->fetchAll();
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Tambah user (admin only)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah_user'])) {
    if ($_SESSION['role'] == 'admin') {
        $username = $_POST['username'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $nama_lengkap = $_POST['nama_lengkap'];
        $role = $_POST['role'];
        
        $stmt = $pdo->prepare("INSERT INTO users (username, password, nama_lengkap, role) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$username, $password, $nama_lengkap, $role])) {
            $_SESSION['success_message'] = "User berhasil ditambahkan!";
        } else {
            $_SESSION['error_message'] = "Gagal menambahkan user!";
        }
        
        header('Location: dashboard.php');
        exit;
    }
}

// Hapus user (admin only)
if (isset($_GET['delete_user'])) {
    if ($_SESSION['role'] == 'admin') {
        $user_id = $_GET['delete_user'];
        
        // Jangan hapus user sendiri
        if ($user_id != $_SESSION['user_id']) {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            if ($stmt->execute([$user_id])) {
                $_SESSION['success_message'] = "User berhasil dihapus!";
            } else {
                $_SESSION['error_message'] = "Gagal menghapus user!";
            }
        } else {
            $_SESSION['error_message'] = "Tidak dapat menghapus user sendiri!";
        }
        
        header('Location: dashboard.php');
        exit;
    }
}

// Pesan sukses/error
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Monitoring Tambak</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #667eea;
            --secondary: #764ba2;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .sidebar {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            height: 100vh;
            position: fixed;
            padding-top: 20px;
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            margin: 5px 0;
            border-radius: 5px;
            transition: all 0.3s;
        }
        
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
        
        .metric-card {
            text-align: center;
            padding: 20px;
        }
        
        .metric-value {
            font-size: 2.5rem;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .metric-suhu {
            color: var(--warning);
        }
        
        .metric-ph {
            color: var(--primary);
        }
        
        .status-optimal {
            color: var(--success);
        }
        
        .status-warning {
            color: var(--warning);
        }
        
        .status-danger {
            color: var(--danger);
        }
        
        .gauge-container {
            width: 200px;
            height: 200px;
            margin: 0 auto;
        }
        
        .collapse.show {
            transition: all 0.3s ease;
        }

        .nav-link[aria-expanded="true"] #userManagementIcon {
        transform: rotate(180deg);
        transition: transform 0.3s ease;
    }
    
    .card {
        transition: all 0.3s ease;
    }
    
    .btn-close {
        cursor: pointer;
    }

    #monitoringChart {
        width: 100% !important;
        height: 400px !important;
    }
    
    .gauge-container {
        position: relative;
        width: 200px;
        height: 200px;
        margin: 0 auto;
    }
    
    .gauge-container canvas {
        width: 100% !important;
        height: 100% !important;
    }

    /* Style untuk komponen waktu */
    #timezoneSelect {
        width: 200px;
        font-size: 0.875rem;
    }
    
    #realtimeClock {
        font-family: 'Courier New', monospace;
        font-weight: bold;
        background: linear-gradient(135deg, #667eea, #764ba2);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        padding: 5px 10px;
        border-radius: 5px;
        background-color: #f8f9fa;
        border: 1px solid #e9ecef;
    }
    
    .timestamp-element {
        font-family: 'Courier New', monospace;
        font-size: 0.875rem;
    }

    

    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar">
                <div class="text-center mb-4">
                    <h4><i class="fas fa-fish me-2"></i>Monitoring Tambak</h4>
                    <hr class="bg-light">
                </div>
                
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                    </li>
                    <?php if ($_SESSION['role'] == 'admin'): ?>
<li class="nav-item">
    <a class="nav-link" href="#userManagement" data-bs-toggle="collapse" aria-expanded="false" aria-controls="userManagement">
        <i class="fas fa-users me-2"></i>Manajemen User
        <i class="fas fa-chevron-down float-end mt-1" id="userManagementIcon"></i>
    </a>
    <div class="collapse" id="userManagement">
        <ul class="nav flex-column ps-3">
            <li class="nav-item">
                <a class="nav-link" href="javascript:void(0)" onclick="showTambahUser()">
                    <i class="fas fa-user-plus me-2"></i>Tambah User
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="javascript:void(0)" onclick="showDaftarUser()">
                    <i class="fas fa-list me-2"></i>Daftar User
                </a>
            </li>
        </ul>
    </div>
</li>
<?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link" href="?logout=true">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </a>
                    </li>
                </ul>
                
                <div class="position-absolute bottom-0 start-0 p-3 w-100">
                    <div class="text-center">
                        <small>Login sebagai: <strong><?php echo $_SESSION['nama_lengkap']; ?></strong></small>
                        <br>
                        <small>Role: <strong><?php echo ucfirst($_SESSION['role']); ?></strong></small>
                    </div>
                </div>
            </div>
            
            <!-- Main Content -->

            <?php if (empty($chart_data)): ?>
<div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle me-2"></i>
    <strong>Peringatan:</strong> Data chart tidak ditemukan. Pastikan tabel sensor_data berisi data.
</div>
<?php endif; ?>

<!-- Debug info (opsional, bisa dihapus setelah berhasil) -->
<div class="alert alert-info d-none">
    <strong>Debug Info:</strong><br>
    Data terbaru: <?php echo $latest_data ? 'Ada' : 'Tidak ada'; ?><br>
    Total data chart: <?php echo count($chart_data); ?><br>
    Data chart: <?php echo json_encode($chart_data); ?>
</div>


            <div class="col-md-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Dashboard Monitoring Kualitas Air</h2>
                    <div class="text-muted">
                          <!-- Pilihan Zona Waktu -->
            <div class="me-3">
                <select id="timezoneSelect" class="form-select form-select-sm">
                    <option value="Asia/Jakarta">WIB (Jakarta)</option>
                    <option value="Asia/Makassar">WITA (Makassar)</option>
                    <option value="Asia/Jayapura">WIT (Jayapura)</option>
                    <option value="UTC">UTC</option>
                    <option value="Asia/Singapore">Singapore</option>
                    <option value="Asia/Tokyo">Japan</option>
                </select>
            </div>
            <!-- Jam Realtime -->
            <div class="text-muted">
                <i class="fas fa-clock me-1"></i>
                <span id="realtimeClock"><?php echo date('d F Y H:i:s'); ?></span>
            </div>  
                    </div>
                </div>
               
                
                <!-- Alert Messages -->
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Alert Status -->
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Info:</strong> Sistem monitoring kualitas air tambak secara real-time. Parameter optimal: Suhu 26-30°C, pH 6.5-8.5.
                </div>
                
                <!-- Metrics Cards -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card metric-card">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-thermometer-half me-2 metric-suhu"></i>Suhu Air</h5>
                                <div class="metric-value metric-suhu">
                                    <?php echo $latest_data ? $latest_data['suhu'] . '°C' : 'N/A'; ?>
                                </div>
                                <div class="metric-status">
                                    <?php if ($latest_data): ?>
                                        <?php if ($latest_data['suhu'] >= 26 && $latest_data['suhu'] <= 30): ?>
                                            <span class="status-optimal"><i class="fas fa-check-circle me-1"></i>Optimal</span>
                                        <?php elseif ($latest_data['suhu'] >= 24 && $latest_data['suhu'] <= 32): ?>
                                            <span class="status-warning"><i class="fas fa-exclamation-triangle me-1"></i>Perhatian</span>
                                        <?php else: ?>
                                            <span class="status-danger"><i class="fas fa-times-circle me-1"></i>Bahaya</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card metric-card">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-tint me-2 metric-ph"></i>pH Air</h5>
                                <div class="metric-value metric-ph">
                                    <?php echo $latest_data ? $latest_data['ph'] : 'N/A'; ?>
                                </div>
                                <div class="metric-status">
                                    <?php if ($latest_data): ?>
                                        <?php if ($latest_data['ph'] >= 6.5 && $latest_data['ph'] <= 8.5): ?>
                                            <span class="status-optimal"><i class="fas fa-check-circle me-1"></i>Optimal</span>
                                        <?php elseif ($latest_data['ph'] >= 6.0 && $latest_data['ph'] <= 9.0): ?>
                                            <span class="status-warning"><i class="fas fa-exclamation-triangle me-1"></i>Perhatian</span>
                                        <?php else: ?>
                                            <span class="status-danger"><i class="fas fa-times-circle me-1"></i>Bahaya</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Charts and Data -->
                <div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fas fa-chart-line me-2"></i>Grafik Monitoring 24 Jam Terakhir</h5>
            </div>
            <div class="card-body">
                <div style="height: 400px;">
                    <canvas id="monitoringChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fas fa-gauge me-2"></i>Meteran Status</h5>
            </div>
            <div class="card-body text-center">
                <!-- Gauge Suhu -->
                <div class="mb-4">
                    <h6>Suhu Air</h6>
                    <div class="gauge-container">
                        <canvas id="suhuGauge" width="200" height="200"></canvas>
                    </div>
                </div>
                
                <!-- Gauge pH -->
                <div>
                    <h6>pH Air</h6>
                    <div class="gauge-container">
                        <canvas id="phGauge" width="200" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
                
                <!-- Data Table -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0"><i class="fas fa-table me-2"></i>Data Sensor Terkini</h5>
                                <button class="btn btn-sm btn-primary" onclick="refreshData()">
                                    <i class="fas fa-refresh me-1"></i>Refresh
                                </button>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Waktu</th>
                                                <th>Suhu (°C)</th>
                                                <th>pH</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $stmt = $pdo->query("SELECT * FROM sensor_data ORDER BY timestamp DESC LIMIT 10");
                                            $data_list = $stmt->fetchAll();
                                            $counter = 1;
                                            
                                            foreach ($data_list as $data):
                                                // Tentukan status
                                                $status_suhu = '';
                                                $status_ph = '';
                                                
                                                if ($data['suhu'] >= 26 && $data['suhu'] <= 30) {
                                                    $status_suhu = '<span class="badge bg-success">Optimal</span>';
                                                } elseif ($data['suhu'] >= 24 && $data['suhu'] <= 32) {
                                                    $status_suhu = '<span class="badge bg-warning">Perhatian</span>';
                                                } else {
                                                    $status_suhu = '<span class="badge bg-danger">Bahaya</span>';
                                                }
                                                
                                                if ($data['ph'] >= 6.5 && $data['ph'] <= 8.5) {
                                                    $status_ph = '<span class="badge bg-success">Optimal</span>';
                                                } elseif ($data['ph'] >= 6.0 && $data['ph'] <= 9.0) {
                                                    $status_ph = '<span class="badge bg-warning">Perhatian</span>';
                                                } else {
                                                    $status_ph = '<span class="badge bg-danger">Bahaya</span>';
                                                }
                                            ?>
                                            <tr>
                                                <td><?php echo $counter; ?></td>
                                                <td><?php echo date('d M Y H:i', strtotime($data['timestamp'])); ?></td>
                                                <td><?php echo $data['suhu']; ?>°C <?php echo $status_suhu; ?></td>
                                                <td><?php echo $data['ph']; ?> <?php echo $status_ph; ?></td>
                                                <td>
                                                    <?php 
                                                    if ($status_suhu == '<span class="badge bg-success">Optimal</span>' && 
                                                        $status_ph == '<span class="badge bg-success">Optimal</span>') {
                                                        echo '<span class="badge bg-success">Aman</span>';
                                                    } else {
                                                        echo '<span class="badge bg-warning">Perhatian</span>';
                                                    }
                                                    ?>
                                                </td>
                                            </tr>
                                            <?php 
                                            $counter++;
                                            endforeach; 
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- User Management (Admin Only) -->
                <!-- User Management (Admin Only) -->
<?php if ($_SESSION['role'] == 'admin'): ?>
<div class="row mt-4">
    <div class="col-12">
        <!-- Form Tambah User -->
        <div class="card mb-4" id="tambahUserForm" style="display: none;">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="fas fa-user-plus me-2"></i>Tambah User Baru</h5>
                <button type="button" class="btn-close" onclick="hideTambahUser()"></button>
            </div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" name="username" placeholder="Username" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Password</label>
                        <input type="password" class="form-control" name="password" placeholder="Password" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Nama Lengkap</label>
                        <input type="text" class="form-control" name="nama_lengkap" placeholder="Nama Lengkap" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Role</label>
                        <select class="form-control" name="role">
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="submit" name="tambah_user" class="btn btn-success w-100">
                            <i class="fas fa-plus"></i> Tambah
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Daftar User -->
        <div class="card" id="daftarUser" style="display: none;">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="fas fa-users me-2"></i>Daftar User</h5>
                <button type="button" class="btn-close" onclick="hideDaftarUser()"></button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Username</th>
                                <th>Nama Lengkap</th>
                                <th>Role</th>
                                <th>Tanggal Dibuat</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $index => $user): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo $user['username']; ?></td>
                                <td><?php echo $user['nama_lengkap']; ?></td>
                                <td>
                                    <span class="badge <?php echo $user['role'] == 'admin' ? 'bg-danger' : 'bg-primary'; ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d M Y H:i', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <a href="?delete_user=<?php echo $user['id']; ?>" class="btn btn-sm btn-danger" 
                                       onclick="return confirm('Yakin ingin menghapus user <?php echo $user['username']; ?>?')">
                                        <i class="fas fa-trash"></i> Hapus
                                    </a>
                                    <?php else: ?>
                                    <span class="text-muted">User aktif</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Variabel global untuk zona waktu
    let currentTimezone = 'Asia/Jakarta';
    
    // Fungsi untuk update jam realtime
    function updateRealtimeClock() {
        const now = new Date();
        const formatter = new Intl.DateTimeFormat('id-ID', {
            timeZone: currentTimezone,
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false
        });
        
        const formattedDate = formatter.format(now);
        document.getElementById('realtimeClock').textContent = formattedDate;
    }
    
    // Fungsi untuk mendapatkan offset zona waktu
    function getTimezoneOffset(timezone) {
        const now = new Date();
        const formatter = new Intl.DateTimeFormat('en-US', {
            timeZone: timezone,
            timeZoneName: 'shortOffset'
        });
        const parts = formatter.formatToParts(now);
        const offsetPart = parts.find(part => part.type === 'timeZoneName');
        return offsetPart ? offsetPart.value : 'UTC';
    }
    
    // Fungsi untuk mengupdate pilihan zona waktu
    function updateTimezoneSelect() {
        const select = document.getElementById('timezoneSelect');
        if (select) {
            // Set nilai default berdasarkan preferensi user atau Asia/Jakarta
            const savedTimezone = localStorage.getItem('preferredTimezone') || 'Asia/Jakarta';
            select.value = savedTimezone;
            currentTimezone = savedTimezone;
            
            // Update tampilan zona waktu
            select.querySelectorAll('option').forEach(option => {
                const offset = getTimezoneOffset(option.value);
                if (offset !== 'UTC') {
                    option.text = `${option.text} (${offset})`;
                }
            });
            
            // Event listener untuk perubahan zona waktu
            select.addEventListener('change', function() {
                currentTimezone = this.value;
                localStorage.setItem('preferredTimezone', currentTimezone);
                updateRealtimeClock();
                updateAllTimestamps();
            });
        }
    }
    
    // Fungsi untuk mengupdate semua timestamp di halaman
    function updateAllTimestamps() {
        // Update timestamp di tabel data sensor
        const timeElements = document.querySelectorAll('.timestamp-element');
        timeElements.forEach(element => {
            const utcTime = element.getAttribute('data-utc');
            if (utcTime) {
                const localTime = convertUTCToLocal(utcTime);
                element.textContent = localTime;
            }
        });
    }
    
    // Fungsi untuk konversi UTC ke waktu lokal berdasarkan zona waktu
    function convertUTCToLocal(utcString) {
        const date = new Date(utcString);
        const formatter = new Intl.DateTimeFormat('id-ID', {
            timeZone: currentTimezone,
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
        return formatter.format(date);
    }
    
    // Fungsi untuk menampilkan/sembunyikan form tambah user
    function showTambahUser() {
        document.getElementById('tambahUserForm').style.display = 'block';
        document.getElementById('daftarUser').style.display = 'none';
        document.getElementById('tambahUserForm').scrollIntoView({ behavior: 'smooth' });
    }
    
    function hideTambahUser() {
        document.getElementById('tambahUserForm').style.display = 'none';
    }
    
    function showDaftarUser() {
        document.getElementById('daftarUser').style.display = 'block';
        document.getElementById('tambahUserForm').style.display = 'none';
        document.getElementById('daftarUser').scrollIntoView({ behavior: 'smooth' });
    }
    
    function hideDaftarUser() {
        document.getElementById('daftarUser').style.display = 'none';
    }
    
    // Auto show sections jika ada pesan sukses/error
    document.addEventListener('DOMContentLoaded', function() {
        <?php if ($success_message || $error_message): ?>
            showDaftarUser();
            const userManagementCollapse = new bootstrap.Collapse(document.getElementById('userManagement'));
            userManagementCollapse.show();
        <?php endif; ?>
        
        // Inisialisasi komponen
        updateTimezoneSelect();
        updateRealtimeClock();
        initializeChart();
        initializeGauges();
        updateTableTimestamps();
        
        // Update jam setiap detik
        setInterval(updateRealtimeClock, 1000);
        
        // Animasi untuk chevron icon
        const userManagementLink = document.querySelector('a[href="#userManagement"]');
        const userManagementIcon = document.getElementById('userManagementIcon');
        
        if (userManagementLink) {
            userManagementLink.addEventListener('click', function() {
                setTimeout(() => {
                    const isExpanded = document.getElementById('userManagement').classList.contains('show');
                    userManagementIcon.className = isExpanded ? 
                        'fas fa-chevron-up float-end mt-1' : 
                        'fas fa-chevron-down float-end mt-1';
                }, 350);
            });
        }
    });

    // Fungsi untuk update timestamp di tabel
    function updateTableTimestamps() {
        const timeCells = document.querySelectorAll('tbody td:nth-child(2)');
        timeCells.forEach(cell => {
            const originalTime = cell.textContent.trim();
            if (originalTime) {
                // Simpan timestamp asli sebagai data attribute
                if (!cell.hasAttribute('data-original-time')) {
                    cell.setAttribute('data-original-time', originalTime);
                }
                
                // Konversi ke zona waktu yang dipilih
                try {
                    const localTime = convertUTCToLocal(originalTime);
                    cell.textContent = localTime;
                    cell.classList.add('timestamp-element');
                } catch (e) {
                    console.error('Error converting time:', e);
                }
            }
        });
    }

    // Fungsi inisialisasi chart
    function initializeChart() {
        const ctx = document.getElementById('monitoringChart');
        if (!ctx) return;

        const chartData = {
            labels: [<?php 
                if (!empty($chart_data)) {
                    foreach($chart_data as $data) {
                        echo '"' . date('H:i', strtotime($data['timestamp'])) . '",';
                    }
                } else {
                    echo '"08:00", "09:00", "10:00", "11:00", "12:00"';
                }
            ?>],
            datasets: [
                {
                    label: 'Suhu (°C)',
                    data: [<?php 
                        if (!empty($chart_data)) {
                            foreach($chart_data as $data) {
                                echo $data['suhu'] . ',';
                            }
                        } else {
                            echo '28.5, 29.1, 30.2, 29.8, 28.9';
                        }
                    ?>],
                    borderColor: '#ffc107',
                    backgroundColor: 'rgba(255, 193, 7, 0.1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true,
                    yAxisID: 'y'
                },
                {
                    label: 'pH',
                    data: [<?php 
                        if (!empty($chart_data)) {
                            foreach($chart_data as $data) {
                                echo $data['ph'] . ',';
                            }
                        } else {
                            echo '7.2, 7.0, 6.8, 7.1, 7.3';
                        }
                    ?>],
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true,
                    yAxisID: 'y1'
                }
            ]
        };
        
        const config = {
            type: 'line',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'top' },
                    title: { display: true, text: 'Perkembangan Suhu dan pH 24 Jam Terakhir' },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) label += ': ';
                                if (context.parsed.y !== null) {
                                    label += context.parsed.y;
                                    if (context.dataset.label === 'Suhu (°C)') label += '°C';
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    x: { display: true, title: { display: true, text: 'Waktu' } },
                    y: { 
                        type: 'linear', display: true, position: 'left',
                        title: { display: true, text: 'Suhu (°C)' }, min: 20, max: 35
                    },
                    y1: { 
                        type: 'linear', display: true, position: 'right',
                        title: { display: true, text: 'pH' }, min: 6, max: 9,
                        grid: { drawOnChartArea: false }
                    }
                }
            }
        };
        
        try {
            new Chart(ctx, config);
        } catch (error) {
            console.error('Error inisialisasi chart:', error);
        }
    }

    // Fungsi inisialisasi gauge
    function initializeGauges() {
        <?php if ($latest_data): ?>
        drawGauge('suhuGauge', <?php echo $latest_data['suhu']; ?>, 20, 40, 26, 30, '°C');
        drawGauge('phGauge', <?php echo $latest_data['ph']; ?>, 0, 14, 6.5, 8.5, '');
        <?php else: ?>
        drawGauge('suhuGauge', 28.5, 20, 40, 26, 30, '°C');
        drawGauge('phGauge', 7.2, 0, 14, 6.5, 8.5, '');
        <?php endif; ?>
    }
    
    // Fungsi untuk menggambar gauge meter
    function drawGauge(canvasId, value, min, max, optimalMin, optimalMax, unit) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return;
        
        const ctx = canvas.getContext('2d');
        const centerX = canvas.width / 2;
        const centerY = canvas.height / 2;
        const radius = Math.min(centerX, centerY) - 10;
        
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        
        // Draw background arc
        ctx.beginPath();
        ctx.arc(centerX, centerY, radius, 0.75 * Math.PI, 2.25 * Math.PI);
        ctx.strokeStyle = '#e0e0e0';
        ctx.lineWidth = 15;
        ctx.stroke();
        
        // Calculate angle for value
        const valueAngle = 0.75 * Math.PI + (1.5 * Math.PI * (value - min) / (max - min));
        
        // Determine color based on value
        let color;
        if (value >= optimalMin && value <= optimalMax) {
            color = '#28a745';
        } else if ((value >= optimalMin - 2 && value < optimalMin) || (value > optimalMax && value <= optimalMax + 2)) {
            color = '#ffc107';
        } else {
            color = '#dc3545';
        }
        
        // Draw value arc
        ctx.beginPath();
        ctx.arc(centerX, centerY, radius, 0.75 * Math.PI, valueAngle);
        ctx.strokeStyle = color;
        ctx.lineWidth = 15;
        ctx.stroke();
        
        // Draw value text
        ctx.fillStyle = '#333';
        ctx.font = 'bold 20px Arial';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(value + unit, centerX, centerY);
        
        // Draw min and max labels
        ctx.font = '12px Arial';
        ctx.fillStyle = '#666';
        ctx.fillText(min + unit, centerX - radius - 5, centerY);
        ctx.fillText(max + unit, centerX + radius + 5, centerY);
        
        // Draw optimal range indicator
        ctx.fillStyle = '#28a745';
        ctx.font = '10px Arial';
        ctx.fillText('Optimal: ' + optimalMin + '-' + optimalMax + unit, centerX, centerY + 30);
    }
    
    // Refresh data function
    function refreshData() {
        location.reload();
    }
    
    // Auto refresh every 30 seconds
    setInterval(refreshData, 30000);
    </script>
</body>
</html>