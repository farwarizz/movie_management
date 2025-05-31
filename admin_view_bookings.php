<?php
/**
 * admin_view_bookings.php
 * This file allows administrators to view, delete, and UPDATE cinema ticket bookings.
 */

session_start();
require_once 'db_connect.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$message = '';
$edit_booking_data = null; // To hold data if a booking is being edited

// Function to sanitize input
function sanitize_input($conn, $data) {
    return $conn->real_escape_string(htmlspecialchars(strip_tags($data)));
}

// --- Handle Update Booking ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'update_booking') {
    $booking_id = (int)$_POST['booking_id'];
    $user_id = (int)$_POST['user_id']; // User ID might be updated if user was selected from dropdown
    $movie_id = (int)$_POST['movie_id'];
    $cinema_id = (int)$_POST['cinema_id'];
    $booking_date = sanitize_input($conn, $_POST['booking_date']);
    $show_time = sanitize_input($conn, $_POST['show_time']);
    $seat_number = sanitize_input($conn, $_POST['seat_number']);

    if (empty($user_id) || empty($movie_id) || empty($cinema_id) || empty($booking_date) || empty($show_time) || empty($seat_number)) {
        $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>All fields are required for update.</div>";
    } else {
        $stmt = $conn->prepare("UPDATE booking SET user_id = ?, cinema_id = ?, movie_id = ?, booking_date = ?, show_time = ?, seat_number = ? WHERE booking_id = ?");
        if ($stmt) {
            $stmt->bind_param("iiisssi", $user_id, $cinema_id, $movie_id, $booking_date, $show_time, $seat_number, $booking_id);
            if ($stmt->execute()) {
                $message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4' role='alert'>Booking updated successfully!</div>";
            } else {
                $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Error updating booking: " . $stmt->error . "</div>";
            }
            $stmt->close();
        } else {
            $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Database error: " . $conn->error . "</div>";
        }
    }
}

// --- Handle Delete Booking ---
if (isset($_GET['delete_id'])) {
    $booking_id = (int)$_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM booking WHERE booking_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $booking_id);
        if ($stmt->execute()) {
            $message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4' role='alert'>Booking deleted successfully!</div>";
        } else {
            $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Error deleting booking: " . $stmt->error . "</div>";
        }
        $stmt->close();
    } else {
        $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Database error: " . $conn->error . "</div>";
    }
    header("Location: admin_view_bookings.php?message=" . urlencode(strip_tags($message))); // Pass message
    exit();
}

// Handle message from redirect
if (isset($_GET['message'])) {
    $message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4' role='alert'>" . htmlspecialchars($_GET['message']) . "</div>";
}

// --- Handle Edit Booking Request (Populate form for update) ---
if (isset($_GET['edit_id'])) {
    $booking_id = (int)$_GET['edit_id'];
    $stmt = $conn->prepare("SELECT booking_id, user_id, movie_id, cinema_id, booking_date, show_time, seat_number FROM booking WHERE booking_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $booking_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $edit_booking_data = $result->fetch_assoc();
        } else {
            $message = "<div class='bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative mb-4' role='alert'>Booking not found for editing.</div>";
        }
        $stmt->close();
    }
}

// --- Fetch All Bookings for Display ---
$bookings = [];
$sql = "SELECT b.booking_id, u.name AS user_name, m.title AS movie_title, c.name AS cinema_name,
               b.booking_date, b.show_time, b.seat_number, ct.price AS ticket_price
        FROM booking b
        JOIN user u ON b.user_id = u.user_id
        JOIN movies m ON b.movie_id = m.movie_id
        JOIN cinema c ON b.cinema_id = c.cinema_id
        JOIN cinema_type ct ON c.type_id = ct.type_id
        ORDER BY b.booking_date DESC, b.show_time DESC";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
    }
} else {
    $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Error fetching bookings: " . $conn->error . "</div>";
}

// --- Fetch data for dropdowns (Users, Movies, Cinemas) ---
$users_for_dropdown = [];
$result_users = $conn->query("SELECT user_id, name FROM user ORDER BY name ASC");
if ($result_users) {
    while ($row = $result_users->fetch_assoc()) {
        $users_for_dropdown[] = $row;
    }
}

$movies_for_dropdown = [];
$result_movies = $conn->query("SELECT movie_id, title FROM movies ORDER BY title ASC");
if ($result_movies) {
    while ($row = $result_movies->fetch_assoc()) {
        $movies_for_dropdown[] = $row;
    }
}

$cinemas_for_dropdown = [];
$result_cinemas = $conn->query("SELECT cinema_id, name FROM cinema ORDER BY name ASC");
if ($result_cinemas) {
    while ($row = $result_cinemas->fetch_assoc()) {
        $cinemas_for_dropdown[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Bookings - Admin Panel</title>
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
        }
        .action-buttons .edit-btn { background-color: #3b82f6; } /* blue-500 */
        .action-buttons .edit-btn:hover { background-color: #2563eb; } /* blue-600 */
        .action-buttons .delete-btn { background-color: #ef4444; } /* red-500 */
        .action-buttons .delete-btn:hover { background-color: #dc2626; } /* red-600 */

        /* Modal specific styles */
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
<body class="min-h-screen bg-gray-100 flex flex-col">
    <nav class="bg-indigo-700 p-4 shadow-md">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-white text-2xl font-bold">View Bookings</h1>
            <div class="flex items-center space-x-4">
                <span class="text-white text-lg">Welcome, Admin <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</span>
                <a href="admin.php" class="bg-white text-indigo-700 px-4 py-2 rounded-md font-semibold hover:bg-indigo-100">Admin Dashboard</a>
                <a href="logout.php" class="bg-white text-indigo-700 px-4 py-2 rounded-md font-semibold hover:bg-indigo-100">Logout</a>
            </div>
        </div>
    </nav>

    <main class="flex-grow container mx-auto p-6">
        <?php echo $message; // Display messages ?>

        <h2 class="text-2xl font-bold text-gray-800 mb-6">All Cinema Bookings</h2>
        <?php if (!empty($bookings)): ?>
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="overflow-x-auto">
                    <table>
                        <thead>
                            <tr>
                                <th>Booking ID</th>
                                <th>User</th>
                                <th>Movie</th>
                                <th>Cinema</th>
                                <th>Booking Date</th>
                                <th>Show Time</th>
                                <th>Seat Number</th>
                                <th>Ticket Price</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookings as $booking): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($booking['booking_id']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['user_name']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['movie_title']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['cinema_name']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['booking_date']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['show_time']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['seat_number']); ?></td>
                                    <td>$<?php echo htmlspecialchars(number_format($booking['ticket_price'], 2)); ?></td>
                                    <td class="action-buttons">
                                        <button onclick="openEditBookingModal(<?php echo htmlspecialchars(json_encode($booking)); ?>)" class="edit-btn">Edit</button>
                                        <button onclick="confirmDelete(<?php echo htmlspecialchars($booking['booking_id']); ?>)" class="delete-btn">Delete</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <p class="text-gray-700 text-lg text-center py-10">No cinema bookings found in the database.</p>
        <?php endif; ?>
    </main>

    <footer class="bg-gray-800 text-white text-center p-4 mt-auto">
        <div class="container mx-auto">
            <p>&copy; <?php echo date('Y'); ?> Movies Management System. Admin Panel.</p>
        </div>
    </footer>

    <div id="editBookingModal" class="modal">
        <div class="modal-content">
            <span class="close-button" onclick="closeEditBookingModal()">&times;</span>
            <h3 class="text-2xl font-bold text-gray-800 mb-4">Edit Booking</h3>
            <form action="admin_view_bookings.php" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="update_booking">
                <input type="hidden" name="booking_id" id="edit_booking_id">

                <div>
                    <label for="edit_user_id" class="block text-sm font-medium text-gray-700">User:</label>
                    <select id="edit_user_id" name="user_id" required
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <?php foreach ($users_for_dropdown as $user): ?>
                            <option value="<?php echo htmlspecialchars($user['user_id']); ?>"><?php echo htmlspecialchars($user['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="edit_movie_id" class="block text-sm font-medium text-gray-700">Movie:</label>
                    <select id="edit_movie_id" name="movie_id" required
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <?php foreach ($movies_for_dropdown as $movie): ?>
                            <option value="<?php echo htmlspecialchars($movie['movie_id']); ?>"><?php echo htmlspecialchars($movie['title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="edit_cinema_id" class="block text-sm font-medium text-gray-700">Cinema:</label>
                    <select id="edit_cinema_id" name="cinema_id" required
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <?php foreach ($cinemas_for_dropdown as $cinema): ?>
                            <option value="<?php echo htmlspecialchars($cinema['cinema_id']); ?>"><?php echo htmlspecialchars($cinema['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="edit_booking_date" class="block text-sm font-medium text-gray-700">Booking Date:</label>
                    <input type="date" id="edit_booking_date" name="booking_date" required
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>

                <div>
                    <label for="edit_show_time" class="block text-sm font-medium text-gray-700">Show Time:</label>
                    <input type="time" id="edit_show_time" name="show_time" required
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>

                <div>
                    <label for="edit_seat_number" class="block text-sm font-medium text-gray-700">Seat Number:</label>
                    <input type="text" id="edit_seat_number" name="seat_number" required
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>

                <button type="submit"
                        class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Update Booking
                </button>
            </form>
        </div>
    </div>

    <script>
        const editBookingModal = document.getElementById('editBookingModal');
        const editBookingIdInput = document.getElementById('edit_booking_id');
        const editUserIdSelect = document.getElementById('edit_user_id');
        const editMovieIdSelect = document.getElementById('edit_movie_id');
        const editCinemaIdSelect = document.getElementById('edit_cinema_id');
        const editBookingDateInput = document.getElementById('edit_booking_date');
        const editShowTimeInput = document.getElementById('edit_show_time');
        const editSeatNumberInput = document.getElementById('edit_seat_number');

        function openEditBookingModal(booking) {
            editBookingIdInput.value = booking.booking_id;
            editUserIdSelect.value = booking.user_id; // Assuming user_id is passed in the booking object
            editMovieIdSelect.value = booking.movie_id; // Assuming movie_id is passed
            editCinemaIdSelect.value = booking.cinema_id; // Assuming cinema_id is passed
            editBookingDateInput.value = booking.booking_date;
            editShowTimeInput.value = booking.show_time;
            editSeatNumberInput.value = booking.seat_number;
            editBookingModal.style.display = 'flex';
        }

        function closeEditBookingModal() {
            editBookingModal.style.display = 'none';
        }

        function confirmDelete(id) {
            if (confirm(`Are you sure you want to delete booking ID: ${id}? This action cannot be undone.`)) {
                window.location.href = `admin_view_bookings.php?delete_id=${id}`;
            }
        }

        // Close the modal if the user clicks outside of it
        window.onclick = function(event) {
            if (event.target == editBookingModal) {
                closeEditBookingModal();
            }
        }
    </script>
</body>
</html>
