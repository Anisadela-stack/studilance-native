
<?php
session_start();
require_once __DIR__ . "/config/database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: auth/login.php");
    exit;
}

if (($_SESSION["user_role"] ?? "") !== "client") {
    http_response_code(403);
    exit("Halaman ini khusus klien.");
}

function e($value) {
    return htmlspecialchars((string) ($value ?? ""), ENT_QUOTES, "UTF-8");
}

$clientId = (int) $_SESSION["user_id"];
$error = "";
$success = "";

/* Filter status lamaran */
$statusFilter = $_GET["status"] ?? "all";
$allowedFilters = ["all", "pending", "accepted", "rejected"];

if (!in_array($statusFilter, $allowedFilters, true)) {
    $statusFilter = "all";
}

/* Token keamanan form */
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

/* Memproses keputusan klien */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST["csrf_token"] ?? "";
    $applicationId = filter_input(
        INPUT_POST,
        "application_id",
        FILTER_VALIDATE_INT
    );
    $newStatus = $_POST["status"] ?? "";

    if (
        !is_string($token) ||
        !hash_equals($_SESSION["csrf_token"], $token)
    ) {
        $error = "Permintaan tidak valid. Silakan muat ulang halaman.";
    } elseif (!$applicationId || $applicationId < 1) {
        $error = "Lamaran tidak valid.";
    } elseif (!in_array($newStatus, ["accepted", "rejected"], true)) {
        $error = "Pilihan status tidak valid.";
    } else {
       $stmtUpdate = $conn->prepare(
    "UPDATE applications
     INNER JOIN jobs ON applications.job_id = jobs.id
     SET applications.status = ?,
         applications.decided_at = NOW()
     WHERE applications.id = ?
       AND jobs.client_id = ?
       AND applications.status = 'pending'"
);

$stmtUpdate->bind_param(
    "sii",
    $newStatus,
    $applicationId,
    $clientId
);


        $stmtUpdate->execute();

        if ($stmtUpdate->affected_rows > 0) {
            $success = $newStatus === "accepted"
                ? "Lamaran berhasil diterima."
                : "Lamaran berhasil ditolak.";
        } else {
            $error = "Lamaran tidak ditemukan atau sudah diproses.";
        }

        $stmtUpdate->close();
    }
}

/* Mengambil lamaran untuk proyek milik klien yang login */
$stmt = $conn->prepare(
    "SELECT
        applications.id,
        applications.student_id,
        applications.proposal,
        applications.status AS application_status,
        applications.created_at,
        jobs.title,
        jobs.category,
        jobs.budget,
        jobs.deadline,
        users.name AS student_name,
        users.email AS student_email
     FROM applications
     INNER JOIN jobs ON applications.job_id = jobs.id
     INNER JOIN users ON applications.student_id = users.id
     WHERE jobs.client_id = ?
     ORDER BY applications.created_at DESC"
);

$stmt->bind_param("i", $clientId);
$stmt->execute();
$result = $stmt->get_result();

$applications = [];

while ($row = $result->fetch_assoc()) {
    $applications[] = $row;
}

$stmt->close();

/* Menghitung statistik semua lamaran */
$total = count($applications);
$pending = 0;
$accepted = 0;
$rejected = 0;

foreach ($applications as $application) {
    if ($application["application_status"] === "pending") {
        $pending++;
    } elseif ($application["application_status"] === "accepted") {
        $accepted++;
    } elseif ($application["application_status"] === "rejected") {
        $rejected++;
    }
}

/* Menyiapkan label status */
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

/* Menyaring daftar sesuai tombol yang dipilih */
$filteredApplications = array_values(array_filter(
    $applications,
    function ($application) use ($statusFilter) {
        return $statusFilter === "all"
            || $application["application_status"] === $statusFilter;
    }
));

$filterLabels = [
    "all" => "Semua",
    "pending" => "Menunggu",
    "accepted" => "Diterima",
    "rejected" => "Ditolak"
];

$currentFilterLabel = $filterLabels[$statusFilter];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Lamaran | Studilance</title>
      
<style>
    * {
        box-sizing: border-box;
    }

    :root {
        --primary: #2457c5;
        --primary-dark: #173f9a;
        --primary-light: #eaf1ff;
        --page-bg: #f3f6fc;
        --text-main: #172033;
        --text-muted: #667085;
        --border-color: #e4eaf4;
        --shadow-card: 0 10px 30px rgba(31, 64, 130, 0.07);
    }

    body {
        margin: 0;
        font-family: Arial, sans-serif;
        background: var(--page-bg);
        color: var(--text-main);
        line-height: 1.6;
    }

    header {
        position: sticky;
        top: 0;
        z-index: 10;
        background: rgba(255, 255, 255, 0.97);
        padding: 17px 6%;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 18px;
        flex-wrap: wrap;
        border-bottom: 1px solid var(--border-color);
        box-shadow: 0 3px 15px rgba(31, 64, 130, 0.04);
    }

    .brand {
        color: var(--primary);
        font-size: 25px;
        font-weight: bold;
        text-decoration: none;
        letter-spacing: -0.5px;
    }

    nav {
        display: flex;
        gap: 22px;
        align-items: center;
        flex-wrap: wrap;
    }

    nav a {
        color: #475467;
        text-decoration: none;
        font-size: 14px;
        font-weight: bold;
        transition: color 0.2s;
    }

    nav a:hover,
    nav a.active {
        color: var(--primary);
    }

    nav .logout {
        color: #b42318;
    }

    .hero {
        padding: 42px 20px;
        background: linear-gradient(135deg, #eaf1ff 0%, #f7f9ff 100%);
        border-bottom: 1px solid #e0e9ff;
    }

    .hero-inner {
        max-width: 1100px;
        margin: auto;
    }

    .hero h1 {
        margin: 0 0 10px;
        color: #172b55;
        font-size: clamp(27px, 4vw, 35px);
        line-height: 1.3;
    }

    .hero p {
        max-width: 650px;
        margin: 0;
        color: #536581;
        line-height: 1.8;
        font-size: 15px;
    }

    main {
        max-width: 1100px;
        margin: 32px auto 60px;
        padding: 0 22px;
    }

    .notice {
        padding: 15px 18px;
        margin-bottom: 20px;
        border: 1px solid #abefc6;
        border-radius: 12px;
        background: #ecfdf3;
        color: #067647;
        font-size: 14px;
    }

    .notice.error {
        border-color: #fecdca;
        background: #fff0f0;
        color: #b42318;
    }

    .stats {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 18px;
        margin-bottom: 35px;
    }

    .stat-card {
        position: relative;
        overflow: hidden;
        padding: 23px;
        background: white;
        border: 1px solid var(--border-color);
        border-radius: 17px;
        box-shadow: var(--shadow-card);
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 14px 32px rgba(31, 64, 130, 0.1);
    }

    .stat-card p {
        margin: 0 0 12px;
        color: var(--text-muted);
        font-size: 14px;
    }

    .stat-card strong {
        color: var(--primary);
        font-size: 31px;
        line-height: 1.2;
    }

    .section-heading {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 18px;
    }

    .section-heading h2 {
        margin: 0;
        color: var(--text-main);
        font-size: 24px;
    }

    .count {
        color: var(--text-muted);
        font-size: 13px;
    }

    .application-filters {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 24px;
    }

    .filter-button {
        display: inline-block;
        padding: 10px 17px;
        border: 1px solid #dce4f1;
        border-radius: 30px;
        background: white;
        color: #475467;
        text-decoration: none;
        font-size: 13px;
        font-weight: bold;
        transition: all 0.2s;
    }

    .filter-button:hover {
        border-color: var(--primary);
        color: var(--primary);
        background: #f7f9ff;
    }

    .filter-button.active {
        background: var(--primary);
        border-color: var(--primary);
        color: white;
        box-shadow: 0 5px 12px rgba(36, 87, 197, 0.18);
    }

    .application-card {
        padding: 26px;
        margin-bottom: 20px;
        background: white;
        border: 1px solid var(--border-color);
        border-radius: 18px;
        box-shadow: var(--shadow-card);
        transition: box-shadow 0.2s, transform 0.2s;
    }

    .application-card:hover {
        box-shadow: 0 14px 35px rgba(31, 64, 130, 0.1);
    }

    .card-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        flex-wrap: wrap;
        gap: 15px;
    }

    .category {
        display: inline-block;
        padding: 6px 12px;
        background: var(--primary-light);
        color: var(--primary);
        border-radius: 30px;
        font-size: 12px;
        font-weight: bold;
    }

    .application-card h3 {
        margin: 12px 0 8px;
        color: var(--text-main);
        font-size: 22px;
        line-height: 1.4;
        overflow-wrap: anywhere;
    }

    .student-info {
        margin: 12px 0;
        color: var(--text-muted);
        font-size: 14px;
        line-height: 1.9;
        overflow-wrap: anywhere;
    }

    .student-info strong {
        color: #344054;
    }

    .status {
        display: inline-block;
        padding: 8px 13px;
        border-radius: 30px;
        font-size: 12px;
        font-weight: bold;
        white-space: nowrap;
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

    .details {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 15px;
        padding: 18px;
        margin: 20px 0;
        border: 1px solid #edf1f8;
        border-radius: 13px;
        background: #f8faff;
    }

    .detail-label {
        margin-bottom: 5px;
        color: var(--text-muted);
        font-size: 12px;
    }

    .detail-value {
        color: var(--text-main);
        font-size: 15px;
        font-weight: bold;
        overflow-wrap: anywhere;
    }

    .proposal-title {
        margin: 23px 0 9px;
        color: var(--text-main);
        font-size: 17px;
    }

    .proposal {
        margin: 0;
        padding: 15px 17px;
        border-left: 3px solid #9db8ff;
        border-radius: 0 10px 10px 0;
        background: #f8faff;
        color: #536581;
        font-size: 14px;
        line-height: 1.9;
        white-space: pre-wrap;
        overflow-wrap: anywhere;
    }

    .date {
        margin-top: 17px;
        color: var(--text-muted);
        font-size: 12px;
    }

    .decision-form {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        margin-top: 23px;
        padding-top: 20px;
        border-top: 1px solid #edf1f7;
    }

    .decision-form label {
        color: #344054;
        font-size: 14px;
        font-weight: bold;
    }

    .decision-form select {
        min-width: 180px;
        padding: 11px 13px;
        border: 1px solid #d0d9e8;
        border-radius: 10px;
        outline: none;
        background: white;
        color: var(--text-main);
        font: inherit;
        font-size: 14px;
    }

    .decision-form select:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(36, 87, 197, 0.1);
    }

    .button {
        display: inline-block;
        padding: 12px 18px;
        border: 1px solid transparent;
        border-radius: 10px;
        background: var(--primary);
        color: white;
        text-decoration: none;
        text-align: center;
        font-size: 14px;
        font-weight: bold;
        cursor: pointer;
        transition: all 0.2s;
    }

    .button:hover {
        background: var(--primary-dark);
        transform: translateY(-1px);
    }

    .button-light {
        background: white;
        border-color: #d7e2f5;
        color: var(--primary);
    }

    .button-light:hover {
        background: var(--primary-light);
        border-color: #b7cbff;
        color: var(--primary-dark);
    }

    .profile-button {
        margin-top: 7px;
    }

    .empty {
        padding: 48px 24px;
        background: white;
        border: 1px solid var(--border-color);
        border-radius: 18px;
        box-shadow: var(--shadow-card);
        text-align: center;
    }

    .empty h3 {
        margin: 0 0 10px;
        color: var(--text-main);
        font-size: 21px;
    }

    .empty p {
        max-width: 520px;
        margin: 0 auto 22px;
        color: var(--text-muted);
        line-height: 1.8;
        font-size: 14px;
    }

    footer {
        margin-top: 55px;
        padding: 26px 20px;
        background: white;
        border-top: 1px solid var(--border-color);
        text-align: center;
        color: var(--text-muted);
        font-size: 13px;
    }

    @media (max-width: 800px) {
        header {
            padding: 16px 4%;
        }

        nav {
            gap: 14px;
        }

        .stats {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .hero {
            padding: 32px 20px;
        }
    }

    @media (max-width: 500px) {
        header {
            padding: 16px 18px;
        }

        .brand {
            font-size: 23px;
        }

        nav {
            gap: 12px;
        }

        nav a {
            font-size: 12px;
        }

        main {
            margin-top: 23px;
            padding: 0 14px;
        }

        .stats {
            gap: 11px;
        }

        .stat-card {
            padding: 17px;
            border-radius: 14px;
        }

        .stat-card strong {
            font-size: 26px;
        }

        .section-heading h2 {
            font-size: 21px;
        }

        .application-card {
            padding: 18px;
            border-radius: 15px;
        }

        .application-card h3 {
            font-size: 19px;
        }

        .details {
            grid-template-columns: 1fr;
            padding: 15px;
        }

        .decision-form {
            align-items: stretch;
            flex-direction: column;
        }

        .decision-form select,
        .decision-form .button {
            width: 100%;
        }

        .filter-button {
            padding: 9px 13px;
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
        <a class="active" href="manage_applications.php">Lamaran Masuk</a>
        <a class="logout" href="auth/logout.php">Logout</a>
    </nav>
</header>

<section class="hero">
    <div class="hero-inner">
        <h1>Kelola Lamaran Mahasiswa 📋</h1>
        <p>
            Tinjau proposal mahasiswa dan pilih kandidat yang sesuai
            dengan kebutuhan proyek kamu.
        </p>
    </div>
</section>

<main>

    <?php if ($success !== ""): ?>
        <div class="notice"><?= e($success) ?></div>
    <?php endif; ?>

    <?php if ($error !== ""): ?>
        <div class="notice error"><?= e($error) ?></div>
    <?php endif; ?>

    <section class="stats">
        <div class="stat-card">
            <p>Total Lamaran</p>
            <strong><?= $total ?></strong>
        </div>

        <div class="stat-card">
            <p>Menunggu</p>
            <strong><?= $pending ?></strong>
        </div>

        <div class="stat-card">
            <p>Diterima</p>
            <strong><?= $accepted ?></strong>
        </div>

        <div class="stat-card">
            <p>Ditolak</p>
            <strong><?= $rejected ?></strong>
        </div>
    </section>

    <div class="section-heading">
        <h2>Daftar Lamaran</h2>
        <span class="count">
            <?= count($filteredApplications) ?> lamaran ditampilkan
        </span>
    </div>

    <!-- Tombol filter status -->
    <div class="application-filters">
        <?php foreach ($filterLabels as $filterValue => $filterLabel): ?>
            <a
                href="manage_applications.php?status=<?= e($filterValue) ?>"
                class="filter-button <?= $statusFilter === $filterValue ? 'active' : '' ?>"
                <?= $statusFilter === $filterValue ? 'aria-current="page"' : '' ?>
            >
                <?= e($filterLabel) ?>
                <?php
                    if ($filterValue === "all") {
                        echo "(" . $total . ")";
                    } elseif ($filterValue === "pending") {
                        echo "(" . $pending . ")";
                    } elseif ($filterValue === "accepted") {
                        echo "(" . $accepted . ")";
                    } elseif ($filterValue === "rejected") {
                        echo "(" . $rejected . ")";
                    }
                ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($total === 0): ?>

        <section class="empty">
            <h3>Belum ada lamaran masuk</h3>
            <p>
                Saat mahasiswa melamar proyek yang kamu publikasikan,
                proposal mereka akan muncul di halaman ini.
            </p>

            <a class="button" href="jobs.php">
                Lihat Daftar Proyek
            </a>
        </section>

    <?php elseif (count($filteredApplications) === 0): ?>

        <section class="empty">
            <h3>Belum ada lamaran berstatus <?= e(strtolower($currentFilterLabel)) ?></h3>
            <p>
                Saat ini tidak ada lamaran yang sesuai dengan filter
                <?= e(strtolower($currentFilterLabel)) ?>.
                Coba pilih status lainnya.
            </p>

            <a class="button" href="manage_applications.php?status=all">
                Lihat Semua Lamaran
            </a>
        </section>

    <?php else: ?>

        <?php foreach ($filteredApplications as $application): ?>
            <?php
                $status = $application["application_status"];
                $statusLabel = $statusLabels[$status] ?? $status;
                $statusClass = $statusClasses[$status] ?? "pending";
                $studentId = (int) $application["student_id"];
            ?>

            <article class="application-card">

                <div class="card-top">
                    <div>
                        <span class="category">
                            <?= e($application["category"]) ?>
                        </span>

                        <h3><?= e($application["title"]) ?></h3>
                    </div>

                    <span class="status <?= e($statusClass) ?>">
                        <?= e($statusLabel) ?>
                    </span>
                </div>

                <p class="student-info">
                    <strong>Mahasiswa:</strong>
                    <?= e($application["student_name"]) ?><br>

                    <strong>Email:</strong>
                    <?= e($application["student_email"]) ?>
                </p>

                <a
                    class="button button-light profile-button"
                    href="freelancer_profile.php?id=<?= $studentId ?>"
                >
                    Lihat Profil Mahasiswa
                </a>

                <section class="details">
                    <div>
                        <div class="detail-label">Anggaran Proyek</div>
                        <div class="detail-value">
                            Rp <?= number_format(
                                (float) $application["budget"],
                                0,
                                ",",
                                "."
                            ) ?>
                        </div>
                    </div>

                    <div>
                        <div class="detail-label">Deadline Proyek</div>
                        <div class="detail-value">
                            <?= !empty($application["deadline"])
                                ? e(date(
                                    "d-m-Y",
                                    strtotime($application["deadline"])
                                ))
                                : "Belum ditentukan" ?>
                        </div>
                    </div>
                </section>

                <h4 class="proposal-title">Proposal Mahasiswa</h4>

                <p class="proposal"><?= e($application["proposal"]) ?></p>

                <p class="date">
                    Dikirim pada:
                    <?= e(date(
                        "d-m-Y H:i",
                        strtotime($application["created_at"])
                    )) ?>
                </p>

                <?php if ($status === "pending"): ?>

                    <form
                        class="decision-form"
                        method="POST"
                        action="manage_applications.php?status=<?= e($statusFilter) ?>"
                    >
                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= e($_SESSION["csrf_token"]) ?>"
                        >

                        <input
                            type="hidden"
                            name="application_id"
                            value="<?= (int) $application["id"] ?>"
                        >

                        <label for="status-<?= (int) $application["id"] ?>">
                            Keputusan:
                        </label>

                        <select
                            id="status-<?= (int) $application["id"] ?>"
                            name="status"
                            required
                        >
                            <option value="accepted">Terima Lamaran</option>
                            <option value="rejected">Tolak Lamaran</option>
                        </select>

                        <button class="button" type="submit">
                            Simpan Keputusan
                        </button>
                    </form>

                <?php endif; ?>

            </article>

        <?php endforeach; ?>

    <?php endif; ?>

</main>

<footer>
    &copy; <?= date("Y") ?> Studilance.
    Platform freelance untuk mengembangkan skill mahasiswa.
</footer>

</body>
</html>
