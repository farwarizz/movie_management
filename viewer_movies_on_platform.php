<?php
/**
 * viewer_movies_on_platform.php
 * This file displays movies available on a specific streaming platform,
 * fetching data from the 'streaming_services' table.
 */

session_start();
require_once 'db_connect.php';

// Check if user is logged in and is a viewer
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'viewer') {
    header("Location: login.php");
    exit();
}

$message = '';
$platform_id = isset($_GET['platform_id']) ? (int)$_GET['platform_id'] : 0;
$platform_name = 'Selected Platform';
$movies_on_platform = [];

if ($platform_id <= 0) {
    $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Invalid platform ID provided.</div>";
} else {
    // Fetch platform name
    $stmt_platform = $conn->prepare("SELECT platform_name FROM streaming_platform WHERE platform_id = ?");
    if ($stmt_platform) {
        $stmt_platform->bind_param("i", $platform_id);
        $stmt_platform->execute();
        $result_platform = $stmt_platform->get_result();
        if ($row_platform = $result_platform->fetch_assoc()) {
            $platform_name = htmlspecialchars($row_platform['platform_name']);
        } else {
            $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Platform not found.</div>";
            $platform_id = 0; // Invalidate ID if not found
        }
        $stmt_platform->close();
    } else {
        $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Database error fetching platform details: " . $conn->error . "</div>";
    }

    if ($platform_id > 0) {
        // Fetch movies available on this platform from the 'streaming_services' table
        // This query assumes 'streaming_services' table links movies to platforms with prices.
        $sql_movies = "SELECT m.title, m.genre, m.release_date, m.language, m.duration, m.rating,
                               ss.price_720p, ss.price_1080p, ss.price_4k
                        FROM streaming_services ss
                        JOIN movies m ON ss.movie_id = m.movie_id
                        WHERE ss.platform_id = ?
                        ORDER BY m.title ASC";
        $stmt_movies = $conn->prepare($sql_movies);
        if ($stmt_movies) {
            $stmt_movies->bind_param("i", $platform_id);
            $stmt_movies->execute();
            $result_movies = $stmt_movies->get_result();
            while ($row = $result_movies->fetch_assoc()) {
                $movies_on_platform[] = $row;
            }
            $stmt_movies->close();
        } else {
            $message .= "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Error preparing movies on platform query: " . $conn->error . "</div>";
        }
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movies on <?php echo $platform_name; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f2f5;
        }
        .container-section {
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 24px;
            margin-bottom: 24px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        th {
            background-color: #f8fafc;
            font-weight: 600;
            color: #4a5568;
            text-transform: uppercase;
            font-size: 0.75rem;
        }
        tr:hover {
            background-color: #f0f2f5;
        }
        .btn {
            padding: 8px 16px;
            border-radius: 5px;
            font-weight: 500;
            text-decoration: none;
            transition: background-color 0.2s ease-in-out;
            display: inline-block;
        }
        .btn-blue {
            background-color: #3b82f6; /* blue-500 */
            color: white;
        }
        .btn-blue:hover {
            background-color: #2563eb; /* blue-600 */
        }
    </style>
</head>
<body class="min-h-screen bg-gray-100 flex flex-col">
    <nav class="bg-blue-700 p-4 shadow-md">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-white text-2xl font-bold">Movies on <?php echo $platform_name; ?></h1>
            <div class="flex items-center space-x-4">
                <a href="viewer.php" class="bg-white text-blue-700 px-4 py-2 rounded-md font-semibold hover:bg-blue-100">Back to Dashboard</a>
                <a href="logout.php" class="bg-white text-blue-700 px-4 py-2 rounded-md font-semibold hover:bg-blue-100">Logout</a>
            </div>
        </div>
    </nav>

    <main class="flex-grow container mx-auto p-6">
        <?php echo $message; // Display messages ?>

        <div class="container-section">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Movies available on <?php echo $platform_name; ?></h2>

            <?php if ($platform_id > 0 && !empty($movies_on_platform)): ?>
                <div class="overflow-x-auto">
                    <table>
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Genre</th>
                                <th>Release Date</th>
                                <th>Language</th>
                                <th>Duration (min)</th>
                                <th>Rating</th>
                                <th>720p Price</th>
                                <th>1080p Price</th>
                                <th>4K Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($movies_on_platform as $movie): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($movie['title']); ?></td>
                                    <td><?php echo htmlspecialchars($movie['genre']); ?></td>
                                    <td><?php echo htmlspecialchars($movie['release_date']); ?></td>
                                    <td><?php echo htmlspecialchars($movie['language']); ?></td>
                                    <td><?php echo htmlspecialchars($movie['duration']); ?></td>
                                    <td><?php echo htmlspecialchars($movie['rating']); ?></td>
                                    <td>PKR <?php echo number_format(htmlspecialchars($movie['price_720p']), 2); ?></td>
                                    <td>PKR <?php echo number_format(htmlspecialchars($movie['price_1080p']), 2); ?></td>
                                    <td>PKR <?php echo number_format(htmlspecialchars($movie['price_4k']), 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php elseif ($platform_id > 0): ?>
                <p class="text-gray-600">No movies found for <?php echo $platform_name; ?> in your streaming services.</p>
            <?php else: ?>
                <p class="text-gray-600">Please select a valid streaming platform.</p>
            <?php endif; ?>

            <div class="mt-6">
                <a href="viewer.php" class="btn btn-blue">Back to Dashboard</a>
            </div>
        </div>
    </main>

    <footer class="bg-gray-800 text-white text-center p-4 mt-auto">
        <div class="container mx-auto">
            <p>&copy; <?php echo date('Y'); ?> MovieVerse. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>
