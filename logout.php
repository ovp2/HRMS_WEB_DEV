<?php
/**
 * HRMS - Logout Handler
 * Destroys session and redirects to login
 */

require_once __DIR__ . '/includes/auth.php';

destroyUserSession();

// Start a new session to set flash message
session_start();
setFlashMessage('You have been logged out successfully.', 'success');

header('Location: ' . APP_URL . '/login.php');
exit;
