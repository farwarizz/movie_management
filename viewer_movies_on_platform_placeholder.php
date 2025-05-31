<?php
/**
 * viewer.php
 * This is the main dashboard for regular users (viewers) of the Movies Management System.
 * It displays cinema listings, streaming platforms, recommendations,
 * and the user's personal bookings and subscriptions.
 */

session_start();
require_once 'db_connect.php';

// Check if user is logged in and is a viewer
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'viewer') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';

// --- General Message Handling ---
if (isset($_GET['message'])) {
    $message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4' role='alert'>" . htmlspecialchars($_GET['message']) . "</div>";
}
if (isset($_GET['error'])) {
    $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>" . htmlspecialchars($_GET['error']) . "</div>";
}

// --- Fetch Cinema Listings ---
$cinemas = [];
$sql_cinemas = "SELECT c.cinema_id, c.name AS cinema_name, c.location, ct.type_name, ct.price
                FROM cinema c JOIN cinema_type ct ON c.type_id = ct.type_id ORDER BY c.name";
$result_cinemas = $conn->query($sql_cinemas);
if ($result_cinemas) {
    while ($row = $result_cinemas->fetch_assoc()) {
        $cinemas[] = $row;
    }
} else {
    $message .= "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Error fetching cinema listings: " . $conn->error . "</div>";
}

// --- Fetch Streaming Platforms ---
$platforms = [];
$sql_platforms = "SELECT platform_id, platform_name, website FROM streaming_platform ORDER BY platform_name";
$result_platforms = $conn->query($sql_platforms);
if ($result_platforms) {
    while ($row = $result_platforms->fetch_assoc()) {
        $platforms[] = $row;
    }
} else {
    $message .= "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Error fetching streaming platforms: " . $conn->error . "</div>";
}

// --- Fetch User Recommendations (Activity Log) ---
$recommendations = [];
// Assuming recommendations table has user_id and movie_id, joining with movies table
$sql_recommendations = "SELECT r.reason, m.title AS movie_title, m.genre, m.release_date, m.language
                        FROM recommendations r
                        JOIN movies m ON r.movie_id = m.movie_id
                        WHERE r.user_id = ?
                        ORDER BY r.recommendation_id DESC LIMIT 5"; // Show latest 5 recommendations
$stmt_recommendations = $conn->prepare($sql_recommendations);
if ($stmt_recommendations) {
    $stmt_recommendations->bind_param("i", $user_id);
    $stmt_recommendations->execute();
    $result_recommendations = $stmt_recommendations->get_result();
    while ($row = $result_recommendations->fetch_assoc()) {
        $recommendations[] = $row;
    }
    $stmt_recommendations->close();
} else {
    $message .= "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Error preparing recommendations query: " . $conn->error . "</div>";
}

// --- Fetch My Cinema Bookings ---
$my_bookings = [];
$sql_my_bookings = "SELECT b.booking_id, m.title AS movie_title, c.name AS cinema_name, c.location,
                           b.booking_date, b.show_time, b.seat_number, ct.price AS estimated_price
                    FROM booking b
                    JOIN movies m ON b.movie_id = m.movie_id
                    JOIN cinema c ON b.cinema_id = c.cinema_id
                    JOIN cinema_type ct ON c.type_id = ct.type_id
                    WHERE b.user_id = ?
                    ORDER BY b.booking_date DESC, b.show_time DESC";
$stmt_my_bookings = $conn->prepare($sql_my_bookings);
if ($stmt_my_bookings) {
    $stmt_my_bookings->bind_param("i", $user_id);
    $stmt_my_bookings->execute();
    $result_my_bookings = $stmt_my_bookings->get_result();
    while ($row = $result_my_bookings->fetch_assoc()) {
        $my_bookings[] = $row;
    }
    $stmt_my_bookings->close();
} else {
    $message .= "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Error preparing bookings query: " . $conn->error . "</div>";
}

// --- Fetch My Streaming Subscriptions ---
$my_subscriptions = [];
$sql_my_subscriptions = "SELECT s.subscription_id, sp.platform_name, s.plan_type, s.start_date, s.end_date
                         FROM subscription s
                         JOIN streaming_platform sp ON s.platform_id = sp.platform_id
                         WHERE s.user_id = ?
                         ORDER BY s.end_date DESC";
$stmt_my_subscriptions = $conn->prepare($sql_my_subscriptions);
if ($stmt_my_subscriptions) {
    $stmt_my_subscriptions->bind_param("i", $user_id);
    $stmt_my_subscriptions->execute();
    $result_my_subscriptions = $stmt_my_subscriptions->get_result();
    while ($row = $result_my_subscriptions->fetch_assoc()) {
        $my_subscriptions[] = $row;
    }
    $stmt_my_subscriptions->close();
} else {
    $message .= "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Error preparing subscriptions query: " . $conn->error . "</div>";
}


$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Viewer Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f2f5;
        }
        .dashboard-section {
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
        .btn-green {
            background-color: #22c55e; /* green-500 */
            color: white;
        }
        .btn-green:hover {
            background-color: #16a34a; /* green-600 */
        }
        .btn-purple {
            background-color: #8b5cf6; /* purple-500 */
            color: white;
        }
        .btn-purple:hover {
            background-color: #7c3aed; /* purple-600 */
        }
    </style>
</head>
<body class="min-h-screen bg-gray-100 flex flex-col">
    <nav class="bg-blue-700 p-4 shadow-md">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-white text-2xl font-bold">Viewer Dashboard</h1>
            <div class="flex items-center space-x-4">
                <span class="text-white">Hello, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</span>
                <a href="logout.php" class="bg-white text-blue-700 px-4 py-2 rounded-md font-semibold hover:bg-blue-100">Logout</a>
            </div>
        </div>
    </nav>

    <main class="flex-grow container mx-auto p-6">
        <?php echo $message; // Display messages ?>

        <h2 class="text-3xl font-bold text-gray-800 mb-6">What's happening in MovieVerse today.</h2>

        <div class="dashboard-section">
            <h3 class="text-2xl font-semibold text-gray-700 mb-4">Cinema Listings</h3>
            <?php if (!empty($cinemas)): ?>
                <div class="overflow-x-auto">
                    <table>
                        <thead>
                            <tr>
                                <th>Cinema Name</th>
                                <th>Location</th>
                                <th>Type</th>
                                <th>Standard Price</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cinemas as $cinema): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($cinema['cinema_name']); ?></td>
                                    <td><?php echo htmlspecialchars($cinema['location']); ?></td>
                                    <td><?php echo htmlspecialchars($cinema['type_name']); ?></td>
                                    <td>PKR <?php echo number_format(htmlspecialchars($cinema['price']), 2); ?></td>
                                    <td>
                                        <a href="viewer_movies_by_cinema.php?cinema_id=<?php echo htmlspecialchars($cinema['cinema_id']); ?>" class="btn btn-blue">View Movies & Book</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-gray-600">No cinema listings available at the moment.</p>
            <?php endif; ?>
        </div>

        <div class="dashboard-section">
            <h3 class="text-2xl font-semibold text-gray-700 mb-4">Streaming Platforms</h3>
            <p class="text-gray-600 mb-4">Click "Visit Site" to go to the platform's external website. Click "View Movies" to see movies available on that platform within MovieVerse (if applicable).</p>
            <a href="viewer_streaming_services.php" class="btn btn-purple mb-6">View All Streaming Services & Subscribe</a>
            <?php if (!empty($platforms)): ?>
                <div class="overflow-x-auto">
                    <table>
                        <thead>
                            <tr>
                                <th>Platform Name</th>
                                <th>Website (External)</th>
                                <th>View Movies on Platform</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($platforms as $platform): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($platform['platform_name']); ?></td>
                                    <td><a href="<?php echo htmlspecialchars($platform['website']); ?>" target="_blank" class="btn btn-blue">Visit Site</a></td>
                                    <td>
                                        <a href="viewer_movies_on_platform_placeholder.php?platform_id=<?php echo htmlspecialchars($platform['platform_id']); ?>" class="btn btn-green">View Movies</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-gray-600">No streaming platforms available at the moment.</p>
            <?php endif; ?>
        </div>

        <div class="dashboard-section">
            <h3 class="text-2xl font-semibold text-gray-700 mb-4">Your Recommendations</h3>
            <?php if (!empty($recommendations)): ?>
                <div class="overflow-x-auto">
                    <table>
                        <thead>
                            <tr>
                                <th>Movie Title</th>
                                <th>Genre</th>
                                <th>Release Date</th>
                                <th>Language</th>
                                <th>Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recommendations as $rec): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($rec['movie_title']); ?></td>
                                    <td><?php echo htmlspecialchars($rec['genre']); ?></td>
                                    <td><?php echo htmlspecialchars($rec['release_date']); ?></td>
                                    <td><?php echo htmlspecialchars($rec['language']); ?></td>
                                    <td><?php echo htmlspecialchars($rec['reason']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-gray-600">No recommendations for you yet.</p>
            <?php endif; ?>
        </div>

        <div class="dashboard-section">
            <h3 class="text-2xl font-semibold text-gray-700 mb-4">My Cinema Bookings</h3>
            <?php if (!empty($my_bookings)): ?>
                <div class="overflow-x-auto">
                    <table>
                        <thead>
                            <tr>
                                <th>Movie</th>
                                <th>Cinema</th>
                                <th>Location</th>
                                <th>Booking Date</th>
                                <th>Show Time</th>
                                <th>Seat Number</th>
                                <th>Estimated Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($my_bookings as $booking): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($booking['movie_title']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['cinema_name']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['location']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['booking_date']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['show_time']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['seat_number']); ?></td>
                                    <td>PKR <?php echo number_format(htmlspecialchars($booking['estimated_price']), 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-gray-600">No cinema bookings found.</p>
            <?php endif; ?>
        </div>

        <div class="dashboard-section">
            <h3 class="text-2xl font-semibold text-gray-700 mb-4">My Streaming Subscriptions</h3>
            <?php if (!empty($my_subscriptions)): ?>
                <div class="overflow-x-auto">
                    <table>
                        <thead>
                            <tr>
                                <th>Platform</th>
                                <th>Plan Type</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($my_subscriptions as $sub): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($sub['platform_name']); ?></td>
                                    <td><?php echo htmlspecialchars($sub['plan_type']); ?></td>
                                    <td><?php echo htmlspecialchars($sub['start_date']); ?></td>
                                    <td><?php echo htmlspecialchars($sub['end_date']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-gray-600">No active subscriptions found.</p>
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
