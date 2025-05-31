<?php
/**
 * subscription.php
 * This file displays a logged-in user's current streaming subscriptions.
 * It also provides a form to subscribe to new streaming plans (with simulated plan details).
 * It interacts with the 'subscription', 'streaming_platform', and 'payments' tables.
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

// --- Define Simulated Subscription Plans (In a real app, these would come from a DB table) ---
$available_plans = [
    'basic_monthly' => ['name' => 'Basic Monthly', 'price' => 9.99, 'description' => 'Access to standard library, SD quality.'],
    'premium_monthly' => ['name' => 'Premium Monthly', 'price' => 14.99, 'description' => 'Full library access, HD/4K, multiple screens.'],
    'yearly_basic' => ['name' => 'Yearly Basic', 'price' => 99.99, 'description' => 'Discounted annual basic plan.'],
    'yearly_premium' => ['name' => 'Yearly Premium', 'price' => 149.99, 'description' => 'Discounted annual premium plan.'],
];

// --- Handle Subscription Form Submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'subscribe') {
    $platform_id = (int)$_POST['platform_id'];
    $plan_key = $conn->real_escape_string($_POST['plan_type']); // This is the key from $available_plans
    $start_date = date('Y-m-d');

    if (!isset($available_plans[$plan_key])) {
        $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Invalid plan selected.</div>";
    } else {
        $selected_plan = $available_plans[$plan_key];
        $plan_name = $selected_plan['name'];
        $plan_price = $selected_plan['price'];

        // Calculate end date based on plan type (simple approximation)
        $end_date = date('Y-m-d', strtotime($start_date . ($plan_key == 'basic_monthly' || $plan_key == 'premium_monthly' ? '+1 month' : '+1 year')));

        $conn->begin_transaction();
        try {
            // Check if user already has an active subscription for this platform
            $stmt_check = $conn->prepare("SELECT subscription_id FROM subscription WHERE user_id = ? AND platform_id = ? AND end_date >= CURDATE()");
            $stmt_check->bind_param("ii", $user_id, $platform_id);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            if ($result_check->num_rows > 0) {
                throw new Exception("You already have an active subscription for this platform.");
            }
            $stmt_check->close();

            // Insert into payments table
            $stmt_payment = $conn->prepare("INSERT INTO payments (user_id, amount, payment_method, payment_date, purpose) VALUES (?, ?, ?, ?, ?)");
            $payment_method = 'Credit Card'; // Default for now
            $purpose = 'Streaming Subscription: ' . $plan_name;
            $stmt_payment->bind_param("idss", $user_id, $plan_price, $payment_method, $start_date, $purpose);
            if (!$stmt_payment->execute()) {
                throw new Exception("Error recording payment: " . $stmt_payment->error);
            }
            $payment_id = $conn->insert_id;
            $stmt_payment->close();

            // Insert into subscription table
            $stmt_sub = $conn->prepare("INSERT INTO subscription (user_id, platform_id, start_date, end_date, plan_type) VALUES (?, ?, ?, ?, ?)");
            if ($stmt_sub) {
                $stmt_sub->bind_param("iisss", $user_id, $platform_id, $start_date, $end_date, $plan_name);
                if (!$stmt_sub->execute()) {
                    throw new Exception("Error creating subscription: " . $stmt_sub->error);
                }
                $subscription_id = $conn->insert_id;
                $stmt_sub->close();
            } else {
                throw new Exception("Database error preparing subscription statement: " . $conn->error);
            }

            $conn->commit();
            $message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4' role='alert'>" .
                       "<p class='font-bold mb-2'>Subscription successful!</p>" .
                       "<p><strong>Subscription ID:</strong> {$subscription_id}</p>" .
                       "<p><strong>Platform:</strong> " . htmlspecialchars($platform_details_map[$platform_id]['platform_name'] ?? 'N/A') . "</p>" .
                       "<p><strong>Plan:</strong> " . htmlspecialchars($plan_name) . "</p>" .
                       "<p><strong>Price:</strong> $" . htmlspecialchars(number_format($plan_price, 2)) . "</p>" .
                       "<p><strong>Start Date:</strong> " . htmlspecialchars($start_date) . "</p>" .
                       "<p><strong>End Date:</strong> " . htmlspecialchars($end_date) . "</p>" .
                       "<p><strong>Payment ID:</strong> {$payment_id}</p>" .
                       "</div>";
        } catch (Exception $e) {
            $conn->rollback();
            $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Subscription failed: " . $e->getMessage() . "</div>";
        }
    }
}

// --- Fetch User's Current Subscriptions ---
$user_subscriptions = [];
$sql_subscriptions = "SELECT s.subscription_id, sp.platform_name, s.start_date, s.end_date, s.plan_type
                      FROM subscription s
                      JOIN streaming_platform sp ON s.platform_id = sp.platform_id
                      WHERE s.user_id = ?
                      ORDER BY s.end_date DESC";
$stmt_user_subs = $conn->prepare($sql_subscriptions);
if ($stmt_user_subs) {
    $stmt_user_subs->bind_param("i", $user_id);
    $stmt_user_subs->execute();
    $result_user_subs = $stmt_user_subs->get_result();
    while ($row = $result_user_subs->fetch_assoc()) {
        $user_subscriptions[] = $row;
    }
    $stmt_user_subs->close();
}

// --- Fetch All Streaming Platforms (for the subscription form dropdown) ---
$streaming_platforms = [];
$platform_details_map = []; // For easy lookup by platform_id
$result_platforms = $conn->query("SELECT platform_id, platform_name FROM streaming_platform ORDER BY platform_name ASC");
if ($result_platforms) {
    while ($row = $result_platforms->fetch_assoc()) {
        $streaming_platforms[] = $row;
        $platform_details_map[$row['platform_id']] = $row;
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
    <title>My Subscriptions - Movies Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f2f5;
        }
    </style>
</head>
<body class="min-h-screen bg-gray-100">
    <nav class="bg-indigo-600 p-4 shadow-md">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-white text-2xl font-bold">My Subscriptions</h1>
            <div class="flex items-center space-x-4">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <span class="text-white text-lg">Welcome, <?php echo htmlspecialchars($user_name); ?>!</span>
                    <a href="viewer.php" class="bg-white text-indigo-600 px-4 py-2 rounded-md font-semibold hover:bg-indigo-100">My Dashboard</a>
                    <a href="movies.php" class="bg-white text-indigo-600 px-4 py-2 rounded-md font-semibold hover:bg-indigo-100">Browse All Movies</a>
                    <a href="cinema.php" class="bg-white text-indigo-600 px-4 py-2 rounded-md font-semibold hover:bg-indigo-100">Cinemas</a>
                    <a href="streaming_platform.php" class="bg-white text-indigo-600 px-4 py-2 rounded-md font-semibold hover:bg-indigo-100">Streaming Platforms</a>
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

        <h2 class="text-3xl font-bold text-gray-800 mb-6">My Current Subscriptions</h2>

        <?php if (!empty($user_subscriptions)): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                <?php foreach ($user_subscriptions as $sub): ?>
                    <div class="bg-white rounded-lg shadow-md overflow-hidden p-6 border border-indigo-200">
                        <h3 class="text-xl font-semibold text-gray-800 mb-2"><?php echo htmlspecialchars($sub['platform_name']); ?></h3>
                        <p class="text-gray-600 text-sm mb-1">Plan: <span class="font-medium"><?php echo htmlspecialchars($sub['plan_type']); ?></span></p>
                        <p class="text-gray-600 text-sm mb-1">Start Date: <span class="font-medium"><?php echo htmlspecialchars($sub['start_date']); ?></span></p>
                        <p class="text-gray-600 text-sm mb-1">End Date: <span class="font-medium"><?php echo htmlspecialchars($sub['end_date']); ?></span></p>
                        <?php
                            $status_class = 'bg-gray-100 text-gray-800';
                            $status_text = 'Unknown';
                            if (strtotime($sub['end_date']) >= strtotime(date('Y-m-d'))) {
                                $status_class = 'bg-green-100 text-green-800';
                                $status_text = 'Active';
                            } else {
                                $status_class = 'bg-red-100 text-red-800';
                                $status_text = 'Expired';
                            }
                        ?>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $status_class; ?> mt-3">
                            <?php echo $status_text; ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-gray-700 text-lg text-center py-10">You do not have any active subscriptions yet.</p>
        <?php endif; ?>

        <h2 class="text-3xl font-bold text-gray-800 mb-6 mt-8">Available Subscription Plans</h2>

        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h3 class="text-xl font-semibold text-gray-700 mb-4">Choose a Plan to Subscribe</h3>
            <form action="subscription.php" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="subscribe">

                <div>
                    <label for="platform_id" class="block text-sm font-medium text-gray-700">Select Platform:</label>
                    <select id="platform_id" name="platform_id" required
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <option value="">-- Select a Streaming Platform --</option>
                        <?php foreach ($streaming_platforms as $platform): ?>
                            <option value="<?php echo htmlspecialchars($platform['platform_id']); ?>"><?php echo htmlspecialchars($platform['platform_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="plan_type" class="block text-sm font-medium text-gray-700">Select Plan:</label>
                    <select id="plan_type" name="plan_type" required
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <option value="">-- Select a Plan Type --</option>
                        <?php foreach ($available_plans as $key => $plan): ?>
                            <option value="<?php echo htmlspecialchars($key); ?>">
                                <?php echo htmlspecialchars($plan['name']); ?> - $<?php echo htmlspecialchars(number_format($plan['price'], 2)); ?> (<?php echo htmlspecialchars($plan['description']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit"
                        class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Subscribe Now
                </button>
            </form>
        </div>

    </div>
</body>
</html>
