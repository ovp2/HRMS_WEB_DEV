<?php
/**
 * HRMS - Index / Entry Point
 * Redirects to appropriate dashboard based on role, or login page
 */

require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    if (isAdmin()) {
        header('Location: ' . APP_URL . '/admin/dashboard.php');
    } else {
        header('Location: ' . APP_URL . '/employee/dashboard.php');
    }
} else {
    header('Location: ' . APP_URL . '/login.php');
}
exit;
