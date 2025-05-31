<?php
/**
 * viewer_streaming_services.php
 * This file displays all available streaming platforms for viewers to subscribe to.
 */

session_start();
require_once 'db_connect.php'; // Ensure this path is correct

// Check if user is logged in and is a viewer
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'viewer') {
    header("Location: login.php");
    exit();
}

$message = '';
$platforms_for_subscription = [];

// Fetch all streaming platforms for subscription
// This query directly fetches from 'streaming_platform' table.
// Please ensure your 'streaming_platform' table exists and is correctly named.
$sql_platforms_for_sub = "SELECT platform_id, platform_name, website FROM streaming_platform ORDER BY platform_name";
$result_platforms_for_sub = $conn->query($sql_platforms_for_sub);

if ($result_platforms_for_sub) {
    while ($row = $result_platforms_for_sub->fetch_assoc()) {
        $platforms_for_subscription[] = $row;
    }
} else {
    // This will now show the exact SQL error if the table name is still wrong or other issue
    $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Error fetching streaming platforms for subscription: " . $conn->error . "</div>";
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Available Streaming Services</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f2f5;
        }
        .container-section {
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 24px;
            margin-bottom: 24px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
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
        .btn {
            padding: 8px 16px;
            border-radius: 5px;
            font-weight: 500;
            text-decoration: none;
            transition: background-color 0.2s ease-in-out;
            display: inline-block;
        }
        .btn-green {
            background-color: #22c55e; /* green-500 */
            color: white;
        }
        .btn-green:hover {
            background-color: #16a34a; /* green-600 */
        }
    </style>
</head>
<body class="min-h-screen bg-gray-100 flex flex-col">
    <nav class="bg-blue-700 p-4 shadow-md">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-white text-2xl font-bold">Available Streaming Services</h1>
            <div class="flex items-center space-x-4">
                <a href="viewer.php" class="bg-white text-blue-700 px-4 py-2 rounded-md font-semibold hover:bg-blue-100">Back to Dashboard</a>
                <a href="logout.php" class="bg-white text-blue-700 px-4 py-2 rounded-md font-semibold hover:bg-blue-100">Logout</a>
            </div>
        </div>
    </nav>

    <main class="flex-grow container mx-auto p-6">
        <?php echo $message; // Display messages ?>

        <div class="container-section">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Choose Your Streaming Platform to Subscribe</h2>

            <?php if (!empty($platforms_for_subscription)): ?>
                <div class="overflow-x-auto">
                    <table>
                        <thead>
                            <tr>
                                <th>Platform</th>
                                <th>Website</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($platforms_for_subscription as $platform): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($platform['platform_name']); ?></td>
                                    <td>
                                        <?php if (!empty($platform['website'])): ?>
                                            <a href="<?php echo htmlspecialchars($platform['website']); ?>" target="_blank" class="text-blue-600 hover:underline">Visit Site</a>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="subscribe_platform.php?platform_id=<?php echo htmlspecialchars($platform['platform_id']); ?>" class="btn btn-green">Subscribe to Plan</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-gray-600">No streaming platforms available for subscription.</p>
            <?php endif; ?>
        </div>
    </main>

    <footer class="bg-gray-800 text-white text-center p-4 mt-auto">
        <div class="container mx-auto">
            <p>&copy; <?php echo date('Y'); ?> MovieVerse. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>
