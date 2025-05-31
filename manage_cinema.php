<?php
include 'db_connection.php';
include 'session_check.php';
include 'header.php'; 


// Admin check
$user_id = $_SESSION['user_id'];
$admin_check = $conn->query("SELECT * FROM admin WHERE user_id = $user_id");
if ($admin_check->num_rows == 0) {
    die("Access denied: Admins only.");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $conn->real_escape_string($_POST['name']);
    $location = $conn->real_escape_string($_POST['location']);
    $type_id = intval($_POST['type_id']);
    $conn->query("INSERT INTO cinema (name, location, type_id) VALUES ('$name', '$location', $type_id)");
    echo "<p style='color:green;'>Cinema added successfully.</p>";
}

$cinema_types = $conn->query("SELECT * FROM cinema_type");
$cinemas = $conn->query("SELECT c.*, ct.type_name FROM cinema c LEFT JOIN cinema_type ct ON c.type_id = ct.type_id");

include 'includes/header.php';
?>

<h2>Manage Cinemas</h2>

<h3>Add New Cinema</h3>
<form method="POST" action="">
    <label>Name:</label><br/>
    <input type="text" name="name" required><br/><br/>
    <label>Location:</label><br/>
    <input type="text" name="location" required><br/><br/>
    <label>Cinema Type:</label><br/>
    <select name="type_id" required>
        <option value="">Select Type</option>
        <?php while ($row = $cinema_types->fetch_assoc()) : ?>
            <option value="<?= $row['type_id'] ?>"><?= htmlspecialchars($row['type_name']) ?></option>
        <?php endwhile; ?>
    </select><br/><br/>
    <input type="submit" value="Add Cinema">
</form>

<h3>Existing Cinemas</h3>
<table border="1" cellpadding="5" cellspacing="0">
    <thead>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Location</th>
            <th>Type</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($cinema = $cinemas->fetch_assoc()) : ?>
            <tr>
                <td><?= $cinema['cinema_id'] ?></td>
                <td><?= htmlspecialchars($cinema['name']) ?></td>
                <td><?= htmlspecialchars($cinema['location']) ?></td>
                <td><?= htmlspecialchars($cinema['type_name']) ?></td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>

<?php
include 'includes/footer.php';
?>
