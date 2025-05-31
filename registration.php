<?php
/**
 * registration.php
 * This file handles user registration for the Movies Management System.
 * It provides an HTML form for users to register as either a 'viewer' or 'admin'.
 * User data (name, age, email, password, preference) is stored in the 'user' table.
 * Additionally, the user_id is stored in either the 'viewer' or 'admin' table based on selection.
 * Passwords are hashed for security.
 */

// Include the database connection file
require_once 'db_connect.php';

$registration_message = ''; // Variable to store feedback messages for the user

// Check if the form has been submitted using POST method
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and retrieve form data
    // mysqli_real_escape_string is used to prevent SQL injection
    $name = $conn->real_escape_string($_POST['name']);
    $age = (int)$_POST['age']; // Cast to integer
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password']; // Password will be hashed
    $preference = $conn->real_escape_string($_POST['preference']);
    $user_type = $conn->real_escape_string($_POST['user_type']); // 'viewer' or 'admin'

    // Validate input (basic validation)
    if (empty($name) || empty($age) || empty($email) || empty($password) || empty($preference) || empty($user_type)) {
        $registration_message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>All fields are required.</div>";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $registration_message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Invalid email format.</div>";
    } elseif ($age <= 0) {
        $registration_message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Age must be a positive number.</div>";
    } else {
        // Hash the password for security before storing it in the database
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Start a database transaction for atomicity
        // This ensures that either both inserts (user and viewer/admin) succeed, or neither does.
        $conn->begin_transaction();

        try {
            // Prepare and execute the SQL statement to insert into the 'user' table
            // Using prepared statements to prevent SQL injection
            $stmt_user = $conn->prepare("INSERT INTO user (name, age, email, password, preference) VALUES (?, ?, ?, ?, ?)");
            if ($stmt_user === false) {
                throw new Exception("Prepare statement failed for user: " . $conn->error);
            }
            $stmt_user->bind_param("sisss", $name, $age, $email, $hashed_password, $preference);

            if (!$stmt_user->execute()) {
                // Check for duplicate email error (MySQL error code 1062)
                if ($stmt_user->errno == 1062) {
                    throw new Exception("Email already registered. Please use a different email or log in.");
                } else {
                    throw new Exception("Error registering user: " . $stmt_user->error);
                }
            }

            // Get the user_id of the newly inserted user
            $user_id = $conn->insert_id;

            // Insert into 'viewer' or 'admin' table based on user_type
            if ($user_type == 'viewer') {
                $stmt_specialization = $conn->prepare("INSERT INTO viewer (user_id) VALUES (?)");
            } elseif ($user_type == 'admin') {
                $stmt_specialization = $conn->prepare("INSERT INTO admin (user_id) VALUES (?)");
            } else {
                throw new Exception("Invalid user type selected.");
            }

            if ($stmt_specialization === false) {
                throw new Exception("Prepare statement failed for specialization: " . $conn->error);
            }
            $stmt_specialization->bind_param("i", $user_id);

            if (!$stmt_specialization->execute()) {
                throw new Exception("Error assigning user type: " . $stmt_specialization->error);
            }

            // If all operations are successful, commit the transaction
            $conn->commit();
            $registration_message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4' role='alert'>Registration successful! You can now log in.</div>";

            // Clear form fields after successful registration (optional)
            $_POST = array();

        } catch (Exception $e) {
            // If any error occurs, rollback the transaction
            $conn->rollback();
            $registration_message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Error: " . $e->getMessage() . "</div>";
        } finally {
            // Close prepared statements
            if (isset($stmt_user) && $stmt_user) {
                $stmt_user->close();
            }
            if (isset($stmt_specialization) && $stmt_specialization) {
                $stmt_specialization->close();
            }
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
    <title>Register - Movies Management System</title>
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
        <h2 class="text-3xl font-bold text-center text-gray-800 mb-6">Register</h2>

        <?php echo $registration_message; // Display registration messages here ?>

        <form action="registration.php" method="POST" class="space-y-5">
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700">Name:</label>
                <input type="text" id="name" name="name" required
                       class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                       value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
            </div>

            <div>
                <label for="age" class="block text-sm font-medium text-gray-700">Age:</label>
                <input type="number" id="age" name="age" required min="1"
                       class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                       value="<?php echo htmlspecialchars($_POST['age'] ?? ''); ?>">
            </div>

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

            <div>
                <label for="preference" class="block text-sm font-medium text-gray-700">Preference:</label>
                <select id="preference" name="preference" required
                        class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    <option value="">Select a preference</option>
                    <option value="cinema" <?php echo (($_POST['preference'] ?? '') == 'cinema') ? 'selected' : ''; ?>>Cinema</option>
                    <option value="streaming" <?php echo (($_POST['preference'] ?? '') == 'streaming') ? 'selected' : ''; ?>>Streaming</option>
                    <option value="both" <?php echo (($_POST['preference'] ?? '') == 'both') ? 'selected' : ''; ?>>Both</option>
                </select>
            </div>

            <div class="flex items-center space-x-6">
                <span class="text-sm font-medium text-gray-700">Register as:</span>
                <div class="flex items-center">
                    <input type="radio" id="viewer" name="user_type" value="viewer" required
                           class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300"
                           <?php echo (($_POST['user_type'] ?? '') == 'viewer' || !isset($_POST['user_type'])) ? 'checked' : ''; ?>>
                    <label for="viewer" class="ml-2 block text-sm text-gray-900">Viewer</label>
                </div>
                <div class="flex items-center">
                    <input type="radio" id="admin" name="user_type" value="admin" required
                           class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300"
                           <?php echo (($_POST['user_type'] ?? '') == 'admin') ? 'checked' : ''; ?>>
                    <label for="admin" class="ml-2 block text-sm text-gray-900">Admin</label>
                </div>
            </div>

            <button type="submit"
                    class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                Register
            </button>
        </form>

        <div class="mt-6 text-center">
            <p class="text-sm text-gray-600">Already have an account?
                <a href="login.php" class="font-medium text-indigo-600 hover:text-indigo-500">Login here</a>
            </p>
        </div>
    </div>
</body>
</html>
