<?php
session_start();
include 'db_connect.php'; // Include your database connection file

// Check if user is logged in and is an Admin
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Verify if the logged-in user is an admin
$stmt_admin_check = $conn->prepare("SELECT user_id FROM admin WHERE user_id = ?");
$stmt_admin_check->bind_param("i", $user_id);
$stmt_admin_check->execute();
$result_admin_check = $stmt_admin_check->get_result();

if ($result_admin_check->num_rows === 0) {
    // Not an admin, redirect to viewer dashboard or deny access
    header("Location: viewer.php"); // Or a more appropriate page
    exit();
}
$stmt_admin_check->close();

// Get admin's name for display
$admin_name = 'Admin';
$stmt_name = $conn->prepare("SELECT name FROM user WHERE user_id = ?");
$stmt_name->bind_param("i", $user_id);
$stmt_name->execute();
$stmt_name->bind_result($admin_name);
$stmt_name->fetch();
$stmt_name->close();

// --- Handle CRUD Operations ---

// Function to sanitize input
function sanitize($conn, $data) {
    return mysqli_real_escape_string($conn, htmlspecialchars(strip_tags(trim($data))));
}

// ----------------------------------------------------
// Handle User Management Actions (Delete, Change Role)
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'delete_user' && isset($_POST['user_id'])) {
        $target_user_id = intval($_POST['user_id']);
        if ($target_user_id == $_SESSION['user_id']) {
            $_SESSION['message'] = "Error: You cannot delete your own admin account.";
            $_SESSION['message_type'] = "danger";
        } else {
            // Delete from user table (cascades to viewer/admin tables)
            $stmt = $conn->prepare("DELETE FROM user WHERE user_id = ?");
            $stmt->bind_param("i", $target_user_id);
            if ($stmt->execute()) {
                $_SESSION['message'] = "User deleted successfully!";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Error deleting user: " . $stmt->error;
                $_SESSION['message_type'] = "danger";
            }
            $stmt->close();
        }
    } elseif ($action === 'change_user_role' && isset($_POST['user_id'], $_POST['current_role'])) {
        $target_user_id = intval($_POST['user_id']);
        $current_role = sanitize($conn, $_POST['current_role']);

        if ($target_user_id == $_SESSION['user_id']) {
            $_SESSION['message'] = "Error: You cannot change your own role through this interface.";
            $_SESSION['message_type'] = "danger";
        } else {
            if ($current_role === 'Admin') {
                // Change to Viewer: Delete from admin table
                $stmt = $conn->prepare("DELETE FROM admin WHERE user_id = ?");
                $stmt->bind_param("i", $target_user_id);
                if ($stmt->execute()) {
                    $_SESSION['message'] = "User role changed to Viewer.";
                    $_SESSION['message_type'] = "success";
                } else {
                    $_SESSION['message'] = "Error changing role: " . $stmt->error;
                    $_SESSION['message_type'] = "danger";
                }
                $stmt->close();
            } elseif ($current_role === 'Viewer') {
                // Change to Admin: Insert into admin table
                $stmt = $conn->prepare("INSERT INTO admin (user_id) VALUES (?)");
                $stmt->bind_param("i", $target_user_id);
                if ($stmt->execute()) {
                    $_SESSION['message'] = "User role changed to Admin.";
                    $_SESSION['message_type'] = "success";
                } else {
                    $_SESSION['message'] = "Error changing role: " . $stmt->error;
                    $_SESSION['message_type'] = "danger";
                }
                $stmt->close();
            }
        }
    }

    // Redirect to prevent form resubmission
    header("Location: admin.php?tab=users");
    exit();
}


// ----------------------------------------------------
// Handle Movie CRUD (Add, Update, Delete)
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['movie_action'])) {
    $movie_action = $_POST['movie_action'];

    if ($movie_action === 'add') {
        $title = sanitize($conn, $_POST['title']);
        $genre = sanitize($conn, $_POST['genre']);
        $release_date = sanitize($conn, $_POST['release_date']);
        $language = sanitize($conn, $_POST['language']);
        $duration = intval($_POST['duration']);
        $rating = floatval($_POST['rating']);

        $stmt = $conn->prepare("INSERT INTO movies (title, genre, release_date, language, duration, rating) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssid", $title, $genre, $release_date, $language, $duration, $rating);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Movie added successfully!";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error adding movie: " . $stmt->error;
            $_SESSION['message_type'] = "danger";
        }
        $stmt->close();
    } elseif ($movie_action === 'update' && isset($_POST['movie_id'])) {
        $movie_id = intval($_POST['movie_id']);
        $title = sanitize($conn, $_POST['title']);
        $genre = sanitize($conn, $_POST['genre']);
        $release_date = sanitize($conn, $_POST['release_date']);
        $language = sanitize($conn, $_POST['language']);
        $duration = intval($_POST['duration']);
        $rating = floatval($_POST['rating']);

        $stmt = $conn->prepare("UPDATE movies SET title=?, genre=?, release_date=?, language=?, duration=?, rating=? WHERE movie_id=?");
        $stmt->bind_param("ssssidi", $title, $genre, $release_date, $language, $duration, $rating, $movie_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Movie updated successfully!";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error updating movie: " . $stmt->error;
            $_SESSION['message_type'] = "danger";
        }
        $stmt->close();
    } elseif ($movie_action === 'delete' && isset($_POST['movie_id'])) {
        $movie_id = intval($_POST['movie_id']);
        $stmt = $conn->prepare("DELETE FROM movies WHERE movie_id = ?");
        $stmt->bind_param("i", $movie_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Movie deleted successfully!";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error deleting movie: " . $stmt->error;
            $_SESSION['message_type'] = "danger";
        }
        $stmt->close();
    }
    header("Location: admin.php?tab=movies");
    exit();
}

// ----------------------------------------------------
// Handle Cinema CRUD (Add, Update, Delete)
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cinema_action'])) {
    $cinema_action = $_POST['cinema_action'];

    if ($cinema_action === 'add') {
        $name = sanitize($conn, $_POST['name']);
        $location = sanitize($conn, $_POST['location']);
        $type_id = intval($_POST['type_id']);

        $stmt = $conn->prepare("INSERT INTO cinema (name, location, type_id) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $name, $location, $type_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Cinema added successfully!";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error adding cinema: " . $stmt->error;
            $_SESSION['message_type'] = "danger";
        }
        $stmt->close();
    } elseif ($cinema_action === 'update' && isset($_POST['cinema_id'])) {
        $cinema_id = intval($_POST['cinema_id']);
        $name = sanitize($conn, $_POST['name']);
        $location = sanitize($conn, $_POST['location']);
        $type_id = intval($_POST['type_id']);

        $stmt = $conn->prepare("UPDATE cinema SET name=?, location=?, type_id=? WHERE cinema_id=?");
        $stmt->bind_param("ssii", $name, $location, $type_id, $cinema_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Cinema updated successfully!";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error updating cinema: " . $stmt->error;
            $_SESSION['message_type'] = "danger";
        }
        $stmt->close();
    } elseif ($cinema_action === 'delete' && isset($_POST['cinema_id'])) {
        $cinema_id = intval($_POST['cinema_id']);
        $stmt = $conn->prepare("DELETE FROM cinema WHERE cinema_id = ?");
        $stmt->bind_param("i", $cinema_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Cinema deleted successfully!";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error deleting cinema: " . $stmt->error;
            $_SESSION['message_type'] = "danger";
        }
        $stmt->close();
    }
    header("Location: admin.php?tab=cinemas");
    exit();
}

// ----------------------------------------------------
// Handle Cinema Type CRUD (Add, Update, Delete)
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cinema_type_action'])) {
    $cinema_type_action = $_POST['cinema_type_action'];

    if ($cinema_type_action === 'add') {
        $type_name = sanitize($conn, $_POST['type_name']);
        $price = floatval($_POST['price']);

        $stmt = $conn->prepare("INSERT INTO cinema_type (type_name, price) VALUES (?, ?)");
        $stmt->bind_param("sd", $type_name, $price);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Cinema Type added successfully!";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error adding cinema type: " . $stmt->error;
            $_SESSION['message_type'] = "danger";
        }
        $stmt->close();
    } elseif ($cinema_type_action === 'update' && isset($_POST['type_id'])) {
        $type_id = intval($_POST['type_id']);
        $type_name = sanitize($conn, $_POST['type_name']);
        $price = floatval($_POST['price']);

        $stmt = $conn->prepare("UPDATE cinema_type SET type_name=?, price=? WHERE type_id=?");
        $stmt->bind_param("sdi", $type_name, $price, $type_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Cinema Type updated successfully!";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error updating cinema type: " . $stmt->error;
            $_SESSION['message_type'] = "danger";
        }
        $stmt->close();
    } elseif ($cinema_type_action === 'delete' && isset($_POST['type_id'])) {
        $type_id = intval($_POST['type_id']);
        $stmt = $conn->prepare("DELETE FROM cinema_type WHERE type_id = ?");
        $stmt->bind_param("i", $type_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Cinema Type deleted successfully!";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error deleting cinema type: " . $stmt->error;
            $_SESSION['message_type'] = "danger";
        }
        $stmt->close();
    }
    header("Location: admin.php?tab=cinema_types");
    exit();
}

// ----------------------------------------------------
// Handle Streaming Platform CRUD (Add, Update, Delete)
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['platform_action'])) {
    $platform_action = $_POST['platform_action'];

    if ($platform_action === 'add') {
        $platform_name = sanitize($conn, $_POST['platform_name']);
        $website = sanitize($conn, $_POST['website']);

        $stmt = $conn->prepare("INSERT INTO streaming_platform (platform_name, website) VALUES (?, ?)");
        $stmt->bind_param("ss", $platform_name, $website);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Streaming Platform added successfully!";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error adding platform: " . $stmt->error;
            $_SESSION['message_type'] = "danger";
        }
        $stmt->close();
    } elseif ($platform_action === 'update' && isset($_POST['platform_id'])) {
        $platform_id = intval($_POST['platform_id']);
        $platform_name = sanitize($conn, $_POST['platform_name']);
        $website = sanitize($conn, $_POST['website']);

        $stmt = $conn->prepare("UPDATE streaming_platform SET platform_name=?, website=? WHERE platform_id=?");
        $stmt->bind_param("ssi", $platform_name, $website, $platform_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Streaming Platform updated successfully!";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error updating platform: " . $stmt->error;
            $_SESSION['message_type'] = "danger";
        }
        $stmt->close();
    } elseif ($platform_action === 'delete' && isset($_POST['platform_id'])) {
        $platform_id = intval($_POST['platform_id']);
        $stmt = $conn->prepare("DELETE FROM streaming_platform WHERE platform_id = ?");
        $stmt->bind_param("i", $platform_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Streaming Platform deleted successfully!";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error deleting platform: " . $stmt->error;
            $_SESSION['message_type'] = "danger";
        }
        $stmt->close();
    }
    header("Location: admin.php?tab=platforms");
    exit();
}

// ----------------------------------------------------
// Handle Streaming Services CRUD (Add, Update, Delete)
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['streaming_service_action'])) {
    $streaming_service_action = $_POST['streaming_service_action'];

    if ($streaming_service_action === 'add') {
        $movie_id = intval($_POST['movie_id']);
        $platform_id = intval($_POST['platform_id']);
        $price_720p = floatval($_POST['price_720p']);
        $price_1080p = floatval($_POST['price_1080p']);
        $price_4k = floatval($_POST['price_4k']);

        $stmt = $conn->prepare("INSERT INTO streaming_services (movie_id, platform_id, price_720p, price_1080p, price_4k) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iiddd", $movie_id, $platform_id, $price_720p, $price_1080p, $price_4k);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Streaming Service link added successfully!";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error adding streaming service link: " . $stmt->error;
            $_SESSION['message_type'] = "danger";
        }
        $stmt->close();
    } elseif ($streaming_service_action === 'update' && isset($_POST['streaming_id'])) {
        $streaming_id = intval($_POST['streaming_id']);
        $movie_id = intval($_POST['movie_id']); // Can technically be updated if allowed
        $platform_id = intval($_POST['platform_id']); // Can technically be updated if allowed
        $price_720p = floatval($_POST['price_720p']);
        $price_1080p = floatval($_POST['price_1080p']);
        $price_4k = floatval($_POST['price_4k']);

        $stmt = $conn->prepare("UPDATE streaming_services SET movie_id=?, platform_id=?, price_720p=?, price_1080p=?, price_4k=? WHERE streaming_id=?");
        $stmt->bind_param("iidddi", $movie_id, $platform_id, $price_720p, $price_1080p, $price_4k, $streaming_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Streaming Service link updated successfully!";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error updating streaming service link: " . $stmt->error;
            $_SESSION['message_type'] = "danger";
        }
        $stmt->close();
    } elseif ($streaming_service_action === 'delete' && isset($_POST['streaming_id'])) {
        $streaming_id = intval($_POST['streaming_id']);
        $stmt = $conn->prepare("DELETE FROM streaming_services WHERE streaming_id = ?");
        $stmt->bind_param("i", $streaming_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Streaming Service link deleted successfully!";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error deleting streaming service link: " . $stmt->error;
            $_SESSION['message_type'] = "danger";
        }
        $stmt->close();
    }
    header("Location: admin.php?tab=streaming_services");
    exit();
}

// Handle messages
$message = '';
$message_type = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// Determine active tab
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard'; // Default to dashboard

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Movies Management System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
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
        .sidebar {
            height: 100vh;
            background-color: #f2f2f2;
            padding-top: 20px;
            border-right: 1px solid #e0e0e0;
        }
        .sidebar .nav-link {
            color: #333;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 5px;
        }
        .sidebar .nav-link.active {
            background-color: #007bff;
            color: #fff;
        }
        .sidebar .nav-link:hover {
            background-color: #e9ecef;
            color: #0056b3;
        }
        .content {
            padding: 20px;
        }
        .card {
            margin-bottom: 20px;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
        }
        .card-header {
            background-color: #007bff;
            color: white;
        }
        .form-inline .form-control {
            margin-right: 10px;
        }
        .btn-action {
            margin-right: 5px;
        }
        /* Style for modals */
        .modal-body .form-group {
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <a class="navbar-brand" href="admin.php">MovieVerse Admin</a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ml-auto">
                <li class="nav-item">
                    <span class="navbar-text">Welcome, <?php echo htmlspecialchars($admin_name); ?>!</span>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="logout.php">Logout</a>
                </li>
            </ul>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <nav class="col-md-2 d-none d-md-block sidebar">
                <div class="sidebar-sticky">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($active_tab == 'dashboard' ? 'active' : ''); ?>" href="admin.php?tab=dashboard">
                                <i class="fas fa-home"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($active_tab == 'users' ? 'active' : ''); ?>" href="admin.php?tab=users">
                                <i class="fas fa-users"></i> Manage Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($active_tab == 'movies' ? 'active' : ''); ?>" href="admin.php?tab=movies">
                                <i class="fas fa-film"></i> Manage Movies
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($active_tab == 'cinemas' ? 'active' : ''); ?>" href="admin.php?tab=cinemas">
                                <i class="fas fa-building"></i> Manage Cinemas
                            </a>
                        </li>
                         <li class="nav-item">
                            <a class="nav-link <?php echo ($active_tab == 'cinema_types' ? 'active' : ''); ?>" href="admin.php?tab=cinema_types">
                                <i class="fas fa-tags"></i> Manage Cinema Types
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($active_tab == 'platforms' ? 'active' : ''); ?>" href="admin.php?tab=platforms">
                                <i class="fas fa-tv"></i> Manage Streaming Platforms
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($active_tab == 'streaming_services' ? 'active' : ''); ?>" href="admin.php?tab=streaming_services">
                                <i class="fas fa-link"></i> Manage Movie Availability
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="admin_view_bookings.php">
                                <i class="fas fa-ticket-alt"></i> View Bookings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="admin_view_payments.php">
                                <i class="fas fa-credit-card"></i> View Payments
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="admin_view_subscriptions.php">
                                <i class="fas fa-file-invoice-dollar"></i> View Subscriptions
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="admin_view_recommendations.php">
                                <i class="fas fa-lightbulb"></i> View Recommendations
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <main role="main" class="col-md-9 ml-sm-auto col-lg-10 content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Admin Dashboard</h1>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <?php if ($active_tab == 'dashboard'): ?>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="card text-white bg-primary mb-3">
                                <div class="card-header"><i class="fas fa-users"></i> Total Users</div>
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <?php
                                        $result = $conn->query("SELECT COUNT(*) FROM user");
                                        echo $result->fetch_row()[0];
                                        ?>
                                    </h5>
                                    <p class="card-text">Registered users in the system.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card text-white bg-success mb-3">
                                <div class="card-header"><i class="fas fa-film"></i> Total Movies</div>
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <?php
                                        $result = $conn->query("SELECT COUNT(*) FROM movies");
                                        echo $result->fetch_row()[0];
                                        ?>
                                    </h5>
                                    <p class="card-text">Movies available for booking/streaming.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card text-white bg-info mb-3">
                                <div class="card-header"><i class="fas fa-ticket-alt"></i> Total Bookings</div>
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <?php
                                        $result = $conn->query("SELECT COUNT(*) FROM booking");
                                        echo $result->fetch_row()[0];
                                        ?>
                                    </h5>
                                    <p class="card-text">Number of cinema tickets booked.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($active_tab == 'users'): ?>
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-users"></i> Manage Users
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Age</th>
                                            <th>Preference</th>
                                            <th>Role</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        // FIX: Corrected query for user roles based on specialization tables
                                        $sql_users = "SELECT u.user_id, u.name, u.age, u.email, u.preference,
                                                             CASE WHEN a.user_id IS NOT NULL THEN 'Admin' ELSE 'Viewer' END AS user_role
                                                      FROM user u
                                                      LEFT JOIN admin a ON u.user_id = a.user_id
                                                      ORDER BY u.name ASC";
                                        $stmt_users = $conn->prepare($sql_users);
                                        $stmt_users->execute();
                                        $result_users = $stmt_users->get_result();

                                        if ($result_users->num_rows > 0) {
                                            while ($row = $result_users->fetch_assoc()) {
                                                echo "<tr>";
                                                echo "<td>" . htmlspecialchars($row['user_id']) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['name']) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['email']) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['age']) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['preference']) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['user_role']) . "</td>";
                                                echo "<td>";
                                                if ($row['user_id'] != $_SESSION['user_id']) { // Prevent admin from deleting/changing own role
                                                    echo "<form method='POST' style='display:inline-block; margin-right: 5px;' onsubmit='return confirm(\"Are you sure you want to change this user\\'s role?\");'>";
                                                    echo "<input type='hidden' name='action' value='change_user_role'>";
                                                    echo "<input type='hidden' name='user_id' value='" . htmlspecialchars($row['user_id']) . "'>";
                                                    echo "<input type='hidden' name='current_role' value='" . htmlspecialchars($row['user_role']) . "'>";
                                                    echo "<button type='submit' class='btn btn-sm btn-warning btn-action'>" . ($row['user_role'] == 'Admin' ? 'Demote to Viewer' : 'Promote to Admin') . "</button>";
                                                    echo "</form>";

                                                    echo "<form method='POST' style='display:inline-block;' onsubmit='return confirm(\"Are you sure you want to delete this user? This action is irreversible.\");'>";
                                                    echo "<input type='hidden' name='action' value='delete_user'>";
                                                    echo "<input type='hidden' name='user_id' value='" . htmlspecialchars($row['user_id']) . "'>";
                                                    echo "<button type='submit' class='btn btn-sm btn-danger btn-action'><i class='fas fa-trash'></i> Delete</button>";
                                                    echo "</form>";
                                                } else {
                                                    echo "<span class='text-muted'>Current Admin</span>";
                                                }
                                                echo "</td>";
                                                echo "</tr>";
                                            }
                                        } else {
                                            echo "<tr><td colspan='7'>No users found.</td></tr>";
                                        }
                                        $stmt_users->close();
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($active_tab == 'movies'): ?>
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-film"></i> Manage Movies
                        </div>
                        <div class="card-body">
                            <button type="button" class="btn btn-primary mb-3" data-toggle="modal" data-target="#addMovieModal">
                                <i class="fas fa-plus"></i> Add New Movie
                            </button>

                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Title</th>
                                            <th>Genre</th>
                                            <th>Release Date</th>
                                            <th>Language</th>
                                            <th>Duration (min)</th>
                                            <th>Rating</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $sql_movies = "SELECT * FROM movies";
                                        $result_movies = $conn->query($sql_movies);

                                        if ($result_movies->num_rows > 0) {
                                            while ($row = $result_movies->fetch_assoc()) {
                                                echo "<tr>";
                                                echo "<td>" . htmlspecialchars($row['movie_id']) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['title']) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['genre']) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['release_date']) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['language']) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['duration']) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['rating']) . "</td>";
                                                echo "<td>";
                                                echo "<button type='button' class='btn btn-sm btn-info btn-action' data-toggle='modal' data-target='#editMovieModal' " .
                                                     "data-movie_id='" . htmlspecialchars($row['movie_id']) . "' " .
                                                     "data-title='" . htmlspecialchars($row['title']) . "' " .
                                                     "data-genre='" . htmlspecialchars($row['genre']) . "' " .
                                                     "data-release_date='" . htmlspecialchars($row['release_date']) . "' " .
                                                     "data-language='" . htmlspecialchars($row['language']) . "' " .
                                                     "data-duration='" . htmlspecialchars($row['duration']) . "' " .
                                                     "data-rating='" . htmlspecialchars($row['rating']) . "'>" .
                                                     "<i class='fas fa-edit'></i> Edit</button>";
                                                echo "<form method='POST' style='display:inline-block;' onsubmit='return confirm(\"Are you sure you want to delete this movie?\");'>";
                                                echo "<input type='hidden' name='movie_action' value='delete'>";
                                                echo "<input type='hidden' name='movie_id' value='" . htmlspecialchars($row['movie_id']) . "'>";
                                                echo "<button type='submit' class='btn btn-sm btn-danger btn-action'><i class='fas fa-trash'></i> Delete</button>";
                                                echo "</form>";
                                                echo "</td>";
                                                echo "</tr>";
                                            }
                                        } else {
                                            echo "<tr><td colspan='8'>No movies found.</td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="modal fade" id="addMovieModal" tabindex="-1" role="dialog" aria-labelledby="addMovieModalLabel" aria-hidden="true">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="addMovieModalLabel">Add New Movie</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <form action="admin.php" method="POST">
                                <div class="modal-body">
                                    <input type="hidden" name="movie_action" value="add">
                                    <div class="form-group">
                                        <label for="add_title">Title</label>
                                        <input type="text" class="form-control" id="add_title" name="title" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="add_genre">Genre</label>
                                        <input type="text" class="form-control" id="add_genre" name="genre" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="add_release_date">Release Date</label>
                                        <input type="date" class="form-control" id="add_release_date" name="release_date" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="add_language">Language</label>
                                        <input type="text" class="form-control" id="add_language" name="language" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="add_duration">Duration (minutes)</label>
                                        <input type="number" class="form-control" id="add_duration" name="duration" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="add_rating">Rating (0.0 - 10.0)</label>
                                        <input type="number" step="0.1" class="form-control" id="add_rating" name="rating" min="0" max="10" required>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                    <button type="submit" class="btn btn-primary">Add Movie</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="modal fade" id="editMovieModal" tabindex="-1" role="dialog" aria-labelledby="editMovieModalLabel" aria-hidden="true">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="editMovieModalLabel">Edit Movie</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <form action="admin.php" method="POST">
                                <div class="modal-body">
                                    <input type="hidden" name="movie_action" value="update">
                                    <input type="hidden" name="movie_id" id="edit_movie_id">
                                    <div class="form-group">
                                        <label for="edit_title">Title</label>
                                        <input type="text" class="form-control" id="edit_title" name="title" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="edit_genre">Genre</label>
                                        <input type="text" class="form-control" id="edit_genre" name="genre" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="edit_release_date">Release Date</label>
                                        <input type="date" class="form-control" id="edit_release_date" name="release_date" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="edit_language">Language</label>
                                        <input type="text" class="form-control" id="edit_language" name="language" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="edit_duration">Duration (minutes)</label>
                                        <input type="number" class="form-control" id="edit_duration" name="duration" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="edit_rating">Rating (0.0 - 10.0)</label>
                                        <input type="number" step="0.1" class="form-control" id="edit_rating" name="rating" min="0" max="10" required>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>


                <?php if ($active_tab == 'cinemas'): ?>
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-building"></i> Manage Cinemas
                        </div>
                        <div class="card-body">
                            <button type="button" class="btn btn-primary mb-3" data-toggle="modal" data-target="#addCinemaModal">
                                <i class="fas fa-plus"></i> Add New Cinema
                            </button>

                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Name</th>
                                            <th>Location</th>
                                            <th>Type</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $sql_cinemas = "SELECT c.cinema_id, c.name, c.location, ct.type_name, ct.type_id
                                                        FROM cinema c JOIN cinema_type ct ON c.type_id = ct.type_id";
                                        $result_cinemas = $conn->query($sql_cinemas);

                                        if ($result_cinemas->num_rows > 0) {
                                            while ($row = $result_cinemas->fetch_assoc()) {
                                                echo "<tr>";
                                                echo "<td>" . htmlspecialchars($row['cinema_id']) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['name']) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['location']) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['type_name']) . "</td>";
                                                echo "<td>";
                                                echo "<button type='button' class='btn btn-sm btn-info btn-action' data-toggle='modal' data-target='#editCinemaModal' " .
                                                     "data-cinema_id='" . htmlspecialchars($row['cinema_id']) . "' " .
                                                     "data-name='" . htmlspecialchars($row['name']) . "' " .
                                                     "data-location='" . htmlspecialchars($row['location']) . "' " .
                                                     "data-type_id='" . htmlspecialchars($row['type_id']) . "'>" . // Pass type_id for dropdown
                                                     "<i class='fas fa-edit'></i> Edit</button>";
                                                echo "<form method='POST' style='display:inline-block;' onsubmit='return confirm(\"Are you sure you want to delete this cinema?\");'>";
                                                echo "<input type='hidden' name='cinema_action' value='delete'>";
                                                echo "<input type='hidden' name='cinema_id' value='" . htmlspecialchars($row['cinema_id']) . "'>";
                                                echo "<button type='submit' class='btn btn-sm btn-danger btn-action'><i class='fas fa-trash'></i> Delete</button>";
                                                echo "</form>";
                                                echo "</td>";
                                                echo "</tr>";
                                            }
                                        } else {
                                            echo "<tr><td colspan='5'>No cinemas found.</td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="modal fade" id="addCinemaModal" tabindex="-1" role="dialog" aria-labelledby="addCinemaModalLabel" aria-hidden="true">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="addCinemaModalLabel">Add New Cinema</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <form action="admin.php" method="POST">
                                <div class="modal-body">
                                    <input type="hidden" name="cinema_action" value="add">
                                    <div class="form-group">
                                        <label for="add_cinema_name">Name</label>
                                        <input type="text" class="form-control" id="add_cinema_name" name="name" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="add_cinema_location">Location</label>
                                        <input type="text" class="form-control" id="add_cinema_location" name="location" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="add_cinema_type_id">Type</label>
                                        <select class="form-control" id="add_cinema_type_id" name="type_id" required>
                                            <?php
                                            $sql_cinema_types = "SELECT type_id, type_name FROM cinema_type";
                                            $result_cinema_types = $conn->query($sql_cinema_types);
                                            while ($row_type = $result_cinema_types->fetch_assoc()) {
                                                echo "<option value='" . htmlspecialchars($row_type['type_id']) . "'>" . htmlspecialchars($row_type['type_name']) . "</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                    <button type="submit" class="btn btn-primary">Add Cinema</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="modal fade" id="editCinemaModal" tabindex="-1" role="dialog" aria-labelledby="editCinemaModalLabel" aria-hidden="true">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="editCinemaModalLabel">Edit Cinema</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <form action="admin.php" method="POST">
                                <div class="modal-body">
                                    <input type="hidden" name="cinema_action" value="update">
                                    <input type="hidden" name="cinema_id" id="edit_cinema_id">
                                    <div class="form-group">
                                        <label for="edit_cinema_name">Name</label>
                                        <input type="text" class="form-control" id="edit_cinema_name" name="name" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="edit_cinema_location">Location</label>
                                        <input type="text" class="form-control" id="edit_cinema_location" name="location" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="edit_cinema_type_id">Type</label>
                                        <select class="form-control" id="edit_cinema_type_id" name="type_id" required>
                                            <?php
                                            // Re-fetch types for the edit modal
                                            $result_cinema_types_edit = $conn->query($sql_cinema_types);
                                            while ($row_type_edit = $result_cinema_types_edit->fetch_assoc()) {
                                                echo "<option value='" . htmlspecialchars($row_type_edit['type_id']) . "'>" . htmlspecialchars($row_type_edit['type_name']) . "</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <?php if ($active_tab == 'cinema_types'): ?>
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-tags"></i> Manage Cinema Types
                        </div>
                        <div class="card-body">
                            <button type="button" class="btn btn-primary mb-3" data-toggle="modal" data-target="#addCinemaTypeModal">
                                <i class="fas fa-plus"></i> Add New Cinema Type
                            </button>

                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Type Name</th>
                                            <th>Price (PKR)</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $sql_cinema_types_list = "SELECT * FROM cinema_type";
                                        $result_cinema_types_list = $conn->query($sql_cinema_types_list);

                                        if ($result_cinema_types_list->num_rows > 0) {
                                            while ($row = $result_cinema_types_list->fetch_assoc()) {
                                                echo "<tr>";
                                                echo "<td>" . htmlspecialchars($row['type_id']) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['type_name']) . "</td>";
                                                echo "<td>PKR " . number_format($row['price'], 2) . "</td>";
                                                echo "<td>";
                                                echo "<button type='button' class='btn btn-sm btn-info btn-action' data-toggle='modal' data-target='#editCinemaTypeModal' " .
                                                     "data-type_id='" . htmlspecialchars($row['type_id']) . "' " .
                                                     "data-type_name='" . htmlspecialchars($row['type_name']) . "' " .
                                                     "data-price='" . htmlspecialchars($row['price']) . "'>" .
                                                     "<i class='fas fa-edit'></i> Edit</button>";
                                                echo "<form method='POST' style='display:inline-block;' onsubmit='return confirm(\"Are you sure you want to delete this cinema type?\");'>";
                                                echo "<input type='hidden' name='cinema_type_action' value='delete'>";
                                                echo "<input type='hidden' name='type_id' value='" . htmlspecialchars($row['type_id']) . "'>";
                                                echo "<button type='submit' class='btn btn-sm btn-danger btn-action'><i class='fas fa-trash'></i> Delete</button>";
                                                echo "</form>";
                                                echo "</td>";
                                                echo "</tr>";
                                            }
                                        } else {
                                            echo "<tr><td colspan='4'>No cinema types found.</td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="modal fade" id="addCinemaTypeModal" tabindex="-1" role="dialog" aria-labelledby="addCinemaTypeModalLabel" aria-hidden="true">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="addCinemaTypeModalLabel">Add New Cinema Type</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <form action="admin.php" method="POST">
                                <div class="modal-body">
                                    <input type="hidden" name="cinema_type_action" value="add">
                                    <div class="form-group">
                                        <label for="add_type_name">Type Name</label>
                                        <select class="form-control" id="add_type_name" name="type_name" required>
                                            <option value="Normal">Normal</option>
                                            <option value="3D">3D</option>
                                            <option value="IMAX">IMAX</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="add_type_price">Price (PKR)</label>
                                        <input type="number" step="0.01" class="form-control" id="add_type_price" name="price" required>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                    <button type="submit" class="btn btn-primary">Add Cinema Type</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="modal fade" id="editCinemaTypeModal" tabindex="-1" role="dialog" aria-labelledby="editCinemaTypeModalLabel" aria-hidden="true">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="editCinemaTypeModalLabel">Edit Cinema Type</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <form action="admin.php" method="POST">
                                <div class="modal-body">
                                    <input type="hidden" name="cinema_type_action" value="update">
                                    <input type="hidden" name="type_id" id="edit_type_id">
                                    <div class="form-group">
                                        <label for="edit_type_name">Type Name</label>
                                        <select class="form-control" id="edit_type_name" name="type_name" required>
                                            <option value="Normal">Normal</option>
                                            <option value="3D">3D</option>
                                            <option value="IMAX">IMAX</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="edit_type_price">Price (PKR)</label>
                                        <input type="number" step="0.01" class="form-control" id="edit_type_price" name="price" required>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>


                <?php if ($active_tab == 'platforms'): ?>
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-tv"></i> Manage Streaming Platforms
                        </div>
                        <div class="card-body">
                            <button type="button" class="btn btn-primary mb-3" data-toggle="modal" data-target="#addPlatformModal">
                                <i class="fas fa-plus"></i> Add New Platform
                            </button>

                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Platform Name</th>
                                            <th>Website</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $sql_platforms = "SELECT * FROM streaming_platform";
                                        $result_platforms = $conn->query($sql_platforms);

                                        if ($result_platforms->num_rows > 0) {
                                            while ($row = $result_platforms->fetch_assoc()) {
                                                echo "<tr>";
                                                echo "<td>" . htmlspecialchars($row['platform_id']) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['platform_name']) . "</td>";
                                                echo "<td><a href='" . htmlspecialchars($row['website']) . "' target='_blank'>" . htmlspecialchars($row['website']) . "</a></td>";
                                                echo "<td>";
                                                echo "<button type='button' class='btn btn-sm btn-info btn-action' data-toggle='modal' data-target='#editPlatformModal' " .
                                                     "data-platform_id='" . htmlspecialchars($row['platform_id']) . "' " .
                                                     "data-platform_name='" . htmlspecialchars($row['platform_name']) . "' " .
                                                     "data-website='" . htmlspecialchars($row['website']) . "'>" .
                                                     "<i class='fas fa-edit'></i> Edit</button>";
                                                echo "<form method='POST' style='display:inline-block;' onsubmit='return confirm(\"Are you sure you want to delete this platform?\");'>";
                                                echo "<input type='hidden' name='platform_action' value='delete'>";
                                                echo "<input type='hidden' name='platform_id' value='" . htmlspecialchars($row['platform_id']) . "'>";
                                                echo "<button type='submit' class='btn btn-sm btn-danger btn-action'><i class='fas fa-trash'></i> Delete</button>";
                                                echo "</form>";
                                                echo "</td>";
                                                echo "</tr>";
                                            }
                                        } else {
                                            echo "<tr><td colspan='4'>No streaming platforms found.</td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="modal fade" id="addPlatformModal" tabindex="-1" role="dialog" aria-labelledby="addPlatformModalLabel" aria-hidden="true">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="addPlatformModalLabel">Add New Streaming Platform</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <form action="admin.php" method="POST">
                                <div class="modal-body">
                                    <input type="hidden" name="platform_action" value="add">
                                    <div class="form-group">
                                        <label for="add_platform_name">Platform Name</label>
                                        <input type="text" class="form-control" id="add_platform_name" name="platform_name" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="add_website">Website URL</label>
                                        <input type="url" class="form-control" id="add_website" name="website" placeholder="e.g., https://www.netflix.com" required>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                    <button type="submit" class="btn btn-primary">Add Platform</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="modal fade" id="editPlatformModal" tabindex="-1" role="dialog" aria-labelledby="editPlatformModalLabel" aria-hidden="true">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="editPlatformModalLabel">Edit Streaming Platform</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <form action="admin.php" method="POST">
                                <div class="modal-body">
                                    <input type="hidden" name="platform_action" value="update">
                                    <input type="hidden" name="platform_id" id="edit_platform_id">
                                    <div class="form-group">
                                        <label for="edit_platform_name">Platform Name</label>
                                        <input type="text" class="form-control" id="edit_platform_name" name="platform_name" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="edit_website">Website URL</label>
                                        <input type="url" class="form-control" id="edit_website" name="website" placeholder="e.g., https://www.netflix.com" required>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>


                <?php if ($active_tab == 'streaming_services'): ?>
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-link"></i> Manage Movie Availability on Platforms
                        </div>
                        <div class="card-body">
                            <button type="button" class="btn btn-primary mb-3" data-toggle="modal" data-target="#addStreamingServiceModal">
                                <i class="fas fa-plus"></i> Add Movie to Platform
                            </button>

                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Movie Title</th>
                                            <th>Platform Name</th>
                                            <th>Price (720p)</th>
                                            <th>Price (1080p)</th>
                                            <th>Price (4K)</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $sql_streaming_services = "SELECT ss.streaming_id, m.title AS movie_title, sp.platform_name, 
                                                                    ss.movie_id, ss.platform_id,
                                                                    ss.price_720p, ss.price_1080p, ss.price_4k
                                                                FROM streaming_services ss
                                                                JOIN movies m ON ss.movie_id = m.movie_id
                                                                JOIN streaming_platform sp ON ss.platform_id = sp.platform_id";
                                        $result_streaming_services = $conn->query($sql_streaming_services);

                                        if ($result_streaming_services->num_rows > 0) {
                                            while ($row = $result_streaming_services->fetch_assoc()) {
                                                echo "<tr>";
                                                echo "<td>" . htmlspecialchars($row['streaming_id']) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['movie_title']) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['platform_name']) . "</td>";
                                                echo "<td>PKR " . number_format($row['price_720p'], 2) . "</td>";
                                                echo "<td>PKR " . number_format($row['price_1080p'], 2) . "</td>";
                                                echo "<td>PKR " . number_format($row['price_4k'], 2) . "</td>";
                                                echo "<td>";
                                                echo "<button type='button' class='btn btn-sm btn-info btn-action' data-toggle='modal' data-target='#editStreamingServiceModal' " .
                                                     "data-streaming_id='" . htmlspecialchars($row['streaming_id']) . "' " .
                                                     "data-movie_id='" . htmlspecialchars($row['movie_id']) . "' " .
                                                     "data-platform_id='" . htmlspecialchars($row['platform_id']) . "' " .
                                                     "data-price_720p='" . htmlspecialchars($row['price_720p']) . "' " .
                                                     "data-price_1080p='" . htmlspecialchars($row['price_1080p']) . "' " .
                                                     "data-price_4k='" . htmlspecialchars($row['price_4k']) . "'>" .
                                                     "<i class='fas fa-edit'></i> Edit</button>";
                                                echo "<form method='POST' style='display:inline-block;' onsubmit='return confirm(\"Are you sure you want to delete this streaming service link?\");'>";
                                                echo "<input type='hidden' name='streaming_service_action' value='delete'>";
                                                echo "<input type='hidden' name='streaming_id' value='" . htmlspecialchars($row['streaming_id']) . "'>";
                                                echo "<button type='submit' class='btn btn-sm btn-danger btn-action'><i class='fas fa-trash'></i> Delete</button>";
                                                echo "</form>";
                                                echo "</td>";
                                                echo "</tr>";
                                            }
                                        } else {
                                            echo "<tr><td colspan='7'>No movie availability links found.</td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="modal fade" id="addStreamingServiceModal" tabindex="-1" role="dialog" aria-labelledby="addStreamingServiceModalLabel" aria-hidden="true">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="addStreamingServiceModalLabel">Add Movie to Platform</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <form action="admin.php" method="POST">
                                <div class="modal-body">
                                    <input type="hidden" name="streaming_service_action" value="add">
                                    <div class="form-group">
                                        <label for="add_ss_movie_id">Movie</label>
                                        <select class="form-control" id="add_ss_movie_id" name="movie_id" required>
                                            <?php
                                            $sql_all_movies = "SELECT movie_id, title FROM movies ORDER BY title ASC";
                                            $result_all_movies = $conn->query($sql_all_movies);
                                            while ($row_movie = $result_all_movies->fetch_assoc()) {
                                                echo "<option value='" . htmlspecialchars($row_movie['movie_id']) . "'>" . htmlspecialchars($row_movie['title']) . "</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="add_ss_platform_id">Platform</label>
                                        <select class="form-control" id="add_ss_platform_id" name="platform_id" required>
                                            <?php
                                            $sql_all_platforms = "SELECT platform_id, platform_name FROM streaming_platform ORDER BY platform_name ASC";
                                            $result_all_platforms = $conn->query($sql_all_platforms);
                                            while ($row_platform = $result_all_platforms->fetch_assoc()) {
                                                echo "<option value='" . htmlspecialchars($row_platform['platform_id']) . "'>" . htmlspecialchars($row_platform['platform_name']) . "</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="add_price_720p">Price (720p)</label>
                                        <input type="number" step="0.01" class="form-control" id="add_price_720p" name="price_720p" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="add_price_1080p">Price (1080p)</label>
                                        <input type="number" step="0.01" class="form-control" id="add_price_1080p" name="price_1080p" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="add_price_4k">Price (4K)</label>
                                        <input type="number" step="0.01" class="form-control" id="add_price_4k" name="price_4k" required>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                    <button type="submit" class="btn btn-primary">Add Link</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="modal fade" id="editStreamingServiceModal" tabindex="-1" role="dialog" aria-labelledby="editStreamingServiceModalLabel" aria-hidden="true">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="editStreamingServiceModalLabel">Edit Movie Availability</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <form action="admin.php" method="POST">
                                <div class="modal-body">
                                    <input type="hidden" name="streaming_service_action" value="update">
                                    <input type="hidden" name="streaming_id" id="edit_ss_streaming_id">
                                    <div class="form-group">
                                        <label for="edit_ss_movie_id">Movie</label>
                                        <select class="form-control" id="edit_ss_movie_id" name="movie_id" required>
                                            <?php
                                            // Re-fetch all movies for edit modal
                                            $result_all_movies_edit = $conn->query($sql_all_movies);
                                            while ($row_movie_edit = $result_all_movies_edit->fetch_assoc()) {
                                                echo "<option value='" . htmlspecialchars($row_movie_edit['movie_id']) . "'>" . htmlspecialchars($row_movie_edit['title']) . "</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="edit_ss_platform_id">Platform</label>
                                        <select class="form-control" id="edit_ss_platform_id" name="platform_id" required>
                                            <?php
                                            // Re-fetch all platforms for edit modal
                                            $result_all_platforms_edit = $conn->query($sql_all_platforms);
                                            while ($row_platform_edit = $result_all_platforms_edit->fetch_assoc()) {
                                                echo "<option value='" . htmlspecialchars($row_platform_edit['platform_id']) . "'>" . htmlspecialchars($row_platform_edit['platform_name']) . "</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="edit_price_720p">Price (720p)</label>
                                        <input type="number" step="0.01" class="form-control" id="edit_price_720p" name="price_720p" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="edit_price_1080p">Price (1080p)</label>
                                        <input type="number" step="0.01" class="form-control" id="edit_price_1080p" name="price_1080p" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="edit_price_4k">Price (4K)</label>
                                        <input type="number" step="0.01" class="form-control" id="edit_price_4k" name="price_4k" required>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

    <script>
        // JavaScript for populating Edit Movie Modal
        $('#editMovieModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget); // Button that triggered the modal
            var movie_id = button.data('movie_id');
            var title = button.data('title');
            var genre = button.data('genre');
            var release_date = button.data('release_date');
            var language = button.data('language');
            var duration = button.data('duration');
            var rating = button.data('rating');

            var modal = $(this);
            modal.find('#edit_movie_id').val(movie_id);
            modal.find('#edit_title').val(title);
            modal.find('#edit_genre').val(genre);
            modal.find('#edit_release_date').val(release_date);
            modal.find('#edit_language').val(language);
            modal.find('#edit_duration').val(duration);
            modal.find('#edit_rating').val(rating);
        });

        // JavaScript for populating Edit Cinema Modal
        $('#editCinemaModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var cinema_id = button.data('cinema_id');
            var name = button.data('name');
            var location = button.data('location');
            var type_id = button.data('type_id'); // Get the type_id

            var modal = $(this);
            modal.find('#edit_cinema_id').val(cinema_id);
            modal.find('#edit_cinema_name').val(name);
            modal.find('#edit_cinema_location').val(location);
            modal.find('#edit_cinema_type_id').val(type_id); // Set the selected option
        });

        // JavaScript for populating Edit Cinema Type Modal
        $('#editCinemaTypeModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var type_id = button.data('type_id');
            var type_name = button.data('type_name');
            var price = button.data('price');

            var modal = $(this);
            modal.find('#edit_type_id').val(type_id);
            modal.find('#edit_type_name').val(type_name); // Set the selected option
            modal.find('#edit_type_price').val(price);
        });

        // JavaScript for populating Edit Platform Modal
        $('#editPlatformModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var platform_id = button.data('platform_id');
            var platform_name = button.data('platform_name');
            var website = button.data('website');

            var modal = $(this);
            modal.find('#edit_platform_id').val(platform_id);
            modal.find('#edit_platform_name').val(platform_name);
            modal.find('#edit_website').val(website);
        });

        // JavaScript for populating Edit Streaming Service Modal
        $('#editStreamingServiceModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var streaming_id = button.data('streaming_id');
            var movie_id = button.data('movie_id');
            var platform_id = button.data('platform_id');
            var price_720p = button.data('price_720p');
            var price_1080p = button.data('price_1080p');
            var price_4k = button.data('price_4k');

            var modal = $(this);
            modal.find('#edit_ss_streaming_id').val(streaming_id);
            modal.find('#edit_ss_movie_id').val(movie_id); // Set selected option
            modal.find('#edit_ss_platform_id').val(platform_id); // Set selected option
            modal.find('#edit_price_720p').val(price_720p);
            modal.find('#edit_price_1080p').val(price_1080p);
            modal.find('#edit_price_4k').val(price_4k);
        });
    </script>
</body>
</html>
<?php
$conn->close();
?>