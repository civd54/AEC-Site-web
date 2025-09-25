<?php
session_start();
header('Content-Type: application/json');

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'c2658673c_AEC_DATABASE');
define('DB_USER', 'c2658673c_Admin');
define('DB_PASS', '9XKZr@GTr3Uy9H8');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    error_log("Erreur BDD: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Erreur de connexion BDD"]);
    exit;
}

// Récupération des données
$data = json_decode(file_get_contents("php://input"), true);
$username = trim($data['username'] ?? '');
$password = trim($data['password'] ?? '');

// Vérification de la saisie
if ($username === '' || $password === '') {
    echo json_encode(["success" => false, "message" => "Champs requis manquants"]);
    exit;
}

// Recherche de l’utilisateur
$stmt = $pdo->prepare("SELECT * FROM admins WHERE username = :username LIMIT 1");
$stmt->execute(['username' => $username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Fake hash pour limiter timing attacks
$fakeHash = '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG'; 

if (!$user || !password_verify($password, $user['password'] ?? $fakeHash)) {
    echo json_encode([
        "success" => false,
        "message" => "Identifiants incorrects"
    ]);
    exit;
}

// Login OK → session sécurisée
session_regenerate_id(true);
$_SESSION['admin_id'] = $user['id'];
$_SESSION['admin_username'] = $user['username'];
$_SESSION['logged_in'] = true;

echo json_encode([
    "success" => true,
    "message" => "Connexion réussie",
    "username" => $user['username']
]);

