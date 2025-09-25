<?php
/**
 * ProuveTech - Gestion sécurisée des inscriptions bêta
 * À placer dans /api/subscribe.php
 */

// Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'beta'); // À remplacer
define('DB_USER', 'root'); // À remplacer
define('DB_PASS', ''); // À remplacer

// Email configuration
define('ADMIN_EMAIL', 'kevinlokoka@gmail.com');
define('FROM_EMAIL', 'noreply@prouvetech.com');
define('FROM_NAME', 'ProuveTech');

// Headers de sécurité
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// CORS - Ajustez selon vos besoins
$allowed_origins = ['http://127.0.0.1:5500/'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
}

// Gestion des requêtes OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Vérifier que c'est bien une requête POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

// Vérifier que c'est une requête AJAX
if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Requête invalide']);
    exit();
}

// Anti-spam : vérifier le honeypot
if (!empty($_POST['website'])) {
    // C'est probablement un bot
    http_response_code(200); // On retourne 200 pour ne pas alerter le bot
    echo json_encode(['success' => true, 'message' => 'Inscription enregistrée']);
    exit();
}

// Rate limiting simple (optionnel)
session_start();
$session_key = 'last_submission_' . md5($_SERVER['REMOTE_ADDR']);
if (isset($_SESSION[$session_key])) {
    $last_submission = $_SESSION[$session_key];
    if (time() - $last_submission < 60) { // Limite à 1 soumission par minute
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => 'Trop de tentatives. Veuillez réessayer dans une minute.']);
        exit();
    }
}
$_SESSION[$session_key] = time();

// Fonction de validation et nettoyage
function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Validation des données
$errors = [];
$firstName = clean_input($_POST['firstName'] ?? '');
$lastName = clean_input($_POST['lastName'] ?? '');
$email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
$profile = clean_input($_POST['profile'] ?? '');
$linkedin = filter_var($_POST['linkedin'] ?? '', FILTER_SANITIZE_URL);
$consent = isset($_POST['consent']) ? 1 : 0;
$newsletter = isset($_POST['newsletter']) ? 1 : 0;

// Validations
if (empty($firstName) || strlen($firstName) < 2 || strlen($firstName) > 100) {
    $errors[] = 'Prénom invalide';
}

if (empty($lastName) || strlen($lastName) < 2 || strlen($lastName) > 100) {
    $errors[] = 'Nom invalide';
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Email invalide';
}

if (!in_array($profile, ['developer', 'recruiter', 'both'])) {
    $errors[] = 'Profil invalide';
}

if (!empty($linkedin) && !filter_var($linkedin, FILTER_VALIDATE_URL)) {
    $errors[] = 'URL LinkedIn invalide';
}

if (!$consent) {
    $errors[] = 'Vous devez accepter les conditions';
}

// Si des erreurs, on arrête
if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
    exit();
}

// Connexion à la base de données
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    error_log("Erreur de connexion DB: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur. Veuillez réessayer plus tard.']);
    exit();
}

// Vérifier si l'email existe déjà
try {
    $stmt = $pdo->prepare("SELECT id FROM beta_subscribers WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Cette adresse email est déjà inscrite.']);
        exit();
    }
} catch (PDOException $e) {
    error_log("Erreur vérification email: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur. Veuillez réessayer.']);
    exit();
}

// Générer un token de confirmation
$confirmation_token = bin2hex(random_bytes(32));

// Insérer dans la base de données
try {
    $stmt = $pdo->prepare("
        INSERT INTO beta_subscribers 
        (first_name, last_name, email, profile, linkedin, consent, newsletter, confirmation_token, ip_address, user_agent) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $firstName,
        $lastName,
        $email,
        $profile,
        $linkedin ?: null,
        $consent,
        $newsletter,
        $confirmation_token,
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT']
    ]);
    
    $subscriber_id = $pdo->lastInsertId();
    
} catch (PDOException $e) {
    error_log("Erreur insertion DB: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'inscription. Veuillez réessayer.']);
    exit();
}

// Envoyer l'email de confirmation à l'utilisateur
$confirmation_link = "https://prouvetech.com/confirm?token=" . $confirmation_token;
$to_user = $email;
$subject_user = "Confirmez votre inscription à ProuveTech Beta";

$message_user = "
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #3b82f6, #1e3a8a); color: white; padding: 30px; text-align: center; }
        .content { background: white; padding: 30px; }
        .button { display: inline-block; background: #3b82f6; color: white; padding: 12px 30px; text-decoration: none; border-radius: 50px; margin: 20px 0; }
        .footer { text-align: center; padding: 20px; color: #666; font-size: 0.9em; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>Bienvenue sur ProuveTech !</h1>
        </div>
        <div class='content'>
            <p>Bonjour $firstName,</p>
            <p>Merci de votre intérêt pour ProuveTech ! Vous êtes sur le point de rejoindre notre programme bêta exclusif.</p>
            <p>Pour confirmer votre inscription, cliquez sur le bouton ci-dessous :</p>
            <div style='text-align: center;'>
                <a href='$confirmation_link' class='button'>Confirmer mon inscription</a>
            </div>
            <p>Ou copiez ce lien dans votre navigateur :</p>
            <p style='word-break: break-all;'>$confirmation_link</p>
            <p>Ce lien expire dans 48 heures.</p>
            <p>À très bientôt sur ProuveTech !</p>
            <p>L'équipe ProuveTech</p>
        </div>
        <div class='footer'>
            <p>Cet email a été envoyé à $email</p>
            <p>© 2025 ProuveTech - Tous droits réservés</p>
        </div>
    </div>
</body>
</html>
";

$headers_user = "MIME-Version: 1.0" . "\r\n";
$headers_user .= "Content-type:text/html;charset=UTF-8" . "\r\n";
$headers_user .= "From: " . FROM_NAME . " <" . FROM_EMAIL . ">" . "\r\n";
$headers_user .= "Reply-To: " . ADMIN_EMAIL . "\r\n";

mail($to_user, $subject_user, $message_user, $headers_user);

// Envoyer une notification à l'admin
$subject_admin = "Nouvelle inscription bêta ProuveTech";
$message_admin = "
Nouvelle inscription sur ProuveTech Beta :

Nom : $firstName $lastName
Email : $email
Profil : $profile
LinkedIn : " . ($linkedin ?: 'Non fourni') . "
Newsletter : " . ($newsletter ? 'Oui' : 'Non') . "
Date : " . date('Y-m-d H:i:s') . "
IP : " . $_SERVER['REMOTE_ADDR'] . "
";

$headers_admin = "From: " . FROM_NAME . " <" . FROM_EMAIL . ">" . "\r\n";
$headers_admin .= "Reply-To: $email" . "\r\n";

mail(ADMIN_EMAIL, $subject_admin, $message_admin, $headers_admin);

// Réponse de succès
http_response_code(200);
echo json_encode([
    'success' => true, 
    'message' => 'Inscription réussie ! Vérifiez votre email pour confirmer.'
]);

// Créer la table si elle n'existe pas (à exécuter une fois)
/*
CREATE TABLE IF NOT EXISTS beta_subscribers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    profile ENUM('developer', 'recruiter', 'both') NOT NULL,
    linkedin VARCHAR(255),
    consent BOOLEAN DEFAULT TRUE,
    newsletter BOOLEAN DEFAULT FALSE,
    confirmation_token VARCHAR(64),
    confirmed_at DATETIME DEFAULT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_confirmation_token (confirmation_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
*/