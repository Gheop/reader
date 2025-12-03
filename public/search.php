<?php
include(__DIR__ . '/../config/conf.php');
if(!isset($_SESSION['user_id'])) exit;
if(isset($_GET['s'])) $_POST['s'] = $_GET['s'];
if(!isset($_POST['s']) || trim($_POST['s']) === '') exit;

$user_id = (int)$_SESSION['user_id'];
$search_string = trim($_POST['s']);
$offset = (isset($_POST['nb']) && is_numeric($_POST['nb'])) ? (int)$_POST['nb'] : 0;
$limit = 50;

$stmt = $mysqli->prepare("SELECT I.id, I.title, I.pubdate, I.author, I.description, I.link, I.id_flux, F.title as flux_title, F.link as flux_link
FROM reader_item I, reader_flux F, reader_user_flux U
WHERE U.id_user = ?
AND U.id_flux = I.id_flux
AND I.id_flux = F.id
AND I.pubdate > (NOW() - INTERVAL 45 DAY)
AND (MATCH(I.description) AGAINST(?) OR MATCH(I.title) AGAINST(?))
ORDER BY pubdate DESC
LIMIT ?, ?");

if (!$stmt) {
    exit;
}

$stmt->bind_param("issii", $user_id, $search_string, $search_string, $offset, $limit);
if (!$stmt->execute()) {
    exit;
}

$result = $stmt->get_result();
$items = [];

// Build highlight pattern
$search_lower = mb_strtolower($search_string, 'UTF-8');
$highlight = '<span class=\'surligne\'>' . htmlspecialchars($search_string) . '</span>';

while ($row = $result->fetch_assoc()) {
    // Highlight search term in title and description
    $title = str_ireplace($search_string, $highlight, mb_strtolower($row['title'], 'UTF-8'));
    $desc = str_ireplace($search_string, $highlight, mb_strtolower($row['description'], 'UTF-8'));

    $items[] = [
        'i' => (int)$row['id'],
        't' => $title,
        'p' => $row['pubdate'],
        'a' => $row['author'],
        'd' => $desc,
        'l' => $row['link'],
        'f' => (int)$row['id_flux'],
        'n' => $row['flux_title'],
        'o' => $row['flux_link'],
        'r' => '1'
    ];
}

echo json_encode(['i' => $items], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
