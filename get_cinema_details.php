<?php
/**
 * get_cinema_details.php
 * This file provides cinema details for a given movie_id via AJAX.
 * It fetches cinemas where the movie is playing, including cinema type and price.
 * Returns JSON data.
 */

require_once 'db_connect.php'; // Include your database connection

header('Content-Type: application/json'); // Set header for JSON response

$movie_id = isset($_GET['movie_id']) ? (int)$_GET['movie_id'] : 0;

$cinemas = [];

if ($movie_id > 0) {
    // Fetch cinemas where this movie is available, including their type and price
    $stmt = $conn->prepare("SELECT DISTINCT c.cinema_id, c.name, ct.type_name, ct.price
                            FROM cinema c
                            JOIN cinema_type ct ON c.type_id = ct.type_id
                            JOIN booking b ON c.cinema_id = b.cinema_id
                            WHERE b.movie_id = ?"); // Assuming a movie must have a booking to be "available" in a cinema
    if ($stmt) {
        $stmt->bind_param("i", $movie_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $cinemas[] = $row;
        }
        $stmt->close();
    }
}

echo json_encode(['cinemas' => $cinemas]);

$conn->close();
?>
