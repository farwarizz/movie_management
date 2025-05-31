<?php
require_once "db_connection.php";
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: registration.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'viewer';

// Fetch individual payment history for current user
$payment_history_sql = "SELECT payment_id, amount, payment_method, payment_date, purpose FROM payments WHERE user_id = ? ORDER BY payment_date DESC";
$stmt = $conn->prepare($payment_history_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$payment_history = $stmt->get_result();

// Fetch combined payment summary (by payment method) for current user
$combined_summary_sql = "SELECT payment_method, SUM(amount) AS total_amount, COUNT(*) AS payment_count FROM payments WHERE user_id = ? GROUP BY payment_method";
$summary_stmt = $conn->prepare($combined_summary_sql);
$summary_stmt->bind_param("i", $user_id);
$summary_stmt->execute();
$combined_summary = $summary_stmt->get_result();

// If admin, fetch all payments for all users
if ($user_role === 'admin') {
    $all_payments_sql = "
        SELECT p.payment_id, p.amount, p.payment_method, p.payment_date, p.purpose, u.name AS user_name, u.email
        FROM payments p
        JOIN user u ON p.user_id = u.user_id
        ORDER BY p.payment_date DESC
    ";
    $all_payments_result = $conn->query($all_payments_sql);
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Payment History</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f0f0f0; }
        h1, h2 { color: #333; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 30px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #007bff; color: white; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .summary { background-color: #e9ecef; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
    </style>
</head>
<body>

<h1>Your Payment History</h1>

<?php if ($payment_history->num_rows > 0): ?>
    <table>
        <thead>
            <tr>
                <th>Payment ID</th>
                <th>Amount ($)</th>
                <th>Payment Method</th>
                <th>Payment Date</th>
                <th>Purpose</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $payment_history->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['payment_id']) ?></td>
                    <td><?= number_format($row['amount'], 2) ?></td>
                    <td><?= htmlspecialchars($row['payment_method']) ?></td>
                    <td><?= htmlspecialchars($row['payment_date']) ?></td>
                    <td><?= htmlspecialchars($row['purpose']) ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
<?php else: ?>
    <p>No payments found for your account.</p>
<?php endif; ?>

<h2>Summary of Payments by Method</h2>
<div class="summary">
<?php if ($combined_summary->num_rows > 0): ?>
    <ul>
    <?php while ($sum = $combined_summary->fetch_assoc()): ?>
        <li><strong><?= htmlspecialchars($sum['payment_method']) ?>:</strong> $<?= number_format($sum['total_amount'], 2) ?> (<?= $sum['payment_count'] ?> payments)</li>
    <?php endwhile; ?>
    </ul>
<?php else: ?>
    <p>No payment data available to summarize.</p>
<?php endif; ?>
</div>

<?php if ($user_role === 'admin'): ?>
    <h1>All Users' Payments (Admin View)</h1>
    <?php if ($all_payments_result->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Payment ID</th>
                    <th>User Name</th>
                    <th>User Email</th>
                    <th>Amount ($)</th>
                    <th>Payment Method</th>
                    <th>Payment Date</th>
                    <th>Purpose</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $all_payments_result->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['payment_id']) ?></td>
                        <td><?= htmlspecialchars($row['user_name']) ?></td>
                        <td><?= htmlspecialchars($row['email']) ?></td>
                        <td><?= number_format($row['amount'], 2) ?></td>
                        <td><?= htmlspecialchars($row['payment_method']) ?></td>
                        <td><?= htmlspecialchars($row['payment_date']) ?></td>
                        <td><?= htmlspecialchars($row['purpose']) ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No payment records found.</p>
    <?php endif; ?>
<?php endif; ?>

</body>
</html>
