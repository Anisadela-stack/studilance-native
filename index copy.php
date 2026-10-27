
<?php
require_once __DIR__ . "/config/database.php";

$result = $conn->query("SELECT DATABASE() AS db_name");
$row = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tes Backend Studilance</title>
</head>
<body>
    <h1>Studilance Backend</h1>

    <p>PHP Native berhasil dijalankan!</p>

    <p>
        Database terhubung:
        <strong>
            <?= htmlspecialchars($row["db_name"], ENT_QUOTES, "UTF-8") ?>
        </strong>
    </p>
</body>
</html>
