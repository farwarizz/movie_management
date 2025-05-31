<?php
/**
 * payment.php
 * This file displays the payment history for the logged-in user.
 * It fetches data from the 'payments' table and presents it in a user-friendly format.
 * It also includes a section for a combined view of payments.
 */

// Start a PHP session. This must be the very first thing in your PHP file before any HTML output.
session_start();

// Include the database connection file
require_once 'db_connect.php';

// Check if the user is logged in. If not, redirect to the login page.
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$message = ''; // Variable to store feedback messages for the user

// --- Fetch Individual Payment History for the logged-in user ---
$individual_payments = [];
$sql_individual_payments = "SELECT payment_id, amount, payment_method, payment_date, purpose
                            FROM payments
                            WHERE user_id = ?
                            ORDER BY payment_date DESC, payment_id DESC"; // Order by date, then ID for consistency
$stmt_individual = $conn->prepare($sql_individual_payments);
if ($stmt_individual) {
    $stmt_individual->bind_param("i", $user_id);
    $stmt_individual->execute();
    $result_individual = $stmt_individual->get_result();
    while ($row = $result_individual->fetch_assoc()) {
        $individual_payments[] = $row;
    }
    $stmt_individual->close();
} else {
    $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Database error fetching individual payments: " . $conn->error . "</div>";
}

// --- Fetch Combined Payment History (for all users - typically an admin view) ---
// For a regular user, "combined" might mean a summary or all their own payments.
// For this file, we'll assume "combined" means all payments in the system,
// but we'll only display it if the logged-in user is an admin.
$all_payments = [];
if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin') {
    $sql_all_payments = "SELECT p.payment_id, u.name AS user_name, p.amount, p.payment_method, p.payment_date, p.purpose
                         FROM payments p
                         JOIN user u ON p.user_id = u.user_id
                         ORDER BY p.payment_date DESC, p.payment_id DESC";
    $result_all_payments = $conn->query($sql_all_payments);
    if ($result_all_payments) {
        while ($row = $result_all_payments->fetch_assoc()) {
            $all_payments[] = $row;
        }
    } else {
        $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Database error fetching all payments: " . $conn->error . "</div>";
    }
}


// Close the database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment History - Movies Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f2f5;
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
    </style>
</head>
<body class="min-h-screen bg-gray-100">
    <nav class="bg-indigo-600 p-4 shadow-md">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-white text-2xl font-bold">Payment History</h1>
            <div class="flex items-center space-x-4">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <span class="text-white text-lg">Welcome, <?php echo htmlspecialchars($user_name); ?>!</span>
                    <a href="viewer.php" class="bg-white text-indigo-600 px-4 py-2 rounded-md font-semibold hover:bg-indigo-100">My Dashboard</a>
                    <a href="movies.php" class="bg-white text-indigo-600 px-4 py-2 rounded-md font-semibold hover:bg-indigo-100">Browse All Movies</a>
                    <a href="cinema.php" class="bg-white text-indigo-600 px-4 py-2 rounded-md font-semibold hover:bg-indigo-100">Cinemas</a>
                    <a href="streaming_platform.php" class="bg-white text-indigo-600 px-4 py-2 rounded-md font-semibold hover:bg-indigo-100">Streaming Platforms</a>
                    <a href="subscription.php" class="bg-white text-indigo-600 px-4 py-2 rounded-md font-semibold hover:bg-indigo-100">My Subscriptions</a>
                    <a href="logout.php" class="bg-white text-indigo-600 px-4 py-2 rounded-md font-semibold hover:bg-indigo-100">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="bg-white text-indigo-600 px-4 py-2 rounded-md font-semibold hover:bg-indigo-100">Login</a>
                    <a href="registration.php" class="bg-white text-indigo-600 px-4 py-2 rounded-md font-semibold hover:bg-indigo-100">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="container mx-auto p-6">
        <?php echo $message; // Display general messages here ?>

        <h2 class="text-3xl font-bold text-gray-800 mb-6">My Payment History</h2>

        <?php if (!empty($individual_payments)): ?>
            <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
                <div class="overflow-x-auto">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Date</th>
                                <th>Purpose</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($individual_payments as $payment): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($payment['payment_id']); ?></td>
                                    <td>$<?php echo htmlspecialchars(number_format($payment['amount'], 2)); ?></td>
                                    <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                    <td><?php echo htmlspecialchars($payment['payment_date']); ?></td>
                                    <td><?php echo htmlspecialchars($payment['purpose']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <p class="text-gray-700 text-lg text-center py-10">You have no payment history yet.</p>
        <?php endif; ?>

        <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin'): ?>
            <h2 class="text-3xl font-bold text-gray-800 mb-6 mt-8">All Payments (Admin View)</h2>
            <?php if (!empty($all_payments)): ?>
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="overflow-x-auto">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>User</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Date</th>
                                    <th>Purpose</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_payments as $payment): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($payment['payment_id']); ?></td>
                                        <td><?php echo htmlspecialchars($payment['user_name']); ?></td>
                                        <td>$<?php echo htmlspecialchars(number_format($payment['amount'], 2)); ?></td>
                                        <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                        <td><?php echo htmlspecialchars($payment['payment_date']); ?></td>
                                        <td><?php echo htmlspecialchars($payment['purpose']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <p class="text-gray-700 text-lg text-center py-10">No payments found in the system.</p>
            <?php endif; ?>
        <?php endif; ?>

    </div>
</body>
</html>
