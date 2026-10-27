
<?php
session_start();

require_once __DIR__ . "/config/database.php";

// Hanya klien yang sudah login yang boleh menghapus proyek.
if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["user_role"] ?? "") !== "client"
) {
    header("Location: auth/login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit("Metode permintaan tidak diizinkan.");
}

$token = $_POST["csrf_token"] ?? "";

if (
    !is_string($token) ||
    empty($_SESSION["csrf_token"]) ||
    !hash_equals($_SESSION["csrf_token"], $token)
) {
    http_response_code(403);
    exit("Permintaan tidak valid. Silakan kembali dan coba lagi.");
}

$jobId = filter_input(INPUT_POST, "job_id", FILTER_VALIDATE_INT);

if (!$jobId || $jobId < 1) {
    http_response_code(400);
    exit("ID proyek tidak valid.");
}

$clientId = (int) $_SESSION["user_id"];

// Kondisi client_id mencegah klien menghapus proyek milik klien lain.
$stmt = $conn->prepare(
    "DELETE FROM jobs WHERE id = ? AND client_id = ?"
);
$stmt->bind_param("ii", $jobId, $clientId);
$stmt->execute();

$deleted = $stmt->affected_rows;
$stmt->close();

if ($deleted !== 1) {
    http_response_code(404);
    exit("Proyek tidak ditemukan atau bukan milik akun kamu.");
}

header("Location: jobs.php?deleted=1");
exit;
