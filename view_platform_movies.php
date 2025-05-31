<?php
session_start();
include 'db_connect.php'; // Include your database connection file

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get platform_id from URL
$platform_id = isset($_GET['platform_id']) ? intval($_GET['platform_id']) : 0;

if ($platform_id <= 0) {
    echo "<div class='container mt-4 alert alert-warning'>Invalid platform selected.</div>";
    echo "<p class='container'><a href='viewer.php' class='btn btn-secondary'>Back to Dashboard</a></p>";
    exit();
}

// Fetch platform name
$platform_name = '';
$stmt_platform = $conn->prepare("SELECT platform_name FROM streaming_platform WHERE platform_id = ?");
$stmt_platform->bind_param("i", $platform_id);
$stmt_platform->execute();
$stmt_platform->bind_result($platform_name);
$stmt_platform->fetch();
$stmt_platform->close();

if (empty($platform_name)) {
    echo "<div class='container mt-4 alert alert-danger'>Platform not found.</div>";
    echo "<p class='container'><a href='viewer.php' class='btn btn-secondary'>Back to Dashboard</a></p>";
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movies on <?php echo htmlspecialchars($platform_name); ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .navbar {
            background-color: #343a40 !important;
        }
        .navbar-brand, .nav-link {
            color: #ffffff !important;
        }
        .container {
            margin-top: 20px;
        }
        .section-header {
            background-color: #e9ecef;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .card {
            margin-bottom: 20px;
        }
        .table-responsive {
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <a class="navbar-brand" href="viewer.php">MovieVerse Viewer</a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ml-auto">
                <li class="nav-item">
                    <a class="nav-link" href="viewer.php">Back to Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="logout.php">Logout</a>
                </li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <h2 class="my-4">Movies Available on <?php echo htmlspecialchars($platform_name); ?></h2>

        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Movie Title</th>
                        <th>Genre</th>
                        <th>Release Date</th>
                        <th>Language</th>
                        <th>Duration (min)</th>
                        <th>Rating</th>
                        <th>Price (720p)</th>
                        <th>Price (1080p)</th>
                        <th>Price (4K)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql_movies_on_platform = "SELECT m.title, m.genre, m.release_date, m.language, m.duration, m.rating,
                                                    ss.price_720p, ss.price_1080p, ss.price_4k
                                                FROM streaming_services ss
                                                JOIN movies m ON ss.movie_id = m.movie_id
                                                WHERE ss.platform_id = ?";
                    $stmt_movies = $conn->prepare($sql_movies_on_platform);
                    $stmt_movies->bind_param("i", $platform_id);
                    $stmt_movies->execute();
                    $result_movies = $stmt_movies->get_result();

                    if ($result_movies->num_rows > 0) {
                        while ($row_movie = $result_movies->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($row_movie['title']) . "</td>";
                            echo "<td>" . htmlspecialchars($row_movie['genre']) . "</td>";
                            echo "<td>" . htmlspecialchars($row_movie['release_date']) . "</td>";
                            echo "<td>" . htmlspecialchars($row_movie['language']) . "</td>";
                            echo "<td>" . htmlspecialchars($row_movie['duration']) . "</td>";
                            echo "<td>" . htmlspecialchars($row_movie['rating']) . "</td>";
                            echo "<td>PKR " . number_format($row_movie['price_720p'], 2) . "</td>";
                            echo "<td>PKR " . number_format($row_movie['price_1080p'], 2) . "</td>";
                            echo "<td>PKR " . number_format($row_movie['price_4k'], 2) . "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='9'>No movies found on this platform in your database.</td></tr>";
                    }
                    $stmt_movies->close();
                    $conn->close();
                    ?>
                </tbody>
            </table>
        </div>
        <p><a href='viewer.php' class='btn btn-secondary'>Back to Dashboard</a></p>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>