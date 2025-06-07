<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Użytkownik nie jest zalogowany.'
    ]);
    exit;
}

function analyzeAudioFeatures($pdo, $trackId) {
    // Pobierz podstawowe informacje o utworze
    $stmt = $pdo->prepare("
        SELECT 
            t.track_id,
            t.title,
            t.duration,
            t.file_path,
            t.energy_level,
            t.valence,
            t.danceability,
            t.instrumentalness,
            t.acousticness,
            t.speechiness,
            t.loudness,
            t.bpm,
            t.key_signature,
            t.time_signature
        FROM tracks t
        WHERE t.track_id = ?
    ");
    $stmt->execute([$trackId]);
    $track = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$track) {
        throw new Exception('Utwór nie istnieje.');
    }

    // Analiza segmentów audio
    $segments = analyzeAudioSegments($track['file_path']);
    
    // Analiza beatów
    $beats = analyzeBeats($track['file_path']);
    
    // Analiza harmonii
    $harmony = analyzeHarmony($track['file_path']);

    return [
        'track_info' => $track,
        'segments' => $segments,
        'beats' => $beats,
        'harmony' => $harmony
    ];
}

function analyzeAudioSegments($filePath) {
    // Symulacja analizy segmentów audio
    // W rzeczywistej implementacji należałoby użyć biblioteki do analizy audio
    return [
        'segments' => [
            [
                'start' => 0.0,
                'duration' => 1.5,
                'loudness' => -12.3,
                'timbre' => [0.8, 0.6, 0.4],
                'pitches' => [0.9, 0.2, 0.1, 0.0, 0.1, 0.3, 0.2, 0.1, 0.0, 0.1, 0.2, 0.1]
            ],
            // ... więcej segmentów
        ],
        'segment_count' => 150,
        'average_segment_duration' => 1.2
    ];
}

function analyzeBeats($filePath) {
    // Symulacja analizy beatów
    return [
        'beats' => [
            ['start' => 0.0, 'confidence' => 0.9],
            ['start' => 0.5, 'confidence' => 0.85],
            // ... więcej beatów
        ],
        'tempo' => 120.5,
        'tempo_confidence' => 0.95,
        'beat_count' => 256
    ];
}

function analyzeHarmony($filePath) {
    // Symulacja analizy harmonii
    return [
        'key' => 'C',
        'mode' => 'major',
        'key_confidence' => 0.85,
        'mode_confidence' => 0.9,
        'chord_progression' => [
            ['chord' => 'C', 'start' => 0.0, 'duration' => 2.0],
            ['chord' => 'Am', 'start' => 2.0, 'duration' => 2.0],
            // ... więcej akordów
        ]
    ];
}

function generateWaveform($pdo, $trackId, $resolution = 100) {
    // Pobierz ścieżkę do pliku
    $stmt = $pdo->prepare("SELECT file_path FROM tracks WHERE track_id = ?");
    $stmt->execute([$trackId]);
    $track = $stmt->fetch();

    if (!$track) {
        throw new Exception('Utwór nie istnieje.');
    }

    // Symulacja generowania waveformu
    // W rzeczywistej implementacji należałoby użyć biblioteki do przetwarzania audio
    $waveform = [];
    for ($i = 0; $i < $resolution; $i++) {
        $waveform[] = [
            'position' => $i / $resolution,
            'amplitude' => rand(0, 100) / 100
        ];
    }

    return [
        'resolution' => $resolution,
        'data' => $waveform
    ];
}

function detectSections($pdo, $trackId) {
    // Pobierz podstawowe informacje o utworze
    $stmt = $pdo->prepare("
        SELECT duration, file_path 
        FROM tracks 
        WHERE track_id = ?
    ");
    $stmt->execute([$trackId]);
    $track = $stmt->fetch();

    if (!$track) {
        throw new Exception('Utwór nie istnieje.');
    }

    // Symulacja detekcji sekcji utworu
    return [
        'sections' => [
            [
                'start' => 0.0,
                'duration' => 15.2,
                'type' => 'intro',
                'confidence' => 0.9
            ],
            [
                'start' => 15.2,
                'duration' => 30.4,
                'type' => 'verse',
                'confidence' => 0.85
            ],
            [
                'start' => 45.6,
                'duration' => 25.3,
                'type' => 'chorus',
                'confidence' => 0.95
            ],
            // ... więcej sekcji
        ],
        'structure_confidence' => 0.88
    ];
}

function getSimilarByAudioFeatures($pdo, $trackId, $limit = 20) {
    // Pobierz cechy źródłowego utworu
    $stmt = $pdo->prepare("
        SELECT 
            energy_level,
            valence,
            danceability,
            instrumentalness,
            acousticness,
            speechiness,
            loudness,
            bpm
        FROM tracks
        WHERE track_id = ?
    ");
    $stmt->execute([$trackId]);
    $sourceTrack = $stmt->fetch();

    if (!$sourceTrack) {
        throw new Exception('Utwór nie istnieje.');
    }

    // Znajdź podobne utwory na podstawie cech audio
    $stmt = $pdo->prepare("
        SELECT 
            t.track_id,
            t.title,
            t.duration,
            t.energy_level,
            t.valence,
            t.bpm,
            a.name as artist_name,
            a.artist_id,
            al.title as album_title,
            al.album_id,
            al.cover_art_url,
            (
                POW(t.energy_level - :energy, 2) +
                POW(t.valence - :valence, 2) +
                POW(t.danceability - :dance, 2) +
                POW(t.instrumentalness - :instr, 2) +
                POW(t.acousticness - :acoust, 2) +
                POW(t.speechiness - :speech, 2) +
                POW((t.loudness - :loud) / 60, 2) +
                POW((t.bpm - :bpm) / 200, 2)
            ) as distance
        FROM tracks t
        JOIN artists a ON t.artist_id = a.artist_id
        LEFT JOIN albums al ON t.album_id = al.album_id
        WHERE t.track_id != :track_id
        HAVING distance < 0.5
        ORDER BY distance ASC
        LIMIT :limit
    ");

    $stmt->bindValue(':track_id', $trackId);
    $stmt->bindValue(':energy', $sourceTrack['energy_level']);
    $stmt->bindValue(':valence', $sourceTrack['valence']);
    $stmt->bindValue(':dance', $sourceTrack['danceability']);
    $stmt->bindValue(':instr', $sourceTrack['instrumentalness']);
    $stmt->bindValue(':acoust', $sourceTrack['acousticness']);
    $stmt->bindValue(':speech', $sourceTrack['speechiness']);
    $stmt->bindValue(':loud', $sourceTrack['loudness']);
    $stmt->bindValue(':bpm', $sourceTrack['bpm']);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? '';

        switch ($action) {
            case 'analyze_audio':
                if (!isset($data['track_id'])) {
                    throw new Exception('Brak ID utworu.');
                }
                $analysis = analyzeAudioFeatures($pdo, $data['track_id']);
                echo json_encode([
                    'success' => true,
                    'data' => $analysis
                ]);
                break;

            case 'generate_waveform':
                if (!isset($data['track_id'])) {
                    throw new Exception('Brak ID utworu.');
                }
                $resolution = min(($data['resolution'] ?? 100), 500);
                $waveform = generateWaveform($pdo, $data['track_id'], $resolution);
                echo json_encode([
                    'success' => true,
                    'data' => $waveform
                ]);
                break;

            case 'detect_sections':
                if (!isset($data['track_id'])) {
                    throw new Exception('Brak ID utworu.');
                }
                $sections = detectSections($pdo, $data['track_id']);
                echo json_encode([
                    'success' => true,
                    'data' => $sections
                ]);
                break;

            case 'find_similar':
                if (!isset($data['track_id'])) {
                    throw new Exception('Brak ID utworu.');
                }
                $limit = min(($data['limit'] ?? 20), 50);
                $similar = getSimilarByAudioFeatures($pdo, $data['track_id'], $limit);
                echo json_encode([
                    'success' => true,
                    'data' => $similar
                ]);
                break;

            default:
                throw new Exception('Nieznana akcja.');
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Nieprawidłowa metoda żądania. Wymagana metoda POST.'
    ]);
}
?> 