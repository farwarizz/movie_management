<?php
/**
 * streaming_services.php
 * This file displays movies available on a specific streaming platform.
 * It receives platform_id via GET parameters.
 * Users can view movie details and purchase streaming access for different qualities.
 * It also includes a feature to search for movies across ALL platforms and redirect.
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
$search_results = []; // To store results of the cross-platform movie search

// Get platform_id from GET parameters
$platform_id = isset($_GET['platform_id']) ? (int)$_GET['platform_id'] : 0;

// Redirect if essential parameters are missing
if ($platform_id === 0) {
    header("Location: streaming_platform.php"); // Redirect to platform list if no platform selected
    exit();
}

// --- Fetch Platform Details ---
$platform_details = null;
$stmt_platform = $conn->prepare("SELECT platform_id, platform_name, website FROM streaming_platform WHERE platform_id = ?");
if ($stmt_platform) {
    $stmt_platform->bind_param("i", $platform_id);
    $stmt_platform->execute();
    $result_platform = $stmt_platform->get_result();
    if ($result_platform->num_rows === 1) {
        $platform_details = $result_platform->fetch_assoc();
    }
    $stmt_platform->close();
}

// If platform details are not found, display an error and exit
if (!$platform_details) {
    $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Error: Streaming platform details not found.</div>";
    // header("Location: streaming_platform.php"); exit(); // Uncomment to redirect
}

// --- Handle Streaming Purchase Form Submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'purchase_streaming_from_service') {
    $movie_id = (int)$_POST['movie_id'];
    $selected_platform_id = (int)$_POST['platform_id'];
    $quality = $conn->real_escape_string($_POST['quality']); // e.g., 'price_720p', 'price_1080p', 'price_4k'
    $purchase_date = date('Y-m-d'); // Current date of purchase

    // Re-fetch streaming price based on quality to ensure accuracy
    $stmt_price = $conn->prepare("SELECT {$quality} FROM streaming_services WHERE movie_id = ? AND platform_id = ?");
    if ($stmt_price) {
        $stmt_price->bind_param("ii", $movie_id, $selected_platform_id);
        $stmt_price->execute();
        $result_price = $stmt_price->get_result();
        $streaming_price = 0;
        if ($result_price->num_rows > 0) {
            $row_price = $result_price->fetch_assoc();
            $streaming_price = $row_price[$quality];
        }
        $stmt_price->close();
    } else {
        $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Database error fetching price: " . $conn->error . "</div>";
    }

    if ($selected_platform_id !== $platform_id) {
        $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Security error: Mismatched platform ID.</div>";
    } elseif ($streaming_price <= 0) {
        $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Invalid price or quality selected.</div>";
    } else {
        $conn->begin_transaction();
        try {
            // Insert into payments table
            $stmt_payment = $conn->prepare("INSERT INTO payments (user_id, amount, payment_method, payment_date, purpose) VALUES (?, ?, ?, ?, ?)");
            $payment_method = 'Wallet'; // Default for now
            $purpose = 'Streaming Movie Purchase (' . str_replace('price_', '', $quality) . ')'; // e.g., '720p'
            $stmt_payment->bind_param("idss", $user_id, $streaming_price, $payment_method, $purchase_date, $purpose);
            if (!$stmt_payment->execute()) {
                throw new Exception("Error recording payment: " . $stmt_payment->error);
            }
            $payment_id = $conn->insert_id;
            $stmt_payment->close();

            // Record activity for recommendation system (user purchased a movie)
            recordUserActivity($conn, $user_id, $movie_id, "Purchased streaming movie via Streaming Services page");

            $conn->commit();
            // Enhanced success message with purchase details
            $message = "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4' role='alert'>" .
                       "<p class='font-bold mb-2'>Streaming purchase successful!</p>" .
                       "<p><strong>Movie ID:</strong> {$movie_id}</p>" .
                       "<p><strong>Platform:</strong> " . htmlspecialchars($platform_details['platform_name']) . "</p>" .
                       "<p><strong>Quality:</strong> " . htmlspecialchars(str_replace('price_', '', $quality)) . "</p>" .
                       "<p><strong>Total Price:</strong> $" . htmlspecialchars(number_format($streaming_price, 2)) . "</p>" .
                       "<p><strong>Payment ID:</strong> {$payment_id}</p>" .
                       "</div>";
            $_POST = array(); // Clear POST data
        } catch (Exception $e) {
            $conn->rollback();
            $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Purchase failed: " . $e->getMessage() . "</div>";
        }
    }
}

// Function to record user activity for recommendations (copied for consistency)
function recordUserActivity($conn, $user_id, $movie_id, $reason) {
    $stmt = $conn->prepare("INSERT INTO recommendations (user_id, movie_id, reason) VALUES (?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("iis", $user_id, $movie_id, $reason);
        $stmt->execute();
        $stmt->close();
    }
}

// --- Handle Cross-Platform Movie Search ---
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['search_movie']) && !empty($_GET['search_movie'])) {
    $search_query = '%' . $conn->real_escape_string($_GET['search_movie']) . '%';

    $sql_search = "SELECT m.movie_id, m.title, sp.platform_id, sp.platform_name
                   FROM movies m
                   JOIN streaming_services ss ON m.movie_id = ss.movie_id
                   JOIN streaming_platform sp ON ss.platform_id = sp.platform_id
                   WHERE m.title LIKE ?
                   GROUP BY m.movie_id, m.title, sp.platform_id, sp.platform_name
                   ORDER BY m.title ASC, sp.platform_name ASC";

    $stmt_search = $conn->prepare($sql_search);
    if ($stmt_search) {
        $stmt_search->bind_param("s", $search_query);
        $stmt_search->execute();
        $result_search = $stmt_search->get_result();

        // Group results by movie title
        $temp_search_results = [];
        while ($row = $result_search->fetch_assoc()) {
            $temp_search_results[$row['title']][] = [
                'platform_id' => $row['platform_id'],
                'platform_name' => $row['platform_name']
            ];
        }
        $search_results = $temp_search_results;
        $stmt_search->close();
    } else {
        $message = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Error preparing search query: " . $conn->error . "</div>";
    }
}


// --- Fetch Movies available on this Platform ---
$movies_on_platform = [];
$all_movies_on_platform_data = []; // For JavaScript to populate quality options dynamically

$sql_movies = "SELECT m.movie_id, m.title, m.genre, m.release_date, m.rating,
                      ss.price_720p, ss.price_1080p, ss.price_4k
               FROM streaming_services ss
               JOIN movies m ON ss.movie_id = m.movie_id
               WHERE ss.platform_id = ?
               ORDER BY m.title ASC";

$stmt_movies = $conn->prepare($sql_movies);
if ($stmt_movies) {
    $stmt_movies->bind_param("i", $platform_id);
    $stmt_movies->execute();
    $result_movies = $stmt_movies->get_result();
    while ($row = $result_movies->fetch_assoc()) {
        $movies_on_platform[] = $row;
        $all_movies_on_platform_data[$row['movie_id']] = $row; // Store for JS
    }
    $stmt_movies->close();
}

// Close the database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($platform_details['platform_name'] ?? 'Streaming Services'); ?> - Movies Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f2f5;
        }
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
<body class="min-h-screen bg-gray-100">
    <nav class="bg-indigo-600 p-4 shadow-md">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-white text-2xl font-bold"><?php echo htmlspecialchars($platform_details['platform_name'] ?? 'Streaming Services'); ?></h1>
            <div class="flex items-center space-x-4">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <span class="text-white text-lg">Welcome, <?php echo htmlspecialchars($user_name); ?>!</span>
                    <a href="viewer.php" class="bg-white text-indigo-600 px-4 py-2 rounded-md font-semibold hover:bg-indigo-100">My Dashboard</a>
                    <a href="movies.php" class="bg-white text-indigo-600 px-4 py-2 rounded-md font-semibold hover:bg-indigo-100">Browse All Movies</a>
                    <a href="cinema.php" class="bg-white text-indigo-600 px-4 py-2 rounded-md font-semibold hover:bg-indigo-100">Cinemas</a>
                    <a href="streaming_platform.php" class="bg-white text-indigo-600 px-4 py-2 rounded-md font-semibold hover:bg-indigo-100">All Platforms</a>
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

        <h2 class="text-3xl font-bold text-gray-800 mb-6">Movies on <?php echo htmlspecialchars($platform_details['platform_name'] ?? 'Selected Platform'); ?></h2>

        <?php if ($platform_details): ?>
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h3 class="text-xl font-semibold text-gray-800 mb-4">Looking for a specific movie?</h3>
                <form action="streaming_services.php" method="GET" class="flex flex-col sm:flex-row gap-4">
                    <input type="hidden" name="platform_id" value="<?php echo htmlspecialchars($platform_id); ?>">
                    <input type="text" name="search_movie" placeholder="Search movie across all platforms..."
                           class="flex-grow px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                           value="<?php echo htmlspecialchars($_GET['search_movie'] ?? ''); ?>">
                    <button type="submit"
                            class="px-6 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Search
                    </button>
                </form>

                <?php if (!empty($search_results)): ?>
                    <div class="mt-6 border-t pt-4">
                        <h4 class="text-lg font-semibold text-gray-700 mb-3">Search Results:</h4>
                        <?php foreach ($search_results as $movie_title => $platforms): ?>
                            <div class="mb-4">
                                <p class="font-medium text-gray-800 text-md"><?php echo htmlspecialchars($movie_title); ?> is available on:</p>
                                <ul class="list-disc list-inside ml-4 text-gray-600">
                                    <?php foreach ($platforms as $p): ?>
                                        <li>
                                            <a href="streaming_services.php?platform_id=<?php echo htmlspecialchars($p['platform_id']); ?>"
                                               class="text-indigo-600 hover:text-indigo-800 hover:underline">
                                                <?php echo htmlspecialchars($p['platform_name']); ?>
                                            </a>
                                            <?php if ((int)$p['platform_id'] === $platform_id): ?>
                                                <span class="text-sm text-gray-500">(Current Platform)</span>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php elseif (isset($_GET['search_movie']) && !empty($_GET['search_movie'])): ?>
                    <div class="mt-6 border-t pt-4">
                        <p class="text-gray-700">No results found for "<?php echo htmlspecialchars($_GET['search_movie']); ?>" on any streaming platform.</p>
                    </div>
                <?php endif; ?>
            </div>
            <h3 class="text-2xl font-bold text-gray-800 mb-4">Movies currently on <?php echo htmlspecialchars($platform_details['platform_name']); ?>:</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                <?php if (!empty($movies_on_platform)): ?>
                    <?php foreach ($movies_on_platform as $movie): ?>
                        <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow duration-300 cursor-pointer"
                             onclick="openPurchaseModal(<?php echo htmlspecialchars(json_encode($movie)); ?>, <?php echo htmlspecialchars($platform_details['platform_id']); ?>, '<?php echo htmlspecialchars($platform_details['platform_name']); ?>')">
                            <div class="p-4">
                                <h3 class="text-xl font-semibold text-gray-800 mb-2 truncate"><?php echo htmlspecialchars($movie['title']); ?></h3>
                                <p class="text-gray-600 text-sm mb-1">Genre: <?php echo htmlspecialchars($movie['genre']); ?></p>
                                <p class="text-gray-600 text-sm mb-1">Release Date: <?php echo htmlspecialchars($movie['release_date']); ?></p>
                                <p class="text-gray-600 text-sm mb-2">Rating: <span class="font-bold text-indigo-600"><?php echo htmlspecialchars($movie['rating']); ?></span></p>

                                <div class="mt-3 text-sm font-medium">
                                    <?php if ($movie['price_720p'] > 0) echo '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 mr-2">720p: $' . htmlspecialchars(number_format($movie['price_720p'], 2)) . '</span>'; ?>
                                    <?php if ($movie['price_1080p'] > 0) echo '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 mr-2">1080p: $' . htmlspecialchars(number_format($movie['price_1080p'], 2)) . '</span>'; ?>
                                    <?php if ($movie['price_4k'] > 0) echo '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">4K: $' . htmlspecialchars(number_format($movie['price_4k'], 2)) . '</span>'; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-gray-700 text-lg col-span-full text-center py-10">No movies found on this streaming platform.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <p class="text-red-700 text-lg text-center py-10">Invalid streaming platform selected. Please go back to <a href="streaming_platform.php" class="text-indigo-600 hover:underline">All Platforms</a>.</p>
        <?php endif; ?>
    </div>

    <div id="purchaseStreamingModal" class="modal">
        <div class="modal-content">
            <span class="close-button" onclick="closePurchaseModal()">&times;</span>
            <h3 id="modalPurchaseMovieTitle" class="text-2xl font-bold text-gray-800 mb-4"></h3>
            <p class="text-gray-700 mb-2"><strong class="text-gray-900">Platform:</strong> <span id="modalPurchasePlatformName"></span></p>
            <p class="text-gray-700 mb-4"><strong class="text-gray-900">Rating:</strong> <span id="modalPurchaseMovieRating" class="font-bold text-indigo-600"></span></p>

            <form action="streaming_services.php?platform_id=<?php echo htmlspecialchars($platform_id); ?>" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="purchase_streaming_from_service">
                <input type="hidden" name="movie_id" id="purchaseMovieId">
                <input type="hidden" name="platform_id" id="purchasePlatformId">

                <div>
                    <label for="purchase_quality" class="block text-sm font-medium text-gray-700">Select Quality:</label>
                    <select id="purchase_quality" name="quality" required
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-purple-500 sm:text-sm">
                        </select>
                </div>
                <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-purple-600 hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500">
                    Purchase Streaming
                </button>
            </form>
        </div>
    </div>

    <script>
        const purchaseStreamingModal = document.getElementById('purchaseStreamingModal');
        const modalPurchaseMovieTitle = document.getElementById('modalPurchaseMovieTitle');
        const modalPurchasePlatformName = document.getElementById('modalPurchasePlatformName');
        const modalPurchaseMovieRating = document.getElementById('modalPurchaseMovieRating');
        const purchaseMovieIdInput = document.getElementById('purchaseMovieId');
        const purchasePlatformIdInput = document.getElementById('purchasePlatformId');
        const purchaseQualitySelect = document.getElementById('purchase_quality');

        // PHP variable for all movies on this platform data, accessible by JavaScript
        const allMoviesOnPlatformData = <?php echo json_encode($all_movies_on_platform_data); ?>;

        function openPurchaseModal(movie, platformId, platformName) {
            modalPurchaseMovieTitle.textContent = movie.title;
            modalPurchasePlatformName.textContent = platformName;
            modalPurchaseMovieRating.textContent = movie.rating;
            purchaseMovieIdInput.value = movie.movie_id;
            purchasePlatformIdInput.value = platformId;

            // Clear previous quality options
            purchaseQualitySelect.innerHTML = '<option value="">Select Quality</option>';

            // Populate quality options based on the selected movie's prices
            if (parseFloat(movie.price_720p) > 0) {
                purchaseQualitySelect.innerHTML += `<option value="price_720p">720p ($${parseFloat(movie.price_720p).toFixed(2)})</option>`;
            }
            if (parseFloat(movie.price_1080p) > 0) {
                purchaseQualitySelect.innerHTML += `<option value="price_1080p">1080p ($${parseFloat(movie.price_1080p).toFixed(2)})</option>`;
            }
            if (parseFloat(movie.price_4k) > 0) {
                purchaseQualitySelect.innerHTML += `<option value="price_4k">4K ($${parseFloat(movie.price_4k).toFixed(2)})</option>`;
            }

            purchaseStreamingModal.style.display = 'flex'; // Show the modal
        }

        function closePurchaseModal() {
            purchaseStreamingModal.style.display = 'none'; // Hide the modal
        }

        // Close the modal if the user clicks outside of it
        window.onclick = function(event) {
            if (event.target == purchaseStreamingModal) {
                closePurchaseModal();
            }
        }
    </script>
</body>
</html>
