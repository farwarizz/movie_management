<?php
/**
 * book_movie.php
 * This file handles the movie booking process for viewers.
 * It takes movie_id and cinema_id, allows selecting showtime and seat,
 * and records the booking in the database.
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

$movie_id = isset($_GET['movie_id']) ? (int)$_GET['movie_id'] : 0;
$cinema_id = isset($_GET['cinema_id']) ? (int)$_GET['cinema_id'] : 0;

$movie_title = '';
$cinema_name = '';
$cinema_location = '';
$ticket_price = 0; // This will be fetched from cinema_type

if ($movie_id <= 0 || $cinema_id <= 0) {
    $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Invalid movie or cinema selected.</div>";
} else {
    // Fetch movie and cinema details
    $stmt_details = $conn->prepare("
        SELECT m.title AS movie_title, c.name AS cinema_name, c.location, ct.price AS ticket_price
        FROM movies m, cinema c JOIN cinema_type ct ON c.type_id = ct.type_id
        WHERE m.movie_id = ? AND c.cinema_id = ?
    ");
    if ($stmt_details) {
        $stmt_details->bind_param("ii", $movie_id, $cinema_id);
        $stmt_details->execute();
        $result_details = $stmt_details->get_result();
        if ($row_details = $result_details->fetch_assoc()) {
            $movie_title = htmlspecialchars($row_details['movie_title']);
            $cinema_name = htmlspecialchars($row_details['cinema_name']);
            $cinema_location = htmlspecialchars($row_details['location']);
            $ticket_price = htmlspecialchars($row_details['ticket_price']);
        } else {
            $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Movie or Cinema not found.</div>";
            // Invalidate IDs if not found to prevent booking
            $movie_id = 0;
            $cinema_id = 0;
        }
        $stmt_details->close();
    } else {
        $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Database error fetching movie/cinema details: " . $conn->error . "</div>";
    }
}

// Handle booking submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'book_movie') {
    if ($movie_id > 0 && $cinema_id > 0) {
        $booking_date = $_POST['booking_date'];
        $show_time = $_POST['show_time'];
        $seat_number = $_POST['seat_number'];

        // Basic validation
        if (empty($booking_date) || empty($show_time) || empty($seat_number)) {
            $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>All booking fields are required.</div>";
        } else {
            $stmt_book = $conn->prepare("INSERT INTO booking (user_id, movie_id, cinema_id, booking_date, show_time, seat_number) VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmt_book) {
                $stmt_book->bind_param("iissss", $user_id, $movie_id, $cinema_id, $booking_date, $show_time, $seat_number);
                if ($stmt_book->execute()) {
                    // Redirect to viewer dashboard with success message
                    header("Location: viewer.php?tab=my_bookings&message=" . urlencode("Movie booked successfully!"));
                    exit();
                } else {
                    $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Error booking movie: " . $stmt_book->error . "</div>";
                }
                $stmt_book->close();
            } else {
                $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Database error preparing booking: " . $conn->error . "</div>";
            }
        }
    } else {
        $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Cannot book: Invalid movie or cinema selected.</div>";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Movie: <?php echo $movie_title; ?></title>
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
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #4a5568;
        }
        .form-group input[type="date"],
        .form-group input[type="time"],
        .form-group input[type="text"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            box-sizing: border-box;
        }
        .btn {
            padding: 10px 20px;
            border-radius: 5px;
            font-weight: 600;
            text-decoration: none;
            transition: background-color 0.2s ease-in-out;
            display: inline-block;
            cursor: pointer;
        }
        .btn-green {
            background-color: #22c55e; /* green-500 */
            color: white;
            border: none;
        }
        .btn-green:hover {
            background-color: #16a34a; /* green-600 */
        }
        .btn-secondary {
            background-color: #6b7280; /* gray-500 */
            color: white;
            border: none;
        }
        .btn-secondary:hover {
            background-color: #4b5563; /* gray-600 */
        }
    </style>
</head>
<body class="min-h-screen bg-gray-100 flex flex-col">
    <nav class="bg-blue-700 p-4 shadow-md">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-white text-2xl font-bold">Book Movie: <?php echo $movie_title; ?></h1>
            <div class="flex items-center space-x-4">
                <a href="viewer.php" class="bg-white text-blue-700 px-4 py-2 rounded-md font-semibold hover:bg-blue-100">Back to Dashboard</a>
                <a href="logout.php" class="bg-white text-blue-700 px-4 py-2 rounded-md font-semibold hover:bg-blue-100">Logout</a>
            </div>
        </div>
    </nav>

    <main class="flex-grow container mx-auto p-6">
        <?php echo $message; // Display messages ?>

        <?php if ($movie_id > 0 && $cinema_id > 0): ?>
            <div class="container">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Booking Details</h2>
                <p class="mb-2"><span class="font-semibold">Movie:</span> <?php echo $movie_title; ?></p>
                <p class="mb-2"><span class="font-semibold">Cinema:</span> <?php echo $cinema_name; ?> (<?php echo $cinema_location; ?>)</p>
                <p class="mb-4"><span class="font-semibold">Estimated Ticket Price:</span> PKR <?php echo number_format($ticket_price, 2); ?></p>

                <form action="book_movie.php?movie_id=<?php echo htmlspecialchars($movie_id); ?>&cinema_id=<?php echo htmlspecialchars($cinema_id); ?>" method="POST">
                    <input type="hidden" name="action" value="book_movie">

                    <div class="form-group">
                        <label for="booking_date">Booking Date:</label>
                        <input type="date" id="booking_date" name="booking_date" required min="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <div class="form-group">
                        <label for="show_time">Show Time:</label>
                        <input type="time" id="show_time" name="show_time" required>
                    </div>

                    <div class="form-group">
                        <label for="seat_number">Seat Number (e.g., A1, B5):</label>
                        <input type="text" id="seat_number" name="seat_number" required placeholder="e.g., A10">
                    </div>

                    <div class="flex space-x-4 mt-6">
                        <button type="submit" class="btn btn-green">Confirm Booking</button>
                        <a href="viewer_movies_by_cinema.php?cinema_id=<?php echo htmlspecialchars($cinema_id); ?>" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <p class="text-gray-600 text-center">Cannot proceed with booking due to missing movie or cinema information.</p>
        <?php endif; ?>
    </main>

    <footer class="bg-gray-800 text-white text-center p-4 mt-auto">
        <div class="container mx-auto">
            <p>&copy; <?php echo date('Y'); ?> MovieVerse. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>