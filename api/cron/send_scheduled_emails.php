<?php
require_once __DIR__ . '/../config.php';

// Pobierz zaplanowane maile do wysłania
$stmt = $pdo->prepare("
    SELECT 
        es.*,
        u.email,
        u.username
    FROM email_schedules es
    JOIN users u ON es.user_id = u.user_id
    WHERE es.next_send_date <= CURRENT_TIMESTAMP
    AND u.email_verified = 1
    AND es.is_active = 1
");

$stmt->execute();
$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($schedules as $schedule) {
    try {
        // Wyślij statystyki
        switch ($schedule['email_type']) {
            case 'stats':
                $timeRange = match($schedule['frequency']) {
                    'daily' => '1 day',
                    'weekly' => '7 days',
                    'monthly' => '30 days'
                };
                
                sendUserStats($pdo, $schedule['user_id'], $timeRange);
                break;
                
            // Tutaj można dodać obsługę innych typów maili
        }

        // Zaktualizuj datę następnego wysłania
        $nextSendDate = match($schedule['frequency']) {
            'daily' => date('Y-m-d H:i:s', strtotime('tomorrow')),
            'weekly' => date('Y-m-d H:i:s', strtotime('next monday')),
            'monthly' => date('Y-m-d H:i:s', strtotime('first day of next month'))
        };

        $stmt = $pdo->prepare("
            UPDATE email_schedules
            SET 
                next_send_date = ?,
                last_sent_at = CURRENT_TIMESTAMP
            WHERE schedule_id = ?
        ");

        $stmt->execute([$nextSendDate, $schedule['schedule_id']]);

        // Zapisz log sukcesu
        $stmt = $pdo->prepare("
            INSERT INTO email_logs (
                user_id,
                email_type,
                email_data,
                status,
                sent_at
            ) VALUES (?, ?, ?, 'success', CURRENT_TIMESTAMP)
        ");

        $stmt->execute([
            $schedule['user_id'],
            $schedule['email_type'],
            json_encode([
                'frequency' => $schedule['frequency'],
                'next_send_date' => $nextSendDate
            ])
        ]);

    } catch (Exception $e) {
        // Zapisz log błędu
        $stmt = $pdo->prepare("
            INSERT INTO email_logs (
                user_id,
                email_type,
                email_data,
                status,
                error_message,
                sent_at
            ) VALUES (?, ?, ?, 'error', ?, CURRENT_TIMESTAMP)
        ");

        $stmt->execute([
            $schedule['user_id'],
            $schedule['email_type'],
            json_encode([
                'frequency' => $schedule['frequency']
            ]),
            $e->getMessage()
        ]);
    }
}
?> 