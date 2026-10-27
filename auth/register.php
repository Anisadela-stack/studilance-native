
<?php
session_start();
require_once __DIR__ . "/../config/database.php";

if (isset($_SESSION["user_id"])) {
    header("Location: ../dashboard.php");
    exit;
}

$error = "";
$name = "";
$email = "";
$role = "student";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $role = $_POST["role"] ?? "student";
    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";

    if (
        $name === "" ||
        $email === "" ||
        $password === "" ||
        $confirmPassword === ""
    ) {
        $error = "Semua kolom wajib diisi.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format email tidak valid.";
    } elseif (!in_array($role, ["student", "client"], true)) {
        $error = "Peran pengguna tidak valid.";
    } elseif (strlen($password) < 8) {
        $error = "Password minimal 8 karakter.";
    } elseif ($password !== $confirmPassword) {
        $error = "Konfirmasi password tidak sama.";
    } else {
        $check = $conn->prepare(
            "SELECT id FROM users WHERE email = ?"
        );
        $check->bind_param("s", $email);
        $check->execute();
        $existingUser = $check->get_result()->fetch_assoc();
        $check->close();

        if ($existingUser) {
            $error = "Email sudah terdaftar. Gunakan email lain.";
        } else {
            $hashedPassword = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $stmt = $conn->prepare(
                "INSERT INTO users (name, email, password, role)
                 VALUES (?, ?, ?, ?)"
            );
            $stmt->bind_param(
                "ssss",
                $name,
                $email,
                $hashedPassword,
                $role
            );
            $stmt->execute();
            $stmt->close();

            header("Location: login.php?registered=1");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar | Studilance</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #2457C5;
            --primary-dark: #1945A5;
            --background: #F2F7FF;
            --text: #243047;
            --muted: #718096;
            --border: #E4EAF3;
            --white: #FFFFFF;
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
            color: var(--text);
            background: linear-gradient(135deg, #FFFFFF, var(--background));
        }

        a {
            color: var(--primary);
            text-decoration: none;
        }

        .navbar {
            width: 100%;
            background: rgba(255, 255, 255, .95);
            border-bottom: 1px solid var(--border);
        }

        .nav-inner {
            width: min(1120px, 90%);
            min-height: 74px;
            margin: auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .brand {
            font-family: 'Poppins', sans-serif;
            font-size: 25px;
            font-weight: 800;
            color: var(--primary);
        }

        .back-link {
            font-size: 14px;
            font-weight: 600;
        }

        .page {
            flex: 1;
            display: grid;
            place-items: center;
            padding: 40px 20px;
        }

        .register-layout {
            width: 100%;
            max-width: 980px;
            display: grid;
            grid-template-columns: 1fr 460px;
            align-items: center;
            gap: 45px;
        }

        .intro {
            padding: 10px;
        }

        .eyebrow {
            display: inline-block;
            padding: 8px 13px;
            margin-bottom: 20px;
            border-radius: 30px;
            background: #E8F0FF;
            color: var(--primary);
            font-size: 12px;
            font-weight: 700;
        }

        .intro h1 {
            font-family: 'Poppins', sans-serif;
            font-size: clamp(30px, 4vw, 43px);
            line-height: 1.25;
            margin-bottom: 18px;
        }

        .intro h1 span {
            color: var(--primary);
        }

        .intro > p {
            max-width: 400px;
            color: var(--muted);
            font-size: 15px;
            line-height: 1.9;
        }

        .benefits {
            display: grid;
            gap: 14px;
            margin-top: 26px;
        }

        .benefit {
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .benefit-icon {
            display: grid;
            place-items: center;
            flex-shrink: 0;
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: #E8F0FF;
            color: var(--primary);
            font-weight: 700;
        }

        .benefit strong {
            display: block;
            font-size: 13px;
            margin-bottom: 3px;
        }

        .benefit small {
            display: block;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.6;
        }

        .card {
            width: 100%;
            padding: 32px;
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 20px;
            box-shadow: 0 18px 55px rgba(36, 87, 197, .09);
        }

        .card h2 {
            font-family: 'Poppins', sans-serif;
            font-size: 25px;
            margin-bottom: 8px;
        }

        .subtitle {
            color: var(--muted);
            font-size: 13px;
            line-height: 1.7;
            margin-bottom: 22px;
        }

        .error {
            padding: 12px 14px;
            margin-bottom: 18px;
            border: 1px solid #FECACA;
            border-radius: 9px;
            background: #FFF0F0;
            color: #B42318;
            font-size: 13px;
            line-height: 1.6;
        }

        .form-group {
            margin-bottom: 16px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-size: 13px;
            font-weight: 600;
        }

        input, select {
            display: block;
            width: 100%;
            min-height: 45px;
            padding: 12px 13px;
            border: 1px solid #D7DFEC;
            border-radius: 10px;
            background: #FFFFFF;
            color: var(--text);
            font-family: 'Inter', sans-serif;
            font-size: 13px;
            outline: none;
            transition: border-color .2s, box-shadow .2s;
        }

        input:focus, select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(36, 87, 197, .10);
        }

        .hint {
            margin-top: 6px;
            color: var(--muted);
            font-size: 11px;
        }

        .submit-btn {
            width: 100%;
            padding: 14px;
            margin-top: 5px;
            border: none;
            border-radius: 10px;
            background: var(--primary);
            color: white;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: background .2s, transform .2s;
        }

        .submit-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }

        .bottom {
            margin-top: 21px;
            text-align: center;
            color: var(--muted);
            font-size: 13px;
        }

        .bottom a {
            font-weight: 700;
        }

        .footer {
            padding: 20px;
            text-align: center;
            color: var(--muted);
            font-size: 12px;
        }

        @media (max-width: 800px) {
            .register-layout {
                max-width: 470px;
                grid-template-columns: 1fr;
                gap: 25px;
            }

            .intro {
                padding: 0;
                text-align: center;
            }

            .intro h1 {
                font-size: 30px;
            }

            .intro > p {
                margin: auto;
            }

            .benefits {
                display: none;
            }

            .card {
                padding: 27px 23px;
            }

            .page {
                padding: 30px 18px;
            }
        }
    </style>
</head>

<body>
<header class="navbar">
    <div class="nav-inner">
        <a href="../index.php" class="brand">Studilance.</a>
        <a href="../index.php" class="back-link">&larr; Beranda</a>
    </div>
</header>

<main class="page">
    <div class="register-layout">

        <section class="intro">
            <span class="eyebrow">JOIN STUDILANCE</span>

            <h1>
                Mulai Perjalanan
                <span>Freelance-mu.</span>
            </h1>

            <p>
                Buat akun Studilance dan hubungkan kemampuanmu dengan
                peluang proyek yang sesuai.
            </p>

            <div class="benefits">
                <div class="benefit">
                    <div class="benefit-icon">01</div>
                    <div>
                        <strong>Untuk Mahasiswa</strong>
                        <small>Temukan proyek dan kembangkan portofoliomu.</small>
                    </div>
                </div>

                <div class="benefit">
                    <div class="benefit-icon">02</div>
                    <div>
                        <strong>Untuk Klien</strong>
                        <small>Temukan mahasiswa dengan skill yang sesuai kebutuhan.</small>
                    </div>
                </div>

                <div class="benefit">
                    <div class="benefit-icon">03</div>
                    <div>
                        <strong>Mulai dengan Mudah</strong>
                        <small>Buat akun untuk mengakses fitur Studilance.</small>
                    </div>
                </div>
            </div>
        </section>

        <section class="card">
            <h2>Buat Akun</h2>
            <p class="subtitle">
                Isi informasi berikut untuk bergabung dengan Studilance.
            </p>

            <?php if ($error !== ""): ?>
                <p class="error">
                    <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?>
                </p>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="name">Nama lengkap</label>
                    <input
                        id="name"
                        name="name"
                        type="text"
                        maxlength="100"
                        placeholder="Masukkan nama lengkap"
                        autocomplete="name"
                        value="<?= htmlspecialchars($name, ENT_QUOTES, "UTF-8") ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        maxlength="150"
                        placeholder="nama@email.com"
                        autocomplete="email"
                        value="<?= htmlspecialchars($email, ENT_QUOTES, "UTF-8") ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="role">Daftar sebagai</label>
                    <select id="role" name="role" required>
                        <option value="student" <?= $role === "student" ? "selected" : "" ?>>
                            Mahasiswa — mencari proyek
                        </option>
                        <option value="client" <?= $role === "client" ? "selected" : "" ?>>
                            Klien — menawarkan proyek
                        </option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        minlength="8"
                        autocomplete="new-password"
                        placeholder="Minimal 8 karakter"
                        required
                    >
                    <p class="hint">Gunakan password minimal 8 karakter.</p>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Konfirmasi password</label>
                    <input
                        id="confirm_password"
                        name="confirm_password"
                        type="password"
                        minlength="8"
                        autocomplete="new-password"
                        placeholder="Ulangi password"
                        required
                    >
                </div>

                <button type="submit" class="submit-btn">
                    Buat Akun &rarr;
                </button>
            </form>

            <p class="bottom">
                Sudah punya akun?
                <a href="login.php">Login di sini</a>
            </p>
        </section>

    </div>
</main>

<footer class="footer">
    © <?= date('Y') ?> Studilance. Freelance platform untuk mahasiswa.
</footer>
</body>
</html>
