<?php
/**
 * login.php
 * This file handles user login for the Movies Management System.
 * It provides an HTML form for users to enter their email and password.
 * Upon successful login, it starts a PHP session and redirects the user to viewer.php.
 */

// Start a PHP session. This must be the very first thing in your PHP file before any HTML output.
session_start();

// Include the database connection file
require_once 'db_connect.php';

$login_message = ''; // Variable to store feedback messages for the user

// Check if the form has been submitted using POST method
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and retrieve form data
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'];

    // Basic validation
    if (empty($email) || empty($password)) {
        $login_message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Email and password are required.</div>";
    } else {
        // Prepare SQL statement to fetch user by email
        // Using prepared statements to prevent SQL injection
        $stmt = $conn->prepare("SELECT user_id, name, email, password, preference FROM user WHERE email = ?");
        if ($stmt === false) {
            $login_message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Database error: " . $conn->error . "</div>";
        } else {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                // User found, fetch user data
                $user = $result->fetch_assoc();

                // Verify the submitted password against the hashed password in the database
                if (password_verify($password, $user['password'])) {
                    // Password is correct, start session and store user data
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_preference'] = $user['preference'];

                    // Determine user type (viewer or admin)
                    // Check if user_id exists in the 'viewer' table
                    $stmt_type = $conn->prepare("SELECT user_id FROM viewer WHERE user_id = ?");
                    $stmt_type->bind_param("i", $user['user_id']);
                    $stmt_type->execute();
                    $result_type = $stmt_type->get_result();

                    if ($result_type->num_rows === 1) {
                        $_SESSION['user_type'] = 'viewer';
                    } else {
                        // If not a viewer, check if they are an admin
                        $stmt_type = $conn->prepare("SELECT user_id FROM admin WHERE user_id = ?");
                        $stmt_type->bind_param("i", $user['user_id']);
                        $stmt_type->execute();
                        $result_type = $stmt_type->get_result();
                        if ($result_type->num_rows === 1) {
                            $_SESSION['user_type'] = 'admin';
                        } else {
                            // This case should ideally not happen if registration works correctly
                            $_SESSION['user_type'] = 'unknown';
                        }
                    }
                    $stmt_type->close();

                    // Redirect to viewer.php (or admin.php if user_type is admin)
                    if ($_SESSION['user_type'] == 'admin') {
                        header("Location: admin.php"); // Assuming you'll create admin.php later
                    } else {
                        header("Location: viewer.php");
                    }
                    exit(); // Important to stop script execution after redirection
                } else {
                    $login_message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Invalid email or password.</div>";
                }
            } else {
                $login_message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Invalid email or password.</div>";
            }
            $stmt->close();
        }
    }
}

// Close the database connection when the script finishes
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Movies Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f2f5;
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen bg-gray-100 p-4">
    <div class="bg-white p-8 rounded-lg shadow-xl w-full max-w-md">
        <h2 class="text-3xl font-bold text-center text-gray-800 mb-6">Login</h2>

        <?php echo $login_message; // Display login messages here ?>

        <form action="login.php" method="POST" class="space-y-5">
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Email:</label>
                <input type="email" id="email" name="email" required
                       class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">Password:</label>
                <input type="password" id="password" name="password" required
                       class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            </div>

            <button type="submit"
                    class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                Login
            </button>
        </form>

        <div class="mt-6 text-center">
            <p class="text-sm text-gray-600">Don't have an account?
                <a href="registration.php" class="font-medium text-indigo-600 hover:text-indigo-500">Register here</a>
            </p>
        </div>
    </div>
</body>
</html>
