<?php
session_start();
require_once "db_connection.php";
include 'header.php';


$movie_id = $_GET['movie_id'] ?? null;
$user_id = $_SESSION['user_id'] ?? null;

if (!$movie_id || !$user_id) {
    die("Invalid access.");
}

// Get user preference
$prefStmt = $conn->prepare("SELECT preference FROM user WHERE user_id = ?");
$prefStmt->bind_param("i", $user_id);
$prefStmt->execute();
$pref = $prefStmt->get_result()->fetch_assoc()['preference'];

// Get movie info
$movieStmt = $conn->prepare("SELECT * FROM movies WHERE movie_id = ?");
$movieStmt->bind_param("i", $movie_id);
$movieStmt->execute();
$movie = $movieStmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html>
<head>
    <title><?= htmlspecialchars($movie['title']) ?> - Viewing Options</title>
    <style>
        body { font-family: Arial; background-color: #f9f9f9; padding: 20px; }
        h1, h2 { margin-top: 0; }
        table { width: 100%; border-collapse: collapse; background: #fff; margin-bottom: 20px; }
        th, td { padding: 10px; border: 1px solid #ccc; }
        .alert { color: red; margin: 10px 0; }
    </style>
</head>
<body>

<h1><?= htmlspecialchars($movie['title']) ?></h1>
<p><strong>Genre:</strong> <?= htmlspecialchars($movie['genre']) ?> | 
   <strong>Language:</strong> <?= htmlspecialchars($movie['language']) ?> | 
   <strong>Duration:</strong> <?= $movie['duration'] ?> mins | 
   <strong>Rating:</strong> <?= $movie['rating'] ?>/10</p>

<?php if ($pref === 'cinema' || $pref === 'both'): ?>
    <h2>Available in Cinemas</h2>
    <?php
    $cinemaQuery = "
        SELECT c.name, c.location, ct.type_name, ct.price
        FROM booking b
        JOIN cinema c ON b.cinema_id = c.cinema_id
        JOIN cinema_type ct ON c.type_id = ct.type_id
        WHERE b.movie_id = ?
        GROUP BY c.cinema_id
    ";
    $stmt = $conn->prepare($cinemaQuery);
    $stmt->bind_param("i", $movie_id);
    $stmt->execute();
    $cinemas = $stmt->get_result();

    if ($cinemas->num_rows > 0):
    ?>
    <table>
        <tr><th>Cinema</th><th>Location</th><th>Type</th><th>Price</th></tr>
        <?php while ($cin = $cinemas->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($cin['name']) ?></td>
            <td><?= htmlspecialchars($cin['location']) ?></td>
            <td><?= $cin['type_name'] ?></td>
            <td>$<?= $cin['price'] ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
    <?php else: ?>
        <p class="alert">Not available in any cinema.</p>
    <?php endif; ?>
<?php endif; ?>

<?php if ($pref === 'streaming' || $pref === 'both'): ?>
    <h2>Available on Streaming Platforms</h2>
    <?php
    $streamQuery = "
        SELECT sp.platform_name, sp.website, ss.price_720p, ss.price_1080p, ss.price_4k
        FROM streaming_services ss
        JOIN streaming_platform sp ON ss.platform_id = sp.platform_id
        WHERE ss.movie_id = ?
    ";
    $stmt = $conn->prepare($streamQuery);
    $stmt->bind_param("i", $movie_id);
    $stmt->execute();
    $streams = $stmt->get_result();

    if ($streams->num_rows > 0):
    ?>
    <table>
        <tr><th>Platform</th><th>Website</th><th>720p</th><th>1080p</th><th>4K</th></tr>
        <?php while ($row = $streams->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($row['platform_name']) ?></td>
            <td><a href="<?= htmlspecialchars($row['website']) ?>" target="_blank">Visit</a></td>
            <td>$<?= $row['price_720p'] ?></td>
            <td>$<?= $row['price_1080p'] ?></td>
            <td>$<?= $row['price_4k'] ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
    <?php else: ?>
        <p class="alert">Not available on any streaming platform.</p>
    <?php endif; ?>
<?php endif; ?>

</body>
</html>
