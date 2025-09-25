<?php
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// require 'phpmailer/src/Exception.php';
// require 'phpmailer/src/PHPMailer.php';
// require 'phpmailer/src/SMTP.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $subject = trim($_POST["subject"]);
    $message = trim($_POST["message"]);
    
    $errors = [];
    
    if (empty($name)) $errors[] = "Le nom est requis";
    if (empty($email)) $errors[] = "L'email est requis";
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Format d'email invalide";
    if (empty($subject)) $errors[] = "Le sujet est requis";
    if (empty($message)) $errors[] = "Le message est requis";
    
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(["success" => false, "errors" => $errors]);
        exit;
    }
    
    $mail = new PHPMailer(true);
    
    try {
        // Configuration SMTP
        $mail->isSMTP();
        $mail->Host = 'mail.agriexpertcenter.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'contact@agriexpertcenter.com';
        $mail->Password = 'CYI.gJw920pi_W44';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;
        
        // Destinataires
        $mail->setFrom('contact@agriexpertcenter.com', 'Site Web Agricultural Expertise Center');
        $mail->addAddress('contact@agriexpertcenter.com');
        $mail->addReplyTo($email, $name);
        
        // Contenu
        $mail->isHTML(true);
        $mail->Subject = 'Nouveau message de contact: ' . $subject;
        $mail->Body = "
            <h2>Nouveau message de contact</h2>
            <p><strong>Nom:</strong> $name</p>
            <p><strong>Email:</strong> $email</p>
            <p><strong>Sujet:</strong> $subject</p>
            <p><strong>Message:</strong></p>
            <p>$message</p>
        ";
        
        $mail->send();
        echo json_encode([
            "success" => true, 
            "message" => "Votre message a été envoyé avec succès! Nous vous contacterons bientôt."
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "success" => false, 
            "errors" => ["Le message n'a pas pu être envoyé. Erreur: " . $mail->ErrorInfo]
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode(["success" => false, "errors" => ["Méthode non autorisée"]]);
}
?>