<?php
/**
 * Menu API - Phase 2 Optimized: Utilise les compteurs dénormalisés
 * Performance: 2-5ms au lieu de 50ms+
 */

$start_time = microtime(true);
include(__DIR__ . '/../config/conf.php');

if(!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo '{"error":"Unauthorized"}';
    exit;
}

$userId = (int)$_SESSION['user_id'];

// ============================================================================
// REQUÊTE OPTIMISÉE - utilise reader_flux_user_stats
// ============================================================================
// Utilise les compteurs dans reader_flux_user_stats (mis à jour par read.php)
// Performance: 2-5ms

$sql = "
    SELECT
        F.id,
        F.title,
        S.unread_count as n,
        F.description,
        F.link
    FROM reader_flux F
    INNER JOIN reader_user_flux UF ON UF.id_flux = F.id AND UF.id_user = $userId
    INNER JOIN reader_flux_user_stats S ON S.id_flux = F.id AND S.id_user = $userId
    WHERE S.unread_count > 0
    ORDER BY F.title ASC
";

$r = $mysqli->query($sql);

if (!$r) {
    die($mysqli->error);
}

// Build JSON with simple string concatenation (fastest)
$json = '{';
$first = true;
while($d = $r->fetch_assoc()) {
    if (!$first) $json .= ',';
    $first = false;

    // Escape only quotes and backslashes
    $title = str_replace(['\\', '"'], ['\\\\', '\\"'], $d['title']);
    $desc = str_replace(['\\', '"'], ['\\\\', '\\"'], $d['description'] ?? '');
    $link = $d['link'] ?? '';

    $json .= '"' . $d['id'] . '":{"t":"' . $title . '","n":' . $d['n'] . ',"d":"' . $desc . '","l":"' . $link . '"}';
}
$json .= '}';

$execution_time = round((microtime(true) - $start_time) * 1000, 2);
header('Content-Type: application/json; charset=utf-8');
header('X-Execution-Time: ' . $execution_time . 'ms');
echo $json;
?>
