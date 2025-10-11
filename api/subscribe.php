<?php
/**
 * Fichier : /api/subscribe.php
 * Version simplifiée et fonctionnelle pour localhost
 */

// 🧩 Debug : afficher toutes les erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 🔐 Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'beta'); // <-- mets ici le nom de ta base
define('DB_USER', 'root'); // <-- ton utilisateur MySQL
define('DB_PASS', '');     // <-- ton mot de passe (souvent vide en localhost)

// 📦 Headers pour le retour JSON
header('Content-Type: application/json; charset=utf-8');

// ✅ Autoriser ton frontend local
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gérer la requête OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Vérifier que c’est une requête POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

// 🧹 Fonction pour nettoyer les entrées utilisateur
function clean_input($data) {
    return htmlspecialchars(stripslashes(trim($data)), ENT_QUOTES, 'UTF-8');
}

// 🔍 Récupérer et valider les données du formulaire
$name = clean_input($_POST['name'] ?? '');
$email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
$profile = clean_input($_POST['profile'] ?? '');

$errors = [];

if (empty($name) || strlen($name) < 2) {
    $errors[] = 'Nom invalide';
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Email invalide';
}

if (!in_array($profile, ['developer', 'recruiter'])) {
    $errors[] = 'Profil invalide';
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
    exit();
}

// 🧠 Connexion à la base de données
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur de connexion : ' . $e->getMessage()]);
    exit();
}

// 📋 Créer la table si elle n’existe pas (utile pour tester)
$pdo->exec("
CREATE TABLE IF NOT EXISTS beta_subscribers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    profile ENUM('developer', 'recruiter') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// 🚫 Vérifier si l’email existe déjà
$stmt = $pdo->prepare("SELECT id FROM beta_subscribers WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Cet email est déjà inscrit.']);
    exit();
}

// 💾 Insérer l’utilisateur
$stmt = $pdo->prepare("INSERT INTO beta_subscribers (name, email, profile) VALUES (?, ?, ?)");
$stmt->execute([$name, $email, $profile]);

http_response_code(200);
echo json_encode(['success' => true, 'message' => 'Inscription réussie !']);

// Envoyer un mail à l'admin
$admin_email = 'kevinlokoka@gmail.com'; // Ton email
$from_email = 'noreply@prouvetech.com'; // Email d'envoi
$from_name = 'ProuveTech Beta';

$subject = "Nouvelle inscription bêta ProuveTech";

$message = "
Une nouvelle personne s'est inscrite à la bêta :

Nom : $name
Email : $email
Profil : $profile
Date : " . date('Y-m-d H:i:s') . "
IP : " . $_SERVER['REMOTE_ADDR'] . "
";

// Headers
$headers = "From: $from_name <$from_email>" . "\r\n";
$headers .= "Reply-To: $email" . "\r\n";
$headers .= "Content-Type: text/plain; charset=utf-8";

// Envoyer le mail
mail($admin_email, $subject, $message, $headers);

?>
