<?php
/**
 * get_streaming_details.php
 * This file provides streaming platform details for a given movie_id via AJAX.
 * It fetches platforms where the movie is available and their respective prices for different qualities.
 * Returns JSON data.
 */

require_once 'db_connect.php'; // Include your database connection

header('Content-Type: application/json'); // Set header for JSON response

$movie_id = isset($_GET['movie_id']) ? (int)$_GET['movie_id'] : 0;

$platforms = [];

if ($movie_id > 0) {
    // Fetch streaming platforms where this movie is available, including prices
    $stmt = $conn->prepare("SELECT sp.platform_id, sp.platform_name, ss.price_720p, ss.price_1080p, ss.price_4k
                            FROM streaming_services ss
                            JOIN streaming_platform sp ON ss.platform_id = sp.platform_id
                            WHERE ss.movie_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $movie_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $platforms[] = $row;
        }
        $stmt->close();
    }
}

echo json_encode(['platforms' => $platforms]);

$conn->close();
?>
