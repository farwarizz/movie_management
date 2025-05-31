<?php
/**
 * index.php
 * This file serves as the main entry point and landing page for the Movies Management System.
 * It provides navigation options for users to log in, register, or browse public content.
 */

// Start a PHP session. This is needed to check if a user is already logged in.
session_start();

// No database connection needed directly on this page, as it's primarily for navigation.
// Database connection will be established by the linked pages (login.php, viewer.php, etc.).

// Check if a user is logged in to dynamically adjust navigation links
$is_logged_in = isset($_SESSION['user_id']);
$user_name = $is_logged_in ? $_SESSION['user_name'] : '';
$user_type = $is_logged_in ? $_SESSION['user_type'] : '';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Movies Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f2f5;
        }
    </style>
</head>
<body class="min-h-screen bg-gray-100 flex flex-col">
    <nav class="bg-indigo-600 p-4 shadow-md">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-white text-2xl font-bold">Movies Management System</h1>
            <div class="flex items-center space-x-4">
                <?php if ($is_logged_in): ?>
                    <span class="text-white text-lg">Welcome, <?php echo htmlspecialchars($user_name); ?>!</span>
                    <?php if ($user_type === 'viewer'): ?>
                        <a href="viewer.php" class="bg-white text-indigo-600 px-4 py-2 rounded-md font-semibold hover:bg-indigo-100">My Dashboard</a>
                    <?php elseif ($user_type === 'admin'): ?>
                        <a href="admin.php" class="bg-white text-indigo-600 px-4 py-2 rounded-md font-semibold hover:bg-indigo-100">Admin Dashboard</a>
                    <?php endif; ?>
                    <a href="logout.php" class="bg-white text-indigo-600 px-4 py-2 rounded-md font-semibold hover:bg-indigo-100">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="bg-white text-indigo-600 px-4 py-2 rounded-md font-semibold hover:bg-indigo-100">Login</a>
                    <a href="registration.php" class="bg-white text-indigo-600 px-4 py-2 rounded-md font-semibold hover:bg-indigo-100">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <main class="flex-grow flex items-center justify-center p-6">
        <div class="bg-white p-10 rounded-lg shadow-xl text-center max-w-2xl w-full">
            <h2 class="text-4xl font-bold text-gray-800 mb-6">Your Ultimate Movie Hub</h2>
            <p class="text-lg text-gray-700 mb-8">Discover, book, and stream your favorite movies with ease.</p>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <a href="movies.php" class="block p-6 bg-indigo-500 text-white rounded-lg shadow-md hover:bg-indigo-600 transition-colors duration-300 transform hover:scale-105">
                    <h3 class="text-xl font-semibold mb-2">Browse All Movies</h3>
                    <p class="text-sm">See what's playing and available for streaming.</p>
                </a>
                <a href="cinema.php" class="block p-6 bg-green-500 text-white rounded-lg shadow-md hover:bg-green-600 transition-colors duration-300 transform hover:scale-105">
                    <h3 class="text-xl font-semibold mb-2">Find Cinemas</h3>
                    <p class="text-sm">Explore local theaters and showtimes.</p>
                </a>
                <a href="streaming_platform.php" class="block p-6 bg-purple-500 text-white rounded-lg shadow-md hover:bg-purple-600 transition-colors duration-300 transform hover:scale-105">
                    <h3 class="text-xl font-semibold mb-2">Streaming Platforms</h3>
                    <p class="text-sm">Discover where to stream your next movie.</p>
                </a>
            </div>
        </div>
    </main>

    <footer class="bg-gray-800 text-white text-center p-4 mt-auto">
        <div class="container mx-auto">
            <p>&copy; <?php echo date('Y'); ?> Movies Management System. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>
