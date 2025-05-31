<?php
<?php include 'header.php'; ?>
session_start();
require_once "db_connection.php";

// Simulated login (for testing, set session manually)
if (!isset($_SESSION['user_id'])) {
    die("Access denied. Please <a href='login.php'>log in</a> first.");
}

$user_id = $_SESSION['user_id'];

// Get viewer's preference
$pref_query = "SELECT preference FROM user WHERE user_id = ?";
$stmt = $conn->prepare($pref_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$pref_result = $stmt->get_result();
$preference = $pref_result->fetch_assoc()['preference'];

// Fetch all movies based on viewer preference
$movies = [];
if ($preference === "cinema" || $preference === "both") {
    $cinema_movies_query = "SELECT DISTINCT m.movie_id, m.title, m.genre, m.language, m.rating, m.release_date 
                            FROM movies m 
                            JOIN booking b ON m.movie_id = b.movie_id 
                            ORDER BY m.release_date DESC";
    $cinema_movies = $conn->query($cinema_movies_query);
    while ($row = $cinema_movies->fetch_assoc()) {
        $row['available_in'] = 'Cinema';
        $movies[] = $row;
    }
}
if ($preference === "streaming" || $preference === "both") {
    $streaming_movies_query = "SELECT DISTINCT m.movie_id, m.title, m.genre, m.language, m.rating, m.release_date 
                               FROM movies m 
                               JOIN streaming_services s ON m.movie_id = s.movie_id 
                               ORDER BY m.release_date DESC";
    $streaming_movies = $conn->query($streaming_movies_query);
    while ($row = $streaming_movies->fetch_assoc()) {
        $row['available_in'] = 'Streaming';
        $movies[] = $row;
    }
}

// Recommendation based on user's watched genres
$recommendations = [];
$genre_query = "SELECT DISTINCT m.genre FROM movies m
                JOIN booking b ON m.movie_id = b.movie_id
                WHERE b.user_id = ?";
$stmt = $conn->prepare($genre_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$genre_result = $stmt->get_result();
$genres = [];
while ($row = $genre_result->fetch_assoc()) {
    $genres[] = $row['genre'];
}
if (!empty($genres)) {
    $placeholders = implode(',', array_fill(0, count($genres), '?'));
    $types = str_repeat('s', count($genres));
    $rec_query = "SELECT * FROM movies WHERE genre IN ($placeholders) AND movie_id NOT IN 
                    (SELECT movie_id FROM booking WHERE user_id = ?)";
    $stmt = $conn->prepare($rec_query);
    $stmt->bind_param($types . "i", ...$genres, $user_id);
    $stmt->execute();
    $rec_result = $stmt->get_result();
    while ($row = $rec_result->fetch_assoc()) {
        $recommendations[] = $row;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Viewer Dashboard</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f9f9f9; }
        h2 { margin-top: 40px; }
        table { border-collapse: collapse; width: 100%; margin-top: 10px; }
        th, td { padding: 10px; border: 1px solid #ccc; text-align: left; }
        select, input, button { padding: 8px; margin: 5px 0; }
    </style>
</head>
<body>

<h1>Welcome Viewer!</h1>
<p>Your preference: <strong><?= htmlspecialchars($preference) ?></strong></p>

<h2>Available Movies</h2>
<table>
    <tr>
        <th>Title</th>
        <th>Genre</th>
        <th>Language</th>
        <th>Rating</th>
        <th>Available In</th>
        <th>Action</th>
    </tr>
    <?php foreach ($movies as $movie): ?>
    <tr>
        <td><?= htmlspecialchars($movie['title']) ?></td>
        <td><?= htmlspecialchars($movie['genre']) ?></td>
        <td><?= htmlspecialchars($movie['language']) ?></td>
        <td><?= htmlspecialchars($movie['rating']) ?></td>
        <td><?= htmlspecialchars($movie['available_in']) ?></td>
        <td>
            <form method="GET" action="movie_action.php">
                <input type="hidden" name="movie_id" value="<?= $movie['movie_id'] ?>">
                <input type="hidden" name="source" value="<?= $movie['available_in'] ?>">
                <button type="submit">Watch</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
</table>

<?php if (!empty($recommendations)): ?>
<h2>Recommended for You</h2>
<table>
    <tr>
        <th>Title</th>
        <th>Genre</th>
        <th>Language</th>
        <th>Rating</th>
    </tr>
    <?php foreach ($recommendations as $rec): ?>
    <tr>
        <td><?= htmlspecialchars($rec['title']) ?></td>
        <td><?= htmlspecialchars($rec['genre']) ?></td>
        <td><?= htmlspecialchars($rec['language']) ?></td>
        <td><?= htmlspecialchars($rec['rating']) ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>
<nav>
    <a href="movies.php">Movies</a> |
    <a href="cinema.php">Cinemas</a> |
    <a href="streaming_platform.php">Streaming</a> |
    <a href="recommendations.php">Recommendations</a>
</nav>


</body>
</html>
