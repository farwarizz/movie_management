<?php
session_start();
require_once "db_connection.php";
<?php include 'header.php'; ?>


// Check if viewer is logged in
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    die("Please <a href='login.php'>login</a> to view movies.");
}

// Fetch user preference
$prefStmt = $conn->prepare("SELECT preference FROM user WHERE user_id = ?");
$prefStmt->bind_param("i", $user_id);
$prefStmt->execute();
$prefResult = $prefStmt->get_result();
$pref = $prefResult->fetch_assoc()['preference'];

?>

<!DOCTYPE html>
<html>
<head>
    <title>All Movies</title>
    <style>
        body { font-family: Arial; background-color: #f0f0f0; padding: 20px; }
        table { width: 100%; border-collapse: collapse; background-color: #fff; }
        th, td { padding: 12px; border: 1px solid #ccc; text-align: left; }
        h1 { margin-bottom: 20px; }
        a.button { padding: 8px 12px; background-color: #007BFF; color: white; text-decoration: none; border-radius: 4px; }
        a.button:hover { background-color: #0056b3; }
    </style>
</head>
<body>

<h1>Available Movies</h1>
<p>Viewing as: <strong><?= ucfirst($pref) ?></strong> preference</p>

<table>
    <tr>
        <th>Title</th>
        <th>Genre</th>
        <th>Release Date</th>
        <th>Language</th>
        <th>Duration</th>
        <th>Rating</th>
        <th>Action</th>
    </tr>
    <?php
    $movies = $conn->query("SELECT * FROM movies ORDER BY release_date DESC");
    while ($movie = $movies->fetch_assoc()):
    ?>
    <tr>
        <td><?= htmlspecialchars($movie['title']) ?></td>
        <td><?= htmlspecialchars($movie['genre']) ?></td>
        <td><?= $movie['release_date'] ?></td>
        <td><?= htmlspecialchars($movie['language']) ?></td>
        <td><?= $movie['duration'] ?> mins</td>
        <td><?= $movie['rating'] ?>/10</td>
        <td>
            <a class="button" href="movie_details.php?movie_id=<?= $movie['movie_id'] ?>">View Options</a>
        </td>
    </tr>
    <?php endwhile; ?>
</table>

</body>
</html>
