<?php
session_start();
require_once __DIR__ . "/config/database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: auth/login.php");
    exit;
}

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

$userId = (int) $_SESSION["user_id"];
$isClient = ($_SESSION["user_role"] ?? "") === "client";

$search = trim($_GET["search"] ?? "");
$category = trim($_GET["category"] ?? "");
$minBudget = trim($_GET["min_budget"] ?? "");
$maxBudget = trim($_GET["max_budget"] ?? "");
$sort = $_GET["sort"] ?? "newest";

$categories = [
    "Desain Grafis",
    "Editing Video",
    "Programming",
    "Fotografi",
    "Copywriting",
    "Pembuatan Website",
    "Lainnya"
];

if (!in_array($category, $categories, true)) {
    $category = "";
}

// Validasi budget: harus angka dan tidak boleh negatif.
if ($minBudget !== "" &&
    (!is_numeric($minBudget) || (float) $minBudget < 0)) {
    $minBudget = "";
}

if ($maxBudget !== "" &&
    (!is_numeric($maxBudget) || (float) $maxBudget < 0)) {
    $maxBudget = "";
}

// Jika batas minimum lebih besar daripada maksimum,
// kosongkan kedua filter supaya tidak membingungkan.
if (
    $minBudget !== "" &&
    $maxBudget !== "" &&
    (float) $minBudget > (float) $maxBudget
) {
    $minBudget = "";
    $maxBudget = "";
}

// Pilihan urutan dibatasi agar aman untuk query SQL.
$sortOptions = [
    "newest" => "Terbaru",
    "budget_low" => "Budget Terendah",
    "budget_high" => "Budget Tertinggi"
];

if (!array_key_exists($sort, $sortOptions)) {
    $sort = "newest";
}

$orderBy = [
    "newest" => "jobs.created_at DESC, jobs.id DESC",
    "budget_low" => "jobs.budget ASC, jobs.created_at DESC",
    "budget_high" => "jobs.budget DESC, jobs.created_at DESC"
][$sort];

// Token keamanan untuk formulir hapus.
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$sql = "
    SELECT
        jobs.id,
        jobs.client_id,
        jobs.title,
        jobs.category,
        jobs.description,
        jobs.budget,
        jobs.deadline,
        jobs.status,
        jobs.created_at,
        users.name AS client_name
    FROM jobs
    INNER JOIN users ON jobs.client_id = users.id
";

$params = [];
$types = "";

if ($isClient) {
    $sql .= " WHERE jobs.client_id = ?";
    $params[] = $userId;
    $types .= "i";
} else {
    $sql .= " WHERE jobs.status = 'open'";
}

// Pencarian judul, deskripsi, atau kategori.
if ($search !== "") {
    $sql .= " AND (
        jobs.title LIKE ?
        OR jobs.description LIKE ?
        OR jobs.category LIKE ?
    )";

    $keyword = "%" . $search . "%";
    $params[] = $keyword;
    $params[] = $keyword;
    $params[] = $keyword;
    $types .= "sss";
}

// Filter kategori.
if ($category !== "") {
    $sql .= " AND jobs.category = ?";
    $params[] = $category;
    $types .= "s";
}

// Filter budget minimum.
if ($minBudget !== "") {
    $sql .= " AND jobs.budget >= ?";
    $params[] = (float) $minBudget;
    $types .= "d";
}

// Filter budget maksimum.
if ($maxBudget !== "") {
    $sql .= " AND jobs.budget <= ?";
    $params[] = (float) $maxBudget;
    $types .= "d";
}

$sql .= " ORDER BY " . $orderBy;

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$totalJobs = $result->num_rows;

$notice = "";

if (isset($_GET["created"])) {
    $notice = "Proyek berhasil dipublikasikan!";
} elseif (isset($_GET["updated"])) {
    $notice = "Perubahan proyek berhasil disimpan!";
} elseif (isset($_GET["deleted"])) {
    $notice = "Proyek berhasil dihapus!";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Proyek | Studilance</title>

<!-- Font konsisten dengan halaman Studilance lainnya -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
.hero {
    border-radius: 24px;
    margin: 24px auto;
    width: calc(100% - 48px);
    overflow: hidden;
    box-sizing: border-box;
}

.hero-inner {
    border-radius: inherit;
}

* {
    box-sizing: border-box;
}

html {
    scroll-behavior: smooth;
}

body {
    margin: 0;
    font-family: 'Inter', sans-serif;
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
button,
input,
select,
textarea {
    font-family: 'Inter', sans-serif;
}

h1,
h2,
h3,
h4,
.brand {
    font-family: 'Poppins', sans-serif;
}

/* NAVBAR */
header {
    background: rgba(255, 255, 255, 0.97);
    padding: 18px 7%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    border-bottom: 1px solid var(--border-color);
    box-shadow: 0 4px 18px rgba(23, 32, 51, 0.04);
    position: sticky;
    top: 0;
    z-index: 20;
}

.brand {
    color: var(--primary);
    font-size: 26px;
    font-weight: 800;
    letter-spacing: -1px;
    text-decoration: none;
}

nav {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

nav a {
    text-decoration: none;
    color: var(--text-muted);
    padding: 9px 12px;
    border-radius: 9px;
    font-size: 14px;
    font-weight: 600;
    transition: 0.2s;
}

nav a:hover {
    color: var(--primary);
    background: var(--primary-light);
}

nav .logout {
    color: #B42318;
}

/* BANNER BIRU */
.hero {
    background: linear-gradient(110deg, #2457C5, #4779E8);
    padding: 55px 3.2%;
    color: #FFFFFF;
    box-shadow: 0 15px 35px rgba(36, 87, 197, 0.12);
}

.hero-inner {
    max-width: 1500px;
    margin: auto;
}

.hero h1 {
    margin: 0 0 16px;
    font-size: clamp(30px, 4vw, 42px);
    line-height: 1.25;
    color: #FFFFFF;
    letter-spacing: -1px;
}

.hero p {
    margin: 0;
    max-width: 760px;
    line-height: 1.8;
    font-size: 17px;
    color: #FFFFFF;
}

/* KONTEN */
main {
    max-width: 1500px;
    margin: 35px auto;
    padding: 0 3.2%;
}

/* FILTER PENCARIAN */
.toolbar {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 22px;
    padding: 26px;
    margin-bottom: 32px;
    box-shadow: var(--shadow-card);
}

.toolbar h2 {
    margin: 0 0 8px;
    font-size: 22px;
    color: var(--text-main);
}

.toolbar-description {
    margin: 0 0 22px;
    color: var(--text-muted);
    font-size: 14px;
    line-height: 1.7;
}

.filter-form {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px;
}

.field {
    display: flex;
    flex-direction: column;
    gap: 8px;
    min-width: 0;
}

.field label {
    font-size: 13px;
    color: #344054;
    font-weight: 600;
}

input,
select {
    width: 100%;
    min-width: 0;
    min-height: 46px;
    padding: 12px 14px;
    border: 1px solid #D5DDEB;
    border-radius: 11px;
    background: #FFFFFF;
    color: var(--text-main);
    font: inherit;
    font-size: 14px;
}

input:focus,
select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(36, 87, 197, 0.10);
}

.filter-actions {
    grid-column: 1 / -1;
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 4px;
}

/* TOMBOL */
.button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 12px 18px;
    border: 1px solid transparent;
    border-radius: 11px;
    background: var(--primary);
    color: #FFFFFF;
    font-weight: 600;
    font-size: 14px;
    text-decoration: none;
    cursor: pointer;
    font-family: inherit;
    transition: background 0.2s, transform 0.2s;
}

.button:hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
}

.button-light {
    background: #FFFFFF;
    color: #344054;
    border-color: var(--border-color);
}

.button-light:hover {
    background: var(--primary-light);
    color: var(--primary-dark);
    border-color: #B9CEFA;
}

.button-danger {
    background: #B42318;
    color: #FFFFFF;
}

.button-danger:hover {
    background: #912018;
}

/* JUDUL DAFTAR PROYEK */
.section-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.section-heading h2 {
    margin: 0;
    font-size: 25px;
    color: var(--text-main);
}

.count {
    color: var(--text-muted);
    font-size: 14px;
}

/* KARTU PROYEK */
.job-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 22px;
}

.job-card {
    display: flex;
    flex-direction: column;
    background: #FFFFFF;
    border: 1px solid var(--border-color);
    border-radius: 22px;
    padding: 25px;
    min-width: 0;
    box-shadow: var(--shadow-card);
    transition: transform 0.2s, box-shadow 0.2s;
}

.job-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 15px 35px rgba(31, 64, 130, 0.11);
}

.category-badge {
    display: inline-block;
    align-self: flex-start;
    padding: 7px 12px;
    background: var(--primary-light);
    color: var(--primary);
    border: 1px solid #D9E5FF;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.job-card h3 {
    margin: 16px 0 10px;
    font-size: 20px;
    line-height: 1.4;
    color: var(--text-main);
    overflow-wrap: anywhere;
}

.description {
    color: var(--text-muted);
    font-size: 14px;
    line-height: 1.8;
    overflow-wrap: anywhere;
    white-space: pre-line;
    margin: 0 0 20px;
}

.job-info {
    margin-top: auto;
    padding-top: 18px;
    border-top: 1px solid var(--border-color);
}

.budget {
    color: var(--primary);
    font-size: 21px;
    font-weight: 700;
    margin: 0 0 12px;
    overflow-wrap: anywhere;
}

.meta {
    color: var(--text-muted);
    font-size: 13px;
    line-height: 1.8;
    margin: 5px 0;
    overflow-wrap: anywhere;
}

.status {
    display: inline-block;
    margin-top: 10px;
    padding: 6px 11px;
    border-radius: 20px;
    background: #F2F4F7;
    color: #344054;
    font-size: 12px;
    font-weight: 600;
}

.status-open {
    background: #E7F8EF;
    color: #087443;
}

.job-action {
    display: grid;
    grid-template-columns: 1fr;
    gap: 10px;
    margin-top: 20px;
}

.job-action .button {
    width: 100%;
}

.delete-form {
    margin: 0;
}

/* PESAN KOSONG */
.empty {
    background: #FFFFFF;
    border: 1px solid var(--border-color);
    border-radius: 22px;
    padding: 50px 24px;
    text-align: center;
    box-shadow: var(--shadow-card);
}

.empty h3 {
    margin-top: 0;
    color: var(--text-main);
}

.empty p {
    color: var(--text-muted);
    line-height: 1.8;
    margin-bottom: 24px;
}

/* NOTIFIKASI */
.notice {
    padding: 15px 18px;
    background: #E7F8EF;
    color: #087443;
    border: 1px solid #B7E8CB;
    border-radius: 12px;
    margin-bottom: 24px;
}

/* FOOTER */
footer {
    margin-top: 60px;
    padding: 28px 20px;
    text-align: center;
    color: var(--text-muted);
    font-size: 13px;
    border-top: 1px solid var(--border-color);
    background: #FFFFFF;
}

a:focus-visible,
button:focus-visible {
    outline: 3px solid #91B4FF;
    outline-offset: 3px;
}

/* RESPONSIVE */
@media (max-width: 1000px) {
    .job-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .filter-form {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 600px) {
    header {
        padding: 15px 20px;
    }

    nav {
        gap: 5px;
    }

    nav a {
        padding: 8px 9px;
        font-size: 13px;
    }

    .hero {
        padding: 35px 22px;
    }

    .hero h1 {
        font-size: 30px;
    }

    .hero p {
        font-size: 15px;
    }

    main {
        margin-top: 24px;
        padding: 0 14px;
    }

    .filter-form,
    .job-grid {
        grid-template-columns: 1fr;
    }

    .toolbar {
        padding: 20px;
        border-radius: 17px;
    }

    .job-card {
        padding: 21px;
        border-radius: 17px;
    }

    .filter-actions {
        flex-direction: column;
    }

    .filter-actions .button {
        width: 100%;
    }
}

@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        transition: none !important;
        scroll-behavior: auto !important;
    }
}

    </style>
</head>
<body>

<header>
    <a class="brand" href="dashboard.php">Studilance</a>

    <nav>
        <a href="dashboard.php">Dashboard</a>
        <a href="jobs.php">Daftar Proyek</a>

        <?php if ($isClient): ?>
            <a href="create_job.php">Buat Proyek</a>
            <a href="manage_applications.php">Lamaran Masuk</a>
        <?php else: ?>
            <a href="my_applications.php">Lamaran Saya</a>
        <?php endif; ?>

        <a class="logout" href="auth/logout.php">Logout</a>
    </nav>
</header>

<section class="hero">
    <div class="hero-inner">
        <?php if ($isClient): ?>
            <h1>Kelola Proyekmu ✨</h1>
            <p>Lihat, edit, dan kelola proyek freelance yang sudah kamu buat.</p>
        <?php else: ?>
            <h1>Temukan Proyek Impianmu ✨</h1>
            <p>
                Jelajahi peluang freelance, kembangkan kemampuan,
                dan bangun pengalaman profesional bersama Studilance.
            </p>
        <?php endif; ?>
    </div>
</section>

<main>

    <?php if ($notice !== ""): ?>
        <div class="notice"><?= e($notice) ?></div>
    <?php endif; ?>

    <section class="toolbar">
        <h2>
            <?= $isClient ? "Cari Proyek Milikmu" : "Cari Proyek Freelance" ?>
        </h2>

        <p class="toolbar-description">
            Gunakan kata kunci, kategori, dan budget untuk menemukan proyek
            yang sesuai.
        </p>

        <form class="filter-form" method="GET" action="jobs.php">

            <div class="field">
                <label for="search">Kata kunci</label>
                <input
                    type="search"
                    id="search"
                    name="search"
                    placeholder="Judul, deskripsi, atau skill..."
                    value="<?= e($search) ?>"
                >
            </div>

            <div class="field">
                <label for="category">Kategori proyek</label>
                <select id="category" name="category">
                    <option value="">Semua kategori</option>

                    <?php foreach ($categories as $item): ?>
                        <option
                            value="<?= e($item) ?>"
                            <?= $category === $item ? "selected" : "" ?>
                        >
                            <?= e($item) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="sort">Urutkan proyek</label>
                <select id="sort" name="sort">
                    <?php foreach ($sortOptions as $value => $label): ?>
                        <option
                            value="<?= e($value) ?>"
                            <?= $sort === $value ? "selected" : "" ?>
                        >
                            <?= e($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="min_budget">Budget minimum (Rp)</label>
                <input
                    type="number"
                    id="min_budget"
                    name="min_budget"
                    min="0"
                    step="any"
                    placeholder="Contoh: 100000"
                    value="<?= e($minBudget) ?>"
                >
            </div>

            <div class="field">
                <label for="max_budget">Budget maksimum (Rp)</label>
                <input
                    type="number"
                    id="max_budget"
                    name="max_budget"
                    min="0"
                    step="any"
                    placeholder="Contoh: 1000000"
                    value="<?= e($maxBudget) ?>"
                >
            </div>

            <div class="filter-actions">
                <button class="button" type="submit">
                    Cari Proyek
                </button>

                <a class="button button-light" href="jobs.php">
                    Reset Filter
                </a>
            </div>

        </form>
    </section>

    <div class="section-heading">
        <h2><?= $isClient ? "Proyek Saya" : "Proyek Tersedia" ?></h2>
        <span class="count"><?= $totalJobs ?> proyek ditemukan</span>
    </div>

    <?php if ($totalJobs === 0): ?>

        <section class="empty">
            <h3>
                <?= $isClient
                    ? "Kamu belum memiliki proyek yang cocok."
                    : "Belum ada proyek yang cocok 🔎" ?>
            </h3>

            <p>
                <?= $isClient
                    ? "Buat proyek baru atau coba ubah filter pencarian."
                    : "Coba kata kunci, kategori, atau rentang budget yang berbeda." ?>
            </p>

            <?php if ($isClient): ?>
                <a class="button" href="create_job.php">
                    + Buat Proyek Baru
                </a>
            <?php else: ?>
                <a class="button" href="jobs.php">
                    Tampilkan Semua Proyek
                </a>
            <?php endif; ?>
        </section>

    <?php else: ?>

        <section class="job-grid">

            <?php while ($job = $result->fetch_assoc()): ?>
                <article class="job-card">

                    <span class="category-badge">
                        <?= e($job["category"]) ?>
                    </span>

                    <h3><?= e($job["title"]) ?></h3>

                    <p class="description"><?= e(mb_strimwidth(
                        $job["description"],
                        0,
                        180,
                        "..."
                    )) ?></p>

                    <div class="job-info">

                        <p class="budget">
                            Rp <?= number_format(
                                (float) $job["budget"],
                                0,
                                ",",
                                "."
                            ) ?>
                        </p>

                        <p class="meta">
                            Klien: <?= e($job["client_name"]) ?>
                        </p>

                        <p class="meta">
                            Deadline:
                            <?= $job["deadline"]
                                ? e(date("d-m-Y", strtotime($job["deadline"])))
                                : "Belum ditentukan" ?>
                        </p>

                        <?php if ($isClient): ?>
                            <?php
                            $statusLabels = [
                                "open" => "Terbuka",
                                "in_progress" => "Sedang Dikerjakan",
                                "completed" => "Selesai",
                                "closed" => "Ditutup"
                            ];
                            ?>

                            <span class="status <?= $job["status"] === "open" ? "status-open" : "" ?>">
                                Status:
                                <?= e($statusLabels[$job["status"]] ?? $job["status"]) ?>
                            </span>
                        <?php endif; ?>

                        <div class="job-action">

                            <?php if ($isClient): ?>

                                <a
                                    class="button"
                                    href="edit_job.php?id=<?= (int) $job["id"] ?>"
                                >
                                    Edit Proyek
                                </a>

                                <a
                                    class="button button-light"
                                    href="manage_applications.php"
                                >
                                    Kelola Lamaran
                                </a>

                                <form
                                    class="delete-form"
                                    method="POST"
                                    action="delete_job.php"
                                    onsubmit="return confirm('Yakin ingin menghapus proyek ini? Lamaran terkait juga akan terhapus.');"
                                >
                                    <input
                                        type="hidden"
                                        name="job_id"
                                        value="<?= (int) $job["id"] ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= e($_SESSION["csrf_token"]) ?>"
                                    >

                                    <button
                                        class="button button-danger"
                                        type="submit"
                                    >
                                        Hapus Proyek
                                    </button>
                                </form>

                            <?php else: ?>

                                <a
                                    class="button"
                                    href="apply.php?job_id=<?= (int) $job["id"] ?>"
                                >
                                    Lamar Proyek
                                </a>

                            <?php endif; ?>

                        </div>
                    </div>
                </article>
            <?php endwhile; ?>

        </section>

    <?php endif; ?>

</main>

<footer>
    &copy; <?= date("Y") ?> Studilance.
    Platform freelance untuk mengembangkan skill mahasiswa.
</footer>

</body>
</html>

<?php
$stmt->close();
?>