<?php
require_once "db_connection.php";
include 'header.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "Please login to book a movie.";
    exit;
}

$user_id = $_SESSION['user_id'];
$movie_id = $_GET['movie_id'] ?? null;
$cinema_id = $_GET['cinema_id'] ?? null;

if (!$movie_id || !$cinema_id) {
    echo "Invalid booking request.";
    exit;
}

// Fetch movie title
$movieStmt = $conn->prepare("SELECT title FROM movies WHERE movie_id = ?");
$movieStmt->bind_param("i", $movie_id);
$movieStmt->execute();
$movieResult = $movieStmt->get_result();
$movie = $movieResult->fetch_assoc();

// Fetch cinema details + type
$cinemaStmt = $conn->prepare("
    SELECT c.name, c.location, ct.type_name, ct.price 
    FROM cinema c 
    JOIN cinema_type ct ON c.type_id = ct.type_id 
    WHERE c.cinema_id = ?
");
$cinemaStmt->bind_param("i", $cinema_id);
$cinemaStmt->execute();
$cinemaResult = $cinemaStmt->get_result();
$cinema = $cinemaResult->fetch_assoc();

$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $show_date = $_POST['show_date'];
    $show_time = $_POST['show_time'];
    $seat_number = $_POST['seat_number'];

    $insert = $conn->prepare("
        INSERT INTO booking (user_id, cinema_id, movie_id, booking_date, show_time, seat_number)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $insert->bind_param("iiisss", $user_id, $cinema_id, $movie_id, $show_date, $show_time, $seat_number);

    if ($insert->execute()) {
        $success = "✅ Booking confirmed for " . htmlspecialchars($movie['title']) . "!";
    } else {
        $success = "❌ Booking failed: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Book Movie</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f7f7f7; padding: 30px; }
        .card { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); max-width: 600px; margin: auto; }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: bold; }
        input, select { width: 100%; padding: 8px; margin-top: 5px; }
        button { background: #007BFF; color: white; padding: 10px 15px; border: none; border-radius: 5px; cursor: pointer; }
        button:hover { background: #0056b3; }
        .success { background: #d4edda; color: #155724; padding: 10px; margin-bottom: 20px; border-radius: 5px; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; margin-bottom: 20px; border-radius: 5px; }
    </style>
</head>
<body>

<div class="card">
    <h2>Book Your Movie</h2>

    <?php if ($success): ?>
        <div class="<?= strpos($success, '✅') !== false ? 'success' : 'error' ?>">
            <?= $success ?>
        </div>
    <?php endif; ?>

    <p><strong>Movie:</strong> <?= htmlspecialchars($movie['title']) ?></p>
    <p><strong>Cinema:</strong> <?= htmlspecialchars($cinema['name']) ?> (<?= htmlspecialchars($cinema['location']) ?>)</p>
    <p><strong>Cinema Type:</strong> <?= $cinema['type_name'] ?> — $<?= number_format($cinema['price'], 2) ?></p>

    <form method="POST">
        <div class="form-group">
            <label for="show_date">Show Date</label>
            <input type="date" id="show_date" name="show_date" required>
        </div>

        <div class="form-group">
            <label for="show_time">Show Time</label>
            <input type="time" id="show_time" name="show_time" required>
        </div>

        <div class="form-group">
            <label for="seat_number">Seat Number</label>
            <input type="text" id="seat_number" name="seat_number" placeholder="e.g. A12" required>
        </div>

        <button type="submit">Confirm Booking</button>
    </form>
</div>

</body>
</html>
