
<?php
session_start();
require_once __DIR__ . "/config/database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: auth/login.php");
    exit;
}

if (($_SESSION["user_role"] ?? "") !== "client") {
    http_response_code(403);
    exit("Akses ditolak. Halaman ini hanya untuk klien.");
}

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$error = "";
$title = "";
$category = "";
$description = "";
$budget = "";
$deadline = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST["csrf_token"] ?? "";

    $title = trim($_POST["title"] ?? "");
    $category = trim($_POST["category"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $budget = trim($_POST["budget"] ?? "");
    $deadline = trim($_POST["deadline"] ?? "");

    if (!hash_equals($_SESSION["csrf_token"], $token)) {
        $error = "Permintaan tidak valid. Silakan muat ulang halaman.";
    } elseif (
        $title === "" ||
        $category === "" ||
        $description === "" ||
        $budget === ""
    ) {
        $error = "Judul, kategori, deskripsi, dan anggaran wajib diisi.";
    } elseif (
        strlen($title) > 150 ||
        strlen($category) > 100
    ) {
        $error = "Judul maksimal 150 karakter dan kategori maksimal 100 karakter.";
    } elseif (!is_numeric($budget) || (float) $budget < 0) {
        $error = "Anggaran harus berupa angka nol atau lebih.";
    } elseif ((float) $budget > 9999999999.99) {
        $error = "Anggaran terlalu besar.";
    } elseif (
        $deadline !== "" &&
        (
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $deadline) ||
            !checkdate(
                (int) substr($deadline, 5, 2),
                (int) substr($deadline, 8, 2),
                (int) substr($deadline, 0, 4)
            )
        )
    ) {
        $error = "Tanggal deadline tidak valid.";
    } else {
        $clientId = (int) $_SESSION["user_id"];
        $budgetValue = (float) $budget;
        $deadlineValue = $deadline === "" ? null : $deadline;

        $stmt = $conn->prepare(
            "INSERT INTO jobs
                (client_id, title, category, description, budget, deadline)
             VALUES (?, ?, ?, ?, ?, ?)"
        );

        $stmt->bind_param(
            "isssds",
            $clientId,
            $title,
            $category,
            $description,
            $budgetValue,
            $deadlineValue
        );

        $stmt->execute();
        $stmt->close();

        unset($_SESSION["csrf_token"]);

        header("Location: jobs.php?created=1");
        exit;
    }
}

function e($value) {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Buat Proyek | Studilance</title>

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
            --shadow-card: 0 12px 35px rgba(31, 64, 130, 0.08);
        }

        body {
            margin: 0;
            padding: 36px 16px;
            font-family: 'Inter', sans-serif;
            background: var(--page-bg);
            color: var(--text-main);
        }

        .card {
            max-width: 700px;
            margin: 0 auto;
            padding: 36px;
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: 22px;
            box-shadow: var(--shadow-card);
        }

        .brand {
            color: var(--primary);
            font-family: 'Poppins', sans-serif;
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -0.7px;
            margin-bottom: 28px;
        }

        h1, h2, h3 {
            font-family: 'Poppins', sans-serif;
        }

        h1 {
            margin: 0 0 12px;
            color: var(--text-main);
            font-size: clamp(25px, 4vw, 32px);
            line-height: 1.35;
        }

        .intro {
            color: var(--text-muted);
            line-height: 1.8;
            margin: 0 0 28px;
            font-size: 14px;
        }

        label {
            display: block;
            margin: 21px 0 8px;
            font-size: 14px;
            font-weight: 700;
            color: #344054;
        }

        input, select, textarea {
            display: block;
            width: 100%;
            padding: 13px 14px;
            border: 1px solid #D0D8E6;
            border-radius: 10px;
            background: #FFFFFF;
            color: var(--text-main);
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        input:focus, select:focus, textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(36, 87, 197, 0.12);
        }

        input::placeholder, textarea::placeholder {
            color: #98A2B3;
        }

        textarea {
            min-height: 150px;
            resize: vertical;
            line-height: 1.7;
        }

        button {
            width: 100%;
            margin-top: 27px;
            padding: 14px 18px;
            border: none;
            border-radius: 11px;
            background: var(--primary);
            color: #FFFFFF;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s, transform 0.2s;
        }

        button:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }

        .error {
            padding: 14px 16px;
            background: #FFF0F0;
            color: #B42318;
            border: 1px solid #FECACA;
            border-radius: 10px;
            font-size: 14px;
            line-height: 1.6;
        }

        .back-link {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
            font-size: 14px;
        }

        a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }

        a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        @media (max-width: 600px) {
            body {
                padding: 20px 12px;
            }

            .card {
                padding: 24px 20px;
                border-radius: 18px;
            }

            .brand {
                font-size: 22px;
                margin-bottom: 22px;
            }

            h1 {
                font-size: 25px;
            }
        }
    </style>
</head>

<body>
    <main class="card">

        <div class="brand">Studilance</div>

        <h1>Buat Proyek Baru</h1>

        <p class="intro">
            Jelaskan proyek yang ingin kamu tawarkan kepada mahasiswa
            agar freelancer yang tepat dapat menemukan pekerjaan ini.
        </p>

        <?php if ($error !== ""): ?>
            <p class="error"><?= e($error) ?></p>
        <?php endif; ?>

        <form method="POST">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($_SESSION["csrf_token"]) ?>"
            >

            <label for="title">Judul proyek</label>
            <input
                id="title"
                name="title"
                type="text"
                maxlength="150"
                value="<?= e($title) ?>"
                placeholder="Contoh: Membuat desain poster"
                required
            >

            <label for="category">Kategori</label>
            <select id="category" name="category" required>
                <option value="">Pilih kategori</option>
                <?php
                $categories = [
                    "Desain Grafis",
                    "Editing Video",
                    "Programming",
                    "Fotografi",
                    "Copywriting",
                    "Pembuatan Website",
                    "Lainnya"
                ];

                foreach ($categories as $item):
                ?>
                    <option
                        value="<?= e($item) ?>"
                        <?= $category === $item ? "selected" : "" ?>
                    >
                        <?= e($item) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="description">Deskripsi proyek</label>
            <textarea
                id="description"
                name="description"
                placeholder="Jelaskan pekerjaan dan hasil yang dibutuhkan..."
                required
            ><?= e($description) ?></textarea>

            <label for="budget">Anggaran (Rp)</label>
            <input
                id="budget"
                name="budget"
                type="number"
                min="0"
                max="9999999999.99"
                step="0.01"
                value="<?= e($budget) ?>"
                placeholder="Contoh: 150000"
                required
            >

            <label for="deadline">Deadline (opsional)</label>
            <input
                id="deadline"
                name="deadline"
                type="date"
                value="<?= e($deadline) ?>"
            >

            <button type="submit">Publikasikan Proyek</button>
        </form>

        <p class="back-link">
            <a href="jobs.php">&larr; Kembali ke daftar proyek</a>
        </p>

    </main>
</body>
</html>
