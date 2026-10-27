
<?php
session_start();

require_once "config/database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: auth/login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];
$userRole = $_SESSION["user_role"] ?? "";
$success = "";
$error = "";

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

function e($value) {
    return htmlspecialchars(
        (string) ($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

// Mengambil data profil pengguna
$stmt = $conn->prepare(
    "SELECT id, name, email, role, bio, skills, experience, portfolio_url
     FROM users
     WHERE id = ?"
);

$stmt->bind_param("i", $userId);
$stmt->execute();

$result = $stmt->get_result();
$user = $result->fetch_assoc();

$stmt->close();

if (!$user) {
    session_destroy();
    header("Location: auth/login.php");
    exit;
}

// Proses penyimpanan profil
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $csrfToken = $_POST["csrf_token"] ?? "";
    $name = trim($_POST["name"] ?? "");
    $bio = trim($_POST["bio"] ?? "");
    $skills = trim($_POST["skills"] ?? "");
    $experience = trim($_POST["experience"] ?? "");
    $portfolioUrl = trim($_POST["portfolio_url"] ?? "");

    if (
        !is_string($csrfToken) ||
        !hash_equals($_SESSION["csrf_token"], $csrfToken)
    ) {
        $error = "Permintaan tidak valid. Silakan muat ulang halaman.";
    } elseif ($name === "") {
        $error = "Nama tidak boleh kosong.";
    } elseif (strlen($name) > 100) {
        $error = "Nama maksimal 100 karakter.";
    } elseif (strlen($portfolioUrl) > 255) {
        $error = "Link portofolio maksimal 255 karakter.";
    } elseif (
        $portfolioUrl !== "" &&
        (
            !filter_var($portfolioUrl, FILTER_VALIDATE_URL) ||
            !in_array(
                strtolower(
                    parse_url($portfolioUrl, PHP_URL_SCHEME) ?? ""
                ),
                ["http", "https"],
                true
            )
        )
    ) {
        $error = "Masukkan link portofolio yang valid (http atau https).";
    } else {
        if ($userRole === "student") {
            // Simpan data lengkap profil mahasiswa
            $stmt = $conn->prepare(
                "UPDATE users
                 SET name = ?,
                     bio = ?,
                     skills = ?,
                     experience = ?,
                     portfolio_url = ?
                 WHERE id = ?"
            );

            $stmt->bind_param(
                "sssssi",
                $name,
                $bio,
                $skills,
                $experience,
                $portfolioUrl,
                $userId
            );
        } else {
            // Profil klien tetap menyimpan nama dan bio
            $stmt = $conn->prepare(
                "UPDATE users
                 SET name = ?, bio = ?
                 WHERE id = ?"
            );

            $stmt->bind_param(
                "ssi",
                $name,
                $bio,
                $userId
            );
        }

        $stmt->execute();
        $stmt->close();

        $_SESSION["user_name"] = $name;

        header("Location: profile.php?updated=1");
        exit;
    }

    // Pertahankan isian jika validasi gagal
    $user["name"] = $name;
    $user["bio"] = $bio;
    $user["skills"] = $skills;
    $user["experience"] = $experience;
    $user["portfolio_url"] = $portfolioUrl;
}

if (isset($_GET["updated"])) {
    $success = "Profil berhasil diperbarui!";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya - Studilance</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

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

        /* NAVBAR */
        .navbar {
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
            font-size: 26px;
            font-weight: 800;
            color: var(--primary);
            letter-spacing: -1px;
            text-decoration: none;
        }

        .navbar nav {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }

        .navbar nav a {
            color: var(--text-muted);
            text-decoration: none;
            padding: 9px 13px;
            border-radius: 9px;
            font-size: 14px;
            font-weight: 600;
            transition: 0.2s;
        }

        .navbar nav a:hover {
            color: var(--primary);
            background: var(--primary-light);
        }

        /* KONTEN UTAMA */
        .container {
            max-width: 1500px;
            width: 100%;
            margin: 0 auto;
            padding: 40px 3.2%;
        }

        /* BANNER */
        .hero {
            background: linear-gradient(110deg, #2457C5, #4779E8);
            color: #FFFFFF;
            border-radius: 32px;
            padding: 55px;
            margin-bottom: 30px;
            box-shadow: 0 15px 35px rgba(36, 87, 197, 0.15);
        }

        .hero h1 {
            margin: 0 0 20px;
            color: #FFFFFF;
            font-size: clamp(32px, 4vw, 42px);
            line-height: 1.25;
            letter-spacing: -1px;
        }

        .hero p {
            color: #FFFFFF;
            margin: 0;
            font-size: 18px;
            line-height: 1.8;
        }

        /* CARD PROFIL */
        .card {
            background: var(--card-bg);
            padding: 32px;
            border: 1px solid var(--border-color);
            border-radius: 22px;
            box-shadow: var(--shadow-card);
            max-width: 100%;
        }

        .avatar {
            width: 76px;
            height: 76px;
            border-radius: 20px;
            background: var(--primary-light);
            color: var(--primary);
            border: 1px solid #D9E5FF;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            font-weight: bold;
            margin-bottom: 18px;
        }

        .card h2 {
            margin: 0 0 5px;
            color: var(--text-main);
            font-size: 23px;
        }

        .card > p {
            color: var(--text-muted);
            margin: 0 0 14px;
            overflow-wrap: anywhere;
        }

        .role {
            display: inline-block;
            background: var(--primary-light);
            color: var(--primary);
            border: 1px solid #D9E5FF;
            padding: 7px 13px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 26px;
        }

        /* FORM */
        .form-group {
            margin-bottom: 22px;
        }

        label {
            display: block;
            font-weight: 600;
            color: #344054;
            margin-bottom: 8px;
            font-size: 14px;
        }

        input,
        textarea {
            display: block;
            width: 100%;
            padding: 13px 15px;
            border: 1px solid #D5DDEB;
            border-radius: 11px;
            background: #FFFFFF;
            color: var(--text-main);
            font-family: inherit;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        input:focus,
        textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(36, 87, 197, 0.10);
        }

        input::placeholder,
        textarea::placeholder {
            color: #98A2B3;
        }

        textarea {
            resize: vertical;
            min-height: 115px;
        }

        .hint {
            display: block;
            color: var(--text-muted);
            font-size: 12px;
            margin-top: 7px;
        }

        /* PESAN */
        .alert {
            padding: 14px 16px;
            border-radius: 11px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .success {
            color: #087443;
            background: #E7F8EF;
            border: 1px solid #B7E8CB;
        }

        .error {
            color: #B42318;
            background: #FFF0F0;
            border: 1px solid #F7C6C6;
        }

        /* TOMBOL */
        .actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 28px;
            padding-top: 22px;
            border-top: 1px solid var(--border-color);
        }

        .btn {
            border: 1px solid transparent;
            border-radius: 11px;
            padding: 12px 20px;
            font-size: 14px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s, transform 0.2s;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .btn-primary {
            background: var(--primary);
            color: #FFFFFF;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-secondary {
            background: #FFFFFF;
            border-color: var(--border-color);
            color: #344054;
        }

        .btn-secondary:hover {
            background: #F7FAFF;
            border-color: #B9CEFA;
        }

        footer {
            text-align: center;
            color: var(--text-muted);
            padding: 25px 15px;
            font-size: 13px;
        }

        a:focus-visible,
        button:focus-visible {
            outline: 3px solid #91B4FF;
            outline-offset: 3px;
        }

        /* RESPONSIVE */
        @media (max-width: 600px) {
            .navbar {
                padding: 15px 20px;
            }

            .navbar nav {
                width: 100%;
                gap: 5px;
            }

            .navbar nav a {
                padding: 8px 10px;
                font-size: 13px;
            }

            .container {
                padding: 25px 14px;
            }

            .hero {
                padding: 30px 24px;
                border-radius: 22px;
            }

            .hero h1 {
                font-size: 30px;
            }

            .hero p {
                font-size: 15px;
            }

            .card {
                padding: 22px 18px;
                border-radius: 16px;
            }

            .actions {
                flex-direction: column;
            }

            .actions .btn {
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

<header class="navbar">
    <a href="dashboard.php" class="brand">Studilance</a>

    <nav>
        <a href="dashboard.php">Dashboard</a>
        <a href="jobs.php">Proyek</a>
        <a href="profile.php">Profil Saya</a>
        <a href="auth/logout.php">Logout</a>
    </nav>
</header>

<main class="container">

    <section class="hero">
        <h1>Profil Saya</h1>
        <p>
            Lengkapi profilmu agar orang lain lebih mengenal dirimu
            dan keahlianmu di Studilance.
        </p>
    </section>

    <section class="card">

        <div class="avatar">
            <?= e(strtoupper(substr($user["name"], 0, 1))) ?>
        </div>

        <h2><?= e($user["name"]) ?></h2>
        <p><?= e($user["email"]) ?></p>

        <span class="role">
            <?= $user["role"] === "student"
                ? "Mahasiswa / Freelancer"
                : "Klien" ?>
        </span>

        <?php if ($success !== ""): ?>
            <div class="alert success">
                <?= e($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($error !== ""): ?>
            <div class="alert error">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="profile.php">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($_SESSION["csrf_token"]) ?>"
            >

            <div class="form-group">
                <label for="name">Nama lengkap</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    maxlength="100"
                    value="<?= e($user["name"]) ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="bio">Tentang saya</label>
                <textarea
                    id="bio"
                    name="bio"
                    placeholder="Ceritakan sedikit tentang dirimu..."
                ><?= e($user["bio"]) ?></textarea>
            </div>

            <?php if ($userRole === "student"): ?>

                <div class="form-group">
                    <label for="skills">Keahlian</label>
                    <textarea
                        id="skills"
                        name="skills"
                        placeholder="Contoh: UI/UX Design, HTML, CSS, Editing Video"
                    ><?= e($user["skills"]) ?></textarea>

                    <span class="hint">
                        Tuliskan keahlian yang kamu miliki.
                    </span>
                </div>

                <div class="form-group">
                    <label for="experience">
                        Pengalaman Kerja / Freelance
                    </label>

                    <textarea
                        id="experience"
                        name="experience"
                        placeholder="Contoh: Membuat desain poster organisasi kampus, mengerjakan website sederhana, atau pengalaman magang..."
                    ><?= e($user["experience"]) ?></textarea>

                    <span class="hint">
                        Ceritakan pengalaman proyek, organisasi,
                        magang, atau freelance yang pernah kamu lakukan.
                        Jika belum punya pengalaman, boleh dikosongkan.
                    </span>
                </div>

                <div class="form-group">
                    <label for="portfolio_url">Link portofolio</label>

                    <input
                        type="url"
                        id="portfolio_url"
                        name="portfolio_url"
                        maxlength="255"
                        placeholder="https://contoh.com/portofolio"
                        value="<?= e($user["portfolio_url"]) ?>"
                    >

                    <span class="hint">
                        Boleh dikosongkan jika belum punya portofolio.
                    </span>
                </div>

            <?php endif; ?>

            <div class="actions">
                <button type="submit" class="btn btn-primary">
                    Simpan Perubahan
                </button>

                <a href="dashboard.php" class="btn btn-secondary">
                    Kembali ke Dashboard
                </a>
            </div>
        </form>

    </section>
</main>

<footer>
    &copy; <?= date("Y") ?> Studilance — Kembangkan Skill, Temukan Peluang.
</footer>

</body>
</html>