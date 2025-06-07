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

// Parametry filtrowania
$timeRange = isset($_GET['time_range']) ? $_GET['time_range'] : 'week'; // week, month, all
$genre = isset($_GET['genre']) ? (int)$_GET['genre'] : null;
$limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 100) : 50;
$offset = isset($_GET['offset']) ? max((int)$_GET['offset'], 0) : 0;

// Przygotuj warunki filtrowania
$whereConditions = [];
$params = [];
$types = "";

if ($timeRange !== 'all') {
    $interval = $timeRange === 'week' ? '7 DAY' : '30 DAY';
    $whereConditions[] = "t.updated_at >= DATE_SUB(CURRENT_DATE, INTERVAL $interval)";
}

if ($genre) {
    $whereConditions[] = "tg.genre_id = ?";
    $params[] = $genre;
    $types .= "i";
}

$whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

// Pobierz trendujące utwory
$query = "
    SELECT 
        t.track_id,
        t.title,
        t.duration,
        t.play_count,
        t.like_count,
        t.energy_level,
        t.valence,
        COALESCE(AVG(uti.rating), 0) as average_rating,
        COUNT(DISTINCT uti.user_id) as total_ratings,
        a.artist_id,
        a.name as artist_name,
        al.album_id,
        al.title as album_title,
        al.cover_art_url
    FROM tracks t
    JOIN artists a ON t.artist_id = a.artist_id
    LEFT JOIN albums al ON t.album_id = al.album_id
    LEFT JOIN user_track_interactions uti ON t.track_id = uti.track_id
    " . ($genre ? "JOIN track_genres tg ON t.track_id = tg.track_id" : "") . "
    $whereClause
    GROUP BY t.track_id
    ORDER BY t.play_count DESC, t.like_count DESC
    LIMIT ?, ?
";

// Dodaj parametry paginacji
$params[] = $offset;
$params[] = $limit;
$types .= "ii";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$tracks = [];
while ($row = $result->fetch_assoc()) {
    $tracks[] = [
        'track' => [
            'id' => $row['track_id'],
            'title' => $row['title'],
            'duration' => (int)$row['duration'],
            'play_count' => (int)$row['play_count'],
            'like_count' => (int)$row['like_count'],
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
        'ratings' => [
            'average' => round($row['average_rating'], 1),
            'total' => (int)$row['total_ratings']
        ]
    ];
}

// Pobierz całkowitą liczbę utworów spełniających kryteria
$countQuery = "
    SELECT COUNT(DISTINCT t.track_id) as total
    FROM tracks t
    " . ($genre ? "JOIN track_genres tg ON t.track_id = tg.track_id" : "") . "
    $whereClause
";

$stmt = $conn->prepare($countQuery);
if (!empty($params)) {
    // Usuń parametry paginacji
    array_pop($params);
    array_pop($params);
    if (!empty($params)) {
        $types = substr($types, 0, -2);
        $stmt->bind_param($types, ...$params);
    }
}
$stmt->execute();
$totalResult = $stmt->get_result();
$total = $totalResult->fetch_assoc()['total'];

echo json_encode([
    'tracks' => $tracks,
    'pagination' => [
        'total' => (int)$total,
        'offset' => $offset,
        'limit' => $limit
    ],
    'filters' => [
        'time_range' => $timeRange,
        'genre' => $genre
    ]
]);

$conn->close(); 