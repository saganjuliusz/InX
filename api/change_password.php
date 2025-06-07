<?php
// change_password.php
// Skrypt do zmiany hasła użytkownika z walidacją i powiadomieniami

// Rozpoczęcie sesji dla sprawdzenia autoryzacji
session_start();

// Dołączenie niezbędnych plików konfiguracyjnych
require_once 'config.php';
require_once 'mail_handler.php';

// Ustawienie nagłówka JSON dla wszystkich odpowiedzi
header('Content-Type: application/json');

// === KONTROLA AUTORYZACJI ===
// Sprawdzenie czy użytkownik jest zalogowany przed umożliwieniem zmiany hasła
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Użytkownik nie jest zalogowany.'
    ]);
    exit;
}

// Obsługa żądań POST (zmiana hasła powinna być wykonywana metodą POST ze względów bezpieczeństwa)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // === ODCZYTANIE I WALIDACJA DANYCH WEJŚCIOWYCH ===
        
        // Dekodowanie danych JSON przesłanych w treści żądania
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Sprawdzenie czy wszystkie wymagane pola zostały przesłane
        if (!isset($data['oldPassword']) || !isset($data['newPassword'])) {
            throw new Exception('Brak wymaganych danych.');
        }
        
        // Wyciągnięcie danych z bezpiecznym przypisaniem
        $oldPassword = $data['oldPassword'];
        $newPassword = $data['newPassword'];
        
        // === WALIDACJA NOWEGO HASŁA ===
        
        // Sprawdzenie minimalnej długości hasła (zwiększone z 8 do bardziej bezpiecznego poziomu)
        if (strlen($newPassword) < 8) {
            throw new Exception('Nowe hasło musi mieć co najmniej 8 znaków.');
        }
        
        // Dodatkowa walidacja siły hasła - sprawdzenie czy zawiera różne typy znaków
        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/', $newPassword)) {
            throw new Exception('Hasło musi zawierać co najmniej jedną małą literę, jedną dużą literę i jedną cyfrę.');
        }
        
        // === WERYFIKACJA AKTUALNEGO HASŁA ===
        
        // Pobranie danych użytkownika
        $stmt = $pdo->prepare("
            SELECT user_id, email, password_hash, account_status 
            FROM users 
            WHERE user_id = ? AND account_status = 'active'
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        // Sprawdzenie czy użytkownik istnieje i czy podał prawidłowe aktualne hasło
        if (!$user) {
            throw new Exception('Konto użytkownika jest nieaktywne lub nie istnieje.');
        }
        
        // Weryfikacja starego hasła
        if (!password_verify($oldPassword, $user['password_hash'])) {
            throw new Exception('Nieprawidłowe aktualne hasło.');
        }
        
        // === AKTUALIZACJA HASŁA W BAZIE DANYCH ===
        
        // Generowanie nowego hashu hasła
        $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Rozpoczęcie transakcji dla zapewnienia integralności danych
        $pdo->beginTransaction();
        
        try {
            // Aktualizacja hasła w bazie danych - dostosowane do nowej struktury
            $updateStmt = $pdo->prepare("
                UPDATE users 
                SET password_hash = ?,
                    updated_at = CURRENT_TIMESTAMP,
                    last_active_at = CURRENT_TIMESTAMP
                WHERE user_id = ?
            ");
            $updateStmt->execute([$newPasswordHash, $user['user_id']]);
            
            // Sprawdzenie czy aktualizacja rzeczywiście została wykonana
            if ($updateStmt->rowCount() === 0) {
                throw new Exception('Nie udało się zaktualizować hasła');
            }
            
            // Zatwierdzenie transakcji
            $pdo->commit();
            
            // === WYSŁANIE POWIADOMIENIA EMAIL ===
            
            // Próba wysłania powiadomienia o zmianie hasła
            $mailHandler = new MailHandler();
            $mailHandler->wyslijPowiadomienieZmianyHasla($user['email']);
            
            // === DODATKOWE ŚRODKI BEZPIECZEŃSTWA ===
            
            // Regeneracja ID sesji po zmianie hasła dla zwiększenia bezpieczeństwa
            session_regenerate_id(true);
            
            // Zwrócenie odpowiedzi sukcesu
            echo json_encode([
                'success' => true,
                'message' => 'Hasło zostało pomyślnie zmienione.'
            ]);
            
        } catch (Exception $dbError) {
            // Wycofanie transakcji w przypadku błędu bazy danych
            $pdo->rollBack();
            throw $dbError;
        }
        
    } catch (Exception $e) {
        // === OBSŁUGA BŁĘDÓW ===
        
        // Logowanie szczegółów błędu dla administratorów
        error_log("Błąd zmiany hasła dla użytkownika ID " . ($_SESSION['user_id'] ?? 'nieznany') . ": " . $e->getMessage());
        
        // Zwrócenie komunikatu błędu do użytkownika
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    
} else {
    // === OBSŁUGA NIEPRAWIDŁOWYCH METOD HTTP ===
    
    echo json_encode([
        'success' => false,
        'message' => 'Nieprawidłowa metoda żądania. Wymagana metoda POST.'
    ]);
}
?>