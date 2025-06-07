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

function moderateContent($pdo, $content, $contentType, $params = []) {
    // Parametry domyślne
    $defaults = [
        'strict_mode' => false,
        'check_types' => ['profanity', 'spam', 'hate_speech', 'adult'],
        'language' => 'pl',
        'min_confidence' => 0.8
    ];

    $params = array_merge($defaults, $params);

    // Lista słów i fraz zabronionych (przykład)
    $blacklist = [
        'profanity' => [
            // Lista wulgaryzmów
        ],
        'spam' => [
            'buy now', 'click here', 'free money',
            'lottery', 'winner', 'prize won'
        ],
        'hate_speech' => [
            // Lista słów związanych z mową nienawiści
        ],
        'adult' => [
            // Lista słów nieodpowiednich
        ]
    ];

    $results = [
        'content_type' => $contentType,
        'moderation_date' => date('Y-m-d H:i:s'),
        'flags' => [],
        'overall_status' => 'approved',
        'confidence_score' => 1.0
    ];

    // Normalizacja tekstu
    $normalizedContent = mb_strtolower(trim($content));
    
    // Sprawdzanie każdego typu treści
    foreach ($params['check_types'] as $type) {
        if (isset($blacklist[$type])) {
            foreach ($blacklist[$type] as $phrase) {
                if (mb_strpos($normalizedContent, mb_strtolower($phrase)) !== false) {
                    $results['flags'][] = [
                        'type' => $type,
                        'phrase' => $phrase,
                        'confidence' => 1.0
                    ];
                }
            }
        }
    }

    // Dodatkowe sprawdzenia dla różnych typów treści
    switch ($contentType) {
        case 'comment':
            // Sprawdź długość
            if (mb_strlen($content) > 1000) {
                $results['flags'][] = [
                    'type' => 'length',
                    'message' => 'Komentarz jest zbyt długi',
                    'confidence' => 1.0
                ];
            }
            
            // Sprawdź spam
            if (preg_match('/https?:\/\/|www\./i', $content) > 3) {
                $results['flags'][] = [
                    'type' => 'spam',
                    'message' => 'Zbyt wiele linków',
                    'confidence' => 0.9
                ];
            }
            break;

        case 'playlist_name':
            // Sprawdź długość
            if (mb_strlen($content) > 100) {
                $results['flags'][] = [
                    'type' => 'length',
                    'message' => 'Nazwa playlisty jest zbyt długa',
                    'confidence' => 1.0
                ];
            }
            break;

        case 'user_profile':
            // Sprawdź bezpieczeństwo
            if (preg_match('/<script|javascript:|data:/i', $content)) {
                $results['flags'][] = [
                    'type' => 'security',
                    'message' => 'Wykryto potencjalnie niebezpieczną zawartość',
                    'confidence' => 1.0
                ];
            }
            break;
    }

    // Określ ogólny status
    if (!empty($results['flags'])) {
        $highConfidenceFlags = array_filter($results['flags'], function($flag) use ($params) {
            return $flag['confidence'] >= $params['min_confidence'];
        });

        if (!empty($highConfidenceFlags)) {
            $results['overall_status'] = $params['strict_mode'] ? 'rejected' : 'flagged';
            $results['confidence_score'] = max(array_column($highConfidenceFlags, 'confidence'));
        }
    }

    // Zapisz wynik moderacji
    $stmt = $pdo->prepare("
        INSERT INTO content_moderation_logs (
            content_type,
            content_hash,
            moderation_date,
            flags,
            status,
            confidence_score
        ) VALUES (?, ?, CURRENT_TIMESTAMP, ?, ?, ?)
    ");

    $stmt->execute([
        $contentType,
        hash('sha256', $content),
        json_encode($results['flags']),
        $results['overall_status'],
        $results['confidence_score']
    ]);

    return $results;
}

function validateUserContent($pdo, $userId, $contentType, $content) {
    // Sprawdź historię moderacji użytkownika
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_flags,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count
        FROM content_moderation_logs
        WHERE user_id = ?
        AND moderation_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");

    $stmt->execute([$userId]);
    $history = $stmt->fetch(PDO::FETCH_ASSOC);

    // Określ poziom ryzyka
    $riskLevel = 'low';
    if ($history['rejected_count'] >= 5) {
        $riskLevel = 'high';
    } elseif ($history['total_flags'] >= 10) {
        $riskLevel = 'medium';
    }

    // Dostosuj parametry moderacji na podstawie poziomu ryzyka
    $moderationParams = [
        'strict_mode' => $riskLevel === 'high',
        'min_confidence' => match ($riskLevel) {
            'high' => 0.7,
            'medium' => 0.8,
            default => 0.9
        }
    ];

    // Przeprowadź moderację
    $moderationResult = moderateContent($pdo, $content, $contentType, $moderationParams);

    // Dodaj informacje o ryzyku
    $moderationResult['risk_assessment'] = [
        'level' => $riskLevel,
        'history' => $history
    ];

    // Aktualizuj statystyki użytkownika
    if ($moderationResult['overall_status'] !== 'approved') {
        $stmt = $pdo->prepare("
            INSERT INTO user_moderation_stats (
                user_id,
                last_flag_date,
                total_flags,
                risk_level
            ) VALUES (?, CURRENT_TIMESTAMP, 1, ?)
            ON DUPLICATE KEY UPDATE
                last_flag_date = CURRENT_TIMESTAMP,
                total_flags = total_flags + 1,
                risk_level = ?
        ");

        $stmt->execute([$userId, $riskLevel, $riskLevel]);
    }

    return $moderationResult;
}

function reviewModerationDecision($pdo, $moderationId, $reviewerId, $decision) {
    // Sprawdź uprawnienia recenzenta
    $stmt = $pdo->prepare("
        SELECT role
        FROM users
        WHERE user_id = ?
        AND (role = 'moderator' OR role = 'admin')
    ");

    $stmt->execute([$reviewerId]);
    if (!$stmt->fetch()) {
        throw new Exception('Brak uprawnień do przeglądu moderacji.');
    }

    // Pobierz szczegóły moderacji
    $stmt = $pdo->prepare("
        SELECT *
        FROM content_moderation_logs
        WHERE moderation_id = ?
    ");

    $stmt->execute([$moderationId]);
    $moderation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$moderation) {
        throw new Exception('Nie znaleziono wskazanej moderacji.');
    }

    // Zapisz decyzję
    $stmt = $pdo->prepare("
        INSERT INTO moderation_reviews (
            moderation_id,
            reviewer_id,
            review_date,
            original_status,
            review_decision,
            notes
        ) VALUES (?, ?, CURRENT_TIMESTAMP, ?, ?, ?)
    ");

    $stmt->execute([
        $moderationId,
        $reviewerId,
        $moderation['status'],
        $decision['status'],
        $decision['notes'] ?? null
    ]);

    // Aktualizuj status moderacji jeśli potrzeba
    if ($decision['status'] !== $moderation['status']) {
        $stmt = $pdo->prepare("
            UPDATE content_moderation_logs
            SET 
                status = ?,
                last_reviewed_at = CURRENT_TIMESTAMP,
                last_reviewer_id = ?
            WHERE moderation_id = ?
        ");

        $stmt->execute([
            $decision['status'],
            $reviewerId,
            $moderationId
        ]);
    }

    // Aktualizuj statystyki systemu moderacji
    $stmt = $pdo->prepare("
        INSERT INTO moderation_system_stats (
            date,
            total_reviews,
            changed_decisions
        ) VALUES (CURRENT_DATE, 1, ?)
        ON DUPLICATE KEY UPDATE
            total_reviews = total_reviews + 1,
            changed_decisions = changed_decisions + ?
    ");

    $decisionChanged = $decision['status'] !== $moderation['status'] ? 1 : 0;
    $stmt->execute([$decisionChanged, $decisionChanged]);

    return [
        'moderation_id' => $moderationId,
        'original_status' => $moderation['status'],
        'new_status' => $decision['status'],
        'review_date' => date('Y-m-d H:i:s'),
        'reviewer_id' => $reviewerId
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? '';

        switch ($action) {
            case 'moderate_content':
                if (!isset($data['content']) || !isset($data['content_type'])) {
                    throw new Exception('Brak wymaganych parametrów.');
                }
                $params = $data['parameters'] ?? [];
                $result = moderateContent($pdo, $data['content'], $data['content_type'], $params);
                echo json_encode([
                    'success' => true,
                    'data' => $result
                ]);
                break;

            case 'validate_user_content':
                if (!isset($data['content']) || !isset($data['content_type'])) {
                    throw new Exception('Brak wymaganych parametrów.');
                }
                $result = validateUserContent($pdo, $_SESSION['user_id'], $data['content_type'], $data['content']);
                echo json_encode([
                    'success' => true,
                    'data' => $result
                ]);
                break;

            case 'review_moderation':
                if (!isset($data['moderation_id']) || !isset($data['decision'])) {
                    throw new Exception('Brak wymaganych parametrów.');
                }
                $result = reviewModerationDecision($pdo, $data['moderation_id'], $_SESSION['user_id'], $data['decision']);
                echo json_encode([
                    'success' => true,
                    'data' => $result
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