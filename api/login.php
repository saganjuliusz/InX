<?php
// Ustawienia bezpieczeństwa sesji
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
session_start();

require_once 'config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Sprawdzenie czy otrzymano wymagane dane
        if (!isset($data['login']) || !isset($data['password'])) {
            throw new Exception('Brak wymaganych danych.');
        }
        
        $login = trim($data['login']); // może być email lub nazwa użytkownika
        $password = $data['password'];
        
        // Walidacja podstawowa - sprawdzenie czy pola nie są puste
        if (empty($login) || empty($password)) {
            throw new Exception('Login i hasło nie mogą być puste.');
        }
        
        // Przygotowanie zapytania sprawdzającego zarówno nazwę użytkownika jak i email
        // Dostosowane do nowej struktury tabeli 'users'
        $stmt = $pdo->prepare("
            SELECT user_id, username, email, password_hash, subscription_tier, account_status
            FROM users 
            WHERE (username = ? OR email = ?) AND account_status = 'active'
        ");
        $stmt->execute([$login, $login]);
        $user = $stmt->fetch();
        
        // Sprawdzenie czy użytkownik istnieje i czy hasło jest poprawne
        if ($user && password_verify($password, $user['password_hash'])) {
            // Regeneracja ID sesji dla zwiększenia bezpieczeństwa
            session_regenerate_id(true);
            
            // Ustawienie danych sesji - dostosowane do nowej struktury
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['subscription_tier'] = $user['subscription_tier']; // zamiast 'role'
            $_SESSION['logged_in'] = true;
            
            // Aktualizacja czasu ostatniego logowania - dostosowane do nowej struktury
            $updateStmt = $pdo->prepare("
                UPDATE users 
                SET last_login_at = CURRENT_TIMESTAMP, 
                    last_active_at = CURRENT_TIMESTAMP 
                WHERE user_id = ?
            ");
            $updateStmt->execute([$user['user_id']]);
            
            // Zwrócenie odpowiedzi sukcesu z danymi użytkownika
            echo json_encode([
                'success' => true,
                'message' => 'Zalogowano pomyślnie',
                'user' => [
                    'user_id' => $user['user_id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'subscription_tier' => $user['subscription_tier']
                ],
                'session_id' => session_id()
            ]);
            
        } else {
            // Logowanie nieudanej próby logowania do systemu logów PHP
            error_log("Nieudana próba logowania dla: " . $login . " z IP: " . $_SERVER['REMOTE_ADDR']);
            
            throw new Exception('Nieprawidłowe dane logowania lub konto nieaktywne');
        }
        
    } catch (Exception $e) {
        // Logowanie błędu do systemu logów
        error_log("Błąd logowania: " . $e->getMessage() . " dla IP: " . $_SERVER['REMOTE_ADDR']);
        
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