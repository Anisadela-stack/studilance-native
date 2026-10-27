
<?php
session_start();

require_once __DIR__ . "/config/database.php";

// Pastikan pengguna sudah login sebagai klien.
if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["user_role"] ?? "") !== "client"
) {
    header("Location: auth/login.php");
    exit;
}

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

$clientId = (int) $_SESSION["user_id"];
$jobId = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$jobId || $jobId < 1) {
    http_response_code(400);
    exit("ID proyek tidak valid.");
}

// Ambil proyek yang benar-benar dimiliki klien yang sedang login.
$stmt = $conn->prepare(
    "SELECT id, title, category, description, budget, deadline, status
     FROM jobs
     WHERE id = ? AND client_id = ?
     LIMIT 1"
);
$stmt->bind_param("ii", $jobId, $clientId);
$stmt->execute();

$result = $stmt->get_result();
$job = $result->fetch_assoc();
$stmt->close();

if (!$job) {
    http_response_code(404);
    exit("Proyek tidak ditemukan atau bukan milik akun kamu.");
}

// Token keamanan untuk form.
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST["csrf_token"] ?? "";

    if (
        !is_string($token) ||
        !hash_equals($_SESSION["csrf_token"], $token)
    ) {
        $error = "Permintaan tidak valid. Silakan muat ulang halaman.";
    } else {
        $title = trim($_POST["title"] ?? "");
        $category = trim($_POST["category"] ?? "");
        $description = trim($_POST["description"] ?? "");
        $budgetInput = trim($_POST["budget"] ?? "");
        $deadlineInput = trim($_POST["deadline"] ?? "");
        $status = $_POST["status"] ?? "";

        $allowedStatuses = [
            "open",
            "in_progress",
            "completed",
            "closed"
        ];

        if (
            $title === "" ||
            $category === "" ||
            $description === ""
        ) {
            $error = "Judul, kategori, dan deskripsi wajib diisi.";
        } elseif (
            strlen($title) > 150 ||
            strlen($category) > 100
        ) {
            $error = "Judul maksimal 150 karakter dan kategori maksimal 100 karakter.";
        } elseif (!is_numeric($budgetInput) || (float) $budgetInput < 0) {
            $error = "Anggaran harus berupa angka nol atau lebih.";
        } elseif ((float) $budgetInput > 9999999999.99) {
            $error = "Anggaran terlalu besar.";
        } elseif (
            $deadlineInput !== "" &&
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $deadlineInput)
        ) {
            $error = "Format deadline tidak valid.";
        } elseif (!in_array($status, $allowedStatuses, true)) {
            $error = "Status proyek tidak valid.";
        } else {
            // Validasi bahwa tanggal benar-benar ada.
            if ($deadlineInput !== "") {
                $date = DateTime::createFromFormat("!Y-m-d", $deadlineInput);

                if (!$date || $date->format("Y-m-d") !== $deadlineInput) {
                    $error = "Tanggal deadline tidak valid.";
                }
            }

            if ($error === "") {
                $budget = (float) $budgetInput;
                $deadline = $deadlineInput === "" ? null : $deadlineInput;

                // client_id tetap digunakan dalam WHERE untuk keamanan.
                $update = $conn->prepare(
                    "UPDATE jobs
                     SET title = ?,
                         category = ?,
                         description = ?,
                         budget = ?,
                         deadline = ?,
                         status = ?
                     WHERE id = ? AND client_id = ?"
                );

                $update->bind_param(
                    "sssdssii",
                    $title,
                    $category,
                    $description,
                    $budget,
                    $deadline,
                    $status,
                    $jobId,
                    $clientId
                );

                $update->execute();
                $update->close();

                header("Location: jobs.php?updated=1");
                exit;
            }
        }

        // Isi form tetap dipertahankan jika validasi gagal.
        $job["title"] = $title;
        $job["category"] = $category;
        $job["description"] = $description;
        $job["budget"] = $budgetInput;
        $job["deadline"] = $deadlineInput;
        $job["status"] = $status;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Proyek | Studilance</title>
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 35px 16px;
            font-family: Arial, sans-serif;
            background: #f3f6fb;
            color: #172033;
        }

        .container {
            max-width: 760px;
            margin: 0 auto;
            padding: 32px;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 8px 30px rgba(20, 40, 80, .06);
        }

        h1 { color: #2457c5; margin-top: 0; }

        label {
            display: block;
            margin: 18px 0 8px;
            font-weight: bold;
        }

        input, textarea, select {
            width: 100%;
            padding: 13px;
            border: 1px solid #d5dce8;
            border-radius: 9px;
            font: inherit;
        }

        textarea {
            min-height: 140px;
            resize: vertical;
        }

        .error {
            padding: 12px;
            border-radius: 8px;
            background: #fff0ef;
            color: #b42318;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 24px;
        }

        .button {
            display: inline-block;
            padding: 13px 18px;
            border: 0;
            border-radius: 9px;
            background: #2457c5;
            color: white;
            text-decoration: none;
            font: inherit;
            cursor: pointer;
        }

        .secondary {
            background: #edf1f6;
            color: #172033;
        }

        @media (max-width: 500px) {
            .container { padding: 22px; }
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Edit Proyek</h1>
    <p>Perbarui informasi proyek freelance kamu.</p>

    <?php if ($error !== ""): ?>
        <p class="error"><?= e($error) ?></p>
    <?php endif; ?>

    <form method="POST">
        <input
            type="hidden"
            name="csrf_token"
            value="<?= e($_SESSION["csrf_token"]) ?>"
        >

        <label for="title">Judul Proyek</label>
        <input
            id="title"
            type="text"
            name="title"
            maxlength="150"
            required
            value="<?= e($job["title"]) ?>"
        >

        <label for="category">Kategori</label>
        <input
            id="category"
            type="text"
            name="category"
            maxlength="100"
            required
            value="<?= e($job["category"]) ?>"
        >

        <label for="description">Deskripsi Proyek</label>
        <textarea
            id="description"
            name="description"
            required
        ><?= e($job["description"]) ?></textarea>

        <label for="budget">Anggaran (Rp)</label>
        <input
            id="budget"
            type="number"
            name="budget"
            min="0"
            step="0.01"
            required
            value="<?= e($job["budget"]) ?>"
        >

        <label for="deadline">Deadline</label>
        <input
            id="deadline"
            type="date"
            name="deadline"
            value="<?= e($job["deadline"]) ?>"
        >

        <label for="status">Status Proyek</label>
        <select id="status" name="status" required>
            <?php
            $statuses = [
                "open" => "Terbuka",
                "in_progress" => "Sedang Dikerjakan",
                "completed" => "Selesai",
                "closed" => "Ditutup"
            ];
            ?>
            <?php foreach ($statuses as $value => $label): ?>
                <option
                    value="<?= e($value) ?>"
                    <?= $job["status"] === $value ? "selected" : "" ?>
                >
                    <?= e($label) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <div class="actions">
            <button class="button" type="submit">Simpan Perubahan</button>
            <a class="button secondary" href="jobs.php">Batal</a>
        </div>
    </form>
</div>
</body>
</html>
