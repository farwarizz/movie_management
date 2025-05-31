<?php
/**
 * recommendations.php
 * This file displays a logged-in user's past movie activity and provides movie recommendations
 * based on their viewing history and preferences.
 * It interacts with the 'recommendations', 'user', and 'movies' tables.
 */

// Start a PHP session. This must be the very first thing in your PHP file before any HTML output.
session_start();

// Include the database connection file
require_once 'db_connect.php';

// Check if the user is logged in. If not, redirect to the login page.
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$message = ''; // Variable to store feedback messages for the user

// --- Fetch User's Past Activities ---
$user_activities = [];
$stmt_activities = $conn->prepare("SELECT r.recommendation_id, m.title AS movie_title, m.genre, r.reason, r.recommendation_id AS activity_timestamp
                                    FROM recommendations r
                                    JOIN movies m ON r.movie_id = m.movie_id
                                    WHERE r.user_id = ?
                                    ORDER BY r.recommendation_id DESC LIMIT 10"); // Show last 10 activities
if ($stmt_activities) {
    $stmt_activities->bind_param("i", $user_id);
    $stmt_activities->execute();
    $result_activities = $stmt_activities->get_result();
    while ($row = $result_activities->fetch_assoc()) {
        $user_activities[] = $row;
    }
    $stmt_activities->close();
} else {
    $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Database error fetching activities: " . $conn->error . "</div>";
}

// --- Generate Movie Recommendations based on Activity ---
$recommended_movies = [];
$viewed_genres = [];
$viewed_movie_ids = [];

// First, get genres and movie IDs from user's past activities
$stmt_user_genres = $conn->prepare("SELECT DISTINCT m.genre, m.movie_id
                                    FROM recommendations r
                                    JOIN movies m ON r.movie_id = m.movie_id
                                    WHERE r.user_id = ?");
if ($stmt_user_genres) {
    $stmt_user_genres->bind_param("i", $user_id);
    $stmt_user_genres->execute();
    $result_user_genres = $stmt_user_genres->get_result();
    while ($row_genre = $result_user_genres->fetch_assoc()) {
        $viewed_genres[] = $row_genre['genre'];
        $viewed_movie_ids[] = $row_genre['movie_id'];
    }
    $stmt_user_genres->close();
}

if (!empty($viewed_genres)) {
    // Remove duplicate genres
    $viewed_genres = array_unique($viewed_genres);

    // Create placeholders for the IN clause
    $genre_placeholders = implode(',', array_fill(0, count($viewed_genres), '?'));
    $movie_id_placeholders = implode(',', array_fill(0, count($viewed_movie_ids), '?'));

    // Fetch movies from similar genres that the user has NOT already interacted with
    $sql_recommendations = "SELECT movie_id, title, genre, rating
                            FROM movies
                            WHERE genre IN ({$genre_placeholders})";

    // Add condition to exclude already viewed/recommended movies if there are any
    if (!empty($viewed_movie_ids)) {
        $sql_recommendations .= " AND movie_id NOT IN ({$movie_id_placeholders})";
    }

    $sql_recommendations .= " ORDER BY rating DESC LIMIT 10"; // Order by rating for better recommendations

    $stmt_recommendations = $conn->prepare($sql_recommendations);
    if ($stmt_recommendations) {
        // Dynamically bind parameters
        $types = str_repeat('s', count($viewed_genres));
        $params = $viewed_genres;

        if (!empty($viewed_movie_ids)) {
            $types .= str_repeat('i', count($viewed_movie_ids));
            $params = array_merge($params, $viewed_movie_ids);
        }

        // Use call_user_func_array to bind parameters dynamically
        $bind_names = array_merge([$types], $params);
        call_user_func_array([$stmt_recommendations, 'bind_param'], refValues($bind_names));

        $stmt_recommendations->execute();
        $result_recommendations = $stmt_recommendations->get_result();
        while ($row_reco = $result_recommendations->fetch_assoc()) {
            $recommended_movies[] = $row_reco;
        }
        $stmt_recommendations->close();
    }
} else {
    // If no past activity, recommend some top-rated movies
    $stmt_random = $conn->prepare("SELECT movie_id, title, genre, rating FROM movies ORDER BY rating DESC LIMIT 10");
    if ($stmt_random) {
        $stmt_random->execute();
        $result_random = $stmt_random->get_result();
        while ($row_random = $result_random->fetch_assoc()) {
            $recommended_movies[] = $row_random;
        }
        $stmt_random->close();
    }
}

// Helper function for bind_param with dynamic arguments
function refValues($arr){
    if (strnatcmp(phpversion(),'5.3') >= 0) // PHP 5.3+
    {
        $refs = array();
        foreach($arr as $key => $value)
            $refs[$key] = &$arr[$key];
        return $refs;
    }
    return $arr;
}


// Close the database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recommendations - Movies Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f2f5;
        }
        table {
            width: 100%;
            border-collapse: collapse;
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
    </style>
</head>
<body class="min-h-screen bg-gray-100">
    <nav class="bg-indigo-600 p-4 shadow-md">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-white text-2xl font-bold">Recommendations</h1>
            <div class="flex items-center space-x-4">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <span class="text-white text-lg">Welcome, <?php echo htmlspecialchars($user_name); ?>!</span>
                    <a href="viewer.php" class="bg-white text-indigo-600 px-4 py-2 rounded-md font-semibold hover:bg-indigo-100">My Dashboard</a>
                    <a href="movies.php" class="bg-white text-indigo-600 px-4 py-2 rounded-md font-semibold hover:bg-indigo-100">Browse All Movies</a>
                    <a href="cinema.php" class="bg-white text-indigo-600 px-4 py-2 rounded-md font-semibold hover:bg-indigo-100">Cinemas</a>
                    <a href="streaming_platform.php" class="bg-white text-indigo-600 px-4 py-2 rounded-md font-semibold hover:bg-indigo-100">Streaming Platforms</a>
                    <a href="subscription.php" class="bg-white text-indigo-600 px-4 py-2 rounded-md font-semibold hover:bg-indigo-100">My Subscriptions</a>
                    <a href="payment.php" class="bg-white text-indigo-600 px-4 py-2 rounded-md font-semibold hover:bg-indigo-100">My Payments</a>
                    <a href="logout.php" class="bg-white text-indigo-600 px-4 py-2 rounded-md font-semibold hover:bg-indigo-100">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="bg-white text-indigo-600 px-4 py-2 rounded-md font-semibold hover:bg-indigo-100">Login</a>
                    <a href="registration.php" class="bg-white text-indigo-600 px-4 py-2 rounded-md font-semibold hover:bg-indigo-100">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="container mx-auto p-6">
        <?php echo $message; // Display general messages here ?>

        <h2 class="text-3xl font-bold text-gray-800 mb-6">My Recent Activity</h2>

        <?php if (!empty($user_activities)): ?>
            <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
                <div class="overflow-x-auto">
                    <table>
                        <thead>
                            <tr>
                                <th>Activity ID</th>
                                <th>Movie Title</th>
                                <th>Genre</th>
                                <th>Action</th>
                                <th>Timestamp</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($user_activities as $activity): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($activity['recommendation_id']); ?></td>
                                    <td><?php echo htmlspecialchars($activity['movie_title']); ?></td>
                                    <td><?php echo htmlspecialchars($activity['genre']); ?></td>
                                    <td><?php echo htmlspecialchars($activity['reason']); ?></td>
                                    <td><?php echo htmlspecialchars($activity['activity_timestamp']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <p class="text-gray-700 text-lg text-center py-10">No recent activity to display. Start watching movies to get recommendations!</p>
        <?php endif; ?>

        <h2 class="text-3xl font-bold text-gray-800 mb-6 mt-8">Recommended Movies for You</h2>

        <?php if (!empty($recommended_movies)): ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                <?php foreach ($recommended_movies as $movie): ?>
                    <div class="bg-white rounded-lg shadow-md overflow-hidden p-4">
                        <h3 class="text-xl font-semibold text-gray-800 mb-2 truncate"><?php echo htmlspecialchars($movie['title']); ?></h3>
                        <p class="text-gray-600 text-sm mb-1">Genre: <?php echo htmlspecialchars($movie['genre']); ?></p>
                        <p class="text-gray-600 text-sm mb-2">Rating: <span class="font-bold text-indigo-600"><?php echo htmlspecialchars($movie['rating']); ?></span></p>
                        <a href="viewer.php?movie_id=<?php echo htmlspecialchars($movie['movie_id']); ?>"
                           class="inline-block bg-indigo-500 text-white px-4 py-2 rounded-md text-sm font-semibold hover:bg-indigo-600 mt-3">
                            View Details
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-gray-700 text-lg text-center py-10">No specific recommendations at this time. Explore more movies to help us understand your taste!</p>
        <?php endif; ?>

    </div>
</body>
</html>
