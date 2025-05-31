<?php
require_once "db_connection.php";
<?php include 'header.php'; ?>
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: registration.php");
    exit();
}

// Get user_id and user role (admin or viewer)
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'viewer';

// Fetch all streaming platforms and their subscription plans
$platforms_sql = "SELECT sp.platform_id, sp.platform_name FROM streaming_platform sp ORDER BY sp.platform_name";
$platforms_result = $conn->query($platforms_sql);

// Fetch user's active subscriptions
$subscriptions_stmt = $conn->prepare("
    SELECT s.subscription_id, s.platform_id, s.start_date, s.end_date, s.plan_type, sp.platform_name
    FROM subscription s
    JOIN streaming_platform sp ON s.platform_id = sp.platform_id
    WHERE s.user_id = ? AND (s.end_date IS NULL OR s.end_date >= CURDATE())
    ORDER BY s.end_date DESC
");
$subscriptions_stmt->bind_param("i", $user_id);
$subscriptions_stmt->execute();
$user_subscriptions_result = $subscriptions_stmt->get_result();

$user_subscriptions = [];
while ($row = $user_subscriptions_result->fetch_assoc()) {
    $user_subscriptions[$row['platform_id']] = $row;  // store subscription keyed by platform
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Streaming Subscriptions</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f9f9f9; padding: 20px; }
        h1 { color: #333; }
        .platform { background: #fff; padding: 15px; margin-bottom: 20px; border-radius: 6px; box-shadow: 0 0 5px #ccc; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 10px; border: 1px solid #ccc; text-align: left; }
        th { background-color: #007bff; color: white; }
        .subscription-info { background: #e9f7ef; padding: 10px; margin-top: 10px; border-radius: 4px; color: #2d6a4f; }
        .no-subscription { color: #a94442; font-weight: bold; }
    </style>
</head>
<body>

<h1>Streaming Platforms and Subscription Plans</h1>

<?php if ($platforms_result->num_rows > 0): ?>
    <?php while ($platform = $platforms_result->fetch_assoc()): ?>
        <div class="platform">
            <h2><?= htmlspecialchars($platform['platform_name']) ?></h2>
            
            <?php
            // Show user's subscription info for this platform if exists
            if (isset($user_subscriptions[$platform['platform_id']])) {
                $sub = $user_subscriptions[$platform['platform_id']];
                echo "<div class='subscription-info'>";
                echo "You are subscribed to the <strong>" . htmlspecialchars($sub['plan_type']) . "</strong> plan.<br>";
                echo "Start Date: " . htmlspecialchars($sub['start_date']) . "<br>";
                echo "End Date: " . ($sub['end_date'] ? htmlspecialchars($sub['end_date']) : 'Ongoing') . "<br>";
                echo "</div>";
            } else {
                echo "<p class='no-subscription'>You have no active subscription on this platform.</p>";
            }

            // Fetch subscription plans (plan_type and their price)
            $plans_stmt = $conn->prepare("
                SELECT DISTINCT plan_type FROM subscription WHERE platform_id = ? ORDER BY plan_type
            ");
            $plans_stmt->bind_param("i", $platform['platform_id']);
            $plans_stmt->execute();
            $plans_result = $plans_stmt->get_result();

            if ($plans_result->num_rows > 0) {
                echo "<table>";
                echo "<tr><th>Plan Type</th><th>Price (Approx.)</th></tr>";

                // For each plan_type, estimate price from payments table by averaging user payments on that plan (or you can design a separate table for plan prices)
                while ($plan = $plans_result->fetch_assoc()) {
                    $plan_type = $plan['plan_type'];
                    // Calculate approximate price from payments where purpose includes platform and plan type
                    $price_stmt = $conn->prepare("
                        SELECT AVG(amount) AS avg_price FROM payments 
                        WHERE user_id = ? AND purpose LIKE CONCAT('%', ?, '%') AND purpose LIKE CONCAT('%', ?, '%')
                    ");
                    $price_stmt->bind_param("iss", $user_id, $platform['platform_name'], $plan_type);
                    $price_stmt->execute();
                    $price_result = $price_stmt->get_result();
                    $price_data = $price_result->fetch_assoc();
                    $price = $price_data['avg_price'] ? number_format($price_data['avg_price'], 2) : "N/A";

                    echo "<tr><td>" . htmlspecialchars($plan_type) . "</td><td>$" . $price . "</td></tr>";
                }
                echo "</table>";
            } else {
                echo "<p>No subscription plans found for this platform.</p>";
            }
            ?>
        </div>
    <?php endwhile; ?>
<?php else: ?>
    <p>No streaming platforms found in the system.</p>
<?php endif; ?>

</body>
</html>
