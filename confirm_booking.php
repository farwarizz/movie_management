<?php
include 'db_connection.php';
include 'header.php';


if (!isset($_GET['booking_id'])) {
    header("Location: booking.php");
    exit();
}

$booking_id = intval($_GET['booking_id']);
$user_id = $_SESSION['user_id'];

// Verify booking belongs to user
$sql = "SELECT b.*, m.title AS movie_title, c.name AS cinema_name FROM booking b
        LEFT JOIN movies m ON b.movie_id = m.movie_id
        LEFT JOIN cinema c ON b.cinema_id = c.cinema_id
        WHERE b.booking_id = $booking_id AND b.user_id = $user_id";
$result = $conn->query($sql);

if ($result->num_rows == 0) {
    die("Booking not found.");
}

$booking = $result->fetch_assoc();

include 'includes/header.php';
?>

<h2>Confirm Booking</h2>
<p><strong>Movie:</strong> <?= htmlspecialchars($booking['movie_title']) ?></p>
<p><strong>Cinema:</strong> <?= htmlspecialchars($booking['cinema_name']) ?></p>
<p><strong>Date:</strong> <?= htmlspecialchars($booking['booking_date']) ?></p>
<p><strong>Show Time:</strong> <?= htmlspecialchars($booking['show_time']) ?></p>
<p><strong>Seat Number:</strong> <?= htmlspecialchars($booking['seat_number']) ?></p>

<form method="post" action="payment.php">
    <input type="hidden" name="booking_id" value="<?= $booking_id ?>">
    <input type="submit" value="Proceed to Payment">
</form>

<?php include 'includes/footer.php'; ?>
