<?php
/**
 * streaming_platform.php
 * This file displays a list of all streaming platforms available in the database.
 * Each platform name is a clickable link that redirects to streaming_services.php,
 * passing the platform_id to show movies available on that specific platform.
 */

// Start a PHP session (needed for user context in the navigation bar)
session_start();

// Include the database connection file
require_once 'db_connect.php';

$platforms_data = [];

// Fetch all streaming platforms
$sql = "SELECT platform_id, platform_name, website FROM streaming_platform ORDER BY platform_name ASC";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $platforms_data[] = $row;
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
    <title>Streaming Platforms - Movies Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f2f5;
        }
        .platform-link {
            @apply text-indigo-600 hover:text-indigo-800 hover:underline cursor-pointer;
        }
    </style>
</head>
<body class="min-h-screen bg-gray-100">
    <nav class="bg-indigo-600 p-4 shadow-md">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-white text-2xl font-bold">Streaming Platforms</h1>
            <div class="flex items-center space-x-4">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <span class="text-white text-lg">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</span>
                    <a href="viewer.php" class="bg-white text-indigo-600 px-4 py-2 rounded-md font-semibold hover:bg-indigo-100">My Dashboard</a>
                    <a href="movies.php" class="bg-white text-indigo-600 px-4 py-2 rounded-md font-semibold hover:bg-indigo-100">Browse All Movies</a>
                    <a href="cinema.php" class="bg-white text-indigo-600 px-4 py-2 rounded-md font-semibold hover:bg-indigo-100">Cinemas</a>
                    <a href="logout.php" class="bg-white text-indigo-600 px-4 py-2 rounded-md font-semibold hover:bg-indigo-100">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="bg-white text-indigo-600 px-4 py-2 rounded-md font-semibold hover:bg-indigo-100">Login</a>
                    <a href="registration.php" class="bg-white text-indigo-600 px-4 py-2 rounded-md font-semibold hover:bg-indigo-100">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="container mx-auto p-6">
        <h2 class="text-3xl font-bold text-gray-800 mb-6">All Streaming Platforms</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php if (!empty($platforms_data)): ?>
                <?php foreach ($platforms_data as $platform): ?>
                    <div class="bg-white rounded-lg shadow-md overflow-hidden p-6">
                        <h3 class="text-xl font-semibold text-gray-800 mb-2">
                            <a href="streaming_services.php?platform_id=<?php echo htmlspecialchars($platform['platform_id']); ?>" class="platform-link">
                                <?php echo htmlspecialchars($platform['platform_name']); ?>
                            </a>
                        </h3>
                        <p class="text-gray-600 text-sm mb-1">Website: <a href="<?php echo htmlspecialchars($platform['website']); ?>" target="_blank" class="text-blue-600 hover:underline"><?php echo htmlspecialchars($platform['website']); ?></a></p>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-gray-700 text-lg col-span-full text-center py-10">No streaming platforms found in the database.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
