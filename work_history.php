<?php
session_start();
require_once "config/database.php";

// Pastikan pengguna sudah login
if (!isset($_SESSION["user_id"])) {
    header("Location: auth/login.php");
    exit;
}

// Halaman khusus mahasiswa
if (($_SESSION["user_role"] ?? "") !== "student") {
    http_response_code(403);
    exit("Akses ditolak. Halaman ini hanya untuk mahasiswa.");
}

$userId = (int) $_SESSION["user_id"];
$userName = $_SESSION["user_name"] ?? "Mahasiswa";

// Filter pekerjaan
$filter = $_GET["filter"] ?? "all";
$allowedFilters = ["all", "in_progress", "completed", "closed"];

if (!in_array($filter, $allowedFilters, true)) {
    $filter = "all";
}

// Pencarian proyek atau klien
$search = trim($_GET["search"] ?? "");

// Pengurutan
$sort = $_GET["sort"] ?? "newest";

if (!in_array($sort, ["newest", "oldest"], true)) {
    $sort = "newest";
}

// Statistik pekerjaan mahasiswa
$statsSql = "
    SELECT
        COUNT(*) AS total,
        COALESCE(SUM(j.status = 'in_progress'), 0) AS ongoing,
        COALESCE(SUM(j.status = 'completed'), 0) AS completed,
        COALESCE(SUM(j.status = 'closed'), 0) AS closed_count
    FROM applications a
    INNER JOIN jobs j ON a.job_id = j.id
    WHERE a.student_id = ?
      AND a.status = 'accepted'
      AND j.status IN ('in_progress', 'completed', 'closed')
";

$statsStmt = $conn->prepare($statsSql);
$statsStmt->bind_param("i", $userId);
$statsStmt->execute();

$stats = $statsStmt->get_result()->fetch_assoc();
$statsStmt->close();

$totalJobs = (int) ($stats["total"] ?? 0);
$ongoingJobs = (int) ($stats["ongoing"] ?? 0);
$completedJobs = (int) ($stats["completed"] ?? 0);
$closedJobs = (int) ($stats["closed_count"] ?? 0);

// Query daftar pekerjaan
$sql = "
    SELECT
        a.id AS application_id,
        a.proposal,
        a.created_at AS application_date,
        a.decided_at,
        j.id AS job_id,
        j.title AS job_title,
        j.category,
        j.description,
        j.budget,
        j.deadline,
        j.status AS job_status,
        u.name AS client_name
    FROM applications a
    INNER JOIN jobs j ON a.job_id = j.id
    INNER JOIN users u ON j.client_id = u.id
    WHERE a.student_id = ?
      AND a.status = 'accepted'
      AND j.status IN ('in_progress', 'completed', 'closed')
";

if ($filter !== "all") {
    $sql .= " AND j.status = ?";
}

if ($search !== "") {
    $sql .= " AND (j.title LIKE ? OR u.name LIKE ?)";
}

if ($sort === "oldest") {
    $sql .= " ORDER BY a.created_at ASC, a.id ASC";
} else {
    $sql .= " ORDER BY a.created_at DESC, a.id DESC";
}

$stmt = $conn->prepare($sql);

// Ikat parameter sesuai filter yang digunakan
if ($filter !== "all" && $search !== "") {
    $keyword = "%" . $search . "%";
    $stmt->bind_param(
        "isss",
        $userId,
        $filter,
        $keyword,
        $keyword
    );
} elseif ($filter !== "all") {
    $stmt->bind_param("is", $userId, $filter);
} elseif ($search !== "") {
    $keyword = "%" . $search . "%";
    $stmt->bind_param("iss", $userId, $keyword, $keyword);
} else {
    $stmt->bind_param("i", $userId);
}

$stmt->execute();
$result = $stmt->get_result();

$workHistory = [];

while ($row = $result->fetch_assoc()) {
    $workHistory[] = $row;
}

$stmt->close();

// Fungsi untuk mengamankan output HTML
function e($value)
{
    return htmlspecialchars(
        (string) ($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

// Format tanggal
function formatDate($date)
{
    if (empty($date)) {
        return "Belum ditentukan";
    }

    $timestamp = strtotime($date);

    return $timestamp !== false
        ? date("d M Y", $timestamp)
        : "Belum ditentukan";
}

// Label status proyek
$statusLabels = [
    "in_progress" => "Sedang Berlangsung",
    "completed" => "Selesai",
    "closed" => "Ditutup"
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Riwayat Pekerjaan | Studilance</title>

    <style>
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

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background:
                radial-gradient(
                    circle at 8% 0%,
                    rgba(73,132,255,.09),
                    transparent 25%
                ),
                var(--page-bg);
            color: var(--text-main);
            line-height: 1.65;
        }

        a {
            text-decoration: none;
        }

        .navbar {
            background: rgba(255,255,255,.96);
            padding: 18px 7%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
            border-bottom: 1px solid var(--border-color);
            box-shadow: 0 4px 18px rgba(23,32,51,.04);
            position: sticky;
            top: 0;
            z-index: 20;
        }

        .brand {
            color: var(--primary);
            font-size: 26px;
            font-weight: bold;
            letter-spacing: -1px;
        }

        .nav-links {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .nav-links a {
            color: var(--text-muted);
            font-size: 14px;
            font-weight: 600;
            padding: 9px 12px;
            border-radius: 9px;
        }

        .nav-links a:hover,
        .nav-links a.active {
            color: var(--primary);
            background: var(--primary-light);
        }

        .container {
            width: 100%;
            max-width: 1120px;
            margin: 36px auto;
            padding: 0 22px;
        }

        .hero {
            background: linear-gradient(135deg, #2457C5, #4479E4);
            color: #FFFFFF;
            padding: 32px;
            border-radius: 20px;
            margin-bottom: 26px;
            box-shadow: 0 10px 26px rgba(36,87,197,.16);
        }

        .hero h1 {
            font-size: clamp(25px, 4vw, 32px);
            margin-bottom: 8px;
        }

        .hero p {
            color: #F0F5FF;
            font-size: 14px;
            line-height: 1.8;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 17px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: var(--card-bg);
            padding: 23px;
            border: 1px solid var(--border-color);
            border-radius: 16px;
            box-shadow: var(--shadow-card);
        }

        .stat-card p {
            color: var(--text-muted);
            font-size: 13px;
            margin-bottom: 8px;
        }

        .stat-card h2 {
            color: var(--primary);
            font-size: 30px;
        }

        .section-heading {
            margin-bottom: 17px;
        }

        .section-heading h2 {
            font-size: 22px;
            margin-bottom: 4px;
        }

        .section-heading p {
            color: var(--text-muted);
            font-size: 13px;
        }

        .filter-panel {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 17px;
            padding: 22px;
            margin-bottom: 24px;
            box-shadow: var(--shadow-card);
        }

        .filter-form {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            align-items: end;
            gap: 15px;
        }

        .filter-field {
            min-width: 0;
        }

        .filter-field label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #344054;
            margin-bottom: 8px;
        }

        .filter-field input,
        .filter-field select {
            width: 100%;
            min-width: 0;
            padding: 12px;
            border: 1px solid #D5DDEB;
            border-radius: 10px;
            background: #FFFFFF;
            color: var(--text-main);
            font-family: inherit;
            font-size: 14px;
            outline: none;
        }

        .filter-field input:focus,
        .filter-field select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(36,87,197,.10);
        }

        .filter-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 17px;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 11px 17px;
            border-radius: 10px;
            font-size: 13px;
            font-family: inherit;
            font-weight: 600;
            text-align: center;
            cursor: pointer;
            border: 1px solid transparent;
        }

        .button-primary {
            background: var(--primary);
            color: #FFFFFF;
        }

        .button-primary:hover {
            background: var(--primary-dark);
        }

        .button-light {
            background: #FFFFFF;
            border-color: var(--border-color);
            color: var(--primary);
        }

        .work-list {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .work-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 17px;
            padding: 25px;
            box-shadow: var(--shadow-card);
        }

        .card-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 15px;
            margin-bottom: 15px;
        }

        .category {
            display: inline-block;
            background: var(--primary-light);
            color: var(--primary);
            border: 1px solid #D9E5FF;
            padding: 5px 11px;
            border-radius: 20px;
            font-size: 12px;
            margin-bottom: 9px;
        }

        .job-title {
            font-size: 20px;
            line-height: 1.4;
            margin-bottom: 5px;
            overflow-wrap: anywhere;
        }

        .client-name {
            color: var(--text-muted);
            font-size: 13px;
        }

        .status {
            display: inline-block;
            padding: 7px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-align: center;
        }

        .status-in_progress {
            background: #EAF1FF;
            color: #2457C5;
        }

        .status-completed {
            background: #E7F8EF;
            color: #087443;
        }

        .status-closed {
            background: #F2F4F7;
            color: #475467;
        }

        .job-info {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
            background: #F7FAFF;
            border: 1px solid #EDF1F8;
            padding: 17px;
            border-radius: 12px;
            margin: 18px 0;
        }

        .info-item {
            min-width: 0;
        }

        .info-item small {
            display: block;
            color: var(--text-muted);
            font-size: 12px;
            margin-bottom: 5px;
        }

        .info-item strong {
            font-size: 13px;
            color: #344054;
            overflow-wrap: anywhere;
        }

        .description {
            border-top: 1px solid var(--border-color);
            padding-top: 17px;
            margin-top: 17px;
        }

        .description h3 {
            font-size: 14px;
            margin-bottom: 7px;
        }

        .description p {
            color: #475467;
            font-size: 13px;
            white-space: pre-line;
            overflow-wrap: anywhere;
        }

        .date-info {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 17px;
        }

        .empty-state {
            background: #FFFFFF;
            padding: 45px 20px;
            text-align: center;
            border: 1px solid var(--border-color);
            border-radius: 17px;
            box-shadow: var(--shadow-card);
        }

        .empty-icon {
            font-size: 42px;
            margin-bottom: 12px;
        }

        .empty-state h3 {
            margin-bottom: 8px;
        }

        .empty-state p {
            color: var(--text-muted);
            font-size: 14px;
            margin-bottom: 18px;
        }

        .footer {
            text-align: center;
            padding: 25px 15px;
            color: var(--text-muted);
            font-size: 12px;
            margin-top: 30px;
        }

        a:focus-visible,
        button:focus-visible {
            outline: 3px solid #91B4FF;
            outline-offset: 3px;
        }

        @media (max-width: 900px) {
            .filter-form {
                grid-template-columns: 1fr 1fr;
            }

            .filter-field:first-child {
                grid-column: 1 / -1;
            }

            .stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .navbar {
                padding: 16px 4%;
            }
        }

        @media (max-width: 520px) {
            .navbar {
                align-items: flex-start;
                flex-direction: column;
            }

            .nav-links {
                gap: 5px;
            }

            .nav-links a {
                padding: 8px 9px;
                font-size: 13px;
            }

            .container {
                margin: 24px auto;
                padding: 0 14px;
            }

            .hero {
                padding: 24px 20px;
            }

            .stats {
                gap: 10px;
            }

            .stat-card {
                padding: 16px;
            }

            .stat-card h2 {
                font-size: 24px;
            }

            .filter-form {
                grid-template-columns: 1fr;
            }

            .filter-field:first-child {
                grid-column: auto;
            }

            .filter-actions .button {
                flex: 1;
            }

            .card-top {
                flex-direction: column;
            }

            .job-info {
                grid-template-columns: 1fr;
            }

            .work-card {
                padding: 19px;
            }

            .job-title {
                font-size: 18px;
            }
        }
    </style>
</head>

<body>

<nav class="navbar">
    <a href="dashboard.php" class="brand">Studilance.</a>

    <div class="nav-links">
        <a href="dashboard.php">Dashboard</a>
        <a href="jobs.php">Cari Proyek</a>
        <a href="my_applications.php">Lamaran Saya</a>
        <a href="work_history.php" class="active">Riwayat Pekerjaan</a>
        <a href="profile.php">Profil Saya</a>
        <a href="auth/logout.php">Keluar</a>
    </div>
</nav>

<main class="container">

    <section class="hero">
        <h1>Riwayat Pekerjaan</h1>
        <p>
            Halo, <?= e($userName) ?>!
            Pantau pekerjaan freelance yang sedang kamu kerjakan
            dan proyek yang sudah selesai.
        </p>
    </section>

    <section class="stats">
        <div class="stat-card">
            <p>Total Pekerjaan</p>
            <h2><?= $totalJobs ?></h2>
        </div>

        <div class="stat-card">
            <p>Sedang Berlangsung</p>
            <h2><?= $ongoingJobs ?></h2>
        </div>

        <div class="stat-card">
            <p>Pekerjaan Selesai</p>
            <h2><?= $completedJobs ?></h2>
        </div>

        <div class="stat-card">
            <p>Proyek Ditutup</p>
            <h2><?= $closedJobs ?></h2>
        </div>
    </section>

    <section>
        <div class="section-heading">
            <h2>Daftar Pekerjaan</h2>
            <p>
                Menampilkan <?= count($workHistory) ?>
                dari <?= $totalJobs ?> pekerjaan.
            </p>
        </div>

        <div class="filter-panel">
            <form method="GET" action="work_history.php" class="filter-form">

                <div class="filter-field">
                    <label for="search">Cari Proyek atau Klien</label>
                    <input
                        type="search"
                        name="search"
                        id="search"
                        placeholder="Nama proyek atau klien..."
                        value="<?= e($search) ?>"
                    >
                </div>

                <div class="filter-field">
                    <label for="filter">Status Pekerjaan</label>
                    <select name="filter" id="filter">
                        <option value="all"
                            <?= $filter === "all" ? "selected" : "" ?>>
                            Semua Pekerjaan
                        </option>

                        <option value="in_progress"
                            <?= $filter === "in_progress" ? "selected" : "" ?>>
                            Sedang Berlangsung
                        </option>

                        <option value="completed"
                            <?= $filter === "completed" ? "selected" : "" ?>>
                            Selesai
                        </option>

                        <option value="closed"
                            <?= $filter === "closed" ? "selected" : "" ?>>
                            Ditutup
                        </option>
                    </select>
                </div>

                <div class="filter-field">
                    <label for="sort">Urutkan Tanggal Lamaran</label>
                    <select name="sort" id="sort">
                        <option value="newest"
                            <?= $sort === "newest" ? "selected" : "" ?>>
                            Terbaru ke Terlama
                        </option>

                        <option value="oldest"
                            <?= $sort === "oldest" ? "selected" : "" ?>>
                            Terlama ke Terbaru
                        </option>
                    </select>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="button button-primary">
                        Terapkan Filter
                    </button>

                    <a href="work_history.php" class="button button-light">
                        Reset
                    </a>
                </div>

            </form>
        </div>

        <?php if ($totalJobs === 0): ?>

            <div class="empty-state">
                <div class="empty-icon">💼</div>
                <h3>Belum Ada Riwayat Pekerjaan</h3>
                <p>
                    Pekerjaan akan muncul di sini setelah lamaranmu diterima
                    dan proyek memiliki status berlangsung, selesai,
                    atau ditutup.
                </p>

                <a href="jobs.php" class="button button-primary">
                    Cari Proyek
                </a>
            </div>

        <?php elseif (count($workHistory) === 0): ?>

            <div class="empty-state">
                <div class="empty-icon">🔎</div>
                <h3>Pekerjaan Tidak Ditemukan</h3>
                <p>
                    Tidak ada pekerjaan yang cocok dengan pencarian
                    atau filter yang kamu pilih.
                </p>

                <a href="work_history.php" class="button button-primary">
                    Tampilkan Semua
                </a>
            </div>

        <?php else: ?>

            <div class="work-list">

                <?php foreach ($workHistory as $work): ?>

                    <?php
                    $jobStatus = $work["job_status"];
                    $statusClass = "status-" . $jobStatus;
                    ?>

                    <article class="work-card">

                        <div class="card-top">
                            <div>
                                <span class="category">
                                    <?= e($work["category"]) ?>
                                </span>

                                <h3 class="job-title">
                                    <?= e($work["job_title"]) ?>
                                </h3>

                                <p class="client-name">
                                    Klien: <?= e($work["client_name"]) ?>
                                </p>
                            </div>

                            <span class="status <?= e($statusClass) ?>">
                                <?= e(
                                    $statusLabels[$jobStatus]
                                    ?? ucfirst($jobStatus)
                                ) ?>
                            </span>
                        </div>

                        <div class="job-info">
                            <div class="info-item">
                                <small>Budget Proyek</small>
                                <strong>
                                    Rp<?= number_format(
                                        (float) $work["budget"],
                                        0,
                                        ",",
                                        "."
                                    ) ?>
                                </strong>
                            </div>

                            <div class="info-item">
                                <small>Deadline</small>
                                <strong>
                                    <?= e(formatDate($work["deadline"])) ?>
                                </strong>
                            </div>

                            <div class="info-item">
                                <small>Tanggal Lamaran</small>
                                <strong>
                                    <?= e(formatDate($work["application_date"])) ?>
                                </strong>
                            </div>
                        </div>

                        <div class="description">
                            <h3>Deskripsi Proyek</h3>
                            <p><?= e($work["description"]) ?></p>
                        </div>

                        <p class="date-info">
                            Lamaran diterima:
                            <?= e(formatDate($work["decided_at"])) ?>
                        </p>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>

</main>

<footer class="footer">
    &copy; <?= date("Y") ?> Studilance.
    Platform freelance untuk mahasiswa.
</footer>

</body>
</html>