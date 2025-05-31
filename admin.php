<?php
session_start();
require_once "db_connection.php";
include 'header.php'; 

// Check if the user is an admin
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    die("Please <a href='login.php'>login</a> first.");
}

$checkAdmin = $conn->prepare("SELECT * FROM admin WHERE user_id = ?");
$checkAdmin->bind_param("i", $user_id);
$checkAdmin->execute();
$result = $checkAdmin->get_result();

if ($result->num_rows === 0) {
    die("Access denied. You are not an admin.");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #eef; }
        h1 { margin-bottom: 10px; }
        section { margin-bottom: 30px; }
        table { width: 100%; border-collapse: collapse; background: #fff; }
        th, td { padding: 10px; border: 1px solid #ccc; }
        a, button { text-decoration: none; padding: 5px 10px; background: #007BFF; color: #fff; border: none; border-radius: 4px; cursor: pointer; }
        a:hover, button:hover { background: #0056b3; }
    </style>
</head>
<body>

<h1>Admin Dashboard</h1>
<p>Welcome, Admin #<?= htmlspecialchars($user_id) ?></p>

<!-- MOVIES SECTION -->
<section>
    <h2>Manage Movies</h2>
    <a href="add_movie.php">Add Movie</a>
    <table>
        <tr><th>ID</th><th>Title</th><th>Genre</th><th>Actions</th></tr>
        <?php
        $res = $conn->query("SELECT * FROM movies");
        while ($row = $res->fetch_assoc()):
        ?>
        <tr>
            <td><?= $row['movie_id'] ?></td>
            <td><?= htmlspecialchars($row['title']) ?></td>
            <td><?= htmlspecialchars($row['genre']) ?></td>
            <td>
                <a href="delete_movie.php?id=<?= $row['movie_id'] ?>" onclick="return confirm('Delete movie?')">Delete</a>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
</section>

<!-- CINEMAS SECTION -->
<section>
    <h2>Manage Cinemas</h2>
    <a href="add_cinema.php">Add Cinema</a>
    <table>
        <tr><th>ID</th><th>Name</th><th>Location</th><th>Actions</th></tr>
        <?php
        $res = $conn->query("SELECT * FROM cinema");
        while ($row = $res->fetch_assoc()):
        ?>
        <tr>
            <td><?= $row['cinema_id'] ?></td>
            <td><?= htmlspecialchars($row['name']) ?></td>
            <td><?= htmlspecialchars($row['location']) ?></td>
            <td>
                <a href="delete_cinema.php?id=<?= $row['cinema_id'] ?>" onclick="return confirm('Delete cinema?')">Delete</a>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
</section>

<!-- STREAMING PLATFORMS SECTION -->
<section>
    <h2>Streaming Platforms</h2>
    <a href="add_platform.php">Add Platform</a>
    <table>
        <tr><th>ID</th><th>Name</th><th>Website</th><th>Actions</th></tr>
        <?php
        $res = $conn->query("SELECT * FROM streaming_platform");
        while ($row = $res->fetch_assoc()):
        ?>
        <tr>
            <td><?= $row['platform_id'] ?></td>
            <td><?= htmlspecialchars($row['platform_name']) ?></td>
            <td><?= htmlspecialchars($row['website']) ?></td>
            <td>
                <a href="delete_platform.php?id=<?= $row['platform_id'] ?>" onclick="return confirm('Delete platform?')">Delete</a>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
</section>

<!-- VIEWERS SECTION -->
<section>
    <h2>Viewers</h2>
    <a href="add_viewer.php">Add Viewer</a>
    <table>
        <tr><th>ID</th><th>Name</th><th>Email</th><th>Actions</th></tr>
        <?php
        $res = $conn->query("SELECT u.user_id, u.name, u.email FROM user u INNER JOIN viewer v ON u.user_id = v.user_id");
        while ($row = $res->fetch_assoc()):
        ?>
        <tr>
            <td><?= $row['user_id'] ?></td>
            <td><?= htmlspecialchars($row['name']) ?></td>
            <td><?= htmlspecialchars($row['email']) ?></td>
            <td>
                <a href="delete_viewer.php?id=<?= $row['user_id'] ?>" onclick="return confirm('Delete viewer?')">Delete</a>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
</section>

<!-- ADMINS (EXCEPT SELF) -->
<section>
    <h2>Other Admins</h2>
    <table>
        <tr><th>ID</th><th>Name</th><th>Email</th></tr>
        <?php
        $stmt = $conn->prepare("SELECT u.user_id, u.name, u.email FROM user u INNER JOIN admin a ON u.user_id = a.user_id WHERE u.user_id != ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()):
        ?>
        <tr>
            <td><?= $row['user_id'] ?></td>
            <td><?= htmlspecialchars($row['name']) ?></td>
            <td><?= htmlspecialchars($row['email']) ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
</section>

<!-- VIEWER ACTIVITY -->
<section>
    <h2>Viewer Activity (Bookings & Subscriptions)</h2>
    <h4>Bookings</h4>
    <table>
        <tr><th>Viewer</th><th>Movie</th><th>Cinema</th><th>Date</th><th>Show Time</th></tr>
        <?php
        $query = "SELECT u.name, m.title, c.name AS cinema, b.booking_date, b.show_time 
                  FROM booking b
                  JOIN user u ON b.user_id = u.user_id
                  JOIN movies m ON b.movie_id = m.movie_id
                  JOIN cinema c ON b.cinema_id = c.cinema_id";
        $res = $conn->query($query);
        while ($row = $res->fetch_assoc()):
        ?>
        <tr>
            <td><?= htmlspecialchars($row['name']) ?></td>
            <td><?= htmlspecialchars($row['title']) ?></td>
            <td><?= htmlspecialchars($row['cinema']) ?></td>
            <td><?= $row['booking_date'] ?></td>
            <td><?= $row['show_time'] ?></td>
        </tr>
        <?php endwhile; ?>
    </table>

    <h4>Streaming Subscriptions</h4>
    <table>
        <tr><th>Viewer</th><th>Platform</th><th>Start</th><th>End</th><th>Plan</th></tr>
        <?php
        $query = "SELECT u.name, p.platform_name, s.start_date, s.end_date, s.plan_type
                  FROM subscription s
                  JOIN user u ON s.user_id = u.user_id
                  JOIN streaming_platform p ON s.platform_id = p.platform_id";
        $res = $conn->query($query);
        while ($row = $res->fetch_assoc()):
        ?>
        <tr>
            <td><?= htmlspecialchars($row['name']) ?></td>
            <td><?= htmlspecialchars($row['platform_name']) ?></td>
            <td><?= $row['start_date'] ?></td>
            <td><?= $row['end_date'] ?></td>
            <td><?= htmlspecialchars($row['plan_type']) ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
</section>

</body>
</html>
