
<?php
session_start();

require_once __DIR__ . "/config/database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: auth/login.php");
    exit;
}

function e($value) {
    return htmlspecialchars(
        (string) ($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

$userId = (int) $_SESSION["user_id"];
$name = $_SESSION["user_name"] ?? "Pengguna";
$role = $_SESSION["user_role"] ?? "";

$pending = 0;
$accepted = 0;
$rejected = 0;
$recentApplications = [];

/* Statistik dan notifikasi mahasiswa */
if ($role === "student") {
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
    $stats = $stmtStats->get_result()->fetch_assoc();

    $pending = (int) ($stats["pending"] ?? 0);
    $accepted = (int) ($stats["accepted"] ?? 0);
    $rejected = (int) ($stats["rejected"] ?? 0);

    $stmtStats->close();

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

/* Statistik dashboard klien */
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
    <link rel="stylesheet" href="assets/style.css">
    <title>Dashboard | Studilance</title>

    <!-- Font konsisten untuk Studilance -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        * {
            box-sizing: border-box;
        }

        :root {
            --primary: #2457C5;
            --primary-dark: #173F9A;
            --primary-light: #EAF1FF;
            --page-bg: #F3F6FC;
            --card-bg: #FFFFFF;
            --text-main: #172033;
            --text-muted: #667085;
            --border-color: #E4EAF4;
            --shadow-card: 0 10px 30px rgba(31, 64, 130, 0.07);
        }

        /* Font utama untuk semua halaman dashboard */
        html {
            font-family: 'Inter', sans-serif;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
            font-size: 15px;
            font-weight: 400;
            color: var(--text-main);
            background: linear-gradient(
                135deg,
                #FFFFFF 0%,
                #F1F4FF 50%,
                #DFE8FF 100%
            );
        }

        /* Elemen formulir mengikuti font utama */
        button,
        input,
        textarea,
        select {
            font-family: 'Inter', sans-serif;
            font-size: inherit;
        }

        a {
            font-family: 'Inter', sans-serif;
        }

        /* Judul menggunakan Poppins */
        h1,
        h2,
        h3,
        h4,
        header strong {
            font-family: 'Poppins', sans-serif;
        }

        header {
            background: #FFFFFF;
            min-height: 82px;
            padding: 18px 7%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
        }

        header strong {
            color: var(--primary);
            font-size: 27px;
            font-weight: 700;
            letter-spacing: -1px;
        }

        .logout {
            color: #C62828;
            text-decoration: none;
            font-weight: 600;
            padding: 10px 15px;
            border-radius: 10px;
            transition: 0.2s;
        }

        .logout:hover {
            background: #FFF0F0;
        }

        main {
            width: min(1100px, 92%);
            margin: 32px auto;
        }

        .panel {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 34px;
            box-shadow: var(--shadow-card);
        }

        h1 {
            font-size: clamp(27px, 4vw, 36px);
            line-height: 1.3;
            margin: 0 0 12px;
            font-weight: 600;
            letter-spacing: -0.7px;
        }

        h2 {
            font-size: 23px;
            font-weight: 600;
            margin-top: 30px;
            margin-bottom: 12px;
        }

        h3 {
            font-size: 17px;
            font-weight: 600;
        }

        .description {
            color: var(--text-muted);
            line-height: 1.8;
            margin: 0 0 20px;
        }

        .role {
            display: inline-block;
            padding: 8px 15px;
            background: var(--primary-light);
            color: var(--primary);
            border-radius: 30px;
            font-size: 13px;
            font-weight: 700;
        }

        /* Banner sambutan klien */
        .client-hero {
            background: linear-gradient(120deg, #2457C5, #4779E8);
            color: #FFFFFF;
            padding: 38px;
            border-radius: 24px;
            margin: 0 0 26px;
            box-shadow: 0 12px 30px rgba(36, 87, 197, 0.16);
        }

        .client-hero h1 {
            color: #FFFFFF;
            font-size: clamp(28px, 4vw, 38px);
            margin: 0 0 14px;
        }

        .client-hero p {
            color: #FFFFFF;
            opacity: 0.94;
            line-height: 1.8;
            margin: 0;
        }

        .client-hero .role {
            margin-top: 20px;
            background: rgba(255, 255, 255, 0.18);
            color: #FFFFFF;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .client-heading {
            margin-top: 32px;
            margin-bottom: 10px;
        }

        .client-subtitle {
            color: var(--text-muted);
            line-height: 1.7;
            margin-bottom: 22px;
        }

        /* Statistik klien */
        .client-stats {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
            margin: 26px 0;
        }

        .client-stat-card {
            display: block;
            min-width: 0;
            padding: 23px;
            color: inherit;
            text-decoration: none;
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: 18px;
            box-shadow: 0 4px 15px rgba(31, 64, 130, 0.03);
            transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
        }

        .client-stat-card:hover {
            border-color: var(--primary);
            transform: translateY(-3px);
            box-shadow: var(--shadow-card);
        }

        .client-stat-card span {
            display: block;
            color: var(--text-muted);
            font-size: 14px;
            line-height: 1.6;
        }

        .client-stat-card strong {
            display: block;
            margin-top: 10px;
            color: var(--primary);
            font-size: 34px;
            font-weight: 700;
        }

        .client-stat-card small {
            display: block;
            margin-top: 12px;
            color: var(--primary);
            font-size: 13px;
            font-weight: 700;
        }

        /* Menu dashboard */
        .menu {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
            margin-top: 24px;
        }

        .menu a {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 12px;
            min-width: 0;
            padding: 24px;
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: 18px;
            text-decoration: none;
            color: var(--text-main);
            transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
        }

        .menu a:hover {
            border-color: var(--primary);
            background: #FAFCFF;
            transform: translateY(-3px);
            box-shadow: var(--shadow-card);
        }

        .menu a strong {
            display: block;
            color: var(--primary);
            font-family: 'Poppins', sans-serif;
            font-size: 17px;
            font-weight: 600;
            margin: 0;
        }

        .menu a span {
            display: block;
            color: var(--text-muted);
            font-size: 14px;
            line-height: 1.7;
        }

        /* Notifikasi mahasiswa */
        .notification-section {
            margin-top: 28px;
            padding: 24px;
            background: #FAFCFF;
            border: 1px solid var(--border-color);
            border-radius: 18px;
        }

        .notification-section h2 {
            margin-top: 0;
            margin-bottom: 10px;
        }

        .notification-stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin: 20px 0 26px;
        }

        .notification-stat {
            padding: 17px;
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: 14px;
        }

        .notification-stat span {
            display: block;
            color: var(--text-muted);
            font-size: 13px;
            line-height: 1.5;
        }

        .notification-stat strong {
            display: block;
            margin-top: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 25px;
            font-weight: 600;
        }

        .notification-stat.pending strong {
            color: #9A6700;
        }

        .notification-stat.accepted strong {
            color: #067647;
        }

        .notification-stat.rejected strong {
            color: #B42318;
        }

        .notification-list {
            display: grid;
            gap: 12px;
        }

        .notification-item {
            padding: 18px;
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: 14px;
        }

        .notification-item strong {
            display: block;
            margin-bottom: 8px;
            overflow-wrap: anywhere;
            font-weight: 600;
        }

        .notification-item p {
            margin: 0;
            color: var(--text-muted);
            font-size: 14px;
            line-height: 1.7;
        }

        .notification-item .notification-date {
            margin-top: 10px;
            font-size: 12px;
        }

        .status {
            display: inline-block;
            margin-bottom: 10px;
            padding: 6px 11px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }

        .status.pending {
            background: #FFF4E5;
            color: #9A6700;
        }

        .status.accepted {
            background: #ECFDF3;
            color: #067647;
        }

        .status.rejected {
            background: #FFF0F0;
            color: #B42318;
        }

        .notification-link {
            display: inline-block;
            margin-top: 18px;
            color: var(--primary);
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
        }

        .notification-link:hover {
            text-decoration: underline;
        }

        .empty-notification {
            padding: 18px;
            background: #FFFFFF;
            border: 1px dashed #CBD5E1;
            border-radius: 14px;
            color: var(--text-muted);
            font-size: 14px;
            line-height: 1.7;
        }

        /* Tampilan HP */
        @media (max-width: 650px) {
            header {
                min-height: 72px;
                padding: 16px 5%;
            }

            header strong {
                font-size: 23px;
            }

            main {
                width: 92%;
                margin: 22px auto;
            }

            .panel {
                padding: 22px;
                border-radius: 20px;
            }

            .client-stats,
            .menu {
                grid-template-columns: 1fr;
                gap: 13px;
            }

            .client-stat-card,
            .menu a {
                padding: 20px;
                border-radius: 15px;
            }

            .notification-section {
                padding: 17px;
            }

            .notification-stats {
                gap: 7px;
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

            .client-hero {
                padding: 26px 22px;
                border-radius: 20px;
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

        <?php if ($role === "client"): ?>

            <section class="client-hero">
                <h1>Halo, <?= e($name) ?>! 👋</h1>
                <p>
                    Selamat datang di Studilance. Temukan talenta terbaik,
                    publikasikan proyek, dan kelola lamaran mahasiswa
                    dengan lebih mudah.
                </p>
                <span class="role">Dashboard Klien</span>
            </section>

        <?php else: ?>

            <h1>Halo, <?= e($name) ?>! 👋</h1>

            <p class="description">
                Selamat datang di Studilance.
                Temukan peluang freelance dan kembangkan skill kamu!
            </p>

            <p>Peran akun kamu:</p>

            <span class="role">
                <?= $role === "student" ? "Mahasiswa" : "Tidak dikenal" ?>
            </span>

        <?php endif; ?>

        <?php if ($role === "student"): ?>

            <section class="notification-section">
                <h2>🔔 Notifikasi Lamaran</h2>

                <p class="description">
                    Pantau perkembangan lamaran proyek freelance kamu di sini.
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
                                $statusLabel = $statusLabels[$status] ?? $status;
                                $statusClass = $statusClasses[$status] ?? "pending";

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

        <h2 class="<?= $role === 'client' ? 'client-heading' : '' ?>">
            <?= $role === "client" ? "Ringkasan Aktivitas" : "Dashboard" ?>
        </h2>

        <?php if ($role === "student"): ?>

            <p class="description">
                Cari proyek sesuai kemampuanmu dan pantau status
                lamaran yang sudah kamu kirim.
            </p>

            <div class="menu">

                <a href="profile.php">
                    <strong>Profil Saya</strong>
                    <span>Edit nama dan deskripsi profil akunmu.</span>
                </a>

                <a href="jobs.php">
                    <strong>Lihat Daftar Proyek</strong>
                    <span>Temukan proyek freelance yang tersedia.</span>
                </a>

                <a href="work_history.php">
                    <strong>Riwayat Pekerjaan</strong>
                    <span>Lihat pengalaman dan riwayat pekerjaan kamu.</span>
                </a>

                <a href="my_applications.php">
                    <strong>Lamaran Saya</strong>
                    <span>Lihat proposal dan status lamaranmu.</span>
                </a>

            </div>

        <?php elseif ($role === "client"): ?>

            <section class="client-stats">

                <a href="jobs.php" class="client-stat-card">
                    <span>Total Proyek Saya</span>
                    <strong><?= $totalClientJobs ?></strong>
                    <small>Lihat daftar proyek →</small>
                </a>

                <a href="manage_applications.php?status=all" class="client-stat-card">
                    <span>Total Lamaran Masuk</span>
                    <strong><?= $totalClientApplications ?></strong>
                    <small>Lihat semua lamaran →</small>
                </a>

                <a href="manage_applications.php?status=pending" class="client-stat-card">
                    <span>Menunggu Keputusan</span>
                    <strong><?= $clientPendingApplications ?></strong>
                    <small>Periksa lamaran →</small>
                </a>

                <a href="manage_applications.php?status=accepted" class="client-stat-card">
                    <span>Lamaran Diterima</span>
                    <strong><?= $clientAcceptedApplications ?></strong>
                    <small>Lihat lamaran diterima →</small>
                </a>

            </section>

            <p class="description">
                Publikasikan proyek freelance dan kelola lamaran
                mahasiswa yang tertarik dengan proyekmu.
            </p>

            <div class="menu">

                <a href="freelancers.php">
                    <strong>Cari Freelancer</strong>
                    <span>
                        Temukan mahasiswa berdasarkan skill dan keahlian.
                    </span>
                </a>

                <a href="jobs.php">
                    <strong>Daftar Proyek</strong>
                    <span>Lihat proyek freelance yang tersedia.</span>
                </a>

                <a href="create_job.php">
                    <strong>Buat Proyek Baru</strong>
                    <span>Publikasikan pekerjaan dan tentukan anggaran.</span>
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
