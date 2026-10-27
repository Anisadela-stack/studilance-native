
<?php
session_start();
require_once __DIR__ . "/../config/database.php";

if (isset($_SESSION["user_id"])) {
    header("Location: ../dashboard.php");
    exit;
}

$error = "";
$email = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "" || $password === "") {
        $error = "Email dan password wajib diisi.";
    } else {
        $stmt = $conn->prepare(
            "SELECT id, name, email, password, role
             FROM users
             WHERE email = ?
             LIMIT 1"
        );

        $stmt->bind_param("s", $email);
        $stmt->execute();

        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user["password"])) {
            session_regenerate_id(true);

            $_SESSION["user_id"] = $user["id"];
            $_SESSION["user_name"] = $user["name"];
            $_SESSION["user_email"] = $user["email"];
            $_SESSION["user_role"] = $user["role"];

            header("Location: ../dashboard.php");
            exit;
        } else {
            $error = "Email atau password salah.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login | Studilance</title>

    <link rel="stylesheet" href="../assets/style.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --login-primary: #2457C5;
            --login-primary-dark: #1945A5;
            --login-background: #F2F7FF;
            --login-text: #243047;
            --login-muted: #718096;
            --login-border: #E4EAF3;
            --login-white: #FFFFFF;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            font-family: 'Inter', sans-serif;
            color: var(--login-text);
            background: linear-gradient(
                135deg,
                #FFFFFF,
                var(--login-background)
            );
        }

        a {
            text-decoration: none;
        }

        .login-navbar {
            width: 100%;
            background: rgba(255, 255, 255, 0.96);
            border-bottom: 1px solid var(--login-border);
        }

        .login-nav-inner {
            width: min(1120px, 90%);
            min-height: 74px;
            margin: auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .login-brand {
            font-family: 'Poppins', sans-serif;
            font-size: 25px;
            font-weight: 800;
            color: var(--login-primary);
        }

        .login-back-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 11px 17px;
            border: 1px solid var(--login-primary);
            border-radius: 10px;
            background: var(--login-primary);
            color: #FFFFFF;
            font-family: 'Inter', sans-serif;
            font-size: 13px;
            font-weight: 700;
            transition: background 0.2s ease, transform 0.2s ease;
        }

        .login-back-btn:hover {
            background: var(--login-primary-dark);
            border-color: var(--login-primary-dark);
            color: #FFFFFF;
            transform: translateY(-1px);
        }

        .login-page {
            flex: 1;
            display: grid;
            place-items: center;
            padding: 45px 20px;
        }

        .login-layout {
            width: 100%;
            max-width: 940px;
            display: grid;
            grid-template-columns: minmax(0, 1fr) 420px;
            align-items: center;
            gap: 45px;
        }

        .login-intro {
            padding: 10px;
        }

        .login-eyebrow {
            display: inline-block;
            padding: 8px 13px;
            margin-bottom: 20px;
            border-radius: 30px;
            background: #E8F0FF;
            color: var(--login-primary);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .login-intro h1 {
            font-family: 'Poppins', sans-serif;
            font-size: clamp(30px, 4vw, 43px);
            line-height: 1.25;
            margin-bottom: 18px;
        }

        .login-intro h1 span {
            color: var(--login-primary);
        }

        .login-intro p {
            max-width: 390px;
            color: var(--login-muted);
            font-size: 15px;
            line-height: 1.9;
        }

        .login-points {
            display: grid;
            gap: 13px;
            margin-top: 26px;
            font-size: 14px;
            font-weight: 500;
        }

        .login-card {
            width: 100%;
            padding: 34px;
            border: 1px solid var(--login-border);
            border-radius: 20px;
            background: var(--login-white);
            box-shadow: 0 18px 55px rgba(36, 87, 197, 0.09);
        }

        .login-card h2 {
            font-family: 'Poppins', sans-serif;
            font-size: 25px;
            margin-bottom: 8px;
        }

        .login-subtitle {
            color: var(--login-muted);
            font-size: 13px;
            line-height: 1.7;
            margin-bottom: 25px;
        }

        .login-message {
            padding: 12px 14px;
            border-radius: 9px;
            font-size: 13px;
            line-height: 1.6;
            margin-bottom: 17px;
        }

        .login-error {
            color: #B42318;
            background: #FFF0F0;
            border: 1px solid #FECACA;
        }

        .login-success {
            color: #067647;
            background: #ECFDF3;
            border: 1px solid #ABEFC6;
        }

        .login-form-group {
            margin-bottom: 18px;
        }

        .login-form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .login-form-group input {
            display: block;
            width: 100%;
            padding: 13px 14px;
            border: 1px solid #D7DFEC;
            border-radius: 10px;
            background: #FFFFFF;
            color: var(--login-text);
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .login-form-group input:focus {
            border-color: var(--login-primary);
            box-shadow: 0 0 0 3px rgba(36, 87, 197, 0.10);
        }

        .login-submit-btn {
            display: block;
            width: 100%;
            padding: 14px;
            margin-top: 8px;
            border: none;
            border-radius: 10px;
            background: var(--login-primary);
            color: #FFFFFF;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s, transform 0.2s;
        }

        .login-submit-btn:hover {
            background: var(--login-primary-dark);
            transform: translateY(-1px);
        }

        .login-bottom {
            margin-top: 23px;
            text-align: center;
            font-size: 13px;
            color: var(--login-muted);
        }

        .login-bottom a {
            color: var(--login-primary);
            font-weight: 700;
        }

        .login-bottom a:hover {
            text-decoration: underline;
        }

        .login-footer {
            padding: 20px;
            text-align: center;
            color: var(--login-muted);
            font-size: 12px;
        }

        @media (max-width: 760px) {
            .login-layout {
                max-width: 440px;
                grid-template-columns: 1fr;
                gap: 25px;
            }

            .login-intro {
                padding: 0;
                text-align: center;
            }

            .login-intro h1 {
                font-size: 30px;
            }

            .login-intro p {
                margin: auto;
            }

            .login-points {
                display: none;
            }

            .login-card {
                padding: 27px 23px;
            }

            .login-page {
                padding: 32px 18px;
            }

            .login-nav-inner {
                width: 92%;
                min-height: 65px;
            }

            .login-brand {
                font-size: 22px;
            }

            .login-back-btn {
                padding: 10px 12px;
                font-size: 12px;
            }
        }

        @media (max-width: 360px) {
            .login-brand {
                font-size: 19px;
            }

            .login-back-btn {
                padding: 9px;
                font-size: 11px;
            }
        }
    </style>
</head>

<body>

<header class="login-navbar">
    <div class="login-nav-inner">
        <a href="../index.php" class="login-brand">Studilance.</a>

        <a href="../index.php" class="login-back-btn">
            &larr; Kembali ke Beranda
        </a>
    </div>
</header>

<main class="login-page">
    <div class="login-layout">

        <section class="login-intro">
            <span class="login-eyebrow">WELCOME TO STUDILANCE</span>

            <h1>
                Skill Kamu Punya
                <span>Peluang.</span>
            </h1>

            <p>
                Masuk ke akunmu dan lanjutkan perjalanan untuk menemukan
                proyek, mengembangkan kemampuan, serta membangun portofolio.
            </p>

            <div class="login-points">
                <span>✓ Temukan proyek sesuai skill kamu</span>
                <span>✓ Bangun pengalaman freelance</span>
                <span>✓ Kembangkan portofolio profesional</span>
            </div>
        </section>

        <section class="login-card">
            <h2>Selamat Datang!</h2>

            <p class="login-subtitle">
                Login untuk melanjutkan ke akun Studilance kamu.
            </p>

            <?php if (isset($_GET["registered"])): ?>
                <p class="login-message login-success">
                    Registrasi berhasil! Silakan login.
                </p>
            <?php endif; ?>

            <?php if ($error !== ""): ?>
                <p class="login-message login-error">
                    <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?>
                </p>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="login-form-group">
                    <label for="email">Email</label>

                    <input
                        id="email"
                        name="email"
                        type="email"
                        placeholder="nama@email.com"
                        value="<?= htmlspecialchars($email, ENT_QUOTES, "UTF-8") ?>"
                        autocomplete="email"
                        required
                    >
                </div>

                <div class="login-form-group">
                    <label for="password">Password</label>

                    <input
                        id="password"
                        name="password"
                        type="password"
                        placeholder="Masukkan password kamu"
                        autocomplete="current-password"
                        required
                    >
                </div>

                <button type="submit" class="login-submit-btn">
                    Masuk ke Akun &rarr;
                </button>
            </form>

            <p class="login-bottom">
                Belum punya akun?
                <a href="register.php">Daftar sekarang</a>
            </p>
        </section>

    </div>
</main>

<footer class="login-footer">
    &copy; <?= date("Y") ?> Studilance. Freelance platform untuk mahasiswa.
</footer>

</body>
</html>
