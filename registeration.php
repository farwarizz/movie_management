<?php
// Start the session
<?php include 'header.php'; ?>
session_start();

// Include database connection
require_once "db_connection.php";

// Handle registration form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["register"])) {
    $name = $_POST["name"];
    $age = $_POST["age"];
    $email = $_POST["email"];
    $password = password_hash($_POST["password"], PASSWORD_DEFAULT);
    $preference = $_POST["preference"];
    $role = $_POST["role"]; // 'admin' or 'viewer'

    // Check if email already exists
    $checkQuery = "SELECT * FROM user WHERE email = ?";
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $error = "Email already registered!";
    } else {
        // Insert into user table
        $insertUser = "INSERT INTO user (name, age, email, password, preference) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insertUser);
        $stmt->bind_param("sisss", $name, $age, $email, $password, $preference);
        if ($stmt->execute()) {
            $user_id = $stmt->insert_id;

            // Insert into admin or viewer table
            if ($role === "admin") {
                $insertRole = "INSERT INTO admin (user_id) VALUES (?)";
            } else {
                $insertRole = "INSERT INTO viewer (user_id) VALUES (?)";
            }
            $stmt = $conn->prepare($insertRole);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();

            $success = "Registration successful! You can now log in.";
        } else {
            $error = "Registration failed. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Movie Management - Registration</title>
    <style>
        body {
            font-family: Arial;
            background: #f5f5f5;
            padding: 20px;
        }
        .container {
            width: 400px;
            margin: auto;
            background: white;
            padding: 30px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            border-radius: 8px;
        }
        h2 {
            text-align: center;
        }
        input, select {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
        }
        .success {
            color: green;
            text-align: center;
        }
        .error {
            color: red;
            text-align: center;
        }
        button {
            width: 100%;
            padding: 12px;
            background-color: #5cb85c;
            color: white;
            border: none;
            cursor: pointer;
            margin-top: 10px;
        }
        .login-link {
            margin-top: 15px;
            text-align: center;
        }
        .login-link a {
            color: #337ab7;
            text-decoration: none;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>User Registration</h2>

    <?php if (isset($success)) echo "<p class='success'>$success</p>"; ?>
    <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>

    <form method="POST" action="">
        <input type="text" name="name" placeholder="Full Name" required>
        <input type="number" name="age" placeholder="Age" required min="0">
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="password" placeholder="Password" required>

        <label for="preference">Preference:</label>
        <select name="preference" required>
            <option value="">-- Select Preference --</option>
            <option value="cinema">Cinema</option>
            <option value="streaming">Streaming</option>
            <option value="both">Both</option>
        </select>

        <label for="role">Register As:</label>
        <select name="role" required>
            <option value="">-- Select Role --</option>
            <option value="viewer">Viewer</option>
            <option value="admin">Admin</option>
        </select>

        <button type="submit" name="register">Register</button>
    </form>

    <div class="login-link">
        <p>Already registered? <a href="login.php">Login Here</a></p>
    </div>
</div>
</body>
</html>
