
<?php
session_start();

$isLoggedIn = isset($_SESSION['user_id']);
$dashboardUrl = 'dashboard.php';

if ($isLoggedIn) {
    $mainButton = 'Buka Dashboard';
    $mainUrl = $dashboardUrl;
} else {
    $mainButton = 'Mulai Sekarang';
    $mainUrl = 'auth/register.php';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Studilance — Temukan Peluang Freelance</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- CSS utama Studilance -->
    <link rel="stylesheet" href="assets/style.css">

    <style>
        :root {
            --home-primary: #2457C5;
            --home-primary-dark: #1945A5;
            --home-background: #F2F7FF;
            --home-text: #243047;
            --home-muted: #718096;
            --home-border: #E4EAF3;
            --home-white: #FFFFFF;
        }

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
            scroll-padding-top: 90px;
        }

        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            color: var(--home-text);
            background: var(--home-white);
            line-height: 1.6;
        }

        body, button, input {
            font-family: 'Inter', sans-serif;
        }

        a {
            text-decoration: none;
        }

        .home-container {
            width: min(1120px, 90%);
            margin: 0 auto;
        }

        /* NAVBAR */
        .home-navbar {
            position: sticky;
            top: 0;
            z-index: 100;
            background: rgba(255, 255, 255, 0.97);
            border-bottom: 1px solid var(--home-border);
            box-shadow: 0 3px 15px rgba(36, 87, 197, 0.03);
        }

        .home-nav-content {
            min-height: 76px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .home-brand {
            color: var(--home-primary);
            font-family: 'Poppins', sans-serif;
            font-size: 25px;
            font-weight: 800;
            white-space: nowrap;
        }

        .home-nav-links {
            display: flex;
            align-items: center;
            gap: 26px;
        }

        .home-nav-links a {
            color: #526078;
            font-size: 14px;
            font-weight: 500;
            transition: color 0.2s;
        }

        .home-nav-links a:hover {
            color: var(--home-primary);
        }

        .home-nav-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* BUTTONS */
        .home-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 19px;
            border: 1px solid transparent;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            line-height: 1.4;
            cursor: pointer;
            transition: background 0.2s, color 0.2s, transform 0.2s;
        }

        .home-btn:hover {
            transform: translateY(-1px);
        }

        .home-btn-primary {
            background: var(--home-primary);
            color: #FFFFFF;
        }

        .home-btn-primary:hover {
            background: var(--home-primary-dark);
            color: #FFFFFF;
        }

        .home-btn-outline {
            background: #FFFFFF;
            color: var(--home-primary);
            border-color: #D5E1FA;
        }

        .home-btn-outline:hover {
            background: #F0F5FF;
            color: var(--home-primary-dark);
        }

        /* HERO */
        .home-hero {
            padding: 76px 0 85px;
            background: linear-gradient(135deg, #FFFFFF 15%, #F2F7FF 100%);
            overflow: hidden;
        }

        .home-hero-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.1fr) minmax(0, 0.9fr);
            gap: 55px;
            align-items: center;
        }

        .home-eyebrow {
            display: inline-block;
            padding: 7px 13px;
            border-radius: 30px;
            background: #E8F0FF;
            color: var(--home-primary);
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 22px;
        }

        .home-hero h1 {
            font-family: 'Poppins', sans-serif;
            font-size: clamp(35px, 5vw, 55px);
            line-height: 1.2;
            letter-spacing: -1.5px;
            margin: 0 0 22px;
            font-weight: 800;
        }

        .home-hero h1 span {
            color: var(--home-primary);
        }

        .home-hero-description {
            color: var(--home-muted);
            font-size: 16px;
            line-height: 1.9;
            max-width: 540px;
            margin: 0 0 28px;
        }

        .home-hero-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 30px;
        }

        .home-hero-note {
            font-size: 13px;
            color: var(--home-muted);
            line-height: 1.8;
        }

        /* PREVIEW CARD */
        .home-hero-visual {
            position: relative;
            padding: 24px;
            border-radius: 28px;
            background: linear-gradient(145deg, #DCE9FF, #F7FAFF);
        }

        .home-preview-card {
            position: relative;
            background: #FFFFFF;
            border: 1px solid #E6EDFA;
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 18px 50px rgba(36, 87, 197, 0.10);
        }

        .home-preview-label {
            font-size: 12px;
            color: var(--home-muted);
            margin: 0 0 7px;
            font-weight: 600;
            letter-spacing: 0.4px;
        }

        .home-preview-card h3 {
            font-family: 'Poppins', sans-serif;
            font-size: 21px;
            line-height: 1.5;
            margin: 0 0 8px;
        }

        .home-preview-description {
            color: var(--home-muted);
            font-size: 13px;
            line-height: 1.8;
            margin: 0 0 20px;
        }

        .home-skill-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 22px;
        }

        .home-skill {
            padding: 7px 11px;
            border-radius: 8px;
            background: #F0F5FF;
            color: var(--home-primary);
            font-size: 12px;
            font-weight: 600;
        }

        .home-preview-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            border-top: 1px solid var(--home-border);
            padding-top: 17px;
        }

        .home-preview-bottom strong {
            color: var(--home-primary);
            font-size: 14px;
        }

        .home-preview-bottom span {
            color: var(--home-muted);
            font-size: 12px;
        }

        /* GENERAL SECTIONS */
        .home-section {
            padding: 75px 0;
        }

        .home-section-heading {
            text-align: center;
            margin-bottom: 40px;
        }

        .home-section-title {
            font-family: 'Poppins', sans-serif;
            font-size: clamp(25px, 3vw, 34px);
            line-height: 1.4;
            margin: 0 0 12px;
        }

        .home-section-description {
            max-width: 650px;
            margin: 0 auto;
            color: var(--home-muted);
            font-size: 14px;
            line-height: 1.9;
        }

        /* SKILL CATEGORIES */
        .home-categories {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
            margin-top: 32px;
        }

        .home-category-card {
            padding: 25px;
            background: #FFFFFF;
            border: 1px solid var(--home-border);
            border-radius: 16px;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .home-category-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 30px rgba(36, 87, 197, 0.08);
        }

        .home-category-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            border-radius: 13px;
            background: #EAF1FF;
            font-size: 23px;
            margin-bottom: 17px;
        }

        .home-category-card h3 {
            font-family: 'Poppins', sans-serif;
            font-size: 17px;
            margin: 0 0 7px;
        }

        .home-category-card p {
            font-size: 13px;
            color: var(--home-muted);
            line-height: 1.8;
            margin: 0;
        }

        /* HOW IT WORKS */
        .home-how-section {
            background: #F5F8FF;
        }

        .home-steps {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 24px;
        }

        .home-step {
            background: #FFFFFF;
            padding: 26px;
            border-radius: 16px;
            border: 1px solid var(--home-border);
        }

        .home-step-number {
            display: inline-flex;
            width: 38px;
            height: 38px;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            color: #FFFFFF;
            background: var(--home-primary);
            font-weight: 700;
            margin-bottom: 18px;
        }

        .home-step h3 {
            font-family: 'Poppins', sans-serif;
            font-size: 17px;
            margin: 0 0 9px;
        }

        .home-step p {
            color: var(--home-muted);
            font-size: 13px;
            line-height: 1.8;
            margin: 0;
        }

        /* CALL TO ACTION */
        .home-cta {
            padding: 54px 25px;
            text-align: center;
            border-radius: 24px;
            background: linear-gradient(135deg, #2457C5, #477BE0);
            color: #FFFFFF;
        }

        .home-cta h2 {
            font-family: 'Poppins', sans-serif;
            font-size: clamp(25px, 4vw, 35px);
            line-height: 1.4;
            margin: 0 0 14px;
        }

        .home-cta p {
            color: #E7EEFF;
            margin: 0 auto 25px;
            max-width: 600px;
            font-size: 14px;
            line-height: 1.9;
        }

        .home-cta .home-btn {
            background: #FFFFFF;
            color: var(--home-primary);
        }

        .home-cta .home-btn:hover {
            background: #F0F5FF;
        }

        /* FOOTER */
        .home-footer {
            padding: 27px 0;
            border-top: 1px solid var(--home-border);
            color: var(--home-muted);
            font-size: 13px;
        }

        .home-footer-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        .home-footer-content p {
            margin: 0;
        }

        /* RESPONSIVE */
        @media (max-width: 800px) {
            .home-nav-links {
                display: none;
            }

            .home-hero {
                padding: 55px 0;
            }

            .home-hero-grid {
                grid-template-columns: 1fr;
                gap: 35px;
            }

            .home-categories {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .home-steps {
                grid-template-columns: 1fr;
            }

            .home-section {
                padding: 60px 0;
            }
        }

        @media (max-width: 480px) {
            .home-container {
                width: 92%;
            }

            .home-nav-content {
                min-height: 68px;
                gap: 10px;
            }

            .home-brand {
                font-size: 21px;
            }

            .home-nav-actions {
                gap: 7px;
            }

            .home-btn {
                padding: 10px 12px;
                font-size: 12px;
            }

            .home-hero h1 {
                letter-spacing: -0.8px;
            }

            .home-categories {
                grid-template-columns: 1fr;
            }

            .home-hero-visual {
                padding: 12px;
            }

            .home-preview-card {
                padding: 19px;
            }

            .home-preview-bottom {
                align-items: flex-start;
            }

            .home-footer-content {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>

<body>

<header class="home-navbar">
    <div class="home-container home-nav-content">
        <a href="index.php" class="home-brand">Studilance.</a>

        <nav class="home-nav-links" aria-label="Navigasi utama">
            <a href="#beranda">Beranda</a>
            <a href="#kategori">Kategori Skill</a>
            <a href="#cara-kerja">Cara Kerja</a>
        </nav>

        <div class="home-nav-actions">
            <?php if ($isLoggedIn): ?>
                <a href="<?= htmlspecialchars($dashboardUrl, ENT_QUOTES, 'UTF-8') ?>"
                   class="home-btn home-btn-primary">
                    Dashboard
                </a>
            <?php else: ?>
                <a href="auth/login.php" class="home-btn home-btn-outline">
                    Masuk
                </a>

                <a href="auth/register.php" class="home-btn home-btn-primary">
                    Daftar
                </a>
            <?php endif; ?>
        </div>
    </div>
</header>

<main>
    <section class="home-hero" id="beranda">
        <div class="home-container home-hero-grid">
            <div>
                <span class="home-eyebrow">
                    Platform Freelance untuk Mahasiswa
                </span>

                <h1>
                    Kembangkan Skill,<br>
                    Temukan <span>Peluangmu.</span>
                </h1>

                <p class="home-hero-description">
                    Studilance menghubungkan mahasiswa berbakat dengan
                    peluang proyek freelance. Ubah kemampuanmu menjadi
                    pengalaman, portofolio, dan penghasilan.
                </p>

                <div class="home-hero-buttons">
                    <a href="<?= htmlspecialchars($mainUrl, ENT_QUOTES, 'UTF-8') ?>"
                       class="home-btn home-btn-primary">
                        <?= htmlspecialchars($mainButton, ENT_QUOTES, 'UTF-8') ?>
                        &rarr;
                    </a>

                    <a href="jobs.php" class="home-btn home-btn-outline">
                        Jelajahi Proyek
                    </a>
                </div>

                <p class="home-hero-note">
                    Desain · Programming · Editing · Fotografi · Copywriting
                </p>
            </div>

            <div class="home-hero-visual">
                <div class="home-preview-card">
                    <p class="home-preview-label">
                        CONTOH PELUANG PROYEK
                    </p>

                    <h3>Website untuk Bisnis Lokal</h3>

                    <p class="home-preview-description">
                        Bantu bisnis lokal membangun kehadiran digital
                        melalui website yang menarik.
                    </p>

                    <div class="home-skill-list">
                        <span class="home-skill">Web Development</span>
                        <span class="home-skill">UI/UX</span>
                        <span class="home-skill">Programming</span>
                    </div>

                    <div class="home-preview-bottom">
                        <div>
                            <strong>Proyek Freelance</strong><br>
                            <span>Terbuka untuk mahasiswa</span>
                        </div>

                        <span>Studilance</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="home-section" id="kategori">
        <div class="home-container">
            <div class="home-section-heading">
                <h2 class="home-section-title">
                    Kembangkan Skill yang Kamu Miliki
                </h2>

                <p class="home-section-description">
                    Temukan peluang proyek yang sesuai dengan kemampuan
                    dan minatmu sebagai mahasiswa.
                </p>
            </div>

            <div class="home-categories">
                <article class="home-category-card">
                    <div class="home-category-icon">🎨</div>
                    <h3>Desain Grafis</h3>
                    <p>Buat desain visual, poster, logo, dan konten kreatif.</p>
                </article>

                <article class="home-category-card">
                    <div class="home-category-icon">💻</div>
                    <h3>Programming</h3>
                    <p>Kembangkan website dan solusi digital untuk klien.</p>
                </article>

                <article class="home-category-card">
                    <div class="home-category-icon">🎬</div>
                    <h3>Video Editing</h3>
                    <p>Ubah rekaman menjadi video yang menarik dan profesional.</p>
                </article>

                <article class="home-category-card">
                    <div class="home-category-icon">📸</div>
                    <h3>Fotografi</h3>
                    <p>Abadikan momen dan buat konten visual berkualitas.</p>
                </article>

                <article class="home-category-card">
                    <div class="home-category-icon">✍️</div>
                    <h3>Copywriting</h3>
                    <p>Tulis konten promosi dan pesan yang menarik audiens.</p>
                </article>

                <article class="home-category-card">
                    <div class="home-category-icon">🌐</div>
                    <h3>Website Development</h3>
                    <p>Bantu UMKM dan organisasi membangun website mereka.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="home-section home-how-section" id="cara-kerja">
        <div class="home-container">
            <div class="home-section-heading">
                <h2 class="home-section-title">
                    Mulai Perjalanan Freelance-mu
                </h2>

                <p class="home-section-description">
                    Langkah sederhana untuk menghubungkan skill dengan
                    peluang proyek.
                </p>
            </div>

            <div class="home-steps">
                <article class="home-step">
                    <div class="home-step-number">01</div>
                    <h3>Buat Profil</h3>
                    <p>
                        Daftarkan akun dan lengkapi profil serta skill
                        yang kamu kuasai.
                    </p>
                </article>

                <article class="home-step">
                    <div class="home-step-number">02</div>
                    <h3>Temukan Proyek</h3>
                    <p>
                        Jelajahi proyek dari UMKM, organisasi, komunitas,
                        dan individu.
                    </p>
                </article>

                <article class="home-step">
                    <div class="home-step-number">03</div>
                    <h3>Kembangkan Pengalaman</h3>
                    <p>
                        Ajukan lamaran, kerjakan proyek, dan bangun
                        portofolio untuk masa depan.
                    </p>
                </article>
            </div>
        </div>
    </section>

    <section class="home-section">
        <div class="home-container">
            <div class="home-cta">
                <h2>Skill Kamu Punya Peluang.</h2>

                <p>
                    Mulai langkahmu bersama Studilance dan temukan
                    kesempatan untuk berkembang.
                </p>

                <a href="<?= htmlspecialchars($mainUrl, ENT_QUOTES, 'UTF-8') ?>"
                   class="home-btn">
                    <?= htmlspecialchars($mainButton, ENT_QUOTES, 'UTF-8') ?>
                    &rarr;
                </a>
            </div>
        </div>
    </section>
</main>

<footer class="home-footer">
    <div class="home-container home-footer-content">
        <a href="index.php" class="home-brand">Studilance.</a>

        <p>
            &copy; <?= date('Y') ?> Studilance.
            Platform freelance untuk mahasiswa.
        </p>
    </div>
</footer>

</body>
</html>
