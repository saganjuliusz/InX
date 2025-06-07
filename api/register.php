<?php
// Rozpoczęcie sesji
session_start();

// Dołączenie pliku konfiguracyjnego bazy danych
require_once 'config.php';

// Ustawienie nagłówka odpowiedzi jako JSON
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Odczytanie danych JSON z żądania
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Wyciągnięcie i oczyszczenie danych wejściowych
        $username = trim($data['username'] ?? '');
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';
        $confirmPassword = $data['confirmPassword'] ?? '';
        
        // === WALIDACJA DANYCH WEJŚCIOWYCH ===
        
        // Sprawdzenie czy wszystkie wymagane pola zostały wypełnione
        if (empty($username) || empty($email) || empty($password)) {
            throw new Exception('Wszystkie pola są wymagane');
        }
        
        // Sprawdzenie czy hasła są identyczne
        if ($password !== $confirmPassword) {
            throw new Exception('Hasła nie są identyczne');
        }
        
        // Walidacja formatu adresu email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Nieprawidłowy format adresu email');
        }
        
        // Walidacja długości nazwy użytkownika (minimum 3 znaki, maksimum 50)
        if (strlen($username) < 3 || strlen($username) > 50) {
            throw new Exception('Nazwa użytkownika musi mieć od 3 do 50 znaków');
        }
        
        // Walidacja siły hasła (minimum 6 znaków)
        if (strlen($password) < 6) {
            throw new Exception('Hasło musi mieć minimum 6 znaków');
        }
        
        // Sprawdzenie czy nazwa użytkownika zawiera tylko dozwolone znaki
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
            throw new Exception('Nazwa użytkownika może zawierać tylko litery, cyfry, podkreślenia i myślniki');
        }
        
        // === SPRAWDZENIE UNIKALNOŚCI W BAZIE DANYCH ===
        
        // Sprawdzenie czy użytkownik o takiej nazwie lub emailu już istnieje
        // Dostosowane do nowej struktury tabeli 'users'
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count, 
                   SUM(CASE WHEN username = ? THEN 1 ELSE 0 END) as username_exists,
                   SUM(CASE WHEN email = ? THEN 1 ELSE 0 END) as email_exists
            FROM users 
            WHERE username = ? OR email = ?
        ");
        $stmt->execute([$username, $email, $username, $email]);
        $result = $stmt->fetch();
        
        // Sprawdzenie konkretnych konfliktów
        if ($result['username_exists'] > 0) {
            throw new Exception('Użytkownik o takiej nazwie już istnieje');
        }
        
        if ($result['email_exists'] > 0) {
            throw new Exception('Użytkownik o takim adresie email już istnieje');
        }
        
        // === TWORZENIE NOWEGO KONTA UŻYTKOWNIKA ===
        
        // Hashowanie hasła przy użyciu bezpiecznego algorytmu
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Wstawienie nowego użytkownika do bazy danych
        // Dostosowane do nowej struktury tabeli 'users' z domyślnymi wartościami
        $stmt = $pdo->prepare("
            INSERT INTO users (
                username, 
                email, 
                password_hash, 
                account_status, 
                subscription_tier,
                created_at
            ) VALUES (?, ?, ?, 'active', 'free', CURRENT_TIMESTAMP)
        ");
        
        $stmt->execute([$username, $email, $hashedPassword]);
        
        // Pobranie ID nowo utworzonego użytkownika
        $newUserId = $pdo->lastInsertId();
        
        // Logowanie pomyślnej rejestracji
        error_log("Nowe konto utworzone: użytkownik $username (ID: $newUserId) z IP: " . $_SERVER['REMOTE_ADDR']);
        
        // Zwrócenie odpowiedzi sukcesu
        echo json_encode([
            'success' => true,
            'message' => 'Konto zostało utworzone pomyślnie',
            'user_id' => $newUserId
        ]);
        
    } catch (PDOException $e) {
        // Obsługa błędów bazy danych
        error_log("Błąd bazy danych podczas rejestracji: " . $e->getMessage());
        
        echo json_encode([
            'success' => false,
            'message' => 'Błąd podczas rejestracji. Spróbuj ponownie później.'
        ]);
        
    } catch (Exception $e) {
        // Obsługa ogólnych błędów walidacji
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    
} else {
    // Obsługa nieprawidłowej metody HTTP
    echo json_encode([
        'success' => false,
        'message' => 'Nieprawidłowa metoda żądania. Wymagana metoda POST.'
    ]);
}
?>