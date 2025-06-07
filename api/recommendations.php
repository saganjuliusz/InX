<?php
require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die(json_encode(['error' => 'Connection failed: ' . $conn->connect_error]));
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['error' => 'Only GET method is allowed']);
    exit;
}

$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$recommendationType = isset($_GET['type']) ? $_GET['type'] : 'discover_weekly';
$limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 50) : 20;

if ($userId <= 0) {
    echo json_encode(['error' => 'Invalid user ID']);
    exit;
}

// Wywołaj procedurę generowania rekomendacji
$stmt = $conn->prepare("CALL generate_user_recommendations(?, ?, ?)");
$stmt->bind_param("isi", $userId, $recommendationType, $limit);
$stmt->execute();
$stmt->close();

// Pobierz wygenerowane rekomendacje
$stmt = $conn->prepare("
    SELECT 
        t.track_id,
        t.title,
        t.duration,
        t.file_path,
        t.energy_level,
        t.valence,
        a.name as artist_name,
        a.artist_id,
        al.title as album_title,
        al.album_id,
        al.cover_art_url,
        r.confidence_score,
        r.factors
    FROM recommendation_logs r
    JOIN tracks t ON r.track_id = t.track_id
    JOIN artists a ON t.artist_id = a.artist_id
    LEFT JOIN albums al ON t.album_id = al.album_id
    WHERE r.user_id = ?
    AND r.recommendation_type = ?
    ORDER BY r.confidence_score DESC
    LIMIT ?
");

$stmt->bind_param("isi", $userId, $recommendationType, $limit);
$stmt->execute();
$result = $stmt->get_result();

$recommendations = [];
while ($row = $result->fetch_assoc()) {
    $recommendations[] = [
        'track' => [
            'id' => $row['track_id'],
            'title' => $row['title'],
            'duration' => (int)$row['duration'],
            'file_path' => $row['file_path'],
            'energy_level' => (float)$row['energy_level'],
            'valence' => (float)$row['valence']
        ],
        'artist' => [
            'id' => $row['artist_id'],
            'name' => $row['artist_name']
        ],
        'album' => [
            'id' => $row['album_id'],
            'title' => $row['album_title'],
            'cover_art_url' => $row['cover_art_url']
        ],
        'recommendation' => [
            'confidence_score' => (float)$row['confidence_score'],
            'factors' => json_decode($row['factors'], true)
        ]
    ];
}

echo json_encode([
    'type' => $recommendationType,
    'recommendations' => $recommendations
]);

$conn->close();
?> 