<?php
session_start();
include 'db_connect.php'; // Include your database connection file

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
// Fetch user's name and preference from the 'user' table
$user_name = 'Guest'; // Default value
$preference = '';

$stmt = $conn->prepare("SELECT name, preference FROM user WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_name, $preference);
$stmt->fetch();
$stmt->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Viewer Dashboard - Movies Management System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .navbar {
            background-color: #343a40 !important;
        }
        .navbar-brand, .nav-link {
            color: #ffffff !important;
        }
        .container {
            margin-top: 20px;
        }
        .section-header {
            background-color: #e9ecef;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 5px solid #007bff;
        }
        .card {
            margin-bottom: 20px;
        }
        .table-responsive {
            margin-top: 20px;
        }
        .welcome-message {
            font-size: 1.25rem;
            font-weight: 500;
            color: #007bff;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <a class="navbar-brand" href="viewer.php">MovieVerse Viewer</a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav mr-auto">
                <li class="nav-item active">
                    <a class="nav-link" href="viewer.php">Dashboard <span class="sr-only">(current)</span></a>
                </li>
                <?php if ($preference == 'cinema' || $preference == 'both'): ?>
                <li class="nav-item">
                    <a class="nav-link" href="#cinema-listings">Cinema Listings</a>
                </li>
                <?php endif; ?>
                <?php if ($preference == 'streaming' || $preference == 'both'): ?>
                <li class="nav-item">
                    <a class="nav-link" href="#streaming-platforms">Streaming Platforms</a>
                </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a class="nav-link" href="#recommendations">Recommendations</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#my-bookings">My Bookings</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#my-subscriptions">My Subscriptions</a>
                </li>
            </ul>
            <ul class="navbar-nav ml-auto">
                <li class="nav-item">
                    <span class="navbar-text">Welcome, <?php echo htmlspecialchars($user_name); ?>!</span>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="logout.php">Logout</a>
                </li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <h2 class="my-4">Viewer Dashboard</h2>
        <p class="welcome-message">Hello, <?php echo htmlspecialchars($user_name); ?>! Here's what's happening in MovieVerse today.</p>

        <?php if ($preference == 'cinema' || $preference == 'both'): ?>
        ---
        <div id="cinema-listings" class="section-header">
            <h3>Cinema Listings</h3>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Cinema Name</th>
                        <th>Location</th>
                        <th>Type</th>
                        <th>Standard Price</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql_cinemas = "SELECT c.cinema_id, c.name, c.location, ct.type_name, ct.price 
                                    FROM cinema c JOIN cinema_type ct ON c.type_id = ct.type_id";
                    $result_cinemas = $conn->query($sql_cinemas);

                    if ($result_cinemas->num_rows > 0) {
                        while ($row_cinema = $result_cinemas->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($row_cinema['name']) . "</td>";
                            echo "<td>" . htmlspecialchars($row_cinema['location']) . "</td>";
                            echo "<td>" . htmlspecialchars($row_cinema['type_name']) . "</td>";
                            echo "<td>PKR " . number_format($row_cinema['price'], 2) . "</td>";
                            // Link to a page for booking at this specific cinema
                            echo "<td><a href='book_cinema_tickets.php?cinema_id=" . $row_cinema['cinema_id'] . "' class='btn btn-sm btn-primary'>View Movies & Book</a></td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5'>No cinema listings available.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if ($preference == 'streaming' || $preference == 'both'): ?>
        ---
        <div id="streaming-platforms" class="section-header">
            <h3>Streaming Platforms</h3>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Platform Name</th>
                        <th>Website (External)</th>
                        <th>View Movies on Platform</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql_platforms = "SELECT platform_id, platform_name, website FROM streaming_platform";
                    $result_platforms = $conn->query($sql_platforms);

                    if ($result_platforms->num_rows > 0) {
                        while ($row_platform = $result_platforms->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($row_platform['platform_name']) . "</td>";
                            echo "<td>";
                            if (!empty($row_platform['website'])) {
                                echo "<a href='" . htmlspecialchars($row_platform['website']) . "' target='_blank' class='btn btn-sm btn-info'>Visit Site</a>";
                            } else {
                                echo "N/A";
                            }
                            echo "</td>";
                            // *** THIS IS THE MODIFIED LINK ***
                            echo "<td><a href='view_platform_movies.php?platform_id=" . $row_platform['platform_id'] . "' class='btn btn-sm btn-success'>View Movies</a></td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='3'>No streaming platforms available.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        ---
        <div id="recommendations" class="section-header">
            <h3>Your Recommendations</h3>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Movie Title</th>
                        <th>Genre</th>
                        <th>Release Date</th>
                        <th>Language</th>
                        <th>Reason</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql_recommendations = "SELECT r.recommendation_id, m.title, m.genre, m.release_date, m.language, r.reason 
                                            FROM recommendations r JOIN movies m ON r.movie_id = m.movie_id 
                                            WHERE r.user_id = ?";
                    $stmt_recommendations = $conn->prepare($sql_recommendations);
                    $stmt_recommendations->bind_param("i", $user_id);
                    $stmt_recommendations->execute();
                    $result_recommendations = $stmt_recommendations->get_result();

                    if ($result_recommendations->num_rows > 0) {
                        while ($row_recommendation = $result_recommendations->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($row_recommendation['title']) . "</td>";
                            echo "<td>" . htmlspecialchars($row_recommendation['genre']) . "</td>";
                            echo "<td>" . htmlspecialchars($row_recommendation['release_date']) . "</td>";
                            echo "<td>" . htmlspecialchars($row_recommendation['language']) . "</td>";
                            echo "<td>" . htmlspecialchars($row_recommendation['reason']) . "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5'>No recommendations for you yet.</td></tr>";
                    }
                    $stmt_recommendations->close();
                    ?>
                </tbody>
            </table>
        </div>

        ---
        <div id="my-bookings" class="section-header">
            <h3>My Cinema Bookings</h3>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Movie</th>
                        <th>Cinema</th>
                        <th>Location</th>
                        <th>Type</th>
                        <th>Booking Date</th>
                        <th>Show Time</th>
                        <th>Seat Number</th>
                        <th>Estimated Price</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Adjusted query for your booking schema, joining to get cinema type price
                    $sql_bookings = "SELECT b.booking_id, m.title AS movie_title, c.name AS cinema_name, 
                                            c.location, ct.type_name, ct.price AS cinema_ticket_price,
                                            b.booking_date, b.show_time, b.seat_number
                                    FROM booking b
                                    JOIN movies m ON b.movie_id = m.movie_id
                                    JOIN cinema c ON b.cinema_id = c.cinema_id
                                    JOIN cinema_type ct ON c.type_id = ct.type_id
                                    WHERE b.user_id = ?
                                    ORDER BY b.booking_date DESC, b.show_time DESC";
                    $stmt_bookings = $conn->prepare($sql_bookings);
                    $stmt_bookings->bind_param("i", $user_id);
                    $stmt_bookings->execute();
                    $result_bookings = $stmt_bookings->get_result();

                    if ($result_bookings->num_rows > 0) {
                        while ($row_booking = $result_bookings->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($row_booking['movie_title']) . "</td>";
                            echo "<td>" . htmlspecialchars($row_booking['cinema_name']) . "</td>";
                            echo "<td>" . htmlspecialchars($row_booking['location']) . "</td>";
                            echo "<td>" . htmlspecialchars($row_booking['type_name']) . "</td>";
                            echo "<td>" . htmlspecialchars($row_booking['booking_date']) . "</td>";
                            echo "<td>" . htmlspecialchars($row_booking['show_time']) . "</td>";
                            echo "<td>" . htmlspecialchars($row_booking['seat_number']) . "</td>";
                            echo "<td>PKR " . number_format($row_booking['cinema_ticket_price'], 2) . "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='8'>No cinema bookings found.</td></tr>";
                    }
                    $stmt_bookings->close();
                    ?>
                </tbody>
            </table>
        </div>

        ---
        <div id="my-subscriptions" class="section-header">
            <h3>My Streaming Subscriptions</h3>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Platform</th>
                        <th>Plan Type</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql_subscriptions = "SELECT s.plan_type, s.start_date, s.end_date, sp.platform_name 
                                        FROM subscription s JOIN streaming_platform sp ON s.platform_id = sp.platform_id 
                                        WHERE s.user_id = ?
                                        ORDER BY s.end_date DESC";
                    $stmt_subscriptions = $conn->prepare($sql_subscriptions);
                    $stmt_subscriptions->bind_param("i", $user_id);
                    $stmt_subscriptions->execute();
                    $result_subscriptions = $stmt_subscriptions->get_result();

                    if ($result_subscriptions->num_rows > 0) {
                        while ($row_subscription = $result_subscriptions->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($row_subscription['platform_name']) . "</td>";
                            echo "<td>" . htmlspecialchars($row_subscription['plan_type']) . "</td>";
                            echo "<td>" . htmlspecialchars($row_subscription['start_date']) . "</td>";
                            echo "<td>" . htmlspecialchars($row_subscription['end_date']) . "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='4'>No active subscriptions found.</td></tr>";
                    }
                    $stmt_subscriptions->close();
                    $conn->close();
                    ?>
                </tbody>
            </table>
        </div>

    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>