<?php
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

// 🚫 Ne jamais afficher les erreurs en production
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

// 🧱 Paramètres de connexion à la base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'prouvetech_beta');  
define('DB_USER', 'prouvetech_app');   
define('DB_PASS', getenv('PROUVETECH_DB_PASS') ?: '');

// define('DB_HOST', 'localhost');
// define('DB_NAME', 'beta');   
// define('DB_USER', 'root');  
// define('DB_PASS', ''); 

// 📦 Réponse JSON
header('Content-Type: application/json; charset=utf-8');

// 🔒 Sécurité navigateur
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer-when-downgrade');
header('Permissions-Policy: microphone=(), camera=()');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

// 🌍 CORS (si besoin d’un frontend externe)
header('Access-Control-Allow-Origin: https://prouvetech.fr');
// header('Access-Control-Allow-Origin: http://localhost/prouvetechbeta/');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 📮 Fonction utilitaire pour nettoyer les entrées
function clean_input($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// 📊  Gestions des stats  (requête GET avec ?stats=1)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['stats'])) {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        $count = $pdo->query("SELECT COUNT(*) FROM beta_subscribers")->fetchColumn();
        
        echo json_encode([
            'success' => true,
            'count' => (int)$count
        ]);
        exit();
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'count' => 0]);
        exit();
    }
}

// 🧾 Vérification méthode
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

// 🧹 Récupération des données POST
$name = clean_input($_POST['name'] ?? '');
$email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
$profile = clean_input($_POST['profile'] ?? '');
$honeypot = $_POST['website'] ?? ''; // champ invisible anti-spam

// 🚫 Anti-bot basique
if (!empty($honeypot)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Bot détecté.']);
    exit();
}

// ✅ Validation des données
$errors = [];

if (empty($name) || strlen($name) < 2) $errors[] = 'Nom invalide';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email invalide';
if (!in_array($profile, ['developer', 'cto'])) $errors[] = 'Profil invalide';

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
    exit();
}

// 🧠 Connexion base de données
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Erreur DB: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur serveur.']);
    exit();
}

// 🔍 Vérifie si déjà inscrit
$stmt = $pdo->prepare("SELECT id FROM beta_subscribers WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Cet email est déjà inscrit.']);
    exit();
}

// 💾 Enregistrer l’utilisateur
$stmt = $pdo->prepare("INSERT INTO beta_subscribers (name, email, profile) VALUES (?, ?, ?)");
$stmt->execute([$name, $email, $profile]);

// 📧 Envoi du mail à l’admin
$admin_email = 'kevinlokoka@gmail.com';
$from_email  = getenv('PROUVETECH_SMTP_USER') ?: 'kevin.lokoka@prouvetech.fr';
$smtp_pass   = getenv('PROUVETECH_SMTP_PASS') ?: '';
$from_name   = 'ProuveTech Beta';
$subject     = 'Nouvelle inscription bêta ProuveTech';

$message = "
Une nouvelle inscription à la bêta :

👤 Nom : $name
📧 Email : $email
💼 Profil : $profile
📅 Date : " . date('Y-m-d H:i:s') . "
";

try {
    $mailer = new PHPMailer(true);
    $mailer->isSMTP();
    $mailer->Host = 'smtp.hostinger.com';
    $mailer->SMTPAuth = true;
    $mailer->Username = $from_email;
    $mailer->Password = $smtp_pass;
    $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mailer->Port = 465;
    $mailer->CharSet = 'UTF-8';
    $mailer->setFrom($from_email, $from_name);
    $mailer->addAddress($admin_email);
    $mailer->addReplyTo($email);
    $mailer->Subject = $subject;
    $mailer->Body = $message;
    $mailer->send();
} catch (Exception $e) {
    error_log('Erreur SMTP: ' . $e->getMessage());
}

// Réponse au frontend
http_response_code(200);
echo json_encode(['success' => true, 'message' => 'Inscription réussie !']);

exit;
?>
