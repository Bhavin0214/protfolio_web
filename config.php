<?php
session_start();

// ============================================
// DATABASE CONFIGURATION
// ============================================
define('DB_HOST', 'localhost');   // Usually 'localhost'
define('DB_USER', 'root');        // Your MySQL username
define('DB_PASS', '');            // Your MySQL password
define('DB_NAME', 'portfolio_db'); // Database name

// Site URL (change in production)
define('BASE_URL', 'http://localhost/portfolio');

// Upload directories
define('UPLOAD_DIR', __DIR__ . '/assets/uploads/');
define('RESUME_FILE', 'assets/uploads/resume.pdf');

// ============================================
// ENSURE UPLOAD DIRECTORIES EXIST
// ============================================
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
}
if (!is_dir(UPLOAD_DIR . 'works/')) {
    mkdir(UPLOAD_DIR . 'works/', 0777, true);
}

// ============================================
// DATABASE CONNECTION (PDO)
// ============================================
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // In production, log this error instead of displaying
    die("Database connection failed: " . $e->getMessage());
}

// ============================================
// HELPER FUNCTIONS
// ============================================

/**
 * Check if a visitor is logged in (has rated before)
 */
function isVisitor() {
    return isset($_SESSION['visitor_id']);
}

/**
 * Get the current visitor's ID
 */
function getVisitorId() {
    return $_SESSION['visitor_id'] ?? 0;
}

/**
 * Check if admin is logged in
 */
function isAdmin() {
    return isset($_SESSION['admin']);
}

/**
 * Sanitize input data
 */
function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect to a URL
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Track unique visitor per day (by IP)
 */
function trackVisitor($pdo) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT id FROM visitor_stats WHERE ip_address = ? AND DATE(visited_at) = ?");
    $stmt->execute([$ip, $today]);
    if (!$stmt->fetch()) {
        $insert = $pdo->prepare("INSERT INTO visitor_stats (ip_address) VALUES (?)");
        $insert->execute([$ip]);
    }
}

/**
 * Get average rating for a specific work
 */
function getWorkRating($pdo, $work_id) {
    $stmt = $pdo->prepare("SELECT AVG(rating) as avg FROM work_ratings WHERE work_id = ?");
    $stmt->execute([$work_id]);
    $row = $stmt->fetch();
    return $row['avg'] ? round($row['avg'], 1) : 0;
}

/**
 * Get overall profile rating
 */
function getOverallRating($pdo) {
    $stmt = $pdo->query("SELECT AVG(rating) as avg FROM overall_ratings");
    $row = $stmt->fetch();
    return $row['avg'] ? round($row['avg'], 1) : 0;
}

/**
 * Get all categories
 */
function getCategories($pdo) {
    return $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
}

/**
 * Get category name by ID
 */
function getCategoryName($pdo, $id) {
    $stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ? $row['name'] : '';
}

/**
 * Debug function (optional)
 */
function debug($data) {
    echo '<pre>';
    print_r($data);
    echo '</pre>';
}
?>