
<?php
session_start();
require_once "config/database.php";

// Pastikan pengguna sudah login
if (!isset($_SESSION["user_id"])) {
    header("Location: auth/login.php");
    exit;
}

// Halaman ini hanya untuk mahasiswa/freelancer
if (($_SESSION["user_role"] ?? "") !== "student") {
    http_response_code(403);
    exit("Akses ditolak. Halaman ini hanya untuk mahasiswa.");
}

$userId = (int) $_SESSION["user_id"];
$userName = $_SESSION["user_name"] ?? "Mahasiswa";

// Filter status
$statusFilter = $_GET["status"] ?? "all";
$allowedStatuses = ["all", "pending", "accepted", "rejected"];

if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = "all";
}

// Kata kunci pencarian
$search = trim($_GET["search"] ?? "");

// Pengurutan tanggal
$sort = $_GET["sort"] ?? "newest";
$allowedSorts = ["newest", "oldest"];

if (!in_array($sort, $allowedSorts, true)) {
    $sort = "newest";
}

// Susun query berdasarkan filter status dan pencarian
$sql = "SELECT
            a.id AS application_id,
            a.job_id,
            a.proposal,
            a.status AS application_status,
            a.created_at AS application_date,
            j.title AS job_title,
            j.category,
            j.budget,
            j.deadline,
            j.status AS job_status,
            u.name AS client_name
        FROM applications a
        INNER JOIN jobs j ON a.job_id = j.id
        INNER JOIN users u ON j.client_id = u.id
        WHERE a.student_id = ?";

if ($statusFilter !== "all") {
    $sql .= " AND a.status = ?";
}

if ($search !== "") {
    $sql .= " AND (j.title LIKE ? OR u.name LIKE ?)";
}

// Urutkan tanggal lamaran
if ($sort === "oldest") {
    $sql .= " ORDER BY a.created_at ASC, a.id ASC";
} else {
    $sql .= " ORDER BY a.created_at DESC, a.id DESC";
}

$stmt = $conn->prepare($sql);

// Ikat parameter secara aman
if ($statusFilter !== "all" && $search !== "") {
    $keyword = "%" . $search . "%";
    $stmt->bind_param(
        "isss",
        $userId,
        $statusFilter,
        $keyword,
        $keyword
    );
} elseif ($statusFilter !== "all") {
    $stmt->bind_param("is", $userId, $statusFilter);
} elseif ($search !== "") {
    $keyword = "%" . $search . "%";
    $stmt->bind_param("iss", $userId, $keyword, $keyword);
} else {
    $stmt->bind_param("i", $userId);
}

$stmt->execute();
$result = $stmt->get_result();

$filteredApplications = [];

while ($row = $result->fetch_assoc()) {
    $filteredApplications[] = $row;
}

$stmt->close();

// Statistik dihitung dari seluruh lamaran,
// tidak terpengaruh pencarian, filter, atau pengurutan
$statsStmt = $conn->prepare(
    "SELECT
        COUNT(*) AS total,
        COALESCE(SUM(status = 'pending'), 0) AS pending_count,
        COALESCE(SUM(status = 'accepted'), 0) AS accepted_count,
        COALESCE(SUM(status = 'rejected'), 0) AS rejected_count
     FROM applications
     WHERE student_id = ?"
);

$statsStmt->bind_param("i", $userId);
$statsStmt->execute();

$statsResult = $statsStmt->get_result();
$stats = $statsResult->fetch_assoc();

$statsStmt->close();

$totalApplications = (int) ($stats["total"] ?? 0);
$pendingCount = (int) ($stats["pending_count"] ?? 0);
$acceptedCount = (int) ($stats["accepted_count"] ?? 0);
$rejectedCount = (int) ($stats["rejected_count"] ?? 0);

// Label status
$statusLabels = [
    "pending" => "Menunggu Keputusan",
    "accepted" => "Lamaran Diterima",
    "rejected" => "Lamaran Ditolak"
];

// Escape output
function e($value) {
    return htmlspecialchars(
        (string) ($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

// Pertahankan parameter filter saat melakukan pencarian/pengurutan
function filterUrl($status, $search, $sort) {
    $params = [
        "status" => $status,
        "sort" => $sort
    ];

    if ($search !== "") {
        $params["search"] = $search;
    }

    return "my_applications.php?" . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Lamaran Saya | Studilance</title>

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
        radial-gradient(circle at 8% 0%, rgba(73,132,255,.09), transparent 25%),
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
    border-bottom: 1px solid var(--border-color);
    box-shadow: 0 4px 18px rgba(23,32,51,.04);
    gap: 20px;
    flex-wrap: wrap;
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
    transition: background .2s, color .2s;
}

.nav-links a:hover {
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
    letter-spacing: -.6px;
}

.hero p {
    font-size: 14px;
    color: #F0F5FF;
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
    transition: transform .2s, border-color .2s;
}

.stat-card:hover {
    transform: translateY(-2px);
    border-color: #B9CEFA;
}

.stat-card p {
    font-size: 13px;
    color: var(--text-muted);
    margin-bottom: 8px;
}

.stat-card h2 {
    font-size: 30px;
    color: var(--primary);
}

.section-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-bottom: 17px;
    flex-wrap: wrap;
}

.section-heading h2 {
    font-size: 22px;
    color: var(--text-main);
    margin-bottom: 4px;
}

.section-heading p {
    font-size: 13px;
    color: var(--text-muted);
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
    grid-template-columns: minmax(200px, 2fr) minmax(160px, 1fr) minmax(175px, 1fr);
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
    transition: background .2s, transform .2s;
}

.button:hover {
    transform: translateY(-1px);
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

.button-light:hover {
    background: var(--primary-light);
    border-color: #B9CEFA;
}

.application-list {
    display: flex;
    flex-direction: column;
    gap: 18px;
}

.application-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 17px;
    padding: 25px;
    box-shadow: var(--shadow-card);
    transition: border-color .2s, box-shadow .2s;
}

.application-card:hover {
    border-color: #C9D8F7;
    box-shadow: 0 12px 28px rgba(31,64,130,.09);
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
    color: var(--text-main);
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
    white-space: normal;
    text-align: center;
}

.status-pending {
    background: #FFF5DC;
    color: #946200;
}

.status-accepted {
    background: #E7F8EF;
    color: #087443;
}

.status-rejected {
    background: #FFF0F0;
    color: #B42318;
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

.proposal {
    border-top: 1px solid var(--border-color);
    padding-top: 17px;
    margin-top: 17px;
}

.proposal h3 {
    font-size: 14px;
    color: var(--text-main);
    margin-bottom: 7px;
}

.proposal p {
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
    color: var(--text-main);
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

    .container {
        padding: 0 18px;
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

    .stats {
        gap: 10px;
    }

    .stat-card {
        padding: 16px;
    }

    .stat-card h2 {
        font-size: 24px;
    }

    .hero {
        padding: 24px 20px;
        border-radius: 16px;
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

    .application-card {
        padding: 19px;
    }

    .job-title {
        font-size: 18px;
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

<nav class="navbar">
    <a href="dashboard.php" class="brand">Studilance.</a>

    <div class="nav-links">
        <a href="dashboard.php">Dashboard</a>
        <a href="jobs.php">Cari Proyek</a>
        <a href="profile.php">Profil Saya</a>
        <a href="auth/logout.php">Keluar</a>
    </div>
</nav>

<main class="container">

    <section class="hero">
        <h1>Lamaran Saya</h1>
        <p>
            Halo, <?= e($userName) ?>!
            Pantau status lamaran proyek freelance kamu di sini.
        </p>
    </section>

    <!-- Statistik seluruh lamaran -->
    <section class="stats">
        <div class="stat-card">
            <p>Total Lamaran</p>
            <h2><?= $totalApplications ?></h2>
        </div>

        <div class="stat-card">
            <p>Menunggu Keputusan</p>
            <h2><?= $pendingCount ?></h2>
        </div>

        <div class="stat-card">
            <p>Lamaran Diterima</p>
            <h2><?= $acceptedCount ?></h2>
        </div>

        <div class="stat-card">
            <p>Lamaran Ditolak</p>
            <h2><?= $rejectedCount ?></h2>
        </div>
    </section>

    <section>
        <div class="section-heading">
            <div>
                <h2>Riwayat Lamaran</h2>
                <p>
                    Menampilkan <?= count($filteredApplications) ?>
                    dari <?= $totalApplications ?> lamaran
                </p>
            </div>
        </div>

        <!-- Pencarian, filter status, dan pengurutan -->
        <div class="filter-panel">
            <form
                method="GET"
                action="my_applications.php"
                class="filter-form"
            >
                <div class="filter-field">
                    <label for="search">Cari Proyek atau Klien</label>
                    <input
                        type="search"
                        name="search"
                        id="search"
                        placeholder="Masukkan nama proyek atau klien..."
                        value="<?= e($search) ?>"
                    >
                </div>

                <div class="filter-field">
                    <label for="status">Filter Status</label>
                    <select name="status" id="status">
                        <option value="all"
                            <?= $statusFilter === "all" ? "selected" : "" ?>>
                            Semua Lamaran
                        </option>

                        <option value="pending"
                            <?= $statusFilter === "pending" ? "selected" : "" ?>>
                            Menunggu Keputusan
                        </option>

                        <option value="accepted"
                            <?= $statusFilter === "accepted" ? "selected" : "" ?>>
                            Lamaran Diterima
                        </option>

                        <option value="rejected"
                            <?= $statusFilter === "rejected" ? "selected" : "" ?>>
                            Lamaran Ditolak
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
                        Terapkan
                    </button>

                    <a href="my_applications.php" class="button button-light">
                        Reset Filter
                    </a>
                </div>
            </form>
        </div>

        <?php if ($totalApplications === 0): ?>

            <div class="empty-state">
                <div class="empty-icon">📭</div>
                <h3>Belum Ada Lamaran</h3>
                <p>
                    Kamu belum melamar proyek freelance apa pun.
                    Yuk, cari proyek yang sesuai dengan skill kamu!
                </p>
                <a href="jobs.php" class="button button-primary">
                    Cari Proyek
                </a>
            </div>

        <?php elseif (count($filteredApplications) === 0): ?>

            <div class="empty-state">
                <div class="empty-icon">🔎</div>
                <h3>Lamaran Tidak Ditemukan</h3>
                <p>
                    Tidak ada lamaran yang cocok dengan pencarian atau filter
                    yang kamu pilih.
                </p>
                <a href="my_applications.php" class="button button-primary">
                    Tampilkan Semua Lamaran
                </a>
            </div>

        <?php else: ?>

            <div class="application-list">

                <?php foreach ($filteredApplications as $application): ?>

                    <?php
                    $appStatus = $application["application_status"];

                    $statusClass = "status-pending";

                    if ($appStatus === "accepted") {
                        $statusClass = "status-accepted";
                    } elseif ($appStatus === "rejected") {
                        $statusClass = "status-rejected";
                    }

                    $budget = $application["budget"];
                    ?>

                    <article class="application-card">

                        <div class="card-top">
                            <div>
                                <span class="category">
                                    <?= e($application["category"] ?? "Umum") ?>
                                </span>

                                <h3 class="job-title">
                                    <?= e($application["job_title"]) ?>
                                </h3>

                                <p class="client-name">
                                    Diposting oleh
                                    <?= e($application["client_name"]) ?>
                                </p>
                            </div>

                            <span class="status <?= e($statusClass) ?>">
                                <?= e(
                                    $statusLabels[$appStatus]
                                    ?? ucfirst($appStatus)
                                ) ?>
                            </span>
                        </div>

                        <div class="job-info">
                            <div class="info-item">
                                <small>Budget Proyek</small>
                                <strong>
                                    <?php if ($budget !== null && $budget !== ""): ?>
                                        Rp<?= number_format(
                                            (float) $budget,
                                            0,
                                            ",",
                                            "."
                                        ) ?>
                                    <?php else: ?>
                                        Belum ditentukan
                                    <?php endif; ?>
                                </strong>
                            </div>

                            <div class="info-item">
                                <small>Deadline Proyek</small>
                                <strong>
                                    <?php if (!empty($application["deadline"])): ?>
                                        <?php
                                        $deadlineTimestamp = strtotime(
                                            $application["deadline"]
                                        );
                                        ?>
                                        <?= $deadlineTimestamp !== false
                                            ? e(date("d M Y", $deadlineTimestamp))
                                            : "Tidak ditentukan" ?>
                                    <?php else: ?>
                                        Tidak ditentukan
                                    <?php endif; ?>
                                </strong>
                            </div>

                            <div class="info-item">
                                <small>Status Proyek</small>
                                <strong>
                                    <?= e(
                                        ucfirst(
                                            str_replace(
                                                "_",
                                                " ",
                                                $application["job_status"] ?? "open"
                                            )
                                        )
                                    ) ?>
                                </strong>
                            </div>
                        </div>

                        <div class="proposal">
                            <h3>Proposal Kamu</h3>
                            <p><?= e(
                                $application["proposal"]
                                ?? "Tidak ada proposal."
                            ) ?></p>
                        </div>

                        <p class="date-info">
                            Lamaran dikirim:
                            <?php if (!empty($application["application_date"])): ?>
                                <?php
                                $applicationTimestamp = strtotime(
                                    $application["application_date"]
                                );
                                ?>
                                <?= $applicationTimestamp !== false
                                    ? e(date(
                                        "d M Y, H:i",
                                        $applicationTimestamp
                                    ))
                                    : "Tanggal tidak tersedia" ?>
                            <?php else: ?>
                                Tanggal tidak tersedia
                            <?php endif; ?>
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