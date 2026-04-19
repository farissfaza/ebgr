<?php
// index.php
// Halaman utama untuk menampilkan data tamu dan pesan status

// 1. Mulai session untuk membaca flash message
session_start();

// 2. Sertakan file koneksi database
include __DIR__ . '/config/koneksi.php';

// 3. Sertakan header UI (asumsi strukturnya sama dengan form_checkin.php)
include 'UI/header.php';

// 4. Ambil dan susun query data tamu dari database dengan filter
$where_clauses = [];
$params = [];
$types = "";

// Sort Logic
$sort_column = $_GET['sort'] ?? 'tanggal_check_in';
$sort_dir = (isset($_GET['dir']) && strtolower($_GET['dir']) == 'asc') ? 'ASC' : 'DESC';

$allowed_sort_columns = ['nama_tamu', 'nik', 'no_hp', 'nama_kamar', 'nama_kelas', 'tanggal_check_in', 'tanggal_check_out', 'status'];
if (!in_array($sort_column, $allowed_sort_columns)) {
    $sort_column = 'tanggal_check_in';
}

// Global Search (nama, nik, no_hp, kamar, kelas)
if (!empty($_GET['search'])) {
    $search = "%" . $_GET['search'] . "%";
    $where_clauses[] = "(nama_tamu LIKE ? OR nik LIKE ? OR no_hp LIKE ? OR nama_kelas LIKE ? OR nama_kamar LIKE ?)";
    array_push($params, $search, $search, $search, $search, $search);
    $types .= "sssss";
}

// Date Range Filter
if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
    $where_clauses[] = "(tanggal_check_in >= ? AND tanggal_check_out <= ?)";
    array_push($params, $_GET['start_date'], $_GET['end_date']);
    $types .= "ss";
} elseif (!empty($_GET['start_date'])) {
    $where_clauses[] = "tanggal_check_in >= ?";
    array_push($params, $_GET['start_date']);
    $types .= "s";
} elseif (!empty($_GET['end_date'])) {
    $where_clauses[] = "tanggal_check_out <= ?";
    array_push($params, $_GET['end_date']);
    $types .= "s";
}

$sql = "SELECT id, nama_tamu, nik, nama_kamar, nama_kelas, tanggal_check_in, tanggal_check_out, no_hp, status 
        FROM data_tamu";

if (count($where_clauses) > 0) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}

// Apply dynamic sorting
$sql .= " ORDER BY $sort_column $sort_dir";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}


?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Data Tamu</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <style>
        /* Modern Gradient Background for Dashboard Content */
        .glass-dashboard-bg {
            background: rgba(255, 255, 255, 0.9); /* White background with slight transparency for glass vibe */
            min-height: calc(100vh - 60px);
            padding: 2rem 1.5rem;
            position: relative;
        }
        
        /* Decorative blur effects in the background */
        .glass-dashboard-bg::before,
        .glass-dashboard-bg::after {
            content: '';
            position: fixed;
            border-radius: 50%;
            z-index: 0;
            filter: blur(60px);
            opacity: 0.6;
            pointer-events: none;
        }
        .glass-dashboard-bg::before {
            background: #ff9a9e;
            width: 300px;
            height: 300px;
            top: -50px;
            left: -50px;
        }
        .glass-dashboard-bg::after {
            background: #fecfef;
            width: 400px;
            height: 400px;
            bottom: -100px;
            right: -50px;
        }

        .content-container {
            position: relative;
            z-index: 1;
        }

        .h3, h1 {
            color: #2c3e50;
            font-weight: 700;
            margin-bottom: 1.5rem;
            text-shadow: 1px 1px 3px rgba(0,0,0,0.1);
            letter-spacing: -0.5px;
        }

        /* Liquid Glass Effect for Card */
        .glass-card {
            background: rgba(255, 255, 255, 0.45);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            border-radius: 20px;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.1);
            overflow: hidden;
            transition: all 0.3s ease;
        }
        .glass-card:hover {
            box-shadow: 0 12px 40px 0 rgba(31, 38, 135, 0.15);
            transform: translateY(-2px);
        }

        .glass-header {
            background: rgba(255, 255, 255, 0.3);
            border-bottom: 1px solid rgba(255, 255, 255, 0.4);
            padding: 1.25rem 1.5rem;
        }

        /* Table modern styling */
        .glass-table {
            --bs-table-bg: transparent;
            --bs-table-hover-bg: rgba(255,255,255,0.3);
            margin-bottom: 0;
            color: #2c3e50;
        }
        
        .glass-table thead th {
            border-bottom: 2px solid rgba(255, 255, 255, 0.5) !important;
            color: #34495e;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            padding: 1rem;
        }
        
        .glass-table tbody td {
            vertical-align: middle;
            border-bottom: 1px solid rgba(255, 255, 255, 0.3) !important;
            padding: 1rem;
            font-weight: 500;
        }
        
        /* Table Sorting Arrows */
        .sortable { cursor: pointer; position: relative; padding-right: 1.5rem !important; color: #34495e; text-decoration: none; display: inline-block;}
        .sortable:hover { color: #667eea; }
        .sortable::after { content: '\f128'; font-family: 'bootstrap-icons'; position: absolute; right: 0; top: 50%; transform: translateY(-50%); color: rgba(0,0,0,0.15); font-size: 0.8rem; }
        .sort-asc::after { content: '\f145'; color: #667eea; opacity: 1; }
        .sort-desc::after { content: '\f12e'; color: #667eea; opacity: 1; }

        /* Filter Container */
        .glass-filter-container {
            background: linear-gradient(to right, rgba(255, 255, 255, 0.7), rgba(255, 255, 255, 0.4));
            backdrop-filter: blur(14px);
            border-radius: 16px;
            padding: 1.25rem 1.75rem;
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.7), 0 4px 15px rgba(0,0,0,0.03);
            margin: 1.5rem;
        }

        /* Button Enhancements */
        .btn-glass-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 50px;
            padding: 0.5rem 1.5rem;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(118, 75, 162, 0.3);
            transition: all 0.3s ease;
        }
        
        .btn-glass-primary:hover {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(118, 75, 162, 0.4);
            color: white;
        }

        .action-buttons .btn {
            margin-right: 5px;
            border-radius: 10px;
            transition: all 0.2s;
            border: 1px solid rgba(255,255,255,0.6);
            backdrop-filter: blur(4px);
        }

        .action-buttons .btn-info {
            background: rgba(13, 202, 240, 0.6);
            color: #055160;
        }
        .action-buttons .btn-info:hover {
            background: rgba(13, 202, 240, 0.9);
            transform: scale(1.1);
        }
        
        .action-buttons .btn-warning {
            background: rgba(255, 193, 7, 0.6);
            color: #664d03;
        }
        .action-buttons .btn-warning:hover {
            background: rgba(255, 193, 7, 0.9);
            transform: scale(1.1);
        }

        /* Status Badge Enhancements */
        .status-badge {
            padding: 0.5em 1em;
            border-radius: 50px;
            font-size: 0.75em;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            display: inline-block;
        }
        .status-active {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: #fff;
            border: 1px solid rgba(255,255,255,0.4);
        }
        .status-checkout {
            background: linear-gradient(135deg, #bdc3c7 0%, #2c3e50 100%);
            color: #fff;
            border: 1px solid rgba(255,255,255,0.4);
        }
        .status-cancelled {
            background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%);
            color: #fff;
            border: 1px solid rgba(255,255,255,0.4);
        }
    </style>
</head>
<body>

<div id="wrapper">
    
    <?php include 'UI/sidebar.php'; // Sertakan sidebar ?>
    
    <div class="main-content">
        
        <?php include 'UI/navbar.php'; // Sertakan navbar ?>
        
        <main class="content glass-dashboard-bg">
            <div class="container-fluid p-0 content-container">

                <h1 class="h3 mb-3"><strong>Dashboard</strong> Data Tamu</h1>

                <?php
                if (isset($_SESSION['flash_message'])) {
                    $message = $_SESSION['flash_message'];
                    
                    // Tampilkan Bootstrap alert
                    echo '<div class="alert alert-' . htmlspecialchars($message['type']) . ' alert-dismissible fade show" role="alert">';
                    echo htmlspecialchars($message['message']);
                    echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
                    echo '</div>';
                    
                    // Hapus flash message setelah ditampilkan
                    unset($_SESSION['flash_message']);
                }
                ?>

                <div class="row">
                    <div class="col-12">
                        <div class="card glass-card border-0">
                            <div class="card-header glass-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <h5 class="card-title mb-0" style="font-weight: 700; color: #34495e;">Daftar Tamu</h5>
                                <a href="form_checkin.php" class="btn btn-glass-primary">
                                    <i class="bi bi-plus-circle me-2"></i>
                                    Check-In Tamu Baru
                                </a>
                            </div>
                            <!-- Search & Filter Bar -->
                            <div class="glass-filter-container">
                                <form method="GET" action="index.php" class="row g-3 align-items-center">
                                    <div class="col-md-6">
                                        <div class="input-group">
                                            <span class="input-group-text bg-white border-0 shadow-sm" style="border-radius: 12px 0 0 12px;"><i class="bi bi-search" style="color: #667eea;"></i></span>
                                            <input type="text" class="form-control border-0 shadow-sm" name="search" placeholder="Cari Nama Tamu, NIK, HP, Kamar, Kelas..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" style="border-radius: 0 12px 12px 0; padding: 0.75rem 1.25rem; font-weight: 500;">
                                        </div>
                                    </div>
                                    <div class="col-md-5">
                                        <div class="input-group shadow-sm" style="border-radius: 12px; overflow: hidden;">
                                            <span class="input-group-text bg-white border-0"><i class="bi bi-calendar-event text-info"></i></span>
                                            <input type="date" class="form-control border-0 text-muted" name="start_date" value="<?php echo htmlspecialchars($_GET['start_date'] ?? ''); ?>" title="Tanggal Check-In" style="font-size: 0.9rem;">
                                            <span class="input-group-text bg-light border-0 text-muted" style="font-size: 0.8rem;">s/d</span>
                                            <input type="date" class="form-control border-0 text-muted" name="end_date" value="<?php echo htmlspecialchars($_GET['end_date'] ?? ''); ?>" title="Tanggal Check-Out" style="font-size: 0.9rem;">
                                        </div>
                                    </div>
                                    <div class="col-md-1 d-flex gap-2 justify-content-end">
                                        <button type="submit" class="btn btn-primary bg-gradient shadow-sm" style="border-radius: 12px; width: 100%; border: none;" title="Terapkan Pencarian"><i class="bi bi-funnel-fill"></i></button>
                                        <a href="index.php" class="btn btn-secondary bg-gradient shadow-sm" style="border-radius: 12px; width: 100%; border: none;" title="Reset Semua Filter"><i class="bi bi-arrow-counterclockwise"></i></a>
                                    </div>
                                </form>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="data-tamu-table" class="table table-hover glass-table">
                                        <thead>
                                            <?php
                                            // Helper fungsi untuk link sorting kolom
                                            function sortLink($col, $current_col, $current_dir, $label) {
                                                $dir = ($current_col == $col && $current_dir == 'ASC') ? 'desc' : 'asc';
                                                $class = 'sortable';
                                                if ($current_col == $col) {
                                                    $class .= ($current_dir == 'ASC') ? ' sort-asc' : ' sort-desc';
                                                }
                                                // Retain existing filters
                                                $params = $_GET;
                                                $params['sort'] = $col;
                                                $params['dir'] = $dir;
                                                $qs = http_build_query($params);
                                                return "<a href=\"?$qs\" class=\"$class\">$label</a>";
                                            }
                                            ?>
                                            <tr>
                                                <th>#</th>
                                                <th><?php echo sortLink('nama_tamu', $sort_column, $sort_dir, 'Nama Tamu'); ?></th>
                                                <th><?php echo sortLink('nik', $sort_column, $sort_dir, 'NIK'); ?></th>
                                                <th><?php echo sortLink('no_hp', $sort_column, $sort_dir, 'No. Handphone'); ?></th>
                                                <th><?php echo sortLink('nama_kamar', $sort_column, $sort_dir, 'Kamar'); ?></th>
                                                <th><?php echo sortLink('nama_kelas', $sort_column, $sort_dir, 'Kelas'); ?></th>
                                                <th><?php echo sortLink('tanggal_check_in', $sort_column, $sort_dir, 'Check-In'); ?></th>
                                                <th><?php echo sortLink('tanggal_check_out', $sort_column, $sort_dir, 'Check-Out'); ?></th>
                                                <th><?php echo sortLink('status', $sort_column, $sort_dir, 'Status'); ?></th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            // 7. Loop untuk menampilkan data tamu
                                            if ($result && $result->num_rows > 0) {
                                                $i = 1;
                                                while($row = $result->fetch_assoc()) {
                                                    echo '<tr>';
                                                    echo '<td>' . $i++ . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['nama_tamu']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['nik'] ?? '-') . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['no_hp']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['nama_kamar']) . '</td>';
                                                    echo '<td>' . htmlspecialchars($row['nama_kelas'] ?? '-') . '</td>';
                                                    echo '<td>' . date('d M Y', strtotime($row['tanggal_check_in'])) . '</td>';
                                                    echo '<td>' . date('d M Y', strtotime($row['tanggal_check_out'])) . '</td>';
                                                    
                                                    // ================================================== //
                                                    //              ** AWAL PERUBAHAN ** //
                                                    // ================================================== //
                                                    
                                                    // Logika Badge Status yang Diperbarui
                                                    $status_text = '';
                                                    $status_class = '';
                                                    
                                                    switch ($row['status']) {
                                                        case 'active':
                                                            // Evaluasi apakah tanggal check-out telah berlalu (H+1 atau lebih)
                                                            if (strtotime($row['tanggal_check_out']) < strtotime('today')) {
                                                                $status_text = 'Selesai';
                                                                $status_class = 'status-checkout';
                                                                // Mutate row status so the action button below hides itself
                                                                $row['status'] = 'checkout'; 
                                                            } else {
                                                                $status_text = 'Aktif';
                                                                $status_class = 'status-active';
                                                            }
                                                            break;
                                                        case 'cancelled':
                                                            $status_text = 'Dibatalkan';
                                                            $status_class = 'status-cancelled';
                                                            break;
                                                        case 'checkout':
                                                        case 'checked_out':
                                                        default:
                                                            // Jika status 'checkout', 'checked_out', NULL, '', atau apa pun selain 'active' / 'cancelled'
                                                            $status_text = 'Selesai';
                                                            $status_class = 'status-checkout';
                                                    }
                                                    
                                                    echo '<td><span class="status-badge ' . $status_class . '">' . $status_text . '</span></td>';
                                                    
                                                    // ================================================== //
                                                    //               ** AKHIR PERUBAHAN ** //
                                                    // ================================================== //
                                                    
                                                    // Tombol Aksi
                                                    echo '<td class="action-buttons">';
                                                    echo '<a href="detail_tamu.php?id=' . $row['id'] . '" class="btn btn-sm btn-info" title="Lihat Detail"><i class="bi bi-eye"></i></a>';
                                                    
                                                    if ($row['status'] == 'active') {
                                                        echo '<a href="actions/checkout_tamu.php?id=' . $row['id'] . '" 
                                                               class="btn btn-sm btn-warning" 
                                                               title="Checkout Tamu" 
                                                               onclick="return confirm(\'Apakah Anda yakin ingin checkout tamu ini? Kamar akan dikosongkan.\')">
                                                               <i class="bi bi-box-arrow-right"></i>
                                                             </a>';
                                                    }
                                                    
                                                    echo '</td>';
                                                    echo '</tr>';
                                                }
                                            } else {
                                                echo '<tr><td colspan="10" class="text-center">Belum ada data tamu.</td></tr>';
                                            }
                                            
                                            // 8. Tutup koneksi database
                                            $conn->close();
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </main>
        
    </div>
</div>

<?php include 'UI/settings.php'; // Sertakan settings ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<?php include 'UI/footer.php'; // Sertakan footer ?>

</body>
</html>