<?php
/**
 * booking.php
 * This file handles the cinema ticket booking process.
 * It receives movie_id and cinema_id via GET parameters, displays details,
 * and allows the user to select show time and seat number for booking.
 * It integrates with the 'booking' and 'payments' tables.
 * After a successful booking, it displays a detailed confirmation message.
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

// Get movie_id and cinema_id from GET parameters
$movie_id = isset($_GET['movie_id']) ? (int)$_GET['movie_id'] : 0;
$cinema_id = isset($_GET['cinema_id']) ? (int)$_GET['cinema_id'] : 0;

// Redirect if essential parameters are missing
if ($movie_id === 0 || $cinema_id === 0) {
    header("Location: viewer.php"); // Redirect to a safe page if no movie/cinema selected
    exit();
}

// --- Fetch Movie and Cinema Details ---
$movie_details = null;
$cinema_details = null;

// Fetch movie details
$stmt_movie = $conn->prepare("SELECT movie_id, title, genre, release_date, language, duration, rating FROM movies WHERE movie_id = ?");
if ($stmt_movie) {
    $stmt_movie->bind_param("i", $movie_id);
    $stmt_movie->execute();
    $result_movie = $stmt_movie->get_result();
    if ($result_movie->num_rows === 1) {
        $movie_details = $result_movie->fetch_assoc();
    }
    $stmt_movie->close();
}

// Fetch cinema details including type and price
$stmt_cinema = $conn->prepare("SELECT c.cinema_id, c.name AS cinema_name, c.location, ct.type_name, ct.price
                               FROM cinema c
                               JOIN cinema_type ct ON c.type_id = ct.type_id
                               WHERE c.cinema_id = ?");
if ($stmt_cinema) {
    $stmt_cinema->bind_param("i", $cinema_id);
    $stmt_cinema->execute();
    $result_cinema = $stmt_cinema->get_result();
    if ($result_cinema->num_rows === 1) {
        $cinema_details = $result_cinema->fetch_assoc();
    }
    $stmt_cinema->close();
}

// If movie or cinema details are not found, display an error and exit
if (!$movie_details || !$cinema_details) {
    $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Error: Movie or Cinema details not found.</div>";
    // You might want to redirect back to cinema.php or viewer.php here
    // header("Location: cinema.php"); exit();
}

// --- Handle Booking Form Submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Ensure the form is for booking (can be skipped if this is the only form)
    if (isset($_POST['action']) && $_POST['action'] == 'confirm_booking') {
        $selected_movie_id = (int)$_POST['movie_id'];
        $selected_cinema_id = (int)$_POST['cinema_id'];
        $show_time = $conn->real_escape_string($_POST['show_time']);
        $seat_number = $conn->real_escape_string($_POST['seat_number']);
        $booking_date = date('Y-m-d'); // Current date of booking

        // Re-fetch cinema price to ensure accuracy and prevent tampering
        $booking_price = $cinema_details['price'] ?? 0;

        if (empty($show_time) || empty($seat_number)) {
            $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Show time and seat number are required.</div>";
        } elseif ($selected_movie_id !== $movie_id || $selected_cinema_id !== $cinema_id) {
             $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Security error: Mismatched movie/cinema ID.</div>";
        }
        else {
            $conn->begin_transaction();
            try {
                // Insert into payments table
                $stmt_payment = $conn->prepare("INSERT INTO payments (user_id, amount, payment_method, payment_date, purpose) VALUES (?, ?, ?, ?, ?)");
                $payment_method = 'Credit Card'; // Default for now, could be selected by user
                $purpose = 'Cinema Ticket Booking';
                $stmt_payment->bind_param("idss", $user_id, $booking_price, $payment_method, $booking_date, $purpose);
                if (!$stmt_payment->execute()) {
                    throw new Exception("Error recording payment: " . $stmt_payment->error);
                }
                $payment_id = $conn->insert_id;
                $stmt_payment->close();

                // Insert into booking table
                $stmt_booking = $conn->prepare("INSERT INTO booking (user_id, cinema_id, movie_id, booking_date, show_time, seat_number) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt_booking->bind_param("iiisss", $user_id, $selected_cinema_id, $selected_movie_id, $booking_date, $show_time, $seat_number);
                if (!$stmt_booking->execute()) {
                    throw new Exception("Error recording booking: " . $stmt_booking->error);
                }
                $booking_id = $conn->insert_id; // Get the ID of the new booking
                $stmt_booking->close();

                // Record activity for recommendation system (user booked a movie)
                recordUserActivity($conn, $user_id, $selected_movie_id, "Booked cinema ticket via Cinema page");

                $conn->commit();
                // Enhanced success message with booking details
                $message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4' role='alert'>" .
                           "<p class='font-bold mb-2'>Booking successful!</p>" .
                           "<p><strong>Booking ID:</strong> {$booking_id}</p>" .
                           "<p><strong>Movie:</strong> " . htmlspecialchars($movie_details['title']) . "</p>" .
                           "<p><strong>Cinema:</strong> " . htmlspecialchars($cinema_details['cinema_name']) . " (" . htmlspecialchars($cinema_details['type_name']) . ")</p>" .
                           "<p><strong>Show Time:</strong> " . htmlspecialchars($show_time) . "</p>" .
                           "<p><strong>Seat Number:</strong> " . htmlspecialchars($seat_number) . "</p>" .
                           "<p><strong>Total Price:</strong> $" . htmlspecialchars(number_format($booking_price, 2)) . "</p>" .
                           "<p><strong>Payment ID:</strong> {$payment_id}</p>" .
                           "</div>";
                // Optionally clear form fields after successful booking
                $_POST = array(); // Clear POST data to prevent re-submission on refresh
            } catch (Exception $e) {
                $conn->rollback();
                $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Booking failed: " . $e->getMessage() . "</div>";
            }
        }
    }
}

// Function to record user activity for recommendations (copied from viewer.php for consistency)
function recordUserActivity($conn, $user_id, $movie_id, $reason) {
    $stmt = $conn->prepare("INSERT INTO recommendations (user_id, movie_id, reason) VALUES (?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("iis", $user_id, $movie_id, $reason);
        $stmt->execute();
        $stmt->close();
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
    <title>Book Cinema Ticket - Movies Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f2f5;
        }
    </style>
</head>
<body class="min-h-screen bg-gray-100 flex items-center justify-center p-4">
    <div class="bg-white p-8 rounded-lg shadow-xl w-full max-w-lg">
        <h2 class="text-3xl font-bold text-center text-gray-800 mb-6">Book Your Ticket</h2>

        <?php echo $message; // Display booking messages here ?>

        <?php if ($movie_details && $cinema_details): ?>
            <div class="mb-6 border-b pb-4">
                <h3 class="text-xl font-semibold text-gray-800 mb-2">Movie: <?php echo htmlspecialchars($movie_details['title']); ?></h3>
                <p class="text-gray-700 text-sm">Genre: <?php echo htmlspecialchars($movie_details['genre']); ?></p>
                <p class="text-gray-700 text-sm">Duration: <?php echo htmlspecialchars($movie_details['duration']); ?> mins</p>
                <p class="text-gray-700 text-sm mt-2">
                    <strong class="text-gray-900">At Cinema:</strong> <?php echo htmlspecialchars($cinema_details['cinema_name']); ?>
                </p>
                <p class="text-gray-700 text-sm">
                    <strong class="text-gray-900">Location:</strong> <?php echo htmlspecialchars($cinema_details['location']); ?>
                </p>
                <p class="text-gray-700 text-sm">
                    <strong class="text-gray-900">Cinema Type:</strong> <?php echo htmlspecialchars($cinema_details['type_name']); ?>
                </p>
                <p class="text-gray-700 text-lg mt-3">
                    <strong class="text-gray-900">Price:</strong> <span class="font-bold text-indigo-600 text-2xl">$<?php echo htmlspecialchars(number_format($cinema_details['price'], 2)); ?></span>
                </p>
            </div>

            <form action="booking.php?cinema_id=<?php echo htmlspecialchars($cinema_id); ?>&movie_id=<?php echo htmlspecialchars($movie_id); ?>" method="POST" class="space-y-5">
                <input type="hidden" name="action" value="confirm_booking">
                <input type="hidden" name="movie_id" value="<?php echo htmlspecialchars($movie_id); ?>">
                <input type="hidden" name="cinema_id" value="<?php echo htmlspecialchars($cinema_id); ?>">

                <div>
                    <label for="show_time" class="block text-sm font-medium text-gray-700">Select Show Time:</label>
                    <input type="time" id="show_time" name="show_time" required
                           class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>

                <div>
                    <label for="seat_number" class="block text-sm font-medium text-gray-700">Enter Seat Number (e.g., A1, B5):</label>
                    <input type="text" id="seat_number" name="seat_number" required
                           class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>

                <button type="submit"
                        class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Confirm Booking
                </button>
            </form>
        <?php else: ?>
            <p class="text-red-700 text-center">Could not load movie or cinema details for booking. Please go back and select again.</p>
            <div class="mt-6 text-center">
                <a href="cinema.php" class="font-medium text-indigo-600 hover:text-indigo-500">Back to Cinemas</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
