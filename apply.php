
<?php
session_start();
require_once __DIR__ . "/config/database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: auth/login.php");
    exit;
}

if ($_SESSION["user_role"] !== "student") {
    http_response_code(403);
    exit("Hanya mahasiswa yang bisa melamar proyek.");
}

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$error = "";
$jobId = filter_input(INPUT_GET, "job_id", FILTER_VALIDATE_INT);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $jobId = filter_input(INPUT_POST, "job_id", FILTER_VALIDATE_INT);
    $proposal = trim($_POST["proposal"] ?? "");
    $token = $_POST["csrf_token"] ?? "";

    if (!hash_equals($_SESSION["csrf_token"], $token)) {
        $error = "Permintaan tidak valid. Muat ulang halaman.";
    } elseif (!$jobId || $jobId < 1) {
        $error = "Proyek tidak valid.";
    } elseif ($proposal === "" || strlen($proposal) > 5000) {
        $error = "Proposal wajib diisi dan maksimal 5000 karakter.";
    } else {
        $check = $conn->prepare(
            "SELECT id FROM jobs WHERE id = ? AND status = 'open'"
        );
        $check->bind_param("i", $jobId);
        $check->execute();
        $job = $check->get_result()->fetch_assoc();
        $check->close();

        if (!$job) {
            $error = "Proyek tidak ditemukan atau sudah tidak tersedia.";
        } else {
            $studentId = (int) $_SESSION["user_id"];

            $check = $conn->prepare(
                "SELECT id FROM applications WHERE job_id = ? AND student_id = ?"
            );
            $check->bind_param("ii", $jobId, $studentId);
            $check->execute();
            $alreadyApplied = $check->get_result()->fetch_assoc();
            $check->close();

            if ($alreadyApplied) {
                $error = "Kamu sudah pernah melamar proyek ini.";
            } else {
                $stmt = $conn->prepare(
                    "INSERT INTO applications (job_id, student_id, proposal)
                     VALUES (?, ?, ?)"
                );
                $stmt->bind_param("iis", $jobId, $studentId, $proposal);
                $stmt->execute();
                $stmt->close();

                header("Location: my_applications.php?applied=1");
                exit;
            }
        }
    }
}

$job = null;
if ($jobId && $jobId > 0) {
    $stmt = $conn->prepare(
        "SELECT id, title, category, budget
         FROM jobs WHERE id = ? AND status = 'open'"
    );
    $stmt->bind_param("i", $jobId);
    $stmt->execute();
    $job = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lamaran Proyek | Studilance</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 30px 16px;
            font-family: Arial, sans-serif;
            background: #f3f6fb;
            color: #172033;
        }
        main {
            max-width: 650px;
            margin: auto;
            padding: 30px;
            background: white;
            border-radius: 16px;
        }
        h1 { color: #2457c5; }
        label { display: block; margin: 18px 0 8px; font-weight: bold; }
        textarea {
            width: 100%;
            min-height: 180px;
            padding: 12px;
            border: 1px solid #d0d5dd;
            border-radius: 8px;
            font: inherit;
        }
        button, .btn {
            display: inline-block;
            margin-top: 18px;
            padding: 12px 16px;
            border: 0;
            border-radius: 8px;
            background: #2457c5;
            color: white;
            text-decoration: none;
            cursor: pointer;
        }
        .error { padding: 12px; border-radius: 8px; background: #fff0f0; color: #b42318; }
    </style>
</head>
<body>
<main>
    <h1>Lamaran Proyek</h1>

    <?php if ($error !== ""): ?>
        <p class="error"><?= e($error) ?></p>
    <?php endif; ?>

    <?php if (!$job): ?>
        <p>Proyek tidak ditemukan atau sudah tidak tersedia.</p>
        <a class="btn" href="jobs.php">Kembali ke daftar proyek</a>
    <?php else: ?>
        <h2><?= e($job["title"]) ?></h2>
        <p>Kategori: <?= e($job["category"]) ?></p>
        <p>Anggaran: Rp <?= number_format((float) $job["budget"], 0, ",", ".") ?></p>

        <form method="POST">
            <input type="hidden" name="job_id" value="<?= (int) $job["id"] ?>">
            <input type="hidden" name="csrf_token" value="<?= e($_SESSION["csrf_token"]) ?>">

            <label for="proposal">Proposal kamu</label>
            <textarea
                id="proposal"
                name="proposal"
                maxlength="5000"
                placeholder="Perkenalkan diri, jelaskan skill yang kamu punya, dan bagaimana kamu akan mengerjakan proyek ini."
                required
            ><?= e($_POST["proposal"] ?? "") ?></textarea>

            <button type="submit">Kirim Lamaran</button>
        </form>
    <?php endif; ?>

    <p><a href="jobs.php">← Kembali ke daftar proyek</a></p>
</main>
</body>
</html>
