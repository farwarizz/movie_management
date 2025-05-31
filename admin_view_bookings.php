<?php
/**
 * admin_view_bookings.php
 * This file allows administrators to view and manage cinema bookings.
 */

session_start();
require_once 'db_connect.php'; // Ensure this path is correct

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$message = '';

// Function to sanitize input (if needed for future edit/delete)
function sanitize_input($conn, $data) {
    return $conn->real_escape_string(htmlspecialchars(strip_tags($data)));
}

// --- Fetch Bookings Data ---
$bookings = [];
$sql_bookings = "SELECT b.booking_id, u.name AS user_name, u.email AS user_email,
                        m.title AS movie_title, c.name AS cinema_name, c.location,
                        ct.type_name AS cinema_type, ct.price AS ticket_price,
                        b.booking_date, b.show_time, b.seat_number
                 FROM booking b
                 JOIN user u ON b.user_id = u.user_id
                 JOIN movies m ON b.movie_id = m.movie_id
                 JOIN cinema c ON b.cinema_id = c.cinema_id
                 JOIN cinema_type ct ON c.type_id = ct.type_id
                 ORDER BY b.booking_date DESC, b.show_time DESC";
$result_bookings = $conn->query($sql_bookings);

if ($result_bookings) {
    while ($row = $result_bookings->fetch_assoc()) {
        $bookings[] = $row;
    }
} else {
    $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Error fetching bookings: " . $conn->error . "</div>";
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - View Bookings</title>
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
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        .action-buttons a, .action-buttons button {
            padding: 6px 12px;
            border-radius: 5px;
            font-size: 0.875rem;
            text-decoration: none;
            color: white;
            cursor: pointer;
            transition: background-color 0.2s ease-in-out;
            border: none;
        }
        .action-buttons .edit-btn { background-color: #3b82f6; } /* blue-500 */
        .action-buttons .edit-btn:hover { background-color: #2563eb; } /* blue-600 */
        .action-buttons .delete-btn { background-color: #ef4444; } /* red-500 */
        .action-buttons .delete-btn:hover { background-color: #dc2626; } /* red-600 */
    </style>
</head>
<body class="min-h-screen bg-gray-100 flex flex-col">
    <nav class="bg-indigo-700 p-4 shadow-md">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-white text-2xl font-bold">Admin Panel - Bookings</h1>
            <div class="flex items-center space-x-4">
                <a href="admin.php" class="bg-white text-indigo-700 px-4 py-2 rounded-md font-semibold hover:bg-indigo-100">Back to Dashboard</a>
                <a href="logout.php" class="bg-white text-indigo-700 px-4 py-2 rounded-md font-semibold hover:bg-indigo-100">Logout</a>
            </div>
        </div>
    </nav>

    <main class="flex-grow container mx-auto p-6">
        <?php echo $message; // Display messages ?>

        <div class="bg-white rounded-lg shadow-md overflow-hidden p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">All Cinema Bookings</h2>

            <?php if (!empty($bookings)): ?>
                <div class="overflow-x-auto">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User Name</th>
                                <th>User Email</th>
                                <th>Movie Title</th>
                                <th>Cinema Name</th>
                                <th>Location</th>
                                <th>Cinema Type</th>
                                <th>Ticket Price</th>
                                <th>Booking Date</th>
                                <th>Show Time</th>
                                <th>Seat Number</th>
                                </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookings as $booking): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($booking['booking_id']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['user_name']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['user_email']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['movie_title']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['cinema_name']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['location']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['cinema_type']); ?></td>
                                    <td>PKR <?php echo number_format(htmlspecialchars($booking['ticket_price']), 2); ?></td>
                                    <td><?php echo htmlspecialchars($booking['booking_date']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['show_time']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['seat_number']); ?></td>
                                    <!-- <td class="action-buttons">
                                        <button class="edit-btn">Edit</button>
                                        <button class="delete-btn">Delete</button>
                                    </td> -->
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-gray-700 text-center py-4">No bookings found.</p>
            <?php endif; ?>
        </div>
    </main>

    <footer class="bg-gray-800 text-white text-center p-4 mt-auto">
        <div class="container mx-auto">
            <p>&copy; <?php echo date('Y'); ?> Movies Management System. Admin Panel.</p>
        </div>
    </footer>
</body>
</html>
