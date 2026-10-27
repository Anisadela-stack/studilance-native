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

// Fungsi untuk mengamankan teks
function e($value) {
    return htmlspecialchars(
        (string) ($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

// Ambil kata kunci pencarian
$search = trim($_GET["search"] ?? "");
$freelancers = [];

if ($search !== "") {
    $keyword = "%" . $search . "%";

    $stmt = $conn->prepare(
        "SELECT id, name, bio, skills, portfolio_url
         FROM users
         WHERE role = 'student'
         AND (
             name LIKE ?
             OR skills LIKE ?
             OR bio LIKE ?
         )
         ORDER BY name ASC"
    );

    $stmt->bind_param(
        "sss",
        $keyword,
        $keyword,
        $keyword
    );
} else {
    $stmt = $conn->prepare(
        "SELECT id, name, bio, skills, portfolio_url
         FROM users
         WHERE role = 'student'
         ORDER BY name ASC"
    );
}

// Jalankan query
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $freelancers[] = $row;
}

$stmt->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Cari Freelancer | Studilance</title>
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

body {
    margin: 0;
    font-family: 'Inter', sans-serif;
    background: var(--page-bg);
    color: var(--text-main);
    line-height: 1.65;
}
h1,
h2,
h3,
h4,
.brand {
    font-family: 'Poppins', sans-serif;
}

button,
input,
select,
textarea {
    font-family: 'Inter', sans-serif;
}

/* Navbar */
header {
    background: #FFFFFF;
    min-height: 82px;
    padding: 18px 7%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
    border-bottom: 1px solid var(--border-color);
}

.brand {
    color: var(--primary);
    font-size: 27px;
    font-weight: 800;
    letter-spacing: -1px;
}

header a {
    color: var(--primary);
    font-weight: 700;
    text-decoration: none;
    padding: 10px 14px;
    border-radius: 10px;
    transition: 0.2s;
}

header a:hover {
    color: var(--primary-dark);
    background: var(--primary-light);
}

/* Konten utama */
main {
    width: min(1100px, 92%);
    margin: 32px auto 45px;
}

/* Banner */
.intro {
    background: linear-gradient(120deg, #2457C5, #4779E8);
    color: #FFFFFF;
    padding: 38px;
    margin-bottom: 26px;
    border-radius: 24px;
    box-shadow: 0 12px 30px rgba(36, 87, 197, 0.14);
}

.intro h1 {
    color: #FFFFFF;
    font-size: clamp(28px, 4vw, 38px);
    line-height: 1.2;
    letter-spacing: -0.7px;
    margin: 0 0 14px;
}

.intro p {
    color: #FFFFFF;
    opacity: 0.95;
    line-height: 1.8;
    margin: 0;
    max-width: 800px;
}

/* Form pencarian */
.search-box {
    display: flex;
    gap: 12px;
    margin: 0 0 16px;
    padding: 18px;
    background: #FFFFFF;
    border: 1px solid var(--border-color);
    border-radius: 18px;
    box-shadow: 0 5px 20px rgba(31, 64, 130, 0.04);
}

.search-box input {
    flex: 1;
    min-width: 0;
    padding: 14px 16px;
    border: 1px solid #D5DDEB;
    border-radius: 11px;
    font-size: 15px;
    font-family: inherit;
    color: var(--text-main);
    background: #FFFFFF;
    outline: none;
    transition: 0.2s;
}

.search-box input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(36, 87, 197, 0.1);
}

.search-box button {
    background: var(--primary);
    color: #FFFFFF;
    border: none;
    border-radius: 11px;
    padding: 0 23px;
    min-height: 48px;
    cursor: pointer;
    font-size: 15px;
    font-weight: 700;
    font-family: inherit;
    transition: 0.2s;
}

.search-box button:hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
}

.reset-link {
    display: inline-block;
    margin: 4px 0 18px;
    color: var(--primary);
    text-decoration: none;
    font-size: 14px;
    font-weight: 700;
}

.reset-link:hover {
    text-decoration: underline;
}

/* Jumlah hasil */
.count {
    color: var(--text-muted);
    font-size: 14px;
    margin: 20px 0;
}

/* Grid freelancer */
.grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 20px;
    align-items: stretch;
}

.card {
    min-width: 0;
    background: var(--card-bg);
    padding: 24px;
    border: 1px solid var(--border-color);
    border-radius: 20px;
    box-shadow: 0 5px 20px rgba(31, 64, 130, 0.04);
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
}

.card:hover {
    transform: translateY(-4px);
    border-color: #B9CDF9;
    box-shadow: var(--shadow-card);
}

/* Avatar inisial */
.avatar {
    width: 60px;
    height: 60px;
    flex-shrink: 0;
    border-radius: 18px;
    background: var(--primary-light);
    color: var(--primary);
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 24px;
    font-weight: 800;
    margin-bottom: 17px;
}

.card h2 {
    color: var(--text-main);
    font-size: 19px;
    line-height: 1.4;
    margin: 0 0 10px;
    overflow-wrap: anywhere;
}

.bio {
    color: var(--text-muted);
    font-size: 14px;
    line-height: 1.8;
    margin: 0 0 16px;
    overflow-wrap: anywhere;
    flex: 1;
}

/* Keahlian */
.skills {
    font-size: 13px;
    color: var(--primary-dark);
    background: var(--primary-light);
    border: 1px solid #DCE7FF;
    border-radius: 10px;
    padding: 10px 12px;
    margin-bottom: 20px;
    overflow-wrap: anywhere;
    line-height: 1.7;
    width: 100%;
}

/* Tombol profil */
.profile-link {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    text-align: center;
    text-decoration: none;
    background: var(--primary);
    color: #FFFFFF;
    padding: 12px 16px;
    border-radius: 11px;
    font-size: 14px;
    font-weight: 700;
    margin-top: auto;
    transition: 0.2s;
}

.profile-link:hover {
    background: var(--primary-dark);
}

/* Hasil kosong */
.empty {
    grid-column: 1 / -1;
    background: #FFFFFF;
    padding: 38px 24px;
    text-align: center;
    border-radius: 20px;
    color: var(--text-muted);
    border: 1px solid var(--border-color);
    line-height: 1.8;
}

.empty h3 {
    color: var(--text-main);
    font-size: 21px;
    margin: 0 0 10px;
}

.empty p {
    margin: 0 0 18px;
}

/* Footer */
footer {
    text-align: center;
    color: var(--text-muted);
    padding: 28px 15px;
    font-size: 13px;
}

/* Tablet */
@media (max-width: 850px) {
    .grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .intro {
        padding: 30px;
    }
}

/* HP */
@media (max-width: 560px) {
    header {
        padding: 16px 5%;
        min-height: 72px;
    }

    .brand {
        font-size: 23px;
    }

    header a {
        font-size: 13px;
        padding: 8px;
    }

    main {
        width: 92%;
        margin: 22px auto 32px;
    }

    .intro {
        padding: 26px 22px;
        border-radius: 20px;
    }

    .search-box {
        flex-direction: column;
        padding: 14px;
        border-radius: 15px;
    }

    .search-box input {
        width: 100%;
    }

    .search-box button {
        width: 100%;
        padding: 14px;
    }

    .grid {
        grid-template-columns: 1fr;
        gap: 14px;
    }

    .card {
        padding: 21px;
        border-radius: 17px;
    }

    .empty {
        padding: 28px 18px;
    }
}

    </style>
</head>

<body>

<header>
    <div class="brand">Studilance</div>
    <a href="dashboard.php">&larr; Kembali ke Dashboard</a>
</header>

<main>
    <section class="intro">
        <h1>Temukan Freelancer</h1>

        <p>
            Temukan mahasiswa dengan keahlian yang sesuai dengan kebutuhan
            proyekmu, mulai dari desain, editing video, programming,
            fotografi, hingga copywriting.
        </p>
    </section>

    <form method="GET" action="freelancers.php" class="search-box">
        <input
            type="search"
            name="search"
            placeholder="Cari nama, skill, atau keahlian..."
            value="<?= e($search) ?>"
            aria-label="Cari freelancer berdasarkan nama, skill, atau bio"
        >

        <button type="submit">Cari Freelancer</button>
    </form>

    <?php if ($search !== ""): ?>
        <a href="freelancers.php" class="reset-link">
            &larr; Tampilkan semua freelancer
        </a>
    <?php endif; ?>

    <p class="count">
        Ditemukan <?= count($freelancers) ?> freelancer

        <?php if ($search !== ""): ?>
            untuk pencarian &ldquo;<?= e($search) ?>&rdquo;
        <?php endif; ?>
    </p>

    <section class="grid">

        <?php if (count($freelancers) > 0): ?>

            <?php foreach ($freelancers as $freelancer): ?>

                <article class="card">

                    <div class="avatar">
                        <?= e(
                            strtoupper(
                                substr(
                                    trim($freelancer["name"] ?? ""),
                                    0,
                                    1
                                )
                            )
                        ) ?>
                    </div>

                    <h2>
                        <?= e($freelancer["name"]) ?>
                    </h2>

                    <p class="bio">
                        <?php if (trim($freelancer["bio"] ?? "") !== ""): ?>
                            <?= e($freelancer["bio"]) ?>
                        <?php else: ?>
                            Freelancer mahasiswa di Studilance.
                        <?php endif; ?>
                    </p>

                    <div class="skills">
                        <?= e(
                            trim($freelancer["skills"] ?? "") !== ""
                                ? $freelancer["skills"]
                                : "Keahlian belum ditambahkan"
                        ) ?>
                    </div>

                    <a
                        class="profile-link"
                        href="freelancer_profile.php?id=<?= (int) $freelancer["id"] ?>"
                    >
                        Lihat Profil
                    </a>

                </article>

            <?php endforeach; ?>

        <?php else: ?>

            <div class="empty">
                <h3>Freelancer belum ditemukan</h3>

                <p>
                    <?php if ($search !== ""): ?>
                        Tidak ada freelancer yang cocok dengan kata kunci
                        tersebut. Coba nama atau skill lainnya.
                    <?php else: ?>
                        Belum ada akun mahasiswa yang terdaftar.
                        Freelancer akan muncul di sini setelah tersedia
                        akun mahasiswa.
                    <?php endif; ?>
                </p>

                <?php if ($search !== ""): ?>
                    <a href="freelancers.php" class="profile-link">
                        Lihat Semua Freelancer
                    </a>
                <?php endif; ?>
            </div>

        <?php endif; ?>

    </section>
</main>

<footer>
    &copy; <?= date("Y") ?> Studilance —
    Temukan Proyek, Kembangkan Skill.
</footer>

</body>
</html>