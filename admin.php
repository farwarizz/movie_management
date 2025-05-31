<?php
/**
 * admin.php
 * This is the main administration panel for the Movies Management System.
 * It provides a consolidated dashboard for managing movies, cinemas, users,
 * streaming platforms, streaming services, and viewing activity logs.
 *
 * FIXES:
 * - Corrected user fetching and user type update logic to match
 * the schema where user roles are defined by presence in 'viewer' or 'admin' tables.
 * - ADDED: Functionality to add new users (including role assignment).
 */

session_start();
require_once 'db_connect.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$message = '';
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'movies'; // Default active tab

// Function to sanitize input
function sanitize_input($conn, $data) {
    if (is_array($data)) {
        return array_map(function($value) use ($conn) {
            return $conn->real_escape_string(htmlspecialchars(strip_tags($value)));
        }, $data);
    }
    return $conn->real_escape_string(htmlspecialchars(strip_tags($data)));
}

// --- General Message Handling ---
if (isset($_GET['message'])) {
    $message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4' role='alert'>" . htmlspecialchars($_GET['message']) . "</div>";
}
if (isset($_GET['error'])) {
    $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>" . htmlspecialchars($_GET['error']) . "</div>";
}

// --- Handle Form Submissions for Add/Update/Delete Operations ---

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $action = $_POST['action'];

    // --- MOVIES Management ---
    if ($action === 'add_movie') {
        $title = sanitize_input($conn, $_POST['title']);
        $genre = sanitize_input($conn, $_POST['genre']);
        $release_date = sanitize_input($conn, $_POST['release_date']);
        $language = sanitize_input($conn, $_POST['language']);
        $duration = (int)$_POST['duration'];
        $rating = (float)$_POST['rating'];

        if (empty($title) || empty($genre) || empty($release_date) || empty($language) || empty($duration) || empty($rating)) {
            $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>All movie fields are required.</div>";
            $active_tab = 'movies';
        } else {
            $stmt = $conn->prepare("INSERT INTO movies (title, genre, release_date, language, duration, rating) VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("ssssid", $title, $genre, $release_date, $language, $duration, $rating);
                if ($stmt->execute()) {
                    $message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4' role='alert'>Movie added successfully!</div>";
                } else {
                    $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Error adding movie: " . $stmt->error . "</div>";
                }
                $stmt->close();
            } else {
                $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Database error: " . $conn->error . "</div>";
            }
            $active_tab = 'movies';
        }
    } elseif ($action === 'update_movie') {
        $movie_id = (int)$_POST['movie_id'];
        $title = sanitize_input($conn, $_POST['title']);
        $genre = sanitize_input($conn, $_POST['genre']);
        $release_date = sanitize_input($conn, $_POST['release_date']);
        $language = sanitize_input($conn, $_POST['language']);
        $duration = (int)$_POST['duration'];
        $rating = (float)$_POST['rating'];

        if (empty($title) || empty($genre) || empty($release_date) || empty($language) || empty($duration) || empty($rating)) {
            $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>All movie fields are required for update.</div>";
            $active_tab = 'movies';
        } else {
            $stmt = $conn->prepare("UPDATE movies SET title = ?, genre = ?, release_date = ?, language = ?, duration = ?, rating = ? WHERE movie_id = ?");
            if ($stmt) {
                $stmt->bind_param("ssssidi", $title, $genre, $release_date, $language, $duration, $rating, $movie_id);
                if ($stmt->execute()) {
                    $message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4' role='alert'>Movie updated successfully!</div>";
                } else {
                    $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Error updating movie: " . $stmt->error . "</div>";
                }
                $stmt->close();
            } else {
                $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Database error: " . $conn->error . "</div>";
            }
            $active_tab = 'movies';
        }
    } elseif ($action === 'delete_movie') {
        $movie_id = (int)$_POST['movie_id'];
        $stmt = $conn->prepare("DELETE FROM movies WHERE movie_id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $movie_id);
            if ($stmt->execute()) {
                $message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4' role='alert'>Movie deleted successfully!</div>";
            } else {
                $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Error deleting movie: " . $stmt->error . "</div>";
            }
            $stmt->close();
        } else {
            $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Database error: " . $conn->error . "</div>";
        }
        $active_tab = 'movies';
    }

    // --- CINEMAS Management ---
    elseif ($action === 'add_cinema') {
        $name = sanitize_input($conn, $_POST['name']);
        $location = sanitize_input($conn, $_POST['location']);
        $type_id = (int)$_POST['type_id'];

        if (empty($name) || empty($location) || empty($type_id)) {
            $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>All cinema fields are required.</div>";
            $active_tab = 'cinemas';
        } else {
            $stmt = $conn->prepare("INSERT INTO cinema (name, location, type_id) VALUES (?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("ssi", $name, $location, $type_id);
                if ($stmt->execute()) {
                    $message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4' role='alert'>Cinema added successfully!</div>";
                } else {
                    $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Error adding cinema: " . $stmt->error . "</div>";
                }
                $stmt->close();
            } else {
                $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Database error: " . $conn->error . "</div>";
            }
            $active_tab = 'cinemas';
        }
    } elseif ($action === 'update_cinema') {
        $cinema_id = (int)$_POST['cinema_id'];
        $name = sanitize_input($conn, $_POST['name']);
        $location = sanitize_input($conn, $_POST['location']);
        $type_id = (int)$_POST['type_id'];

        if (empty($name) || empty($location) || empty($type_id)) {
            $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>All cinema fields are required for update.</div>";
            $active_tab = 'cinemas';
        } else {
            $stmt = $conn->prepare("UPDATE cinema SET name = ?, location = ?, type_id = ? WHERE cinema_id = ?");
            if ($stmt) {
                $stmt->bind_param("ssii", $name, $location, $type_id, $cinema_id);
                if ($stmt->execute()) {
                    $message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4' role='alert'>Cinema updated successfully!</div>";
                } else {
                    $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Error updating cinema: " . $stmt->error . "</div>";
                }
                $stmt->close();
            } else {
                $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Database error: " . $conn->error . "</div>";
            }
            $active_tab = 'cinemas';
        }
    } elseif ($action === 'delete_cinema') {
        $cinema_id = (int)$_POST['cinema_id'];
        $stmt = $conn->prepare("DELETE FROM cinema WHERE cinema_id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $cinema_id);
            if ($stmt->execute()) {
                $message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4' role='alert'>Cinema deleted successfully!</div>";
            } else {
                $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Error deleting cinema: " . $stmt->error . "</div>";
            }
            $stmt->close();
        } else {
            $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Database error: " . $conn->error . "</div>";
        }
        $active_tab = 'cinemas';
    }

    // --- USERS Management (Add, Role Change & Delete) ---
    elseif ($action === 'add_user') {
        $name = sanitize_input($conn, $_POST['name']);
        $age = (int)$_POST['age'];
        $email = sanitize_input($conn, $_POST['email']);
        $password = $_POST['password']; // Password will be hashed
        $preference = sanitize_input($conn, $_POST['preference']);
        $user_role_to_add = sanitize_input($conn, $_POST['user_role']);

        if (empty($name) || empty($email) || empty($password) || empty($preference) || empty($user_role_to_add)) {
            $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>All user fields are required.</div>";
            $active_tab = 'users';
        } else {
            // Check if email already exists
            $check_email_stmt = $conn->prepare("SELECT user_id FROM user WHERE email = ?");
            $check_email_stmt->bind_param("s", $email);
            $check_email_stmt->execute();
            $check_email_result = $check_email_stmt->get_result();
            if ($check_email_result->num_rows > 0) {
                $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>User with this email already exists.</div>";
                $active_tab = 'users';
                $check_email_stmt->close();
            } else {
                $check_email_stmt->close();
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                $conn->begin_transaction();
                try {
                    $stmt_user = $conn->prepare("INSERT INTO user (name, age, email, password, preference) VALUES (?, ?, ?, ?, ?)");
                    if (!$stmt_user) {
                        throw new mysqli_sql_exception("Prepare failed: " . $conn->error);
                    }
                    $stmt_user->bind_param("sisss", $name, $age, $email, $hashed_password, $preference);
                    $stmt_user->execute();
                    $new_user_id = $conn->insert_id;
                    $stmt_user->close();

                    if ($user_role_to_add === 'admin') {
                        $stmt_role = $conn->prepare("INSERT INTO admin (user_id) VALUES (?)");
                    } else { // default to viewer
                        $stmt_role = $conn->prepare("INSERT INTO viewer (user_id) VALUES (?)");
                    }
                    if (!$stmt_role) {
                        throw new mysqli_sql_exception("Prepare role failed: " . $conn->error);
                    }
                    $stmt_role->bind_param("i", $new_user_id);
                    $stmt_role->execute();
                    $stmt_role->close();

                    $conn->commit();
                    $message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4' role='alert'>User added successfully with role '" . htmlspecialchars($user_role_to_add) . "'!</div>";
                } catch (mysqli_sql_exception $e) {
                    $conn->rollback();
                    $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Error adding user: " . $e->getMessage() . "</div>";
                }
                $active_tab = 'users';
            }
        }
    } elseif ($action === 'update_user_role') {
        $user_id_to_modify = (int)$_POST['user_id'];
        $new_role = sanitize_input($conn, $_POST['new_role']);

        if ($user_id_to_modify === $_SESSION['user_id']) { // Prevent admin from changing their own type
            $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>You cannot change your own user role.</div>";
        } else {
            // Get current role of the user being modified
            $current_role_stmt = $conn->prepare("SELECT CASE WHEN a.user_id IS NOT NULL THEN 'admin' ELSE 'viewer' END AS user_role
                                                FROM user u LEFT JOIN admin a ON u.user_id = a.user_id WHERE u.user_id = ?");
            $current_role_stmt->bind_param("i", $user_id_to_modify);
            $current_role_stmt->execute();
            $current_role_result = $current_role_stmt->get_result();
            $current_role_row = $current_role_result->fetch_assoc();
            $current_role = $current_role_row['user_role'];
            $current_role_stmt->close();

            // Only proceed if role is actually changing
            if ($current_role !== $new_role) {
                $conn->begin_transaction();
                try {
                    if ($new_role === 'admin') {
                        // Change from viewer to admin
                        $stmt_delete_viewer = $conn->prepare("DELETE FROM viewer WHERE user_id = ?");
                        $stmt_delete_viewer->bind_param("i", $user_id_to_modify);
                        $stmt_delete_viewer->execute();
                        $stmt_delete_viewer->close();

                        $stmt_insert_admin = $conn->prepare("INSERT INTO admin (user_id) VALUES (?)");
                        $stmt_insert_admin->bind_param("i", $user_id_to_modify);
                        $stmt_insert_admin->execute();
                        $stmt_insert_admin->close();
                    } elseif ($new_role === 'viewer') {
                        // Change from admin to viewer
                        $stmt_delete_admin = $conn->prepare("DELETE FROM admin WHERE user_id = ?");
                        $stmt_delete_admin->bind_param("i", $user_id_to_modify);
                        $stmt_delete_admin->execute();
                        $stmt_delete_admin->close();

                        $stmt_insert_viewer = $conn->prepare("INSERT INTO viewer (user_id) VALUES (?)");
                        $stmt_insert_viewer->bind_param("i", $user_id_to_modify);
                        $stmt_insert_viewer->execute();
                        $stmt_insert_viewer->close();
                    }
                    $conn->commit();
                    $message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4' role='alert'>User role updated successfully!</div>";
                } catch (mysqli_sql_exception $e) {
                    $conn->rollback();
                    $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Error updating user role: " . $e->getMessage() . "</div>";
                }
            } else {
                $message = "<div class='bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative mb-4' role='alert'>User already has the selected role. No change made.</div>";
            }
        }
        $active_tab = 'users';
    } elseif ($action === 'delete_user') {
        $user_id = (int)$_POST['user_id'];
        if ($user_id === $_SESSION['user_id']) { // Prevent admin from deleting their own account
            $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>You cannot delete your own admin account.</div>";
        } else {
            // Deleting from 'user' table will cascade delete in 'viewer'/'admin' due to FK ON DELETE CASCADE
            $stmt = $conn->prepare("DELETE FROM user WHERE user_id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $user_id);
                if ($stmt->execute()) {
                    $message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4' role='alert'>User deleted successfully!</div>";
                } else {
                    $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Error deleting user: " . $stmt->error . "</div>";
                }
                $stmt->close();
            } else {
                $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Database error: " . $conn->error . "</div>";
            }
        }
        $active_tab = 'users';
    }

    // --- STREAMING PLATFORMS Management ---
    elseif ($action === 'add_platform') {
        $platform_name = sanitize_input($conn, $_POST['platform_name']);
        $website = sanitize_input($conn, $_POST['website']);

        if (empty($platform_name)) {
            $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Platform name is required.</div>";
            $active_tab = 'platforms';
        } else {
            $stmt = $conn->prepare("INSERT INTO streaming_platform (platform_name, website) VALUES (?, ?)");
            if ($stmt) {
                $stmt->bind_param("ss", $platform_name, $website);
                if ($stmt->execute()) {
                    $message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4' role='alert'>Streaming Platform added successfully!</div>";
                } else {
                    $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Error adding platform: " . $stmt->error . "</div>";
                }
                $stmt->close();
            } else {
                $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Database error: " . $conn->error . "</div>";
            }
            $active_tab = 'platforms';
        }
    } elseif ($action === 'update_platform') {
        $platform_id = (int)$_POST['platform_id'];
        $platform_name = sanitize_input($conn, $_POST['platform_name']);
        $website = sanitize_input($conn, $_POST['website']);

        if (empty($platform_name)) {
            $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Platform name is required for update.</div>";
            $active_tab = 'platforms';
        } else {
            $stmt = $conn->prepare("UPDATE streaming_platform SET platform_name = ?, website = ? WHERE platform_id = ?");
            if ($stmt) {
                $stmt->bind_param("ssi", $platform_name, $website, $platform_id);
                if ($stmt->execute()) {
                    $message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4' role='alert'>Streaming Platform updated successfully!</div>";
                } else {
                    $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Error updating platform: " . $stmt->error . "</div>";
                }
                $stmt->close();
            } else {
                $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Database error: " . $conn->error . "</div>";
            }
            $active_tab = 'platforms';
        }
    } elseif ($action === 'delete_platform') {
        $platform_id = (int)$_POST['platform_id'];
        $stmt = $conn->prepare("DELETE FROM streaming_platform WHERE platform_id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $platform_id);
            if ($stmt->execute()) {
                $message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4' role='alert'>Streaming Platform deleted successfully!</div>";
            } else {
                $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Error deleting platform: " . $stmt->error . "</div>";
            }
            $stmt->close();
        } else {
            $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Database error: " . $conn->error . "</div>";
        }
        $active_tab = 'platforms';
    }

    // --- STREAMING SERVICES (linking movies to platforms with prices) Management ---
    elseif ($action === 'add_streaming_service') {
        $movie_id = (int)$_POST['movie_id'];
        $platform_id = (int)$_POST['platform_id'];
        $price_720p = (float)$_POST['price_720p'];
        $price_1080p = (float)$_POST['price_1080p'];
        $price_4k = (float)$_POST['price_4k'];

        if (empty($movie_id) || empty($platform_id)) {
            $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Movie and Platform are required.</div>";
            $active_tab = 'streaming_services';
        } else {
            // Check for duplicate entry
            $check_stmt = $conn->prepare("SELECT COUNT(*) FROM streaming_services WHERE movie_id = ? AND platform_id = ?");
            $check_stmt->bind_param("ii", $movie_id, $platform_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result()->fetch_row()[0];
            $check_stmt->close();

            if ($check_result > 0) {
                $message = "<div class='bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative mb-4' role='alert'>This movie is already linked to this platform. Consider updating instead.</div>";
            } else {
                $stmt = $conn->prepare("INSERT INTO streaming_services (movie_id, platform_id, price_720p, price_1080p, price_4k) VALUES (?, ?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param("iiddd", $movie_id, $platform_id, $price_720p, $price_1080p, $price_4k);
                    if ($stmt->execute()) {
                        $message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4' role='alert'>Streaming service link added successfully!</div>";
                    } else {
                        $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Error adding service link: " . $stmt->error . "</div>";
                    }
                    $stmt->close();
                } else {
                    $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Database error: " . $conn->error . "</div>";
                }
            }
            $active_tab = 'streaming_services';
        }
    } elseif ($action === 'update_streaming_service') {
        $streaming_id = (int)$_POST['streaming_id'];
        $movie_id = (int)$_POST['movie_id'];
        $platform_id = (int)$_POST['platform_id'];
        $price_720p = (float)$_POST['price_720p'];
        $price_1080p = (float)$_POST['price_1080p'];
        $price_4k = (float)$_POST['price_4k'];

        if (empty($movie_id) || empty($platform_id)) {
            $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Movie and Platform are required for update.</div>";
            $active_tab = 'streaming_services';
        } else {
            // Check for duplicate entry, excluding the current record being updated
            $check_stmt = $conn->prepare("SELECT COUNT(*) FROM streaming_services WHERE movie_id = ? AND platform_id = ? AND streaming_id != ?");
            $check_stmt->bind_param("iii", $movie_id, $platform_id, $streaming_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result()->fetch_row()[0];
            $check_stmt->close();

            if ($check_result > 0) {
                $message = "<div class='bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative mb-4' role='alert'>This movie is already associated with this platform.</div>";
            } else {
                $stmt = $conn->prepare("UPDATE streaming_services SET movie_id = ?, platform_id = ?, price_720p = ?, price_1080p = ?, price_4k = ? WHERE streaming_id = ?");
                if ($stmt) {
                    $stmt->bind_param("iidddi", $movie_id, $platform_id, $price_720p, $price_1080p, $price_4k, $streaming_id);
                    if ($stmt->execute()) {
                        $message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4' role='alert'>Streaming service link updated successfully!</div>";
                    } else {
                        $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Error updating service link: " . $stmt->error . "</div>";
                    }
                    $stmt->close();
                } else {
                    $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Database error: " . $conn->error . "</div>";
                }
            }
            $active_tab = 'streaming_services';
        }
    } elseif ($action === 'delete_streaming_service') {
        $streaming_id = (int)$_POST['streaming_id'];
        $stmt = $conn->prepare("DELETE FROM streaming_services WHERE streaming_id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $streaming_id);
            if ($stmt->execute()) {
                $message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4' role='alert'>Streaming service link deleted successfully!</div>";
            } else {
                $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Error deleting link: " . $stmt->error . "</div>";
            }
            $stmt->close();
        } else {
            $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Database error: " . $conn->error . "</div>";
        }
        $active_tab = 'streaming_services';
    }
}

// --- Fetch Data for Display (all tabs) ---

// Movies
$movies = [];
$result = $conn->query("SELECT movie_id, title, genre, release_date, language, duration, rating FROM movies ORDER BY title ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $movies[] = $row;
    }
} else {
    $message .= "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Error fetching movies: " . $conn->error . "</div>";
}

// Cinemas
$cinemas = [];
$result = $conn->query("SELECT c.cinema_id, c.name, c.location, ct.type_name, ct.type_id, ct.price FROM cinema c JOIN cinema_type ct ON c.type_id = ct.type_id ORDER BY c.name ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $cinemas[] = $row;
    }
} else {
    $message .= "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Error fetching cinemas: " . $conn->error . "</div>";
}

// Cinema Types for dropdown (for adding/editing cinemas)
$cinema_types = [];
$result = $conn->query("SELECT type_id, type_name, price FROM cinema_type ORDER BY type_name ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $cinema_types[] = $row;
    }
}

// Users (FIXED: Using LEFT JOIN to determine role from admin/viewer tables)
$users = [];
$sql_users = "SELECT u.user_id, u.name, u.email, u.preference,
                     CASE WHEN a.user_id IS NOT NULL THEN 'admin' ELSE 'viewer' END AS user_role
              FROM user u
              LEFT JOIN admin a ON u.user_id = a.user_id
              ORDER BY u.name ASC";
$result_users = $conn->query($sql_users);
if ($result_users) {
    while ($row = $result_users->fetch_assoc()) {
        $users[] = $row;
    }
} else {
    $message .= "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Error fetching users: " . $conn->error . "</div>";
}


// Streaming Platforms
$platforms = [];
$result = $conn->query("SELECT platform_id, platform_name, website FROM streaming_platform ORDER BY platform_name ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $platforms[] = $row;
    }
} else {
    $message .= "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Error fetching streaming platforms: " . $conn->error . "</div>";
}

// Streaming Services (linking movies to platforms with prices)
$streaming_services = [];
$sql = "SELECT ss.streaming_id, m.title AS movie_title, ss.movie_id, sp.platform_name, ss.platform_id,
               ss.price_720p, ss.price_1080p, ss.price_4k
        FROM streaming_services ss
        JOIN movies m ON ss.movie_id = m.movie_id
        JOIN streaming_platform sp ON ss.platform_id = sp.platform_id
        ORDER BY m.title ASC, sp.platform_name ASC";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $streaming_services[] = $row;
    }
} else {
    $message .= "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Error fetching streaming services: " . $conn->error . "</div>";
}

// Close DB connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f2f5;
        }
        .tab-button.active {
            background-color: #4f46e5; /* indigo-600 */
            color: white;
            border-bottom: 2px solid #4f46e5;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        th {
            background-color: #f8fafc;
            font-weight: 600;
            color: #4a5568;
            text-transform: uppercase;
            font-size: 0.75rem;
        }
        tr:hover {
            background-color: #f0f2f5;
        }
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        .action-buttons a, .action-buttons button, .action-buttons .btn {
            padding: 6px 12px;
            border-radius: 5px;
            font-size: 0.875rem;
            text-decoration: none;
            color: white;
            cursor: pointer;
            transition: background-color 0.2s ease-in-out;
            border: none;
        }
        .action-buttons .edit-btn { background-color: #3b82f6; } /* blue-500 */
        .action-buttons .edit-btn:hover { background-color: #2563eb; } /* blue-600 */
        .action-buttons .delete-btn { background-color: #ef4444; } /* red-500 */
        .action-buttons .delete-btn:hover { background-color: #dc2626; } /* red-600 */
        .action-buttons .view-btn { background-color: #10b981; } /* emerald-500 */
        .action-buttons .view-btn:hover { background-color: #059669; } /* emerald-600 */
        .modal {
            display: none; /* Hidden by default */
            position: fixed; /* Stay in place */
            z-index: 1000; /* Sit on top */
            left: 0;
            top: 0;
            width: 100%; /* Full width */
            height: 100%; /* Full height */
            overflow: auto; /* Enable scroll if needed */
            background-color: rgba(0,0,0,0.4); /* Black w/ opacity */
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background-color: #fefefe;
            margin: auto;
            padding: 20px;
            border-radius: 8px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            position: relative;
        }
        .close-button {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            position: absolute;
            top: 10px;
            right: 20px;
        }
        .close-button:hover,
        .close-button:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }
    </style>
</head>
<body class="min-h-screen bg-gray-100 flex flex-col">
    <nav class="bg-indigo-700 p-4 shadow-md">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-white text-2xl font-bold">Admin Dashboard</h1>
            <div class="flex items-center space-x-4">
                <span class="text-white text-lg">Welcome, Admin <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</span>
                <a href="index.php" class="bg-white text-indigo-700 px-4 py-2 rounded-md font-semibold hover:bg-indigo-100">Home</a>
                <a href="logout.php" class="bg-white text-indigo-700 px-4 py-2 rounded-md font-semibold hover:bg-indigo-100">Logout</a>
            </div>
        </div>
    </nav>

    <main class="flex-grow container mx-auto p-6">
        <?php echo $message; // Display messages ?>

        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="p-4 bg-gray-50 border-b border-gray-200">
                <div class="flex space-x-2 overflow-x-auto pb-2"> <button class="tab-button px-4 py-2 rounded-md text-gray-700 font-semibold hover:bg-indigo-100 hover:text-indigo-700 <?php echo ($active_tab === 'movies') ? 'active' : ''; ?>" onclick="openTab('movies')">Movies</button>
                    <button class="tab-button px-4 py-2 rounded-md text-gray-700 font-semibold hover:bg-indigo-100 hover:text-indigo-700 <?php echo ($active_tab === 'cinemas') ? 'active' : ''; ?>" onclick="openTab('cinemas')">Cinemas</button>
                    <button class="tab-button px-4 py-2 rounded-md text-gray-700 font-semibold hover:bg-indigo-100 hover:text-indigo-700 <?php echo ($active_tab === 'users') ? 'active' : ''; ?>" onclick="openTab('users')">Users</button>
                    <button class="tab-button px-4 py-2 rounded-md text-gray-700 font-semibold hover:bg-indigo-100 hover:text-indigo-700 <?php echo ($active_tab === 'platforms') ? 'active' : ''; ?>" onclick="openTab('platforms')">Streaming Platforms</button>
                    <button class="tab-button px-4 py-2 rounded-md text-gray-700 font-semibold hover:bg-indigo-100 hover:text-indigo-700 <?php echo ($active_tab === 'streaming_services') ? 'active' : ''; ?>" onclick="openTab('streaming_services')">Streaming Services</button>
                    <a href="admin_view_bookings.php" class="btn px-4 py-2 rounded-md text-gray-700 font-semibold hover:bg-indigo-100 hover:text-indigo-700 bg-white text-gray-700 border border-gray-300">View Bookings</a>
                    <a href="admin_view_payments.php" class="btn px-4 py-2 rounded-md text-gray-700 font-semibold hover:bg-indigo-100 hover:text-indigo-700 bg-white text-gray-700 border border-gray-300">View Payments</a>
                    <a href="admin_view_subscriptions.php" class="btn px-4 py-2 rounded-md text-gray-700 font-semibold hover:bg-indigo-100 hover:text-indigo-700 bg-white text-gray-700 border border-gray-300">View Subscriptions</a>
                    <a href="admin_view_recommendations.php" class="btn px-4 py-2 rounded-md text-gray-700 font-semibold hover:bg-indigo-100 hover:text-indigo-700 bg-white text-gray-700 border border-gray-300">View Activity Log</a>
                </div>
            </div>

            <div id="movies" class="tab-content p-6 <?php echo ($active_tab === 'movies') ? 'active' : ''; ?>">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Manage Movies</h2>

                <div class="bg-gray-50 p-4 rounded-md mb-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-700 mb-3">Add New Movie</h3>
                    <form action="admin.php" method="POST" class="space-y-3">
                        <input type="hidden" name="action" value="add_movie">
                        <input type="hidden" name="tab" value="movies"> <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="movie_title" class="block text-sm font-medium text-gray-700">Title:</label>
                                <input type="text" id="movie_title" name="title" required
                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="movie_genre" class="block text-sm font-medium text-gray-700">Genre:</label>
                                <input type="text" id="movie_genre" name="genre" required
                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="movie_release_date" class="block text-sm font-medium text-gray-700">Release Date:</label>
                                <input type="date" id="movie_release_date" name="release_date" required
                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="movie_language" class="block text-sm font-medium text-gray-700">Language:</label>
                                <input type="text" id="movie_language" name="language" required
                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="movie_duration" class="block text-sm font-medium text-gray-700">Duration (mins):</label>
                                <input type="number" id="movie_duration" name="duration" required min="1"
                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="movie_rating" class="block text-sm font-medium text-gray-700">Rating (0.0 - 10.0):</label>
                                <input type="number" step="0.1" min="0" max="10" id="movie_rating" name="rating" required
                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            </div>
                        </div>
                        <button type="submit"
                                class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Add Movie
                        </button>
                    </form>
                </div>

                <h3 class="text-lg font-semibold text-gray-700 mb-3">Existing Movies</h3>
                <?php if (!empty($movies)): ?>
                    <div class="overflow-x-auto bg-white rounded-lg shadow-md">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Title</th>
                                    <th>Genre</th>
                                    <th>Release Date</th>
                                    <th>Language</th>
                                    <th>Duration</th>
                                    <th>Rating</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($movies as $movie): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($movie['movie_id']); ?></td>
                                        <td><?php echo htmlspecialchars($movie['title']); ?></td>
                                        <td><?php echo htmlspecialchars($movie['genre']); ?></td>
                                        <td><?php echo htmlspecialchars($movie['release_date']); ?></td>
                                        <td><?php echo htmlspecialchars($movie['language']); ?></td>
                                        <td><?php echo htmlspecialchars($movie['duration']); ?> mins</td>
                                        <td><?php echo htmlspecialchars($movie['rating']); ?></td>
                                        <td class="action-buttons">
                                            <button onclick="openEditMovieModal(<?php echo htmlspecialchars(json_encode($movie)); ?>)" class="edit-btn">Edit</button>
                                            <button onclick="confirmDelete('movie', <?php echo htmlspecialchars($movie['movie_id']); ?>)" class="delete-btn">Delete</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-gray-700 text-center py-4">No movies found.</p>
                <?php endif; ?>
            </div>

            <div id="cinemas" class="tab-content p-6 <?php echo ($active_tab === 'cinemas') ? 'active' : ''; ?>">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Manage Cinemas</h2>

                <div class="bg-gray-50 p-4 rounded-md mb-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-700 mb-3">Add New Cinema</h3>
                    <form action="admin.php" method="POST" class="space-y-3">
                        <input type="hidden" name="action" value="add_cinema">
                        <input type="hidden" name="tab" value="cinemas"> <div>
                            <label for="cinema_name" class="block text-sm font-medium text-gray-700">Cinema Name:</label>
                            <input type="text" id="cinema_name" name="name" required
                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        </div>
                        <div>
                            <label for="cinema_location" class="block text-sm font-medium text-gray-700">Location:</label>
                            <input type="text" id="cinema_location" name="location" required
                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        </div>
                        <div>
                            <label for="cinema_type_id" class="block text-sm font-medium text-gray-700">Cinema Type:</label>
                            <select id="cinema_type_id" name="type_id" required
                                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <option value="">Select Type</option>
                                <?php foreach ($cinema_types as $type): ?>
                                    <option value="<?php echo htmlspecialchars($type['type_id']); ?>"><?php echo htmlspecialchars($type['type_name']); ?> (PKR <?php echo htmlspecialchars(number_format($type['price'], 2)); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit"
                                class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Add Cinema
                        </button>
                    </form>
                </div>

                <h3 class="text-lg font-semibold text-gray-700 mb-3">Existing Cinemas</h3>
                <?php if (!empty($cinemas)): ?>
                    <div class="overflow-x-auto bg-white rounded-lg shadow-md">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Location</th>
                                    <th>Type</th>
                                    <th>Base Price</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cinemas as $cinema): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($cinema['cinema_id']); ?></td>
                                        <td><?php echo htmlspecialchars($cinema['name']); ?></td>
                                        <td><?php echo htmlspecialchars($cinema['location']); ?></td>
                                        <td><?php echo htmlspecialchars($cinema['type_name']); ?></td>
                                        <td>PKR <?php echo htmlspecialchars(number_format($cinema['price'], 2)); ?></td>
                                        <td class="action-buttons">
                                            <button onclick="openEditCinemaModal(<?php echo htmlspecialchars(json_encode($cinema)); ?>)" class="edit-btn">Edit</button>
                                            <button onclick="confirmDelete('cinema', <?php echo htmlspecialchars($cinema['cinema_id']); ?>)" class="delete-btn">Delete</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-gray-700 text-center py-4">No cinemas found.</p>
                <?php endif; ?>
            </div>

            <div id="users" class="tab-content p-6 <?php echo ($active_tab === 'users') ? 'active' : ''; ?>">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Manage Users</h2>

                <div class="bg-gray-50 p-4 rounded-md mb-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-700 mb-3">Add New User</h3>
                    <form action="admin.php" method="POST" class="space-y-3">
                        <input type="hidden" name="action" value="add_user">
                        <input type="hidden" name="tab" value="users"> <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="user_name" class="block text-sm font-medium text-gray-700">Name:</label>
                                <input type="text" id="user_name" name="name" required
                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="user_age" class="block text-sm font-medium text-gray-700">Age:</label>
                                <input type="number" id="user_age" name="age" required min="1"
                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="user_email" class="block text-sm font-medium text-gray-700">Email:</label>
                                <input type="email" id="user_email" name="email" required
                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="user_password" class="block text-sm font-medium text-gray-700">Password:</label>
                                <input type="password" id="user_password" name="password" required
                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="user_preference" class="block text-sm font-medium text-gray-700">Preference:</label>
                                <select id="user_preference" name="preference" required
                                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option value="">Select Preference</option>
                                    <option value="cinema">Cinema</option>
                                    <option value="streaming">Streaming</option>
                                    <option value="both">Both</option>
                                </select>
                            </div>
                            <div>
                                <label for="user_role_to_add" class="block text-sm font-medium text-gray-700">User Role:</label>
                                <select id="user_role_to_add" name="user_role" required
                                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option value="">Select Role</option>
                                    <option value="viewer">Viewer</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                        </div>
                        <button type="submit"
                                class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Add User
                        </button>
                    </form>
                </div>

                <h3 class="text-lg font-semibold text-gray-700 mb-3">Existing Users</h3>
                <?php if (!empty($users)): ?>
                    <div class="overflow-x-auto bg-white rounded-lg shadow-md">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Preference</th>
                                    <th>User Role</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['user_id']); ?></td>
                                        <td><?php echo htmlspecialchars($user['name']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo htmlspecialchars(ucfirst($user['preference'])); ?></td>
                                        <td>
                                            <form action="admin.php" method="POST" class="flex items-center space-x-2">
                                                <input type="hidden" name="action" value="update_user_role">
                                                <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['user_id']); ?>">
                                                <input type="hidden" name="tab" value="users"> <select name="new_role" onchange="this.form.submit()"
                                                        class="px-2 py-1 border border-gray-300 rounded-md shadow-sm text-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                                    <option value="viewer" <?php echo ($user['user_role'] === 'viewer') ? 'selected' : ''; ?>>Viewer</option>
                                                    <option value="admin" <?php echo ($user['user_role'] === 'admin') ? 'selected' : ''; ?>>Admin</option>
                                                </select>
                                            </form>
                                        </td>
                                        <td class="action-buttons">
                                            <?php if ($user['user_id'] !== $_SESSION['user_id']): ?>
                                                <button onclick="confirmDelete('user', <?php echo htmlspecialchars($user['user_id']); ?>)" class="delete-btn">Delete</button>
                                            <?php else: ?>
                                                <span class="text-gray-500 text-sm">Cannot delete self</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-gray-700 text-center py-4">No users found.</p>
                <?php endif; ?>
            </div>

            <div id="platforms" class="tab-content p-6 <?php echo ($active_tab === 'platforms') ? 'active' : ''; ?>">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Manage Streaming Platforms</h2>

                <div class="bg-gray-50 p-4 rounded-md mb-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-700 mb-3">Add New Platform</h3>
                    <form action="admin.php" method="POST" class="space-y-3">
                        <input type="hidden" name="action" value="add_platform">
                        <input type="hidden" name="tab" value="platforms"> <div>
                            <label for="platform_name" class="block text-sm font-medium text-gray-700">Platform Name:</label>
                            <input type="text" id="platform_name" name="platform_name" required
                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        </div>
                        <div>
                            <label for="platform_website" class="block text-sm font-medium text-gray-700">Website URL:</label>
                            <input type="url" id="platform_website" name="website" placeholder="e.g., https://www.example.com"
                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        </div>
                        <button type="submit"
                                class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Add Platform
                        </button>
                    </form>
                </div>

                <h3 class="text-lg font-semibold text-gray-700 mb-3">Existing Platforms</h3>
                <?php if (!empty($platforms)): ?>
                    <div class="overflow-x-auto bg-white rounded-lg shadow-md">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Platform Name</th>
                                    <th>Website</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($platforms as $platform): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($platform['platform_id']); ?></td>
                                        <td><?php echo htmlspecialchars($platform['platform_name']); ?></td>
                                        <td>
                                            <?php if (!empty($platform['website'])): ?>
                                                <a href="<?php echo htmlspecialchars($platform['website']); ?>" target="_blank" class="text-blue-600 hover:underline">Visit Site</a>
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </td>
                                        <td class="action-buttons">
                                            <button onclick="openEditPlatformModal(<?php echo htmlspecialchars(json_encode($platform)); ?>)" class="edit-btn">Edit</button>
                                            <button onclick="confirmDelete('platform', <?php echo htmlspecialchars($platform['platform_id']); ?>)" class="delete-btn">Delete</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-gray-700 text-center py-4">No streaming platforms found.</p>
                <?php endif; ?>
            </div>

            <div id="streaming_services" class="tab-content p-6 <?php echo ($active_tab === 'streaming_services') ? 'active' : ''; ?>">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Manage Streaming Services (Movie-Platform Links)</h2>

                <div class="bg-gray-50 p-4 rounded-md mb-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-700 mb-3">Add New Movie-Platform Link</h3>
                    <form action="admin.php" method="POST" class="space-y-3">
                        <input type="hidden" name="action" value="add_streaming_service">
                        <input type="hidden" name="tab" value="streaming_services"> <div>
                            <label for="service_movie_id" class="block text-sm font-medium text-gray-700">Movie:</label>
                            <select id="service_movie_id" name="movie_id" required
                                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <option value="">Select Movie</option>
                                <?php foreach ($movies as $movie): ?>
                                    <option value="<?php echo htmlspecialchars($movie['movie_id']); ?>"><?php echo htmlspecialchars($movie['title']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="service_platform_id" class="block text-sm font-medium text-gray-700">Streaming Platform:</label>
                            <select id="service_platform_id" name="platform_id" required
                                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <option value="">Select Platform</option>
                                <?php foreach ($platforms as $platform): ?>
                                    <option value="<?php echo htmlspecialchars($platform['platform_id']); ?>"><?php echo htmlspecialchars($platform['platform_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="service_price_720p" class="block text-sm font-medium text-gray-700">Price (720p):</label>
                            <input type="number" step="0.01" min="0" id="service_price_720p" name="price_720p" value="0.00" required
                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        </div>
                        <div>
                            <label for="service_price_1080p" class="block text-sm font-medium text-gray-700">Price (1080p):</label>
                            <input type="number" step="0.01" min="0" id="service_price_1080p" name="price_1080p" value="0.00" required
                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        </div>
                        <div>
                            <label for="service_price_4k" class="block text-sm font-medium text-gray-700">Price (4K):</label>
                            <input type="number" step="0.01" min="0" id="service_price_4k" name="price_4k" value="0.00" required
                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        </div>
                        <button type="submit"
                                class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Add Link
                        </button>
                    </form>
                </div>

                <h3 class="text-lg font-semibold text-gray-700 mb-3">Existing Movie-Platform Links</h3>
                <?php if (!empty($streaming_services)): ?>
                    <div class="overflow-x-auto bg-white rounded-lg shadow-md">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Movie Title</th>
                                    <th>Platform Name</th>
                                    <th>720p Price</th>
                                    <th>1080p Price</th>
                                    <th>4K Price</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($streaming_services as $service): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($service['streaming_id']); ?></td>
                                        <td><?php echo htmlspecialchars($service['movie_title']); ?></td>
                                        <td><?php echo htmlspecialchars($service['platform_name']); ?></td>
                                        <td>PKR <?php echo htmlspecialchars(number_format($service['price_720p'], 2)); ?></td>
                                        <td>PKR <?php echo htmlspecialchars(number_format($service['price_1080p'], 2)); ?></td>
                                        <td>PKR <?php echo htmlspecialchars(number_format($service['price_4k'], 2)); ?></td>
                                        <td class="action-buttons">
                                            <button onclick="openEditStreamingServiceModal(<?php echo htmlspecialchars(json_encode($service)); ?>)" class="edit-btn">Edit</button>
                                            <button onclick="confirmDelete('streaming_service', <?php echo htmlspecialchars($service['streaming_id']); ?>)" class="delete-btn">Delete</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-gray-700 text-center py-4">No movie-platform links found.</p>
                <?php endif; ?>
            </div>

            <div id="admin_views" class="tab-content p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Other Admin Views</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <a href="admin_view_bookings.php" class="bg-indigo-600 text-white p-4 rounded-lg shadow hover:bg-indigo-700 flex items-center justify-center text-lg font-semibold">
                        View & Manage Bookings
                    </a>
                    <a href="admin_view_payments.php" class="bg-indigo-600 text-white p-4 rounded-lg shadow hover:bg-indigo-700 flex items-center justify-center text-lg font-semibold">
                        View & Manage Payments
                    </a>
                    <a href="admin_view_subscriptions.php" class="bg-indigo-600 text-white p-4 rounded-lg shadow hover:bg-indigo-700 flex items-center justify-center text-lg font-semibold">
                        View & Manage Subscriptions
                    </a>
                    <a href="admin_view_recommendations.php" class="bg-indigo-600 text-white p-4 rounded-lg shadow hover:bg-indigo-700 flex items-center justify-center text-lg font-semibold">
                        View & Manage Activity Log
                    </a>
                </div>
            </div>

        </div>
    </main>

    <footer class="bg-gray-800 text-white text-center p-4 mt-auto">
        <div class="container mx-auto">
            <p>&copy; <?php echo date('Y'); ?> Movies Management System. Admin Panel.</p>
        </div>
    </footer>

    <div id="editMovieModal" class="modal">
        <div class="modal-content">
            <span class="close-button" onclick="closeModal('editMovieModal')">&times;</span>
            <h3 class="text-2xl font-bold text-gray-800 mb-4">Edit Movie</h3>
            <form action="admin.php" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="update_movie">
                <input type="hidden" name="tab" value="movies"> <input type="hidden" name="movie_id" id="edit_movie_id">

                <div>
                    <label for="edit_movie_title" class="block text-sm font-medium text-gray-700">Title:</label>
                    <input type="text" id="edit_movie_title" name="title" required
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label for="edit_movie_genre" class="block text-sm font-medium text-gray-700">Genre:</label>
                    <input type="text" id="edit_movie_genre" name="genre" required
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label for="edit_movie_release_date" class="block text-sm font-medium text-gray-700">Release Date:</label>
                    <input type="date" id="edit_movie_release_date" name="release_date" required
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label for="edit_movie_language" class="block text-sm font-medium text-gray-700">Language:</label>
                    <input type="text" id="edit_movie_language" name="language" required
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label for="edit_movie_duration" class="block text-sm font-medium text-gray-700">Duration (mins):</label>
                    <input type="number" id="edit_movie_duration" name="duration" required min="1"
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label for="edit_movie_rating" class="block text-sm font-medium text-gray-700">Rating (0.0 - 10.0):</label>
                    <input type="number" step="0.1" min="0" max="10" id="edit_movie_rating" name="rating" required
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>

                <button type="submit"
                        class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Update Movie
                </button>
            </form>
        </div>
    </div>

    <div id="editCinemaModal" class="modal">
        <div class="modal-content">
            <span class="close-button" onclick="closeModal('editCinemaModal')">&times;</span>
            <h3 class="text-2xl font-bold text-gray-800 mb-4">Edit Cinema</h3>
            <form action="admin.php" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="update_cinema">
                <input type="hidden" name="tab" value="cinemas"> <input type="hidden" name="cinema_id" id="edit_cinema_id">

                <div>
                    <label for="edit_cinema_name" class="block text-sm font-medium text-gray-700">Cinema Name:</label>
                    <input type="text" id="edit_cinema_name" name="name" required
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label for="edit_cinema_location" class="block text-sm font-medium text-gray-700">Location:</label>
                    <input type="text" id="edit_cinema_location" name="location" required
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label for="edit_cinema_type_id" class="block text-sm font-medium text-gray-700">Cinema Type:</label>
                    <select id="edit_cinema_type_id" name="type_id" required
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <?php foreach ($cinema_types as $type): ?>
                            <option value="<?php echo htmlspecialchars($type['type_id']); ?>"><?php echo htmlspecialchars($type['type_name']); ?> (PKR <?php echo htmlspecialchars(number_format($type['price'], 2)); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit"
                        class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Update Cinema
                </button>
            </form>
        </div>
    </div>

    <div id="editPlatformModal" class="modal">
        <div class="modal-content">
            <span class="close-button" onclick="closeModal('editPlatformModal')">&times;</span>
            <h3 class="text-2xl font-bold text-gray-800 mb-4">Edit Streaming Platform</h3>
            <form action="admin.php" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="update_platform">
                <input type="hidden" name="tab" value="platforms"> <input type="hidden" name="platform_id" id="edit_platform_id">

                <div>
                    <label for="edit_platform_name" class="block text-sm font-medium text-gray-700">Platform Name:</label>
                    <input type="text" id="edit_platform_name" name="platform_name" required
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label for="edit_platform_website" class="block text-sm font-medium text-gray-700">Website URL:</label>
                    <input type="url" id="edit_platform_website" name="website" placeholder="e.g., https://www.example.com"
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
                <button type="submit"
                        class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Update Platform
                </button>
            </form>
        </div>
    </div>

    <div id="editStreamingServiceModal" class="modal">
        <div class="modal-content">
            <span class="close-button" onclick="closeModal('editStreamingServiceModal')">&times;</span>
            <h3 class="text-2xl font-bold text-gray-800 mb-4">Edit Movie-Platform Link</h3>
            <form action="admin.php" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="update_streaming_service">
                <input type="hidden" name="tab" value="streaming_services"> <input type="hidden" name="streaming_id" id="edit_streaming_id">

                <div>
                    <label for="edit_service_movie_id" class="block text-sm font-medium text-gray-700">Movie:</label>
                    <select id="edit_service_movie_id" name="movie_id" required
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <?php foreach ($movies as $movie): ?>
                            <option value="<?php echo htmlspecialchars($movie['movie_id']); ?>"><?php echo htmlspecialchars($movie['title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="edit_service_platform_id" class="block text-sm font-medium text-gray-700">Streaming Platform:</label>
                    <select id="edit_service_platform_id" name="platform_id" required
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <?php foreach ($platforms as $platform): ?>
                            <option value="<?php echo htmlspecialchars($platform['platform_id']); ?>"><?php echo htmlspecialchars($platform['platform_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="edit_service_price_720p" class="block text-sm font-medium text-gray-700">Price (720p):</label>
                    <input type="number" step="0.01" min="0" id="edit_service_price_720p" name="price_720p" required
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label for="edit_service_price_1080p" class="block text-sm font-medium text-gray-700">Price (1080p):</label>
                    <input type="number" step="0.01" min="0" id="edit_service_price_1080p" name="price_1080p" required
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label for="edit_service_price_4k" class="block text-sm font-medium text-gray-700">Price (4K):</label>
                    <input type="number" step="0.01" min="0" id="edit_service_price_4k" name="price_4k" required
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
                <button type="submit"
                        class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Update Link
                </button>
            </form>
        </div>
    </div>


    <script>
        // Tab functionality
        function openTab(tabName) {
            var i, tabContent, tabButtons;
            tabContent = document.getElementsByClassName('tab-content');
            for (i = 0; i < tabContent.length; i++) {
                tabContent[i].classList.remove('active');
            }
            tabButtons = document.getElementsByClassName('tab-button');
            for (i = 0; i < tabButtons.length; i++) {
                tabButtons[i].classList.remove('active');
            }
            document.getElementById(tabName).classList.add('active');
            event.currentTarget.classList.add('active');

            // Update URL hash to remember active tab
            history.replaceState(null, null, `admin.php?tab=${tabName}`);
        }

        // Set active tab on page load based on URL hash or default
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const tabFromUrl = urlParams.get('tab');
            if (tabFromUrl) {
                const targetTab = document.getElementById(tabFromUrl);
                const targetButton = document.querySelector(`.tab-button[onclick*="${tabFromUrl}"]`);
                if (targetTab && targetButton) {
                    // Remove active from all first
                    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
                    document.querySelectorAll('.tab-button').forEach(el => el.classList.remove('active'));

                    targetTab.classList.add('active');
                    targetButton.classList.add('active');
                }
            } else {
                // Fallback to default if no tab in URL
                document.getElementById('movies').classList.add('active');
                document.querySelector('.tab-button[onclick*="movies"]').classList.add('active');
            }
        });


        // Modals Functions
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }

        // Movie Edit Modal
        function openEditMovieModal(movie) {
            document.getElementById('edit_movie_id').value = movie.movie_id;
            document.getElementById('edit_movie_title').value = movie.title;
            document.getElementById('edit_movie_genre').value = movie.genre;
            document.getElementById('edit_movie_release_date').value = movie.release_date;
            document.getElementById('edit_movie_language').value = movie.language;
            document.getElementById('edit_movie_duration').value = movie.duration;
            document.getElementById('edit_movie_rating').value = movie.rating;
            document.getElementById('editMovieModal').style.display = 'flex';
        }

        // Cinema Edit Modal
        function openEditCinemaModal(cinema) {
            document.getElementById('edit_cinema_id').value = cinema.cinema_id;
            document.getElementById('edit_cinema_name').value = cinema.name;
            document.getElementById('edit_cinema_location').value = cinema.location;
            document.getElementById('edit_cinema_type_id').value = cinema.type_id; // Set selected option
            document.getElementById('editCinemaModal').style.display = 'flex';
        }

        // Platform Edit Modal
        function openEditPlatformModal(platform) {
            document.getElementById('edit_platform_id').value = platform.platform_id;
            document.getElementById('edit_platform_name').value = platform.platform_name;
            document.getElementById('edit_platform_website').value = platform.website;
            document.getElementById('editPlatformModal').style.display = 'flex';
        }

        // Streaming Service Edit Modal
        function openEditStreamingServiceModal(service) {
            document.getElementById('edit_streaming_id').value = service.streaming_id;
            document.getElementById('edit_service_movie_id').value = service.movie_id;
            document.getElementById('edit_service_platform_id').value = service.platform_id;
            document.getElementById('edit_service_price_720p').value = service.price_720p;
            document.getElementById('edit_service_price_1080p').value = service.price_1080p;
            document.getElementById('edit_service_price_4k').value = service.price_4k;
            document.getElementById('editStreamingServiceModal').style.display = 'flex';
        }

        // Delete Confirmation
        function confirmDelete(entityType, id) {
            if (confirm(`Are you sure you want to delete this ${entityType} (ID: ${id})? This action cannot be undone.`)) {
                // Create a form dynamically to send a POST request for deletion
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'admin.php';

                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = `delete_${entityType}`;
                form.appendChild(actionInput);

                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = `${entityType}_id`;
                idInput.value = id;
                form.appendChild(idInput);

                // Add a hidden input for the current active tab to redirect back correctly
                const tabInput = document.createElement('input');
                tabInput.type = 'hidden';
                tabInput.name = 'tab';
                tabInput.value = document.querySelector('.tab-button.active').onclick.toString().match(/'([^']+)'/)[1];
                form.appendChild(tabInput);

                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
