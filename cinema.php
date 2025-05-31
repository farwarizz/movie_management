<?php
/**
 * cinema.php
 * This file displays a list of all cinemas available in the database.
 * For each cinema, it shows its name, location, type, price, and the movies currently playing there.
 * When a user clicks on a movie, it redirects them to booking.php for ticket booking.
 */

// Start a PHP session (needed for user context in the navigation bar)
session_start();

// Include the database connection file
require_once 'db_connect.php';

$cinemas_data = [];

// Fetch all cinemas along with their type details and the movies currently playing in them.
// We use LEFT JOIN to ensure all cinemas are listed, even if they don't currently have movies booked.
$sql = "SELECT c.cinema_id, c.name AS cinema_name, c.location,
               ct.type_name, ct.price,
               GROUP_CONCAT(DISTINCT CONCAT(m.movie_id, ':', m.title) ORDER BY m.title ASC SEPARATOR '; ') AS movies_playing_with_id
        FROM cinema c
        JOIN cinema_type ct ON c.type_id = ct.type_id
        LEFT JOIN booking b ON c.cinema_id = b.cinema_id
        LEFT JOIN movies m ON b.movie_id = m.movie_id
        GROUP BY c.cinema_id, c.name, c.location, ct.type_name, ct.price
        ORDER BY c.name ASC";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $cinemas_data[] = $row;
    }
}

// Close the database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cinemas - Movies Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f2f5;
        }
        .movie-link {
            @apply text-indigo-600 hover:text-indigo-800 hover:underline cursor-pointer;
        }
    </style>
</head>
<body class="min-h-screen bg-gray-100">
    <nav class="bg-indigo-600 p-4 shadow-md">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-white text-2xl font-bold">Cinemas</h1>
            <div class="flex items-center space-x-4">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <span class="text-white text-lg">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</span>
                    <a href="viewer.php" class="bg-white text-indigo-600 px-4 py-2 rounded-md font-semibold hover:bg-indigo-100">My Dashboard</a>
                    <a href="movies.php" class="bg-white text-indigo-600 px-4 py-2 rounded-md font-semibold hover:bg-indigo-100">Browse All Movies</a>
                    <a href="logout.php" class="bg-white text-indigo-600 px-4 py-2 rounded-md font-semibold hover:bg-indigo-100">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="bg-white text-indigo-600 px-4 py-2 rounded-md font-semibold hover:bg-indigo-100">Login</a>
                    <a href="registration.php" class="bg-white text-indigo-600 px-4 py-2 rounded-md font-semibold hover:bg-indigo-100">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="container mx-auto p-6">
        <h2 class="text-3xl font-bold text-gray-800 mb-6">All Cinemas</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php if (!empty($cinemas_data)): ?>
                <?php foreach ($cinemas_data as $cinema): ?>
                    <div class="bg-white rounded-lg shadow-md overflow-hidden p-6">
                        <h3 class="text-xl font-semibold text-gray-800 mb-2"><?php echo htmlspecialchars($cinema['cinema_name']); ?></h3>
                        <p class="text-gray-600 text-sm mb-1">Location: <span class="font-medium"><?php echo htmlspecialchars($cinema['location']); ?></span></p>
                        <p class="text-gray-600 text-sm mb-1">Type: <span class="font-medium"><?php echo htmlspecialchars($cinema['type_name']); ?></span></p>
                        <p class="text-gray-600 text-sm mb-2">Price: <span class="font-bold text-indigo-600">$<?php echo htmlspecialchars(number_format($cinema['price'], 2)); ?></span></p>

                        <div class="mt-4">
                            <h4 class="text-md font-semibold text-gray-700 mb-2">Movies Playing:</h4>
                            <?php if (!empty($cinema['movies_playing_with_id'])): ?>
                                <ul class="list-disc list-inside text-gray-600 text-sm">
                                    <?php
                                    $movies_list = explode('; ', $cinema['movies_playing_with_id']);
                                    foreach ($movies_list as $movie_entry) {
                                        list($movie_id, $movie_title) = explode(':', $movie_entry, 2);
                                        echo '<li><a href="booking.php?cinema_id=' . htmlspecialchars($cinema['cinema_id']) . '&movie_id=' . htmlspecialchars($movie_id) . '" class="movie-link">' . htmlspecialchars($movie_title) . '</a></li>';
                                    }
                                    ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-gray-500 text-sm">No movies currently booked for this cinema.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-gray-700 text-lg col-span-full text-center py-10">No cinemas found in the database.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
