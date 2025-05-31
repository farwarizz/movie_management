<?php
require_once "db_connection.php";
<?php include 'header.php'; ?>

session_start();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Streaming Platforms</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f0f0f0; padding: 20px; }
        .platform-card {
            background: #fff; padding: 15px 20px; margin-bottom: 15px; border-radius: 8px;
            box-shadow: 0 0 6px rgba(0,0,0,0.1);
        }
        a { text-decoration: none; color: #007BFF; font-weight: bold; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>

<h1>Streaming Platforms</h1>

<?php
$result = $conn->query("SELECT platform_id, platform_name, website FROM streaming_platform ORDER BY platform_name");

if ($result->num_rows > 0) {
    while ($platform = $result->fetch_assoc()) {
        echo "<div class='platform-card'>";
        echo "<a href='streaming_services.php?platform_id=" . $platform['platform_id'] . "'>" . htmlspecialchars($platform['platform_name']) . "</a><br>";
        echo "<small>Website: <a href='" . htmlspecialchars($platform['website']) . "' target='_blank'>" . htmlspecialchars($platform['website']) . "</a></small>";
        echo "</div>";
    }
} else {
    echo "<p>No streaming platforms found.</p>";
}
?>

</body>
</html>
