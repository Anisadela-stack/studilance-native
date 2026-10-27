
<?php
session_start();

require_once __DIR__ . "/config/database.php";

// Pastikan pengguna sudah login
if (!isset($_SESSION["user_id"])) {
    header("Location: auth/login.php");
    exit;
}

// Fungsi untuk mengamankan teks
function e($value) {
    return htmlspecialchars(
        (string) ($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

// Ambil informasi akun dari session
$userId = (int) $_SESSION["user_id"];
$name = $_SESSION["user_name"] ?? "Pengguna";
$role = $_SESSION["user_role"] ?? "";

// Data notifikasi mahasiswa
$pending = 0;
$accepted = 0;
$rejected = 0;
$recentApplications = [];

if ($role === "student") {
    // Menghitung jumlah lamaran berdasarkan status
    $stmtStats = $conn->prepare(
        "SELECT
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) AS accepted,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected
         FROM applications
         WHERE student_id = ?"
    );

    $stmtStats->bind_param("i", $userId);
    $stmtStats->execute();
    $statsResult = $stmtStats->get_result();
    $stats = $statsResult->fetch_assoc();

    $pending = (int) ($stats["pending"] ?? 0);
    $accepted = (int) ($stats["accepted"] ?? 0);
    $rejected = (int) ($stats["rejected"] ?? 0);

    $stmtStats->close();

    // Mengambil lima lamaran terbaru milik mahasiswa
    // Mengambil lima lamaran terbaru milik mahasiswa
$stmtRecent = $conn->prepare(
    "SELECT
        applications.id,
        applications.status,
        applications.created_at,
        applications.decided_at,
        jobs.title AS job_title
     FROM applications
     INNER JOIN jobs ON applications.job_id = jobs.id
     WHERE applications.student_id = ?
     ORDER BY applications.created_at DESC
     LIMIT 5"
);

$stmtRecent->bind_param("i", $userId);
$stmtRecent->execute();

$recentResult = $stmtRecent->get_result();

while ($row = $recentResult->fetch_assoc()) {
    $recentApplications[] = $row;
}

$stmtRecent->close();
}


/* Statistik khusus dashboard klien */
$totalClientJobs = 0;
$totalClientApplications = 0;
$clientPendingApplications = 0;
$clientAcceptedApplications = 0;

if ($role === "client") {
    $stmtClientStats = $conn->prepare(
        "SELECT
            (SELECT COUNT(*)
             FROM jobs
             WHERE client_id = ?) AS total_jobs,

            (SELECT COUNT(*)
             FROM applications a
             INNER JOIN jobs j ON a.job_id = j.id
             WHERE j.client_id = ?) AS total_applications,

            (SELECT COUNT(*)
             FROM applications a
             INNER JOIN jobs j ON a.job_id = j.id
             WHERE j.client_id = ?
               AND a.status = 'pending') AS pending_applications,

            (SELECT COUNT(*)
             FROM applications a
             INNER JOIN jobs j ON a.job_id = j.id
             WHERE j.client_id = ?
               AND a.status = 'accepted') AS accepted_applications"
    );

    $stmtClientStats->bind_param(
        "iiii",
        $userId,
        $userId,
        $userId,
        $userId
    );

    $stmtClientStats->execute();

    $clientStats = $stmtClientStats->get_result()->fetch_assoc();

    $totalClientJobs = (int) ($clientStats["total_jobs"] ?? 0);
    $totalClientApplications = (int) ($clientStats["total_applications"] ?? 0);
    $clientPendingApplications = (int) ($clientStats["pending_applications"] ?? 0);
    $clientAcceptedApplications = (int) ($clientStats["accepted_applications"] ?? 0);

    $stmtClientStats->close();
}


$statusLabels = [
    "pending" => "Menunggu Keputusan",
    "accepted" => "Diterima",
    "rejected" => "Ditolak"
];

$statusClasses = [
    "pending" => "pending",
    "accepted" => "accepted",
    "rejected" => "rejected"
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard | Studilance</title>

    <style>
        
.client-stat-card {
    display: block;
    text-decoration: none;
    color: inherit;
    cursor: pointer;
    transition: 0.2s;
}

.client-stat-card:hover {
    border-color: #2457c5;
    background: #f7f9ff;
    transform: translateY(-2px);
}

.client-stat-card small {
    display: block;
    margin-top: 10px;
    color: #2457c5;
    font-size: 13px;
}

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f3f6fb;
            color: #172033;
        }

        header {
            background: white;
            padding: 20px 7%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e4e7ec;
        }

        header strong {
            color: #2457c5;
            font-size: 24px;
        }

        main {
            max-width: 900px;
            margin: 50px auto;
            padding: 0 20px;
        }

        .panel {
            background: white;
            border-radius: 16px;
            padding: 35px;
            box-shadow: 0 8px 30px rgba(20, 40, 80, .06);
        }

        h1 {
            font-size: 32px;
            margin-top: 0;
            margin-bottom: 12px;
        }

        h2 {
            margin-top: 32px;
            margin-bottom: 10px;
        }

        .description {
            color: #667085;
            line-height: 1.7;
        }

        .role {
            display: inline-block;
            padding: 8px 14px;
            background: #eaf0ff;
            color: #2457c5;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
        }

        .menu {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
            margin-top: 22px;
        }

        .menu a {
            display: block;
            padding: 20px;
            border: 1px solid #e4e7ec;
            border-radius: 12px;
            text-decoration: none;
            color: #172033;
            background: #fff;
            transition: 0.2s;
        }

        .menu a:hover {
            border-color: #2457c5;
            background: #f7f9ff;
            transform: translateY(-2px);
        }

        .menu a strong {
            display: block;
            color: #2457c5;
            margin-bottom: 8px;
            font-size: 16px;
        }

        .menu a span {
            display: block;
            color: #667085;
            font-size: 14px;
            line-height: 1.5;
        }

        .logout {
            color: #b42318;
            text-decoration: none;
            font-weight: bold;
        }

        .logout:hover {
            text-decoration: underline;
        }
        
/* Statistik dashboard klien */
.client-stats {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
    margin-top: 22px;
    margin-bottom: 28px;
}

.client-stat-card {
    background: white;
    border: 1px solid #e4e7ec;
    border-radius: 12px;
    padding: 20px;
}

.client-stat-card span {
    display: block;
    color: #667085;
    font-size: 14px;
    line-height: 1.5;
}

.client-stat-card strong {
    display: block;
    margin-top: 8px;
    color: #2457c5;
    font-size: 28px;
}

@media (max-width: 600px) {
    .client-stats {
        grid-template-columns: 1fr;
    }
}


        /* Bagian notifikasi mahasiswa */
        .notification-section {
            margin-top: 28px;
            padding: 22px;
            border: 1px solid #e4e7ec;
            border-radius: 12px;
            background: #fbfcff;
        }

        .notification-section h2 {
            margin-top: 0;
            margin-bottom: 8px;
        }

        .notification-stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin: 18px 0 24px;
        }

        .notification-stat {
            padding: 16px 12px;
            background: white;
            border: 1px solid #e4e7ec;
            border-radius: 10px;
        }

        .notification-stat span {
            display: block;
            color: #667085;
            font-size: 13px;
            line-height: 1.5;
        }

        .notification-stat strong {
            display: block;
            margin-top: 8px;
            font-size: 25px;
        }

        .notification-stat.pending strong {
            color: #9a6700;
        }

        .notification-stat.accepted strong {
            color: #067647;
        }

        .notification-stat.rejected strong {
            color: #b42318;
        }

        .notification-list {
            display: grid;
            gap: 12px;
        }

        .notification-item {
            padding: 16px;
            background: white;
            border: 1px solid #e4e7ec;
            border-radius: 10px;
        }

        .notification-item strong {
            display: block;
            margin-bottom: 7px;
            overflow-wrap: anywhere;
        }

        .notification-item p {
            margin: 0;
            color: #667085;
            font-size: 14px;
            line-height: 1.6;
        }

        .notification-item .notification-date {
            margin-top: 9px;
            font-size: 12px;
        }

        .status {
            display: inline-block;
            margin-bottom: 10px;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .status.pending {
            background: #fff4e5;
            color: #9a6700;
        }

        .status.accepted {
            background: #ecfdf3;
            color: #067647;
        }

        .status.rejected {
            background: #fff0f0;
            color: #b42318;
        }

        .notification-link {
            display: inline-block;
            margin-top: 16px;
            color: #2457c5;
            font-weight: bold;
            font-size: 14px;
            text-decoration: none;
        }

        .notification-link:hover {
            text-decoration: underline;
        }

        .empty-notification {
            padding: 16px;
            background: white;
            border: 1px dashed #d0d5dd;
            border-radius: 10px;
            color: #667085;
            font-size: 14px;
            line-height: 1.7;
        }

        @media (max-width: 600px) {
            header {
                padding: 18px 20px;
            }

            main {
                margin: 25px auto;
            }

            .panel {
                padding: 24px;
            }

            h1 {
                font-size: 26px;
            }

            .menu {
                grid-template-columns: 1fr;
            }

            .notification-section {
                padding: 16px;
            }

            .notification-stats {
                gap: 8px;
            }

            .notification-stat {
                padding: 12px 8px;
            }

            .notification-stat span {
                font-size: 12px;
            }

            .notification-stat strong {
                font-size: 22px;
            }
        }
        
/* ===== Penyempurnaan UI/UX Studilance ===== */

body {
    line-height: 1.6;
    -webkit-font-smoothing: antialiased;
}

header {
    position: sticky;
    top: 0;
    z-index: 10;
    min-height: 76px;
    box-shadow: 0 3px 14px rgba(23, 32, 51, 0.04);
}

header strong {
    letter-spacing: -0.7px;
}

main {
    width: 100%;
    max-width: 1080px;
    margin: 36px auto;
}

.panel {
    border: 1px solid #e8edf5;
    box-shadow: 0 12px 35px rgba(23, 32, 51, 0.06);
}

h1 {
    letter-spacing: -0.8px;
    line-height: 1.25;
}

h2 {
    letter-spacing: -0.3px;
}

.description {
    font-size: 15px;
}

.role {
    letter-spacing: 0.2px;
}

.menu a,
.client-stat-card,
.notification-item,
.notification-stat {
    transition:
        transform 0.2s ease,
        box-shadow 0.2s ease,
        border-color 0.2s ease;
}

.menu a,
.client-stat-card {
    border-radius: 14px;
}

.menu a {
    min-height: 112px;
}

.menu a:hover,
.client-stat-card:hover {
    box-shadow: 0 8px 20px rgba(36, 87, 197, 0.08);
}

.menu a:focus-visible,
.client-stat-card:focus-visible,
.notification-link:focus-visible,
.logout:focus-visible {
    outline: 3px solid #91b4ff;
    outline-offset: 3px;
}

.client-stat-card {
    padding: 24px;
}

.client-stat-card strong {
    font-size: 32px;
    letter-spacing: -0.7px;
}

.notification-section {
    margin-top: 32px;
    padding: 26px;
    border-color: #e2e9f6;
    border-radius: 16px;
}

.notification-stat {
    border-radius: 12px;
}

.notification-item {
    border-radius: 12px;
}

.notification-item:hover {
    border-color: #c5d5f5;
    box-shadow: 0 5px 16px rgba(23, 32, 51, 0.04);
}

.status {
    letter-spacing: 0.1px;
}

.notification-link {
    padding: 5px 0;
}

.empty-notification {
    border-radius: 12px;
}

@media (max-width: 600px) {
    header {
        min-height: 64px;
    }

    main {
        margin: 22px auto;
        padding: 0 14px;
    }

    .panel {
        padding: 22px 18px;
        border-radius: 14px;
    }

    .notification-section {
        padding: 16px;
    }

    .client-stat-card {
        padding: 18px;
    }

    .menu a {
        min-height: auto;
    }
}

@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        scroll-behavior: auto !important;
        transition-duration: 0.01ms !important;
        animation-duration: 0.01ms !important;
    }
}

/* ===== STUDILANCE: MODERN CREATIVE UI ===== */

:root {
    --primary: #2457c5;
    --primary-dark: #173f9a;
    --primary-light: #eaf1ff;
    --page-bg: #f3f6fc;
    --card-bg: #ffffff;
    --text-main: #172033;
    --text-muted: #667085;
    --border-color: #e4eaf4;
    --shadow-card: 0 10px 30px rgba(31, 64, 130, 0.07);
}

body {
    background:
        radial-gradient(
            circle at 8% 0%,
            rgba(73, 132, 255, 0.09),
            transparent 25%
        ),
        var(--page-bg);
    color: var(--text-main);
    line-height: 1.65;
}

header {
    background: rgba(255, 255, 255, 0.96);
    border-bottom: 1px solid var(--border-color);
    box-shadow: 0 4px 18px rgba(23, 32, 51, 0.04);
    position: sticky;
    top: 0;
    z-index: 20;
    min-height: 76px;
}

header strong {
    color: var(--primary);
    font-size: 26px;
    letter-spacing: -1px;
}

.logout {
    padding: 9px 15px;
    background: #fff1f1;
    border-radius: 9px;
    transition: background 0.2s ease;
}

.logout:hover {
    background: #ffe0e0;
    text-decoration: none;
}

main {
    max-width: 1080px;
    width: 100%;
    margin: 38px auto;
}

.panel {
    padding: 36px;
    border: 1px solid rgba(224, 232, 245, 0.9);
    border-radius: 22px;
    background: var(--card-bg);
    box-shadow: var(--shadow-card);
}

h1 {
    color: #15274b;
    font-size: clamp(26px, 4vw, 36px);
    letter-spacing: -1.1px;
    line-height: 1.25;
}

h2 {
    color: #1b2f55;
    letter-spacing: -0.4px;
}

.description {
    color: var(--text-muted);
    line-height: 1.8;
}

.role {
    background: var(--primary-light);
    color: var(--primary);
    border: 1px solid #d9e5ff;
    padding: 8px 15px;
}

.menu {
    gap: 18px;
}

.menu a {
    position: relative;
    overflow: hidden;
    min-height: 120px;
    padding: 23px;
    border: 1px solid var(--border-color);
    border-radius: 16px;
    background: linear-gradient(145deg, #ffffff, #f9fbff);
    transition:
        transform 0.2s ease,
        box-shadow 0.2s ease,
        border-color 0.2s ease;
}

.menu a::before {
    content: "";
    position: absolute;
    left: 0;
    top: 0;
    width: 4px;
    height: 100%;
    background: var(--primary);
    opacity: 0;
    transition: opacity 0.2s ease;
}

.menu a:hover {
    border-color: #b9cefa;
    background: #f7faff;
    box-shadow: 0 10px 24px rgba(36, 87, 197, 0.09);
    transform: translateY(-3px);
}

.menu a:hover::before {
    opacity: 1;
}

.menu a strong {
    color: var(--primary);
    font-size: 17px;
    margin-bottom: 9px;
}

.menu a span {
    line-height: 1.7;
}

.client-stats {
    gap: 18px;
    margin-top: 26px;
    margin-bottom: 32px;
}

.client-stat-card {
    position: relative;
    overflow: hidden;
    padding: 24px;
    border: 1px solid var(--border-color);
    border-radius: 16px;
    background: linear-gradient(145deg, #ffffff, #f7faff);
    box-shadow: 0 5px 18px rgba(31, 64, 130, 0.035);
}

.client-stat-card::after {
    content: "";
    position: absolute;
    width: 65px;
    height: 65px;
    right: -24px;
    top: -24px;
    border-radius: 50%;
    background: rgba(36, 87, 197, 0.07);
}

.client-stat-card strong {
    color: var(--primary);
    font-size: 34px;
}

.client-stat-card small {
    font-weight: 600;
}

.client-stat-card:hover {
    border-color: #b9cefa;
    box-shadow: 0 10px 24px rgba(36, 87, 197, 0.09);
}

.notification-section {
    padding: 26px;
    border: 1px solid #e1e9f8;
    border-radius: 18px;
    background: linear-gradient(160deg, #f8faff, #ffffff 65%);
}

.notification-stats {
    gap: 14px;
}

.notification-stat {
    border: 1px solid var(--border-color);
    border-radius: 13px;
    box-shadow: 0 4px 12px rgba(31, 64, 130, 0.025);
}

.notification-stat strong {
    font-size: 29px;
}

.notification-list {
    gap: 14px;
}

.notification-item {
    padding: 19px;
    border: 1px solid var(--border-color);
    border-radius: 13px;
    background: #ffffff;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.notification-item:hover {
    border-color: #c4d5f7;
    box-shadow: 0 6px 18px rgba(31, 64, 130, 0.05);
}

.status {
    padding: 7px 12px;
    letter-spacing: 0.1px;
}

.status.pending {
    background: #fff5dc;
    color: #946200;
}

.status.accepted {
    background: #e7f8ef;
    color: #087443;
}

.status.rejected {
    background: #fff0f0;
    color: #b42318;
}

.notification-link {
    padding: 8px 0;
    font-size: 14px;
}

.menu a:focus-visible,
.client-stat-card:focus-visible,
.logout:focus-visible,
.notification-link:focus-visible {
    outline: 3px solid #91b4ff;
    outline-offset: 4px;
}

@media (max-width: 700px) {
    main {
        margin: 22px auto;
        padding: 0 14px;
    }

    .panel {
        padding: 23px 18px;
        border-radius: 16px;
    }

    header {
        padding: 17px 20px;
    }

    .notification-section {
        padding: 17px;
    }

    .menu {
        grid-template-columns: 1fr;
    }

    .menu a {
        min-height: auto;
        padding: 20px;
    }

    .notification-stats {
        gap: 8px;
    }

    .notification-stat {
        padding: 12px 8px;
    }

    .notification-stat span {
        font-size: 11px;
    }

    .notification-stat strong {
        font-size: 23px;
    }

    .client-stat-card {
        padding: 19px;
    }
}

@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        transition: none !important;
    }
}

    </style>
</head>

<body>

<header>
    <strong>Studilance</strong>
    <a class="logout" href="auth/logout.php">Logout</a>
</header>

<main>
    <section class="panel">

        <h1>Halo, <?= e($name) ?>! 👋</h1>

        <p class="description">
            Selamat datang di Studilance.
            Temukan peluang freelance dan kembangkan skill kamu!
        </p>

        <p>Peran akun kamu:</p>

        <span class="role">
            <?= $role === "student"
                ? "Mahasiswa"
                : ($role === "client" ? "Klien" : "Tidak dikenal") ?>
        </span>

        <?php if ($role === "student"): ?>

            <!-- NOTIFIKASI MAHASISWA -->
            <section class="notification-section">
                <h2>🔔 Notifikasi Lamaran</h2>

                <p class="description">
                    Pantau perkembangan lamaran proyek freelance kamu
                    di sini.
                </p>

                <div class="notification-stats">
                    <div class="notification-stat pending">
                        <span>Menunggu</span>
                        <strong><?= $pending ?></strong>
                    </div>

                    <div class="notification-stat accepted">
                        <span>Diterima</span>
                        <strong><?= $accepted ?></strong>
                    </div>

                    <div class="notification-stat rejected">
                        <span>Ditolak</span>
                        <strong><?= $rejected ?></strong>
                    </div>
                </div>

                <h3>Aktivitas Lamaran Terbaru</h3>

                <?php if (count($recentApplications) === 0): ?>

                    <div class="empty-notification">
                        Kamu belum mengirim lamaran.
                        Yuk, cari proyek yang sesuai dengan skill kamu!
                    </div>

                <?php else: ?>

                    <div class="notification-list">
                        <?php foreach ($recentApplications as $application): ?>
                            <?php
                                $status = $application["status"];
                                $statusLabel = $statusLabels[$status]
                                    ?? $status;
                                $statusClass = $statusClasses[$status]
                                    ?? "pending";

                                if ($status === "accepted") {
                                    $message = "Selamat! Lamaran kamu telah diterima klien.";
                                } elseif ($status === "rejected") {
                                    $message = "Lamaran kamu belum diterima klien. Tetap semangat mencari peluang lainnya!";
                                } else {
                                    $message = "Lamaran kamu sedang menunggu keputusan klien.";
                                }
                            ?>

                            <article class="notification-item">
                                <span class="status <?= e($statusClass) ?>">
                                    <?= e($statusLabel) ?>
                                </span>

                                <strong>
                                    <?= e($application["job_title"]) ?>
                                </strong>

                                <p><?= e($message) ?></p>

                                <?php if (
    in_array($status, ["accepted", "rejected"], true)
    && !empty($application["decided_at"])
): ?>

    <p class="notification-date">
        Keputusan klien:
        <?= e(date(
            "d-m-Y H:i",
            strtotime($application["decided_at"])
        )) ?>
    </p>

<?php else: ?>

    <p class="notification-date">
        Dikirim:
        <?= e(date(
            "d-m-Y H:i",
            strtotime($application["created_at"])
        )) ?>
    </p>

<?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>

                <?php endif; ?>

                <a class="notification-link" href="my_applications.php">
                    Lihat Semua Lamaran →
                </a>
            </section>

        <?php endif; ?>

        <h2>Dashboard</h2>

        <?php if ($role === "student"): ?>

            <!-- DASHBOARD MAHASISWA -->
            <p class="description">
                Cari proyek sesuai kemampuanmu dan pantau status
                lamaran yang sudah kamu kirim.
            </p>

            <div class="menu">

                <a href="profile.php">
                    <strong>Profil Saya</strong>
                    <span>
                        Edit nama dan deskripsi profil akunmu.
                    </span>
                </a>

                <a href="jobs.php">
                    <strong>Lihat Daftar Proyek</strong>
                    <span>
                        Temukan proyek freelance yang tersedia.
                    </span>
                </a>

                <a href="my_applications.php">
                    <strong>Lamaran Saya</strong>
                    <span>
                        Lihat proposal dan status lamaranmu.
                    </span>
                </a>

            </div>

        <?php elseif ($role === "client"): ?>
            

<section class="client-stats">

    <!-- Total Proyek -->
    <a href="jobs.php" class="client-stat-card">
        <span>Total Proyek Saya</span>
        <strong><?= $totalClientJobs ?></strong>
        <small>Lihat daftar proyek →</small>
    </a>

    <!-- Semua Lamaran -->
    <a href="manage_applications.php?status=all" class="client-stat-card">
        <span>Total Lamaran Masuk</span>
        <strong><?= $totalClientApplications ?></strong>
        <small>Lihat semua lamaran →</small>
    </a>

    <!-- Lamaran Pending -->
    <a href="manage_applications.php?status=pending" class="client-stat-card">
        <span>Menunggu Keputusan</span>
        <strong><?= $clientPendingApplications ?></strong>
        <small>Periksa lamaran →</small>
    </a>

    <!-- Lamaran Diterima -->
    <a href="manage_applications.php?status=accepted" class="client-stat-card">
        <span>Lamaran Diterima</span>
        <strong><?= $clientAcceptedApplications ?></strong>
        <small>Lihat lamaran diterima →</small>
    </a>

</section>


            <!-- DASHBOARD KLIEN -->
            <p class="description">
                Publikasikan proyek freelance dan kelola lamaran
                mahasiswa yang tertarik dengan proyekmu.
            </p>

            <div class="menu">

                <a href="freelancers.php">
                    <strong>Cari Freelancer</strong>
                    <span>
                        Temukan mahasiswa berdasarkan skill
                        dan keahlian.
                    </span>
                </a>

                <a href="jobs.php">
                    <strong>Daftar Proyek</strong>
                    <span>
                        Lihat proyek freelance yang tersedia.
                    </span>
                </a>

                <a href="create_job.php">
                    <strong>Buat Proyek Baru</strong>
                    <span>
                        Publikasikan pekerjaan dan tentukan anggaran.
                    </span>
                </a>

                <a href="manage_applications.php">
                    <strong>Kelola Lamaran</strong>
                    <span>
                        Periksa proposal mahasiswa dan tentukan keputusan.
                    </span>
                </a>

            </div>

        <?php else: ?>

            <p class="description">
                Peran akun tidak dikenali. Silakan logout,
                lalu login kembali menggunakan akun yang benar.
            </p>

        <?php endif; ?>

    </section>
</main>

</body>
</html>
