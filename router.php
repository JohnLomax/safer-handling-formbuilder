<?php
declare(strict_types=1);

/**
 * Unified front controller: public forms + Laravel admin on one port.
 *
 * Start with (from project root):
 *   php -S localhost:8000 router.php
 *
 * Or from backend/:
 *   composer run dev
 *
 * Clean URLs:
 *   /          → enquiry form
 *   /enquiry   → enquiry form
 *   /feedback  → feedback form
 *   /booking   → booking details (quote accept / venue / terms)
 *   /admin/*   → Laravel admin
 *   /login     → Laravel auth
 */

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = rawurldecode(is_string($uri) ? $uri : '/');
$path = '/' . trim($path, '/');
if ($path === '//') {
    $path = '/';
}
if ($path === '') {
    $path = '/';
}

$root = __DIR__;
$laravelPublic = $root . '/backend/public';

$redirects = [
    '/index.html' => '/',
    '/enquiry.html' => '/enquiry',
    '/feedback.html' => '/feedback',
];

if (isset($redirects[$path])) {
    $query = $_SERVER['QUERY_STRING'] ?? '';
    $target = $redirects[$path] . ($query !== '' ? '?' . $query : '');
    header('Location: ' . $target, true, 301);
    exit;
}

$formPages = [
    '/' => 'enquiry.php',
    '/enquiry' => 'enquiry.php',
    '/feedback' => 'feedback.php',
    '/booking' => 'booking.php',
];

if (isset($formPages[$path])) {
    require $root . '/' . $formPages[$path];
    return true;
}

$formEndpoints = [
    '/submit_enquiry.php',
    '/submit_feedback.php',
    '/submit_booking.php',
    '/monday_continue.php',
    '/resume_enquiry.php',
    '/save_enquiry_progress.php',
    '/monday_online_course.php',
    '/monday_update_trainer_fields.php',
    '/monday_booking_via_company.php',
    '/postcode_lookup.php',
    '/training_matrix.php',
];

if (in_array($path, $formEndpoints, true)) {
    require $root . $path;
    return true;
}

// Public form static assets (e.g. /assets/logo.png)
if (str_starts_with($path, '/assets/')) {
    $asset = $root . $path;
    if (is_file($asset)) {
        return false;
    }
}

// Laravel public static files (Vite build, favicon, etc.)
$laravelFile = $laravelPublic . $path;
if ($path !== '/' && is_file($laravelFile)) {
    $ext = strtolower(pathinfo($laravelFile, PATHINFO_EXTENSION));
    if ($ext !== 'php') {
        $mime = match ($ext) {
            'css' => 'text/css; charset=utf-8',
            'js' => 'application/javascript; charset=utf-8',
            'mjs' => 'application/javascript; charset=utf-8',
            'json' => 'application/json; charset=utf-8',
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'map' => 'application/json; charset=utf-8',
            'txt' => 'text/plain; charset=utf-8',
            default => null,
        };
        if ($mime !== null) {
            header('Content-Type: ' . $mime);
        }
        readfile($laravelFile);
        return true;
    }
}

// Everything else → Laravel
$_SERVER['SCRIPT_FILENAME'] = $laravelPublic . '/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';
$_SERVER['DOCUMENT_ROOT'] = $laravelPublic;

chdir($laravelPublic);
require $laravelPublic . '/index.php';
return true;
