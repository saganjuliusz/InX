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

function updatePreferences($pdo, $preferences) {
    // Walidacja danych
    $validFields = [
        'preferred_genres',
        'disliked_genres',
        'preferred_moods',
        'preferred_energy_range',
        'preferred_valence_range',
        'preferred_danceability_range',
        'preferred_bpm_range',
        'discovery_level',
        'include_explicit',
        'language_preferences',
        'preferred_listening_times',
        'preferred_track_length_range'
    ];

    $updateData = array_intersect_key($preferences, array_flip($validFields));
    
    // Konwersja tablic na JSON
    foreach ($updateData as $key => $value) {
        if (is_array($value)) {
            $updateData[$key] = json_encode($value);
        }
    }

    // Sprawdź czy istnieje rekord preferencji
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM user_preferences 
        WHERE user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $exists = $stmt->fetchColumn() > 0;

    if ($exists) {
        // Aktualizuj istniejące preferencje
        $sql = "UPDATE user_preferences SET ";
        $params = [];
        foreach ($updateData as $key => $value) {
            $sql .= "$key = ?, ";
            $params[] = $value;
        }
        $sql = rtrim($sql, ", ");
        $sql .= " WHERE user_id = ?";
        $params[] = $_SESSION['user_id'];

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    } else {
        // Utwórz nowy rekord preferencji
        $updateData['user_id'] = $_SESSION['user_id'];
        
        $sql = "INSERT INTO user_preferences (" . 
               implode(", ", array_keys($updateData)) . 
               ") VALUES (" . 
               str_repeat("?,", count($updateData) - 1) . "?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_values($updateData));
    }

    return getPreferences($pdo);
}

function getPreferences($pdo) {
    $stmt = $pdo->prepare("
        SELECT 
            preferred_genres,
            disliked_genres,
            preferred_moods,
            preferred_energy_range,
            preferred_valence_range,
            preferred_danceability_range,
            preferred_bpm_range,
            discovery_level,
            include_explicit,
            language_preferences,
            preferred_listening_times,
            preferred_track_length_range
        FROM user_preferences
        WHERE user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $prefs = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$prefs) {
        // Domyślne preferencje
        return [
            'preferred_genres' => [],
            'disliked_genres' => [],
            'preferred_moods' => [],
            'preferred_energy_range' => [0, 1],
            'preferred_valence_range' => [0, 1],
            'preferred_danceability_range' => [0, 1],
            'preferred_bpm_range' => [60, 200],
            'discovery_level' => 'medium',
            'include_explicit' => true,
            'language_preferences' => ['en'],
            'preferred_listening_times' => [],
            'preferred_track_length_range' => [0, 600]
        ];
    }

    // Dekodowanie pól JSON
    foreach ($prefs as $key => $value) {
        if (in_array($key, ['preferred_genres', 'disliked_genres', 'preferred_moods', 
                           'preferred_energy_range', 'preferred_valence_range', 
                           'preferred_danceability_range', 'preferred_bpm_range',
                           'language_preferences', 'preferred_listening_times', 
                           'preferred_track_length_range'])) {
            $prefs[$key] = json_decode($value, true) ?? [];
        }
    }

    return $prefs;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? '';

        switch ($action) {
            case 'update':
                if (!isset($data['preferences']) || !is_array($data['preferences'])) {
                    throw new Exception('Brak lub nieprawidłowe dane preferencji.');
                }
                $result = updatePreferences($pdo, $data['preferences']);
                echo json_encode([
                    'success' => true,
                    'message' => 'Preferencje zostały zaktualizowane.',
                    'data' => $result
                ]);
                break;

            case 'get':
                $preferences = getPreferences($pdo);
                echo json_encode([
                    'success' => true,
                    'data' => $preferences
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