<?php
require_once "db_connection.php";
<?php include 'header.php'; ?>
session_start();

$platform_id = $_GET['platform_id'] ?? null;
$movie_id = $_GET['movie_id'] ?? null;

if (!$platform_id) {
    echo "Invalid platform selected.";
    exit;
}

// Get platform details
$platformStmt = $conn->prepare("SELECT platform_name, website FROM streaming_platform WHERE platform_id = ?");
$platformStmt->bind_param("i", $platform_id);
$platformStmt->execute();
$platformResult = $platformStmt->get_result();
$platform = $platformResult->fetch_assoc();

if (!$platform) {
    echo "Platform not found.";
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Movies on <?= htmlspecialchars($platform['platform_name']) ?></title>
    <style>
        body { font-family: Arial, sans-serif; background: #fafafa; padding: 20px; }
        h1 { margin-bottom: 10px; }
        table {
            border-collapse: collapse; width: 100%; max-width: 900px;
            background: #fff;
            box-shadow: 0 0 6px rgba(0,0,0,0.1);
        }
        th, td {
            border: 1px solid #ddd; padding: 10px; text-align: left;
        }
        th { background: #007BFF; color: white; }
        a { color: #007BFF; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .not-available {
            color: red; font-weight: bold; margin-top: 20px;
        }
        .alternative-list {
            margin-top: 20px;
        }
        .alternative-list h3 {
            margin-bottom: 5px;
        }
    </style>
</head>
<body>

<h1>Movies Available on <?= htmlspecialchars($platform['platform_name']) ?></h1>
<p>Official website: <a href="<?= htmlspecialchars($platform['website']) ?>" target="_blank"><?= htmlspecialchars($platform['website']) ?></a></p>

<?php

if ($movie_id) {
    // Check if movie is available on this platform
    $checkStmt = $conn->prepare("
        SELECT ss.price_720p, ss.price_1080p, ss.price_4k, m.title
        FROM streaming_services ss
        JOIN movies m ON ss.movie_id = m.movie_id
        WHERE ss.platform_id = ? AND ss.movie_id = ?
    ");
    $checkStmt->bind_param("ii", $platform_id, $movie_id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows > 0) {
        $movieData = $checkResult->fetch_assoc();
        echo "<h2>Movie: " . htmlspecialchars($movieData['title']) . "</h2>";
        echo "<table>";
        echo "<tr><th>Resolution</th><th>Price</th></tr>";
        echo "<tr><td>720p</td><td>$" . number_format($movieData['price_720p'], 2) . "</td></tr>";
        echo "<tr><td>1080p</td><td>$" . number_format($movieData['price_1080p'], 2) . "</td></tr>";
        echo "<tr><td>4K</td><td>$" . number_format($movieData['price_4k'], 2) . "</td></tr>";
        echo "</table>";
        // Here you can add further purchase/booking options
    } else {
        echo "<p class='not-available'>Sorry, this movie is <strong>not available</strong> on " . htmlspecialchars($platform['platform_name']) . ".</p>";

        // Show alternative streaming platforms for this movie
        $altStreamingStmt = $conn->prepare("
            SELECT sp.platform_id, sp.platform_name, sp.website
            FROM streaming_services ss
            JOIN streaming_platform sp ON ss.platform_id = sp.platform_id
            WHERE ss.movie_id = ?
        ");
        $altStreamingStmt->bind_param("i", $movie_id);
        $altStreamingStmt->execute();
        $altStreamingResult = $altStreamingStmt->get_result();

        if ($altStreamingResult->num_rows > 0) {
            echo "<div class='alternative-list'><h3>Available on these streaming platforms:</h3><ul>";
            while ($alt = $altStreamingResult->fetch_assoc()) {
                echo "<li><a href='streaming_services.php?platform_id=" . $alt['platform_id'] . "&movie_id=" . $movie_id . "'>" 
                    . htmlspecialchars($alt['platform_name']) . "</a> - <a href='" . htmlspecialchars($alt['website']) . "' target='_blank'>Official Site</a></li>";
            }
            echo "</ul></div>";
        } else {
            echo "<p>No alternative streaming platforms found.</p>";
        }

        // Show cinemas where movie is available
        $altCinemaStmt = $conn->prepare("
            SELECT c.cinema_id, c.name, c.location, ct.type_name, ct.price
            FROM booking b
            JOIN cinema c ON b.cinema_id = c.cinema_id
            JOIN cinema_type ct ON c.type_id = ct.type_id
            WHERE b.movie_id = ?
            GROUP BY c.cinema_id
        ");
        $altCinemaStmt->bind_param("i", $movie_id);
        $altCinemaStmt->execute();
        $altCinemaResult = $altCinemaStmt->get_result();

        if ($altCinemaResult->num_rows > 0) {
            echo "<div class='alternative-list'><h3>Also available in these cinemas:</h3><ul>";
            while ($cinema = $altCinemaResult->fetch_assoc()) {
                echo "<li>" . htmlspecialchars($cinema['name']) . " (" . htmlspecialchars($cinema['location']) . ") - " 
                    . htmlspecialchars($cinema['type_name']) . " - Price: $" . number_format($cinema['price'], 2) 
                    . " - <a href='booking.php?movie_id=" . $movie_id . "&cinema_id=" . $cinema['cinema_id'] . "'>Book Now</a></li>";
            }
            echo "</ul></div>";
        } else {
            echo "<p>No cinemas found showing this movie currently.</p>";
        }
    }

} else {
    // No specific movie selected - list all movies on this platform

    $stmt = $conn->prepare("
        SELECT m.movie_id, m.title, ss.price_720p, ss.price_1080p, ss.price_4k
        FROM streaming_services ss
        JOIN movies m ON ss.movie_id = m.movie_id
        WHERE ss.platform_id = ?
    ");
    $stmt->bind_param("i", $platform_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo "<table>";
        echo "<tr><th>Movie Title</th><th>Price (720p)</th><th>Price (1080p)</th><th>Price (4K)</th><th>Actions</th></tr>";

        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['title']) . "</td>";
            echo "<td>$" . number_format($row['price_720p'], 2) . "</td>";
            echo "<td>$" . number_format($row['price_1080p'], 2) . "</td>";
            echo "<td>$" . number_format($row['price_4k'], 2) . "</td>";
            echo "<td><a href='streaming_services.php?platform_id=" . $platform_id . "&movie_id=" . $row['movie_id'] . "'>View Details</a></td>";
            echo "</tr>";
        }

        echo "</table>";
    } else {
        echo "<p class='not-available'>No movies are currently available on this platform.</p>";
    }
}

?>

</body>
</html>
