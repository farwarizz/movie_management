<?php
/**
 * admin.php
 * This file serves as the administration dashboard for 'admin' users in the Movies Management System.
 * It provides functionalities to view, add, edit, and delete:
 * - Movies
 * - Cinemas
 * - Users (Viewers and other Admins)
 * - Streaming Platforms
 * - Streaming Services
 * It also allows monitoring of viewer activity.
 */

// Start a PHP session. This must be the very first thing in your PHP file before any HTML output.
session_start();

// Include the database connection file
require_once 'db_connect.php';

// Check if the user is logged in AND is an admin. If not, redirect to the login page.
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$admin_id = $_SESSION['user_id'];
$admin_name = $_SESSION['user_name'];
$message = ''; // General message variable for admin feedback

// Determine the current section to display (default to 'movies')
$current_section = isset($_GET['section']) ? $_GET['section'] : 'movies';

// --- Handle Form Submissions for C.R.U.D. Operations ---

// Function to sanitize input
function sanitize_input($conn, $data) {
    return $conn->real_escape_string(htmlspecialchars(strip_tags($data)));
}

// --- MOVIES MANAGEMENT ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && $current_section == 'movies') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action == 'add_movie') {
            $title = sanitize_input($conn, $_POST['title']);
            $genre = sanitize_input($conn, $_POST['genre']);
            $release_date = sanitize_input($conn, $_POST['release_date']);
            $language = sanitize_input($conn, $_POST['language']);
            $duration = (int)$_POST['duration'];
            $rating = (float)$_POST['rating'];

            if (empty($title) || empty($genre) || empty($release_date) || empty($language) || empty($duration) || empty($rating)) {
                $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>All movie fields are required.</div>";
            } else {
                $stmt = $conn->prepare("INSERT INTO movies (title, genre, release_date, language, duration, rating) VALUES (?, ?, ?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param("ssssid", $title, $genre, $release_date, $language, $duration, $rating);
                    if ($stmt->execute()) {
                        $message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4' role='alert'>Movie added successfully!</div>";
                    } else {
                        $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Error adding movie: " . $stmt->error . "</div>";
                    }
                    $stmt->close();
                } else {
                    $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Database error preparing statement: " . $conn->error . "</div>";
                }
            }
        } elseif ($action == 'delete_movie') {
            $movie_id = (int)$_POST['movie_id'];
            $stmt = $conn->prepare("DELETE FROM movies WHERE movie_id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $movie_id);
                if ($stmt->execute()) {
                    $message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4' role='alert'>Movie deleted successfully!</div>";
                } else {
                    $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Error deleting movie: " . $stmt->error . "</div>";
                }
                $stmt->close();
            }
        } elseif ($action == 'edit_movie') {
            $movie_id = (int)$_POST['movie_id'];
            $title = sanitize_input($conn, $_POST['title']);
            $genre = sanitize_input($conn, $_POST['genre']);
            $release_date = sanitize_input($conn, $_POST['release_date']);
            $language = sanitize_input($conn, $_POST['language']);
            $duration = (int)$_POST['duration'];
            $rating = (float)$_POST['rating'];

            if (empty($title) || empty($genre) || empty($release_date) || empty($language) || empty($duration) || empty($rating)) {
                $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>All movie fields are required for update.</div>";
            } else {
                $stmt = $conn->prepare("UPDATE movies SET title=?, genre=?, release_date=?, language=?, duration=?, rating=? WHERE movie_id=?");
                if ($stmt) {
                    $stmt->bind_param("ssssidi", $title, $genre, $release_date, $language, $duration, $rating, $movie_id);
                    if ($stmt->execute()) {
                        $message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4' role='alert'>Movie updated successfully!</div>";
                    } else {
                        $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Error updating movie: " . $stmt->error . "</div>";
                    }
                    $stmt->close();
                } else {
                    $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Database error preparing statement: " . $conn->error . "</div>";
                }
            }
        }
    }
}

// --- USER MANAGEMENT (VIEWERS) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && $current_section == 'users') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action == 'delete_user') {
            $user_id_to_delete = (int)$_POST['user_id'];
            if ($user_id_to_delete === $admin_id) {
                $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>You cannot delete your own admin account.</div>";
            } else {
                $conn->begin_transaction();
                try {
                    // Delete from viewer/admin tables first (due to foreign key constraints, ON DELETE CASCADE should handle this)
                    // However, explicitly deleting from user table is sufficient if ON DELETE CASCADE is set up.
                    $stmt = $conn->prepare("DELETE FROM user WHERE user_id = ?");
                    if ($stmt) {
                        $stmt->bind_param("i", $user_id_to_delete);
                        if ($stmt->execute()) {
                            $conn->commit();
                            $message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4' role='alert'>User deleted successfully!</div>";
                        } else {
                            throw new Exception("Error deleting user: " . $stmt->error);
                        }
                        $stmt->close();
                    } else {
                        throw new Exception("Database error preparing statement: " . $conn->error);
                    }
                } catch (Exception $e) {
                    $conn->rollback();
                    $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Error: " . $e->getMessage() . "</div>";
                }
            }
        }
    }
}

// --- CINEMA MANAGEMENT ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && $current_section == 'cinemas') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action == 'add_cinema') {
            $name = sanitize_input($conn, $_POST['name']);
            $location = sanitize_input($conn, $_POST['location']);
            $type_id = (int)$_POST['type_id']; // cinema_type_id

            if (empty($name) || empty($location) || empty($type_id)) {
                $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>All cinema fields are required.</div>";
            } else {
                $stmt = $conn->prepare("INSERT INTO cinema (name, location, type_id) VALUES (?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param("ssi", $name, $location, $type_id);
                    if ($stmt->execute()) {
                        $message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4' role='alert'>Cinema added successfully!</div>";
                    } else {
                        $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Error adding cinema: " . $stmt->error . "</div>";
                    }
                    $stmt->close();
                } else {
                    $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Database error preparing statement: " . $conn->error . "</div>";
                }
            }
        } elseif ($action == 'delete_cinema') {
            $cinema_id = (int)$_POST['cinema_id'];
            $stmt = $conn->prepare("DELETE FROM cinema WHERE cinema_id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $cinema_id);
                if ($stmt->execute()) {
                    $message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4' role='alert'>Cinema deleted successfully!</div>";
                } else {
                    $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Error deleting cinema: " . $stmt->error . "</div>";
                }
                $stmt->close();
            }
        } // No edit cinema for now, can be added later
    }
}

// --- STREAMING PLATFORM MANAGEMENT ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && $current_section == 'streaming_platforms') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action == 'add_platform') {
            $platform_name = sanitize_input($conn, $_POST['platform_name']);
            $website = sanitize_input($conn, $_POST['website']);

            if (empty($platform_name) || empty($website)) {
                $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Platform name and website are required.</div>";
            } else {
                $stmt = $conn->prepare("INSERT INTO streaming_platform (platform_name, website) VALUES (?, ?)");
                if ($stmt) {
                    $stmt->bind_param("ss", $platform_name, $website);
                    if ($stmt->execute()) {
                        $message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4' role='alert'>Streaming platform added successfully!</div>";
                    } else {
                        $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Error adding platform: " . $stmt->error . "</div>";
                    }
                    $stmt->close();
                } else {
                    $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Database error preparing statement: " . $conn->error . "</div>";
                }
            }
        } elseif ($action == 'delete_platform') {
            $platform_id = (int)$_POST['platform_id'];
            $stmt = $conn->prepare("DELETE FROM streaming_platform WHERE platform_id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $platform_id);
                if ($stmt->execute()) {
                    $message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4' role='alert'>Streaming platform deleted successfully!</div>";
                } else {
                    $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Error deleting platform: " . $stmt->error . "</div>";
                }
                $stmt->close();
            }
        }
    }
}

// --- STREAMING SERVICES MANAGEMENT (Movie-Platform Link) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && $current_section == 'streaming_services') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action == 'add_service') {
            $movie_id = (int)$_POST['movie_id'];
            $platform_id = (int)$_POST['platform_id'];
            $price_720p = (float)$_POST['price_720p'];
            $price_1080p = (float)$_POST['price_1080p'];
            $price_4k = (float)$_POST['price_4k'];

            if (empty($movie_id) || empty($platform_id)) {
                $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Movie and Platform are required for streaming service.</div>";
            } else {
                $stmt = $conn->prepare("INSERT INTO streaming_services (movie_id, platform_id, price_720p, price_1080p, price_4k) VALUES (?, ?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param("iiddd", $movie_id, $platform_id, $price_720p, $price_1080p, $price_4k);
                    if ($stmt->execute()) {
                        $message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4' role='alert'>Streaming service added successfully!</div>";
                    } else {
                        $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Error adding streaming service: " . $stmt->error . "</div>";
                    }
                    $stmt->close();
                } else {
                    $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Database error preparing statement: " . $conn->error . "</div>";
                }
            }
        } elseif ($action == 'delete_service') {
            $streaming_id = (int)$_POST['streaming_id'];
            $stmt = $conn->prepare("DELETE FROM streaming_services WHERE streaming_id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $streaming_id);
                if ($stmt->execute()) {
                    $message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4' role='alert'>Streaming service deleted successfully!</div>";
                } else {
                    $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Error deleting streaming service: " . $stmt->error . "</div>";
                }
                $stmt->close();
            }
        }
    }
}


// --- Fetch Data for Display ---

// Fetch all movies
$movies = [];
$result_movies = $conn->query("SELECT * FROM movies ORDER BY title ASC");
if ($result_movies) {
    while ($row = $result_movies->fetch_assoc()) {
        $movies[] = $row;
    }
}

// Fetch all users (viewers and admins)
$users = [];
$result_users = $conn->query("SELECT u.user_id, u.name, u.email, u.preference,
                                     CASE
                                         WHEN v.user_id IS NOT NULL THEN 'Viewer'
                                         WHEN a.user_id IS NOT NULL THEN 'Admin'
                                         ELSE 'Unknown'
                                     END AS user_type
                              FROM user u
                              LEFT JOIN viewer v ON u.user_id = v.user_id
                              LEFT JOIN admin a ON u.user_id = a.user_id
                              ORDER BY u.name ASC");
if ($result_users) {
    while ($row = $result_users->fetch_assoc()) {
        $users[] = $row;
    }
}

// Fetch all cinemas with their type details
$cinemas = [];
$result_cinemas = $conn->query("SELECT c.cinema_id, c.name, c.location, ct.type_name, ct.price
                                FROM cinema c
                                JOIN cinema_type ct ON c.type_id = ct.type_id
                                ORDER BY c.name ASC");
if ($result_cinemas) {
    while ($row = $result_cinemas->fetch_assoc()) {
        $cinemas[] = $row;
    }
}

// Fetch all cinema types (for dropdowns)
$cinema_types = [];
$result_cinema_types = $conn->query("SELECT * FROM cinema_type ORDER BY type_name ASC");
if ($result_cinema_types) {
    while ($row = $result_cinema_types->fetch_assoc()) {
        $cinema_types[] = $row;
    }
}

// Fetch all streaming platforms
$streaming_platforms = [];
$result_platforms = $conn->query("SELECT * FROM streaming_platform ORDER BY platform_name ASC");
if ($result_platforms) {
    while ($row = $result_platforms->fetch_assoc()) {
        $streaming_platforms[] = $row;
    }
}

// Fetch all streaming services (movie-platform links)
$streaming_services = [];
$result_services = $conn->query("SELECT ss.streaming_id, m.title AS movie_title, sp.platform_name, ss.price_720p, ss.price_1080p, ss.price_4k
                                 FROM streaming_services ss
                                 JOIN movies m ON ss.movie_id = m.movie_id
                                 JOIN streaming_platform sp ON ss.platform_id = sp.platform_id
                                 ORDER BY m.title ASC");
if ($result_services) {
    while ($row = $result_services->fetch_assoc()) {
        $streaming_services[] = $row;
    }
}

// Fetch recent viewer activity (from recommendations table)
$viewer_activities = [];
$result_activities = $conn->query("SELECT r.recommendation_id, u.name AS user_name, m.title AS movie_title, r.reason, r.recommendation_id AS activity_timestamp
                                    FROM recommendations r
                                    JOIN user u ON r.user_id = u.user_id
                                    JOIN movies m ON r.movie_id = m.movie_id
                                    ORDER BY r.recommendation_id DESC LIMIT 20"); // Last 20 activities
if ($result_activities) {
    while ($row = $result_activities->fetch_assoc()) {
        $viewer_activities[] = $row;
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
    <title>Admin Dashboard - Movies Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f2f5;
        }
        .tab-button {
            @apply px-4 py-2 rounded-t-lg font-semibold text-gray-700 hover:bg-gray-200;
        }
        .tab-button.active {
            @apply bg-white text-indigo-600 border-b-2 border-indigo-600;
        }
        .modal {
            display: none; /* Hidden by default */
            position: fixed; /* Stay in place */
            z-index: 1000; /* Sit on top */
            left: 0;
            top: 0;
            width: 100%; /* Full width */
            height: 100%; /* Full height */
            overflow: auto; /* Enable scroll if needed */
            background-color: rgba(0,0,0,0.4); /* Black w/ opacity */
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background-color: #fefefe;
            margin: auto;
            padding: 20px;
            border-radius: 8px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            position: relative;
        }
        .close-button {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            position: absolute;
            top: 10px;
            right: 20px;
        }
        .close-button:hover,
        .close-button:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }
    </style>
</head>
<body class="min-h-screen bg-gray-100">
    <nav class="bg-indigo-600 p-4 shadow-md">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-white text-2xl font-bold">Admin Dashboard</h1>
            <div class="flex items-center space-x-4">
                <span class="text-white text-lg">Welcome, <?php echo htmlspecialchars($admin_name); ?>!</span>
                <a href="logout.php" class="bg-white text-indigo-600 px-4 py-2 rounded-md font-semibold hover:bg-indigo-100">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mx-auto p-6">
        <?php echo $message; // Display general messages here ?>

        <div class="bg-white rounded-lg shadow-md mb-6">
            <div class="flex border-b border-gray-200">
                <a href="?section=movies" class="tab-button <?php echo ($current_section == 'movies') ? 'active' : ''; ?>">Movies</a>
                <a href="?section=cinemas" class="tab-button <?php echo ($current_section == 'cinemas') ? 'active' : ''; ?>">Cinemas</a>
                <a href="?section=users" class="tab-button <?php echo ($current_section == 'users') ? 'active' : ''; ?>">Users</a>
                <a href="?section=streaming_platforms" class="tab-button <?php echo ($current_section == 'streaming_platforms') ? 'active' : ''; ?>">Streaming Platforms</a>
                <a href="?section=streaming_services" class="tab-button <?php echo ($current_section == 'streaming_services') ? 'active' : ''; ?>">Streaming Services</a>
                <a href="?section=activity" class="tab-button <?php echo ($current_section == 'activity') ? 'active' : ''; ?>">Viewer Activity</a>
            </div>

            <div class="p-6">
                <?php if ($current_section == 'movies'): ?>
                    <h2 class="text-2xl font-bold text-gray-800 mb-4">Manage Movies</h2>

                    <div class="bg-gray-50 p-6 rounded-lg shadow-sm mb-6">
                        <h3 class="text-xl font-semibold text-gray-700 mb-3">Add New Movie</h3>
                        <form action="admin.php?section=movies" method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="add_movie">
                            <div>
                                <label for="title" class="block text-sm font-medium text-gray-700">Title:</label>
                                <input type="text" id="title" name="title" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm">
                            </div>
                            <div>
                                <label for="genre" class="block text-sm font-medium text-gray-700">Genre:</label>
                                <input type="text" id="genre" name="genre" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm">
                            </div>
                            <div>
                                <label for="release_date" class="block text-sm font-medium text-gray-700">Release Date:</label>
                                <input type="date" id="release_date" name="release_date" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm">
                            </div>
                            <div>
                                <label for="language" class="block text-sm font-medium text-gray-700">Language:</label>
                                <input type="text" id="language" name="language" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm">
                            </div>
                            <div>
                                <label for="duration" class="block text-sm font-medium text-gray-700">Duration (minutes):</label>
                                <input type="number" id="duration" name="duration" required min="1" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm">
                            </div>
                            <div>
                                <label for="rating" class="block text-sm font-medium text-gray-700">Rating (0.0 - 10.0):</label>
                                <input type="number" id="rating" name="rating" required step="0.1" min="0" max="10" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm">
                            </div>
                            <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md font-semibold hover:bg-indigo-700">Add Movie</button>
                        </form>
                    </div>

                    <h3 class="text-xl font-semibold text-gray-700 mb-3">All Movies</h3>
                    <?php if (!empty($movies)): ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                                <thead>
                                    <tr class="bg-gray-100 text-left text-sm font-medium text-gray-600 uppercase tracking-wider">
                                        <th class="py-3 px-4 border-b">ID</th>
                                        <th class="py-3 px-4 border-b">Title</th>
                                        <th class="py-3 px-4 border-b">Genre</th>
                                        <th class="py-3 px-4 border-b">Release Date</th>
                                        <th class="py-3 px-4 border-b">Language</th>
                                        <th class="py-3 px-4 border-b">Duration</th>
                                        <th class="py-3 px-4 border-b">Rating</th>
                                        <th class="py-3 px-4 border-b">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($movies as $movie): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="py-3 px-4 border-b text-sm text-gray-800"><?php echo htmlspecialchars($movie['movie_id']); ?></td>
                                            <td class="py-3 px-4 border-b text-sm text-gray-800"><?php echo htmlspecialchars($movie['title']); ?></td>
                                            <td class="py-3 px-4 border-b text-sm text-gray-800"><?php echo htmlspecialchars($movie['genre']); ?></td>
                                            <td class="py-3 px-4 border-b text-sm text-gray-800"><?php echo htmlspecialchars($movie['release_date']); ?></td>
                                            <td class="py-3 px-4 border-b text-sm text-gray-800"><?php echo htmlspecialchars($movie['language']); ?></td>
                                            <td class="py-3 px-4 border-b text-sm text-gray-800"><?php echo htmlspecialchars($movie['duration']); ?></td>
                                            <td class="py-3 px-4 border-b text-sm text-gray-800"><?php echo htmlspecialchars($movie['rating']); ?></td>
                                            <td class="py-3 px-4 border-b text-sm">
                                                <button onclick="openEditMovieModal(<?php echo htmlspecialchars(json_encode($movie)); ?>)" class="bg-blue-500 text-white px-3 py-1 rounded-md text-xs hover:bg-blue-600 mr-2">Edit</button>
                                                <form action="admin.php?section=movies" method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to delete this movie?');">
                                                    <input type="hidden" name="action" value="delete_movie">
                                                    <input type="hidden" name="movie_id" value="<?php echo htmlspecialchars($movie['movie_id']); ?>">
                                                    <button type="submit" class="bg-red-500 text-white px-3 py-1 rounded-md text-xs hover:bg-red-600">Delete</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-700">No movies found in the database.</p>
                    <?php endif; ?>

                <?php elseif ($current_section == 'cinemas'): ?>
                    <h2 class="text-2xl font-bold text-gray-800 mb-4">Manage Cinemas</h2>

                    <div class="bg-gray-50 p-6 rounded-lg shadow-sm mb-6">
                        <h3 class="text-xl font-semibold text-gray-700 mb-3">Add New Cinema</h3>
                        <form action="admin.php?section=cinemas" method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="add_cinema">
                            <div>
                                <label for="cinema_name" class="block text-sm font-medium text-gray-700">Cinema Name:</label>
                                <input type="text" id="cinema_name" name="name" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm">
                            </div>
                            <div>
                                <label for="cinema_location" class="block text-sm font-medium text-gray-700">Location:</label>
                                <input type="text" id="cinema_location" name="location" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm">
                            </div>
                            <div>
                                <label for="cinema_type_id" class="block text-sm font-medium text-gray-700">Cinema Type:</label>
                                <select id="cinema_type_id" name="type_id" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm">
                                    <option value="">Select Type</option>
                                    <?php foreach ($cinema_types as $type): ?>
                                        <option value="<?php echo htmlspecialchars($type['type_id']); ?>"><?php echo htmlspecialchars($type['type_name']); ?> ($<?php echo htmlspecialchars($type['price']); ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md font-semibold hover:bg-indigo-700">Add Cinema</button>
                        </form>
                    </div>

                    <h3 class="text-xl font-semibold text-gray-700 mb-3">All Cinemas</h3>
                    <?php if (!empty($cinemas)): ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                                <thead>
                                    <tr class="bg-gray-100 text-left text-sm font-medium text-gray-600 uppercase tracking-wider">
                                        <th class="py-3 px-4 border-b">ID</th>
                                        <th class="py-3 px-4 border-b">Name</th>
                                        <th class="py-3 px-4 border-b">Location</th>
                                        <th class="py-3 px-4 border-b">Type</th>
                                        <th class="py-3 px-4 border-b">Price</th>
                                        <th class="py-3 px-4 border-b">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cinemas as $cinema): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="py-3 px-4 border-b text-sm text-gray-800"><?php echo htmlspecialchars($cinema['cinema_id']); ?></td>
                                            <td class="py-3 px-4 border-b text-sm text-gray-800"><?php echo htmlspecialchars($cinema['name']); ?></td>
                                            <td class="py-3 px-4 border-b text-sm text-gray-800"><?php echo htmlspecialchars($cinema['location']); ?></td>
                                            <td class="py-3 px-4 border-b text-sm text-gray-800"><?php echo htmlspecialchars($cinema['type_name']); ?></td>
                                            <td class="py-3 px-4 border-b text-sm text-gray-800">$<?php echo htmlspecialchars(number_format($cinema['price'], 2)); ?></td>
                                            <td class="py-3 px-4 border-b text-sm">
                                                <form action="admin.php?section=cinemas" method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to delete this cinema?');">
                                                    <input type="hidden" name="action" value="delete_cinema">
                                                    <input type="hidden" name="cinema_id" value="<?php echo htmlspecialchars($cinema['cinema_id']); ?>">
                                                    <button type="submit" class="bg-red-500 text-white px-3 py-1 rounded-md text-xs hover:bg-red-600">Delete</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-700">No cinemas found in the database.</p>
                    <?php endif; ?>

                <?php elseif ($current_section == 'users'): ?>
                    <h2 class="text-2xl font-bold text-gray-800 mb-4">Manage Users</h2>

                    <h3 class="text-xl font-semibold text-gray-700 mb-3">All Users (Viewers & Admins)</h3>
                    <?php if (!empty($users)): ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                                <thead>
                                    <tr class="bg-gray-100 text-left text-sm font-medium text-gray-600 uppercase tracking-wider">
                                        <th class="py-3 px-4 border-b">ID</th>
                                        <th class="py-3 px-4 border-b">Name</th>
                                        <th class="py-3 px-4 border-b">Email</th>
                                        <th class="py-3 px-4 border-b">Preference</th>
                                        <th class="py-3 px-4 border-b">Type</th>
                                        <th class="py-3 px-4 border-b">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="py-3 px-4 border-b text-sm text-gray-800"><?php echo htmlspecialchars($user['user_id']); ?></td>
                                            <td class="py-3 px-4 border-b text-sm text-gray-800"><?php echo htmlspecialchars($user['name']); ?></td>
                                            <td class="py-3 px-4 border-b text-sm text-gray-800"><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td class="py-3 px-4 border-b text-sm text-gray-800"><?php echo htmlspecialchars(ucfirst($user['preference'])); ?></td>
                                            <td class="py-3 px-4 border-b text-sm text-gray-800"><?php echo htmlspecialchars($user['user_type']); ?></td>
                                            <td class="py-3 px-4 border-b text-sm">
                                                <?php if ($user['user_id'] != $admin_id): // Prevent admin from deleting self ?>
                                                    <form action="admin.php?section=users" method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                                        <input type="hidden" name="action" value="delete_user">
                                                        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['user_id']); ?>">
                                                        <button type="submit" class="bg-red-500 text-white px-3 py-1 rounded-md text-xs hover:bg-red-600">Delete</button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="text-gray-400 text-xs">Cannot Delete Self</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-700">No users found in the database.</p>
                    <?php endif; ?>

                <?php elseif ($current_section == 'streaming_platforms'): ?>
                    <h2 class="text-2xl font-bold text-gray-800 mb-4">Manage Streaming Platforms</h2>

                    <div class="bg-gray-50 p-6 rounded-lg shadow-sm mb-6">
                        <h3 class="text-xl font-semibold text-gray-700 mb-3">Add New Platform</h3>
                        <form action="admin.php?section=streaming_platforms" method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="add_platform">
                            <div>
                                <label for="platform_name" class="block text-sm font-medium text-gray-700">Platform Name:</label>
                                <input type="text" id="platform_name" name="platform_name" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm">
                            </div>
                            <div>
                                <label for="website" class="block text-sm font-medium text-gray-700">Website URL:</label>
                                <input type="url" id="website" name="website" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm">
                            </div>
                            <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md font-semibold hover:bg-indigo-700">Add Platform</button>
                        </form>
                    </div>

                    <h3 class="text-xl font-semibold text-gray-700 mb-3">All Streaming Platforms</h3>
                    <?php if (!empty($streaming_platforms)): ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                                <thead>
                                    <tr class="bg-gray-100 text-left text-sm font-medium text-gray-600 uppercase tracking-wider">
                                        <th class="py-3 px-4 border-b">ID</th>
                                        <th class="py-3 px-4 border-b">Platform Name</th>
                                        <th class="py-3 px-4 border-b">Website</th>
                                        <th class="py-3 px-4 border-b">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($streaming_platforms as $platform): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="py-3 px-4 border-b text-sm text-gray-800"><?php echo htmlspecialchars($platform['platform_id']); ?></td>
                                            <td class="py-3 px-4 border-b text-sm text-gray-800"><?php echo htmlspecialchars($platform['platform_name']); ?></td>
                                            <td class="py-3 px-4 border-b text-sm text-gray-800"><a href="<?php echo htmlspecialchars($platform['website']); ?>" target="_blank" class="text-blue-600 hover:underline"><?php echo htmlspecialchars($platform['website']); ?></a></td>
                                            <td class="py-3 px-4 border-b text-sm">
                                                <form action="admin.php?section=streaming_platforms" method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to delete this platform?');">
                                                    <input type="hidden" name="action" value="delete_platform">
                                                    <input type="hidden" name="platform_id" value="<?php echo htmlspecialchars($platform['platform_id']); ?>">
                                                    <button type="submit" class="bg-red-500 text-white px-3 py-1 rounded-md text-xs hover:bg-red-600">Delete</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-700">No streaming platforms found in the database.</p>
                    <?php endif; ?>

                <?php elseif ($current_section == 'streaming_services'): ?>
                    <h2 class="text-2xl font-bold text-gray-800 mb-4">Manage Streaming Services (Movie-Platform Links)</h2>

                    <div class="bg-gray-50 p-6 rounded-lg shadow-sm mb-6">
                        <h3 class="text-xl font-semibold text-gray-700 mb-3">Add New Service Link</h3>
                        <form action="admin.php?section=streaming_services" method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="add_service">
                            <div>
                                <label for="service_movie_id" class="block text-sm font-medium text-gray-700">Movie:</label>
                                <select id="service_movie_id" name="movie_id" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm">
                                    <option value="">Select Movie</option>
                                    <?php foreach ($movies as $movie): ?>
                                        <option value="<?php echo htmlspecialchars($movie['movie_id']); ?>"><?php echo htmlspecialchars($movie['title']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="service_platform_id" class="block text-sm font-medium text-gray-700">Platform:</label>
                                <select id="service_platform_id" name="platform_id" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm">
                                    <option value="">Select Platform</option>
                                    <?php foreach ($streaming_platforms as $platform): ?>
                                        <option value="<?php echo htmlspecialchars($platform['platform_id']); ?>"><?php echo htmlspecialchars($platform['platform_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="price_720p" class="block text-sm font-medium text-gray-700">Price (720p):</label>
                                <input type="number" id="price_720p" name="price_720p" step="0.01" min="0" value="0.00" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm">
                            </div>
                            <div>
                                <label for="price_1080p" class="block text-sm font-medium text-gray-700">Price (1080p):</label>
                                <input type="number" id="price_1080p" name="price_1080p" step="0.01" min="0" value="0.00" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm">
                            </div>
                            <div>
                                <label for="price_4k" class="block text-sm font-medium text-gray-700">Price (4K):</label>
                                <input type="number" id="price_4k" name="price_4k" step="0.01" min="0" value="0.00" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm">
                            </div>
                            <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md font-semibold hover:bg-indigo-700">Add Service Link</button>
                        </form>
                    </div>

                    <h3 class="text-xl font-semibold text-gray-700 mb-3">All Streaming Services</h3>
                    <?php if (!empty($streaming_services)): ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                                <thead>
                                    <tr class="bg-gray-100 text-left text-sm font-medium text-gray-600 uppercase tracking-wider">
                                        <th class="py-3 px-4 border-b">ID</th>
                                        <th class="py-3 px-4 border-b">Movie Title</th>
                                        <th class="py-3 px-4 border-b">Platform</th>
                                        <th class="py-3 px-4 border-b">720p Price</th>
                                        <th class="py-3 px-4 border-b">1080p Price</th>
                                        <th class="py-3 px-4 border-b">4K Price</th>
                                        <th class="py-3 px-4 border-b">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($streaming_services as $service): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="py-3 px-4 border-b text-sm text-gray-800"><?php echo htmlspecialchars($service['streaming_id']); ?></td>
                                            <td class="py-3 px-4 border-b text-sm text-gray-800"><?php echo htmlspecialchars($service['movie_title']); ?></td>
                                            <td class="py-3 px-4 border-b text-sm text-gray-800"><?php echo htmlspecialchars($service['platform_name']); ?></td>
                                            <td class="py-3 px-4 border-b text-sm text-gray-800">$<?php echo htmlspecialchars(number_format($service['price_720p'], 2)); ?></td>
                                            <td class="py-3 px-4 border-b text-sm text-gray-800">$<?php echo htmlspecialchars(number_format($service['price_1080p'], 2)); ?></td>
                                            <td class="py-3 px-4 border-b text-sm text-gray-800">$<?php echo htmlspecialchars(number_format($service['price_4k'], 2)); ?></td>
                                            <td class="py-3 px-4 border-b text-sm">
                                                <form action="admin.php?section=streaming_services" method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to delete this streaming service link?');">
                                                    <input type="hidden" name="action" value="delete_service">
                                                    <input type="hidden" name="streaming_id" value="<?php echo htmlspecialchars($service['streaming_id']); ?>">
                                                    <button type="submit" class="bg-red-500 text-white px-3 py-1 rounded-md text-xs hover:bg-red-600">Delete</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-700">No streaming services found in the database.</p>
                    <?php endif; ?>

                <?php elseif ($current_section == 'activity'): ?>
                    <h2 class="text-2xl font-bold text-gray-800 mb-4">Viewer Activity Log</h2>
                    <?php if (!empty($viewer_activities)): ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                                <thead>
                                    <tr class="bg-gray-100 text-left text-sm font-medium text-gray-600 uppercase tracking-wider">
                                        <th class="py-3 px-4 border-b">Activity ID</th>
                                        <th class="py-3 px-4 border-b">User</th>
                                        <th class="py-3 px-4 border-b">Movie</th>
                                        <th class="py-3 px-4 border-b">Reason/Action</th>
                                        <th class="py-3 px-4 border-b">Timestamp</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($viewer_activities as $activity): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="py-3 px-4 border-b text-sm text-gray-800"><?php echo htmlspecialchars($activity['recommendation_id']); ?></td>
                                            <td class="py-3 px-4 border-b text-sm text-gray-800"><?php echo htmlspecialchars($activity['user_name']); ?></td>
                                            <td class="py-3 px-4 border-b text-sm text-gray-800"><?php echo htmlspecialchars($activity['movie_title']); ?></td>
                                            <td class="py-3 px-4 border-b text-sm text-gray-800"><?php echo htmlspecialchars($activity['reason']); ?></td>
                                            <td class="py-3 px-4 border-b text-sm text-gray-800"><?php echo htmlspecialchars($activity['activity_timestamp']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-700">No recent viewer activity to display.</p>
                    <?php endif; ?>

                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="editMovieModal" class="modal">
        <div class="modal-content">
            <span class="close-button" onclick="closeEditMovieModal()">&times;</span>
            <h3 class="text-2xl font-bold text-gray-800 mb-4">Edit Movie</h3>
            <form action="admin.php?section=movies" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="edit_movie">
                <input type="hidden" name="movie_id" id="edit_movie_id">
                <div>
                    <label for="edit_title" class="block text-sm font-medium text-gray-700">Title:</label>
                    <input type="text" id="edit_title" name="title" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm">
                </div>
                <div>
                    <label for="edit_genre" class="block text-sm font-medium text-gray-700">Genre:</label>
                    <input type="text" id="edit_genre" name="genre" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm">
                </div>
                <div>
                    <label for="edit_release_date" class="block text-sm font-medium text-gray-700">Release Date:</label>
                    <input type="date" id="edit_release_date" name="release_date" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm">
                </div>
                <div>
                    <label for="edit_language" class="block text-sm font-medium text-gray-700">Language:</label>
                    <input type="text" id="edit_language" name="language" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm">
                </div>
                <div>
                    <label for="edit_duration" class="block text-sm font-medium text-gray-700">Duration (minutes):</label>
                    <input type="number" id="edit_duration" name="duration" required min="1" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm">
                </div>
                <div>
                    <label for="edit_rating" class="block text-sm font-medium text-gray-700">Rating (0.0 - 10.0):</label>
                    <input type="number" id="edit_rating" name="rating" required step="0.1" min="0" max="10" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm">
                </div>
                <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-md font-semibold hover:bg-green-700">Update Movie</button>
            </form>
        </div>
    </div>

    <script>
        const editMovieModal = document.getElementById('editMovieModal');
        const editMovieIdInput = document.getElementById('edit_movie_id');
        const editTitleInput = document.getElementById('edit_title');
        const editGenreInput = document.getElementById('edit_genre');
        const editReleaseDateInput = document.getElementById('edit_release_date');
        const editLanguageInput = document.getElementById('edit_language');
        const editDurationInput = document.getElementById('edit_duration');
        const editRatingInput = document.getElementById('edit_rating');

        function openEditMovieModal(movie) {
            editMovieIdInput.value = movie.movie_id;
            editTitleInput.value = movie.title;
            editGenreInput.value = movie.genre;
            editReleaseDateInput.value = movie.release_date;
            editLanguageInput.value = movie.language;
            editDurationInput.value = movie.duration;
            editRatingInput.value = movie.rating;
            editMovieModal.style.display = 'flex';
        }

        function closeEditMovieModal() {
            editMovieModal.style.display = 'none';
        }

        // Close the modal if the user clicks outside of it
        window.onclick = function(event) {
            if (event.target == editMovieModal) {
                closeEditMovieModal();
            }
        }
    </script>
</body>
</html>
