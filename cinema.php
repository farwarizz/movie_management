<?php
require_once "db_connection.php";
<?php include 'header.php'; ?>

session_start();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Cinemas and Movies</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f8f8f8; padding: 20px; }
        .cinema-card {
            background: #fff; border: 1px solid #ddd;
            border-radius: 10px; padding: 20px;
            margin-bottom: 20px;
        }
        .cinema-card h2 { margin-top: 0; }
        .movie-list { margin-top: 10px; }
        .movie-list ul { list-style: disc; padding-left: 20px; }
        .admin-controls { margin-top: 10px; }
        .admin-controls a {
            margin-right: 10px;
            text-decoration: none;
            padding: 6px 12px;
            background: #007BFF; color: white; border-radius: 4px;
        }
        .admin-controls a:hover { background: #0056b3; }
    </style>
</head>
<body>

<h1>Cinemas and Available Movies</h1>

<?php
// Get all cinemas with their type and price
$cinemaQuery = "
    SELECT c.cinema_id, c.name, c.location, ct.type_name, ct.price
    FROM cinema c
    JOIN cinema_type ct ON c.type_id = ct.type_id
";
$cinemaResult = $conn->query($cinemaQuery);

while ($cinema = $cinemaResult->fetch_assoc()) {
    echo "<div class='cinema-card'>";
    echo "<h2>" . htmlspecialchars($cinema['name']) . "</h2>";
    echo "<p><strong>Location:</strong> " . htmlspecialchars($cinema['location']) . "</p>";
    echo "<p><strong>Type:</strong> " . $cinema['type_name'] . "</p>";
    echo "<p><strong>Price:</strong> $" . number_format($cinema['price'], 2) . "</p>";

    // Get movies available in this cinema via booking table
    $movieStmt = $conn->prepare("
        SELECT DISTINCT m.movie_id, m.title
        FROM booking b
        JOIN movies m ON b.movie_id = m.movie_id
        WHERE b.cinema_id = ?
    ");
    $movieStmt->bind_param("i", $cinema['cinema_id']);
    $movieStmt->execute();
    $movieResult = $movieStmt->get_result();

    echo "<div class='movie-list'><strong>Movies Available:</strong>";
    if ($movieResult->num_rows > 0) {
        echo "<ul>";
        while ($movie = $movieResult->fetch_assoc()) {
            // Link to booking.php with movie and cinema ID
            echo "<li><a href='booking.php?movie_id=" . $movie['movie_id'] . "&cinema_id=" . $cinema['cinema_id'] . "'>" . htmlspecialchars($movie['title']) . "</a></li>";
        }
        echo "</ul>";
    } else {
        echo " <em>No movies currently playing.</em>";
    }
    echo "</div>";

    // Admin options to manage cinema
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        echo "<div class='admin-controls'>";
        echo "<a href='edit_cinema.php?cinema_id=" . $cinema['cinema_id'] . "'>Edit Cinema</a>";
        echo "<a href='delete_cinema.php?cinema_id=" . $cinema['cinema_id'] . "' onclick=\"return confirm('Are you sure?')\">Delete Cinema</a>";
        echo "</div>";
    }

    echo "</div>";
}

// If admin, show add cinema option
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    echo "<div class='admin-controls'>";
    echo "<a href='add_cinema.php'>+ Add New Cinema</a>";
    echo "</div>";
}
?>

</body>
</html>
