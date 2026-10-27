
<?php
session_start();

require_once "config/database.php";

// Pastikan pengguna sudah login
if (!isset($_SESSION["user_id"])) {
    header("Location: auth/login.php");
    exit;
}

// Halaman ini hanya untuk klien
if (($_SESSION["user_role"] ?? "") !== "client") {
    header("Location: dashboard.php");
    exit;
}

// Ambil ID freelancer dari URL
$freelancerId = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);

if (!$freelancerId || $freelancerId < 1) {
    http_response_code(400);
    exit("ID freelancer tidak valid.");
}

// Ambil profil mahasiswa dari database
$stmt = $conn->prepare(
    "SELECT id, name, bio, skills, portfolio_url, created_at
     FROM users
     WHERE id = ? AND role = 'student'
     LIMIT 1"
);

$stmt->bind_param("i", $freelancerId);
$stmt->execute();

$result = $stmt->get_result();
$freelancer = $result->fetch_assoc();

$stmt->close();

if (!$freelancer) {
    http_response_code(404);
    exit("Profil freelancer tidak ditemukan.");
}

// Amankan teks sebelum ditampilkan
function e($value) {
    return htmlspecialchars(
        (string) ($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

// Validasi tautan portofolio
$portfolioUrl = trim(
    $freelancer["portfolio_url"] ?? ""
);

$validPortfolioUrl = "";

if (
    $portfolioUrl !== "" &&
    filter_var($portfolioUrl, FILTER_VALIDATE_URL)
) {
    $scheme = strtolower(
        (string) parse_url($portfolioUrl, PHP_URL_SCHEME)
    );

    if (in_array($scheme, ["http", "https"], true)) {
        $validPortfolioUrl = $portfolioUrl;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Detail Freelancer | Studilance</title>
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
    --text-main: #172033;
    --text-muted: #667085;
    --border-color: #E4EAF4;
    --shadow-card: 0 10px 30px rgba(31, 64, 130, 0.07);
}

body {
    margin: 0;
    font-family: 'Inter', sans-serif;
    background: var(--page-bg);
    color: var(--text-main);
}

h1, h2, h3, h4, .brand {
    font-family: 'Poppins', sans-serif;
}

/* Navbar */
.navbar {
    background: #FFFFFF;
    min-height: 82px;
    padding: 18px 7%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    border-bottom: 1px solid var(--border-color);
}

.brand {
    font-size: 27px;
    font-weight: 800;
    letter-spacing: -1px;
    color: var(--primary);
}

.navbar a {
    color: var(--primary);
    text-decoration: none;
    font-size: 14px;
    font-weight: 700;
    padding: 10px 14px;
    border-radius: 10px;
    transition: 0.2s;
}

.navbar a:hover {
    background: var(--primary-light);
    color: var(--primary-dark);
}

/* Konten */
.container {
    width: min(900px, 92%);
    margin: 34px auto 50px;
}

.back-link {
    display: inline-block;
    margin-bottom: 20px;
    color: var(--primary);
    text-decoration: none;
    font-weight: 700;
    font-size: 14px;
}

.back-link:hover {
    text-decoration: underline;
}

/* Kartu profil */
.profile-card {
    background: #FFFFFF;
    padding: 34px;
    border: 1px solid var(--border-color);
    border-radius: 24px;
    box-shadow: var(--shadow-card);
}

.profile-header {
    display: flex;
    align-items: center;
    gap: 22px;
    padding-bottom: 28px;
    border-bottom: 1px solid var(--border-color);
}

.avatar {
    width: 86px;
    height: 86px;
    flex-shrink: 0;
    border-radius: 24px;
    background: var(--primary-light);
    color: var(--primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 34px;
    font-weight: 800;
}

h1 {
    margin: 0 0 9px;
    font-size: clamp(25px, 4vw, 32px);
    line-height: 1.3;
    overflow-wrap: anywhere;
}

.subtitle {
    color: var(--text-muted);
    margin: 0;
    line-height: 1.7;
    font-size: 14px;
}

/* Bagian informasi */
.section {
    margin-top: 28px;
}

.section h2 {
    color: var(--text-main);
    font-size: 19px;
    margin: 0 0 13px;
}

.section p {
    line-height: 1.85;
    color: #526078;
    margin: 0 0 12px;
    overflow-wrap: anywhere;
}

/* Daftar skill */
.skills {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.skill {
    background: var(--primary-light);
    color: var(--primary-dark);
    border: 1px solid #DCE7FF;
    padding: 9px 14px;
    border-radius: 12px;
    font-size: 13px;
    font-weight: 600;
    overflow-wrap: anywhere;
}

/* Tombol portofolio */
.button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-top: 8px;
    padding: 13px 19px;
    border-radius: 11px;
    background: var(--primary);
    color: #FFFFFF;
    text-decoration: none;
    font-weight: 700;
    font-size: 14px;
    transition: 0.2s;
}

.button:hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
}

.empty {
    color: #8792A5 !important;
    font-style: italic;
    font-size: 14px;
}

/* Tampilan HP */
@media (max-width: 600px) {
    .navbar {
        min-height: 72px;
        padding: 16px 5%;
    }

    .brand {
        font-size: 23px;
    }

    .navbar a {
        padding: 8px;
        font-size: 13px;
    }

    .container {
        margin: 24px auto 35px;
    }

    .profile-card {
        padding: 23px;
        border-radius: 20px;
    }

    .profile-header {
        align-items: flex-start;
        gap: 16px;
    }

    .avatar {
        width: 68px;
        height: 68px;
        border-radius: 19px;
        font-size: 28px;
    }

    .section {
        margin-top: 24px;
    }
}

    </style>
</head>

<body>

    <nav class="navbar">
        <div class="brand">Studilance</div>
        <a href="dashboard.php">Dashboard</a>
    </nav>

    <main class="container">

        <a href="freelancers.php" class="back-link">
            &larr; Kembali ke Daftar Freelancer
        </a>

        <section class="profile-card">

            <div class="profile-header">
                <div class="avatar">
                    <?= e(strtoupper(substr(
                        $freelancer["name"],
                        0,
                        1
                    ))) ?>
                </div>

                <div>
                    <h1><?= e($freelancer["name"]) ?></h1>
                    <p class="subtitle">Freelancer Mahasiswa</p>
                </div>
            </div>

            <div class="section">
                <h2>Tentang Freelancer</h2>

                <?php if (trim($freelancer["bio"] ?? "") !== ""): ?>
                    <p><?= nl2br(e($freelancer["bio"])) ?></p>
                <?php else: ?>
                    <p class="empty">
                        Freelancer belum menambahkan deskripsi diri.
                    </p>
                <?php endif; ?>
            </div>

            <div class="section">
                <h2>Keahlian</h2>

                <?php
                $skills = array_filter(
                    array_map(
                        "trim",
                        explode(",", $freelancer["skills"] ?? "")
                    ),
                    fn($skill) => $skill !== ""
                );
                ?>

                <?php if (!empty($skills)): ?>
                    <div class="skills">
                        <?php foreach ($skills as $skill): ?>
                            <span class="skill">
                                <?= e($skill) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="empty">
                        Keahlian belum ditambahkan.
                    </p>
                <?php endif; ?>
            </div>

            <div class="section">
                <h2>Portofolio</h2>

                <?php if ($validPortfolioUrl !== ""): ?>
                    <p>
                        Lihat hasil karya dan proyek freelancer ini.
                    </p>

                    <a
                        class="button"
                        href="<?= e($validPortfolioUrl) ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        Lihat Portofolio
                    </a>
                <?php else: ?>
                    <p class="empty">
                        Portofolio belum tersedia.
                    </p>
                <?php endif; ?>
            </div>

        </section>

    </main>

</body>
</html>
