<?php
/**
 * User Logout
 * Destroys session and redirects to homepage
 */

require_once __DIR__ . '/../config.php';

// Destroy session
session_unset();
session_destroy();

// Start new session for message
session_start();
setSuccessMessage('You have been logged out successfully.');

// Redirect to homepage
redirect(SITE_URL . 'services/browse-services.php');
?>