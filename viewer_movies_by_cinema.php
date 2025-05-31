<?php
/**
 * viewer_movies_by_cinema.php
 * This file displays movies currently showing at a selected cinema
 * and allows viewers to initiate the booking process.
 */

session_start();
require_once 'db_connect.php';

// Check if user is logged in and is a viewer
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'viewer') {
    header("Location: login.php");
    exit();
}

$message = '';
$cinema_id = isset($_GET['cinema_id']) ? (int)$_GET['cinema_id'] : 0;
$cinema_name = '';
$movies_at_cinema = [];

if ($cinema_id <= 0) {
    $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Invalid cinema ID provided.</div>";
} else {
    // Fetch cinema details
    $stmt_cinema = $conn->prepare("SELECT name FROM cinema WHERE cinema_id = ?");
    if ($stmt_cinema) {
        $stmt_cinema->bind_param("i", $cinema_id);
        $stmt_cinema->execute();
        $result_cinema = $stmt_cinema->get_result();
        if ($row_cinema = $result_cinema->fetch_assoc()) {
            $cinema_name = htmlspecialchars($row_cinema['name']);
        } else {
            $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Cinema not found.</div>";
            $cinema_id = 0; // Invalidate ID if cinema not found
        }
        $stmt_cinema->close();
    } else {
        $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Database error fetching cinema: " . $conn->error . "</div>";
    }

    if ($cinema_id > 0) {
        // Fetch movies showing at this cinema (assuming a 'cinema_movies' or 'showtimes' table if multiple showtimes per day)
        // For simplicity, let's assume all movies are generally available at all cinemas for now,
        // and we'll refine with actual showtimes later if a 'showtimes' table is introduced.
        // If you have a 'showtimes' table, you would join 'movies' with 'showtimes' on 'movie_id' and filter by 'cinema_id'.
        // For now, let's just fetch all movies from the 'movies' table and assume they can be booked for this cinema.
        // **IMPORTANT:** You might need to adjust this query based on how you link movies to cinemas for showtimes.
        // A proper system would have a `showtimes` table: `showtime_id, movie_id, cinema_id, show_datetime`.

        // Current approach: list all movies. If you have a showtimes table, modify this query.
        // For example: SELECT m.movie_id, m.title, m.genre, m.release_date FROM movies m JOIN showtimes s ON m.movie_id = s.movie_id WHERE s.cinema_id = ? GROUP BY m.movie_id;
        $sql_movies = "SELECT movie_id, title, genre, release_date, language, duration, rating FROM movies ORDER BY title";
        $result_movies = $conn->query($sql_movies);

        if ($result_movies) {
            while ($row = $result_movies->fetch_assoc()) {
                $movies_at_cinema[] = $row;
            }
        } else {
            $message .= "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Error fetching movies: " . $conn->error . "</div>";
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
    <title>Movies at <?php echo $cinema_name ?: "Selected Cinema"; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f2f5;
        }
        .container {
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 24px;
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
            <h1 class="text-white text-2xl font-bold">Movies at <?php echo $cinema_name ?: "Selected Cinema"; ?></h1>
            <div class="flex items-center space-x-4">
                <a href="viewer.php" class="bg-white text-blue-700 px-4 py-2 rounded-md font-semibold hover:bg-blue-100">Back to Dashboard</a>
                <a href="logout.php" class="bg-white text-blue-700 px-4 py-2 rounded-md font-semibold hover:bg-blue-100">Logout</a>
            </div>
        </div>
    </nav>

    <main class="flex-grow container mx-auto p-6">
        <?php echo $message; // Display messages ?>

        <div class="container">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Movies Showing at <?php echo $cinema_name; ?></h2>

            <?php if ($cinema_id > 0 && !empty($movies_at_cinema)): ?>
                <div class="overflow-x-auto">
                    <table>
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Genre</th>
                                <th>Release Date</th>
                                <th>Duration (min)</th>
                                <th>Rating</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($movies_at_cinema as $movie): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($movie['title']); ?></td>
                                    <td><?php echo htmlspecialchars($movie['genre']); ?></td>
                                    <td><?php echo htmlspecialchars($movie['release_date']); ?></td>
                                    <td><?php echo htmlspecialchars($movie['duration']); ?></td>
                                    <td><?php echo htmlspecialchars($movie['rating']); ?></td>
                                    <td>
                                        <a href="book_movie.php?movie_id=<?php echo htmlspecialchars($movie['movie_id']); ?>&cinema_id=<?php echo htmlspecialchars($cinema_id); ?>" class="btn btn-blue">Book Now</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php elseif ($cinema_id > 0): ?>
                <p class="text-gray-600">No movies found for <?php echo $cinema_name; ?>.</p>
            <?php endif; ?>
        </div>
    </main>

    <footer class="bg-gray-800 text-white text-center p-4 mt-auto">
        <div class="container mx-auto">
            <p>&copy; <?php echo date('Y'); ?> MovieVerse. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>