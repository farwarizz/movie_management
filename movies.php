<?php
/**
 * movies.php
 * This file displays a list of all movies available in the database.
 * When a user clicks on a movie, it opens a modal with more details
 * and indicates availability in cinemas or streaming platforms.
 */

// Start a PHP session (needed for user context in the navigation bar)
session_start();

// Include the database connection file
require_once 'db_connect.php';

$movies = [];
$all_movies_data = []; // To store all movie data for JavaScript access in the modal

// Fetch all movies from the database, including their cinema and streaming availability
$sql = "SELECT DISTINCT m.movie_id, m.title, m.genre, m.release_date, m.language, m.duration, m.rating,
               GROUP_CONCAT(DISTINCT c.name SEPARATOR ', ') AS cinema_names,
               GROUP_CONCAT(DISTINCT sp.platform_name SEPARATOR ', ') AS streaming_platform_names
        FROM movies m
        LEFT JOIN booking b ON m.movie_id = b.movie_id
        LEFT JOIN cinema c ON b.cinema_id = c.cinema_id
        LEFT JOIN streaming_services ss ON m.movie_id = ss.movie_id
        LEFT JOIN streaming_platform sp ON ss.platform_id = sp.platform_id
        GROUP BY m.movie_id
        ORDER BY m.title ASC";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $movies[] = $row;
        $all_movies_data[$row['movie_id']] = $row; // Store for JavaScript lookup
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
    <title>All Movies - Movies Management System</title>
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
            max-width: 700px;
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
            <h1 class="text-white text-2xl font-bold">All Movies</h1>
            <div class="flex items-center space-x-4">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <span class="text-white text-lg">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</span>
                    <a href="viewer.php" class="bg-white text-indigo-600 px-4 py-2 rounded-md font-semibold hover:bg-indigo-100">My Dashboard</a>
                    <a href="logout.php" class="bg-white text-indigo-600 px-4 py-2 rounded-md font-semibold hover:bg-indigo-100">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="bg-white text-indigo-600 px-4 py-2 rounded-md font-semibold hover:bg-indigo-100">Login</a>
                    <a href="registration.php" class="bg-white text-indigo-600 px-4 py-2 rounded-md font-semibold hover:bg-indigo-100">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="container mx-auto p-6">
        <h2 class="text-3xl font-bold text-gray-800 mb-6">Browse All Movies</h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
            <?php if (!empty($movies)): ?>
                <?php foreach ($movies as $movie): ?>
                    <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow duration-300 cursor-pointer"
                         onclick="openMovieDetails(<?php echo htmlspecialchars(json_encode($movie)); ?>)">
                        <div class="p-4">
                            <h3 class="text-xl font-semibold text-gray-800 mb-2 truncate"><?php echo htmlspecialchars($movie['title']); ?></h3>
                            <p class="text-gray-600 text-sm mb-1">Genre: <?php echo htmlspecialchars($movie['genre']); ?></p>
                            <p class="text-gray-600 text-sm mb-1">Release Date: <?php echo htmlspecialchars($movie['release_date']); ?></p>
                            <p class="text-gray-600 text-sm mb-1">Language: <?php echo htmlspecialchars($movie['language']); ?></p>
                            <p class="text-gray-600 text-sm mb-1">Duration: <?php echo htmlspecialchars($movie['duration']); ?> mins</p>
                            <p class="text-gray-600 text-sm mb-2">Rating: <span class="font-bold text-indigo-600"><?php echo htmlspecialchars($movie['rating']); ?></span></p>

                            <div class="mt-3 text-sm font-medium">
                                <?php
                                $available_in_cinema = !empty($movie['cinema_names']);
                                $available_streaming = !empty($movie['streaming_platform_names']);

                                if ($available_in_cinema && $available_streaming) {
                                    echo '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 mr-2">Cinema</span>';
                                    echo '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">Streaming</span>';
                                } elseif ($available_in_cinema) {
                                    echo '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">Cinema Only</span>';
                                } elseif ($available_streaming) {
                                    echo '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">Streaming Only</span>';
                                } else {
                                    echo '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Not Available</span>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-gray-700 text-lg col-span-full text-center py-10">No movies found in the database.</p>
            <?php endif; ?>
        </div>
    </div>

    <div id="movieDetailsModal" class="modal">
        <div class="modal-content">
            <span class="close-button" onclick="closeMovieDetails()">&times;</span>
            <h3 id="modalMovieTitle" class="text-2xl font-bold text-gray-800 mb-4"></h3>
            <p class="text-gray-700 mb-2"><strong class="text-gray-900">Genre:</strong> <span id="modalMovieGenre"></span></p>
            <p class="text-gray-700 mb-2"><strong class="text-gray-900">Release Date:</strong> <span id="modalMovieReleaseDate"></span></p>
            <p class="text-gray-700 mb-2"><strong class="text-gray-900">Language:</strong> <span id="modalMovieLanguage"></span></p>
            <p class="text-gray-700 mb-2"><strong class="text-gray-900">Duration:</strong> <span id="modalMovieDuration"></span> mins</p>
            <p class="text-gray-700 mb-4"><strong class="text-gray-900">Rating:</strong> <span id="modalMovieRating" class="font-bold text-indigo-600"></span></p>

            <div id="cinemaOptions" class="mb-6 hidden">
                <h4 class="text-xl font-semibold text-gray-800 mb-3">Available in Cinemas:</h4>
                <form action="viewer.php" method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="book_cinema">
                    <input type="hidden" name="movie_id" id="bookingMovieId">

                    <div>
                        <label for="cinema_id" class="block text-sm font-medium text-gray-700">Select Cinema:</label>
                        <select id="cinema_id" name="cinema_id" required
                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            </select>
                    </div>
                    <div>
                        <label for="show_time" class="block text-sm font-medium text-gray-700">Show Time:</label>
                        <input type="time" id="show_time" name="show_time" required
                               class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    </div>
                    <div>
                        <label for="seat_number" class="block text-sm font-medium text-gray-700">Seat Number (e.g., A1, B5):</label>
                        <input type="text" id="seat_number" name="seat_number" required
                               class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    </div>
                    <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Book Cinema Ticket
                    </button>
                </form>
            </div>

            <div id="streamingOptions" class="mb-6 hidden">
                <h4 class="text-xl font-semibold text-gray-800 mb-3">Available for Streaming:</h4>
                <form action="viewer.php" method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="purchase_streaming">
                    <input type="hidden" name="movie_id" id="streamingMovieId">

                    <div>
                        <label for="platform_id" class="block text-sm font-medium text-gray-700">Select Platform:</label>
                        <select id="platform_id" name="platform_id" required
                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            </select>
                    </div>
                    <div>
                        <label for="quality" class="block text-sm font-medium text-gray-700">Select Quality:</label>
                        <select id="quality" name="quality" required
                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            </select>
                    </div>
                    <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-purple-600 hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500">
                        Purchase Streaming
                    </button>
                </form>
            </div>

            <div id="notAvailableMessage" class="hidden bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative mb-4" role="alert">
                This movie is not available via your preferred method.
                <span id="alternativeAvailability" class="font-semibold block mt-2"></span>
            </div>
        </div>
    </div>

    <script>
        const movieDetailsModal = document.getElementById('movieDetailsModal');
        const modalMovieTitle = document.getElementById('modalMovieTitle');
        const modalMovieGenre = document.getElementById('modalMovieGenre');
        const modalMovieReleaseDate = document.getElementById('modalMovieReleaseDate');
        const modalMovieLanguage = document.getElementById('modalMovieLanguage');
        const modalMovieDuration = document.getElementById('modalMovieDuration');
        const modalMovieRating = document.getElementById('modalMovieRating');
        const cinemaOptionsDiv = document.getElementById('cinemaOptions');
        const streamingOptionsDiv = document.getElementById('streamingOptions');
        const notAvailableMessageDiv = document.getElementById('notAvailableMessage');
        const alternativeAvailabilitySpan = document.getElementById('alternativeAvailability');
        const bookingMovieIdInput = document.getElementById('bookingMovieId');
        const streamingMovieIdInput = document.getElementById('streamingMovieId');
        const cinemaSelect = document.getElementById('cinema_id');
        const platformSelect = document.getElementById('platform_id');
        const qualitySelect = document.getElementById('quality');

        // PHP variable for all movies data, accessible by JavaScript
        const allMoviesData = <?php echo json_encode($all_movies_data); ?>;

        let currentMovieData = null; // To store data of the movie currently in the modal

        function openMovieDetails(movie) {
            currentMovieData = movie; // Store the movie data
            modalMovieTitle.textContent = movie.title;
            modalMovieGenre.textContent = movie.genre;
            modalMovieReleaseDate.textContent = movie.release_date;
            modalMovieLanguage.textContent = movie.language;
            modalMovieDuration.textContent = movie.duration;
            modalMovieRating.textContent = movie.rating;

            // Set movie IDs for forms
            bookingMovieIdInput.value = movie.movie_id;
            streamingMovieIdInput.value = movie.movie_id;

            // Reset visibility
            cinemaOptionsDiv.classList.add('hidden');
            streamingOptionsDiv.classList.add('hidden');
            notAvailableMessageDiv.classList.add('hidden');
            alternativeAvailabilitySpan.textContent = '';

            // Note: userPreference is not directly available here in movies.php
            // If you want to filter options based on user preference,
            // you'd need to fetch it via AJAX or pass it from the session.
            // For now, it shows ALL available options (cinema and/or streaming).
            const availableInCinema = movie.cinema_names !== null;
            const availableStreaming = movie.streaming_platform_names !== null;

            // Fetch and populate cinema options
            if (availableInCinema) {
                fetch(`get_cinema_details.php?movie_id=${movie.movie_id}`)
                    .then(response => response.json())
                    .then(data => {
                        cinemaSelect.innerHTML = '<option value="">Select a Cinema</option>';
                        if (data.cinemas && data.cinemas.length > 0) {
                            data.cinemas.forEach(cinema => {
                                const option = document.createElement('option');
                                option.value = cinema.cinema_id;
                                option.textContent = `${cinema.name} (${cinema.type_name} - $${parseFloat(cinema.price).toFixed(2)})`;
                                cinemaSelect.appendChild(option);
                            });
                            cinemaOptionsDiv.classList.remove('hidden'); // Show cinema options if available
                        } else {
                            cinemaSelect.innerHTML = '<option value="">No cinemas found for this movie</option>';
                        }
                    })
                    .catch(error => console.error('Error fetching cinema details:', error));
            }

            // Fetch and populate streaming options
            if (availableStreaming) {
                fetch(`get_streaming_details.php?movie_id=${movie.movie_id}`)
                    .then(response => response.json())
                    .then(data => {
                        platformSelect.innerHTML = '<option value="">Select a Platform</option>';
                        qualitySelect.innerHTML = '<option value="">Select Quality</option>';

                        if (data.platforms && data.platforms.length > 0) {
                            data.platforms.forEach(platform => {
                                const option = document.createElement('option');
                                option.value = platform.platform_id;
                                option.textContent = platform.platform_name;
                                platformSelect.appendChild(option);
                            });

                            // Populate quality options based on the first platform's prices for now,
                            // A more robust solution would update quality options dynamically when platform changes.
                            if (data.platforms[0]) {
                                const firstPlatform = data.platforms[0];
                                if (parseFloat(firstPlatform.price_720p) > 0) qualitySelect.innerHTML += `<option value="price_720p">720p ($${parseFloat(firstPlatform.price_720p).toFixed(2)})</option>`;
                                if (parseFloat(firstPlatform.price_1080p) > 0) qualitySelect.innerHTML += `<option value="price_1080p">1080p ($${parseFloat(firstPlatform.price_1080p).toFixed(2)})</option>`;
                                if (parseFloat(firstPlatform.price_4k) > 0) qualitySelect.innerHTML += `<option value="price_4k">4K ($${parseFloat(firstPlatform.price_4k).toFixed(2)})</option>`;
                            }
                            streamingOptionsDiv.classList.remove('hidden'); // Show streaming options if available
                        } else {
                             platformSelect.innerHTML = '<option value="">No streaming platforms found for this movie</option>';
                             qualitySelect.innerHTML = '<option value="">No qualities available</option>';
                        }
                    })
                    .catch(error => console.error('Error fetching streaming details:', error));
            }

            // If not available in either, show a message
            if (!availableInCinema && !availableStreaming) {
                 notAvailableMessageDiv.classList.remove('hidden');
                 alternativeAvailabilitySpan.textContent = `This movie is currently not available in cinemas or for streaming.`;
            }


            movieDetailsModal.style.display = 'flex'; // Show the modal
        }

        function closeMovieDetails() {
            movieDetailsModal.style.display = 'none'; // Hide the modal
        }

        // Close the modal if the user clicks outside of it
        window.onclick = function(event) {
            if (event.target == movieDetailsModal) {
                closeMovieDetails();
            }
        }
    </script>
</body>
</html>
