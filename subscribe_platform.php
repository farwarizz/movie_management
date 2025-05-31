<?php
/**
 * subscribe_platform.php
 * This file handles the streaming platform subscription process.
 * It allows the user to select a plan type and simulates a payment,
 * then records the subscription for the viewer.
 */

session_start();
require_once 'db_connect.php';

// Check if user is logged in and is a viewer
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'viewer') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';

$platform_id = isset($_GET['platform_id']) ? (int)$_GET['platform_id'] : 0;

$platform_name = '';

if ($platform_id <= 0) {
    $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Invalid platform selected.</div>";
} else {
    // Fetch platform details
    $stmt_platform = $conn->prepare("SELECT platform_name FROM streaming_platform WHERE platform_id = ?");
    if ($stmt_platform) {
        $stmt_platform->bind_param("i", $platform_id);
        $stmt_platform->execute();
        $result_platform = $stmt_platform->get_result();
        if ($row_platform = $result_platform->fetch_assoc()) {
            $platform_name = htmlspecialchars($row_platform['platform_name']);
        } else {
            $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Platform not found.</div>";
            $platform_id = 0; // Invalidate ID if not found
        }
        $stmt_platform->close();
    } else {
        $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Database error fetching platform details: " . $conn->error . "</div>";
    }
}

// Handle subscription and payment submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'process_subscription') {
    if ($platform_id > 0) {
        $payment_method = $_POST['payment_method'];
        $plan_type = $_POST['plan_type'];
        $amount = (float)$_POST['amount']; // Amount is now entered by user or fixed here

        $start_date = date('Y-m-d'); // Subscription starts today
        $end_date = date('Y-m-d', strtotime('+1 month')); // For simplicity, 1-month subscription

        // Basic validation
        if (empty($payment_method) || empty($plan_type) || $amount <= 0) {
            $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>All fields are required and amount must be positive.</div>";
        } else {
            // Start transaction for atomicity
            $conn->begin_transaction();

            try {
                // 1. Record the payment
                $purpose = "Subscription for " . $platform_name . " (" . $plan_type . ")";
                $stmt_payment = $conn->prepare("INSERT INTO payments (user_id, amount, payment_method, payment_date, purpose) VALUES (?, ?, ?, ?, ?)");
                if (!$stmt_payment) {
                    throw new mysqli_sql_exception("Prepare payment failed: " . $conn->error);
                }
                $stmt_payment->bind_param("idsss", $user_id, $amount, $payment_method, date('Y-m-d'), $purpose); // Changed date to Y-m-d as per schema
                if (!$stmt_payment->execute()) {
                    throw new mysqli_sql_exception("Execute payment failed: " . $stmt_payment->error);
                }
                $stmt_payment->close();

                // 2. Record the subscription
                $stmt_subscription = $conn->prepare("INSERT INTO subscription (user_id, platform_id, start_date, end_date, plan_type) VALUES (?, ?, ?, ?, ?)");
                if (!$stmt_subscription) {
                    throw new mysqli_sql_exception("Prepare subscription failed: " . $conn->error);
                }
                $stmt_subscription->bind_param("iisss", $user_id, $platform_id, $start_date, $end_date, $plan_type);
                if (!$stmt_subscription->execute()) {
                    throw new mysqli_sql_exception("Execute subscription failed: " . $stmt_subscription->error);
                }
                $stmt_subscription->close();

                // Commit transaction
                $conn->commit();
                header("Location: viewer.php?message=" . urlencode("Subscription to " . $platform_name . " (" . $plan_type . ") successful!"));
                exit();

            } catch (mysqli_sql_exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Error processing subscription: " . $e->getMessage() . "</div>";
            }
        }
    } else {
        $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Cannot process subscription: Invalid platform selected.</div>";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscribe to <?php echo $platform_name; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f2f5;
        }
        .container {
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 24px;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #4a5568;
        }
        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            box-sizing: border-box;
        }
        .btn {
            padding: 10px 20px;
            border-radius: 5px;
            font-weight: 600;
            text-decoration: none;
            transition: background-color 0.2s ease-in-out;
            display: inline-block;
            cursor: pointer;
        }
        .btn-green {
            background-color: #22c55e; /* green-500 */
            color: white;
            border: none;
        }
        .btn-green:hover {
            background-color: #16a34a; /* green-600 */
        }
        .btn-secondary {
            background-color: #6b7280; /* gray-500 */
            color: white;
            border: none;
        }
        .btn-secondary:hover {
            background-color: #4b5563; /* gray-600 */
        }
    </style>
</head>
<body class="min-h-screen bg-gray-100 flex flex-col">
    <nav class="bg-blue-700 p-4 shadow-md">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-white text-2xl font-bold">Subscribe to <?php echo $platform_name; ?></h1>
            <div class="flex items-center space-x-4">
                <a href="viewer.php" class="bg-white text-blue-700 px-4 py-2 rounded-md font-semibold hover:bg-blue-100">Back to Dashboard</a>
                <a href="logout.php" class="bg-white text-blue-700 px-4 py-2 rounded-md font-semibold hover:bg-blue-100">Logout</a>
            </div>
        </div>
    </nav>

    <main class="flex-grow container mx-auto p-6">
        <?php echo $message; // Display messages ?>

        <?php if ($platform_id > 0): ?>
            <div class="container">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Subscription Details for <?php echo $platform_name; ?></h2>
                <p class="mb-4 text-gray-600">Please select your desired plan and payment method.</p>

                <form action="subscribe_platform.php?platform_id=<?php echo htmlspecialchars($platform_id); ?>" method="POST">
                    <input type="hidden" name="action" value="process_subscription">

                    <div class="form-group">
                        <label for="plan_type">Select Plan Type:</label>
                        <select id="plan_type" name="plan_type" required>
                            <option value="">Choose a plan</option>
                            <option value="Basic Monthly">Basic Monthly</option>
                            <option value="Standard Monthly">Standard Monthly</option>
                            <option value="Premium Monthly">Premium Monthly</option>
                            <option value="Annual">Annual</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="amount">Subscription Amount (PKR):</label>
                        <input type="number" step="0.01" min="0" id="amount" name="amount" required placeholder="e.g., 500.00">
                    </div>

                    <div class="form-group">
                        <label for="payment_method">Payment Method:</label>
                        <select id="payment_method" name="payment_method" required>
                            <option value="">Select Method</option>
                            <option value="Credit Card">Credit Card</option>
                            <option value="Debit Card">Debit Card</option>
                            <option value="JazzCash">JazzCash</option>
                            <option value="EasyPaisa">EasyPaisa</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                        </select>
                    </div>
                    
                    <p class="text-sm text-gray-500 mb-4">Note: This is a simulated payment. In a real application, you would be redirected to a payment gateway.</p>

                    <div class="flex space-x-4 mt-6">
                        <button type="submit" class="btn btn-green">Confirm and Subscribe</button>
                        <a href="viewer_streaming_services.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <p class="text-gray-600 text-center">Cannot proceed with subscription due to missing platform information.</p>
        <?php endif; ?>
    </main>

    <footer class="bg-gray-800 text-white text-center p-4 mt-auto">
        <div class="container mx-auto">
            <p>&copy; <?php echo date('Y'); ?> MovieVerse. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>
