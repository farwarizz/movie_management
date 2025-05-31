<?php
/**
 * logout.php
 * This file handles user logout for the Movies Management System.
 * It destroys the current PHP session and redirects the user to the login page.
 */

session_start(); // Start the session if it's not already started

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to the login page
header("Location: login.php");
exit();
?>
