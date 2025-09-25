<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Origin, Content-Type, X-Auth-Token, Authorization, X-Requested-With');

// Debug - Activer l'affichage des erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Gérer les requêtes preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Configuration de la base de données
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'c2658673c_AEC_DATABASE');
define('DB_USER', 'c2658673c_Admin');
define('DB_PASS', '9XKZr@GTr3Uy9H8');
define('UPLOAD_DIR', $_SERVER['DOCUMENT_ROOT'] . '/uploads/certifications/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

// Démarrer la session
session_start();

// Vérifier et créer le dossier uploads s'il n'existe pas
if (!file_exists(UPLOAD_DIR)) {
    if (!mkdir(UPLOAD_DIR, 0777, true)) {
        error_log("Impossible de créer le dossier: " . UPLOAD_DIR);
    }
}

// Connexion à la base de données
function getDBConnection() {
    try {
        $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $conn;
    } catch(PDOException $exception) {
        error_log("Erreur DB: " . $exception->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur de connexion à la base de données']);
        exit;
    }
}

// Récupérer l'action demandée
$action = $_GET['action'] ?? ($_POST['action'] ?? '');
error_log("Action demandée: " . $action);

// Router vers la fonction appropriée
switch ($action) {
    case 'get_certifications':
        getCertifications();
        break;
    case 'save_certification':
        saveCertification();
        break;
    case 'delete_certification':
        deleteCertification();
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Action non spécifiée ou invalide']);
        break;
}

// Fonction pour récupérer toutes les certifications
function getCertifications() {
    try {
        error_log("Début de getCertifications");
        $db = getDBConnection();
        
        // Vérifier si la table existe
        $tableCheck = $db->query("SHOW TABLES LIKE 'certifications'");
        if ($tableCheck->rowCount() === 0) {
            error_log("Table 'certifications' n'existe pas");
            echo json_encode(['success' => true, 'data' => []]);
            return;
        }
        
        $query = "SELECT * FROM certifications ORDER BY created_at DESC";
        error_log("Exécution de la requête: " . $query);
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        $certifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Nombre de certifications trouvées: " . count($certifications));
        
        echo json_encode(['success' => true, 'data' => $certifications]);
        
    } catch (Exception $e) {
        error_log("Erreur dans getCertifications: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la récupération: ' . $e->getMessage()]);
    }
}

// Fonction pour sauvegarder une certification
function saveCertification() {
    // Vérifier l'authentification
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Accès refusé. Authentification requise.']);
        return;
    }

    // Vérifier la méthode
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
        return;
    }

    try {
        $db = getDBConnection();
        
        // Récupérer et valider les données
        $id = $_POST['id'] ?? null;
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $stats = trim($_POST['stats'] ?? '');
        $modules = trim($_POST['modules'] ?? '');
        $inscription = trim($_POST['inscription'] ?? '');
        $date_limite = trim($_POST['date_limite'] ?? '');
        $date_debut = trim($_POST['date_debut'] ?? '');
        $public_cible = trim($_POST['public_cible'] ?? '');
        $methodologie = trim($_POST['methodologie'] ?? '');
        $cout = trim($_POST['cout'] ?? '');
        $duree = trim($_POST['duree'] ?? '');
        $prerequis = trim($_POST['prerequis'] ?? '');
        $debouches = trim($_POST['debouches'] ?? '');
        
        // Validation des champs obligatoires
        if (empty($title) || empty($description)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Le titre et la description sont obligatoires']);
            return;
        }
        
        // Gérer l'upload de l'image
        $image_path = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['image'];
            
            // Vérifier le type de fichier
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($file['type'], $allowed_types)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Type de fichier non autorisé. Formats acceptés: JPG, PNG, GIF, WEBP']);
                return;
            }
            
            // Vérifier la taille du fichier
            if ($file['size'] > MAX_FILE_SIZE) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Le fichier est trop volumineux. Taille maximale: 5MB.']);
                return;
            }
            
            // Générer un nom de fichier unique
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '.' . $extension;
            $target_path = UPLOAD_DIR . $filename;
            
            // Déplacer le fichier
            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                $image_path = $target_path;
                
                // Si mise à jour, supprimer l'ancienne image
                if ($id) {
                    $stmt = $db->prepare("SELECT image FROM certifications WHERE id = ?");
                    $stmt->execute([$id]);
                    $oldImage = $stmt->fetchColumn();
                    
                    if ($oldImage && file_exists($oldImage)) {
                        unlink($oldImage);
                    }
                }
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Erreur lors du téléchargement du fichier.']);
                return;
            }
        } elseif (!$id && empty($_FILES['image']['name'])) {
            // Nouvelle certification mais aucune image fournie
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Une image est requise pour une nouvelle certification']);
            return;
        } elseif ($id && !empty($_POST['existing_image'])) {
            // Conserver l'image existante lors de la mise à jour
            $image_path = $_POST['existing_image'];
        }
        
        if ($id) {
            // Mise à jour
            if ($image_path) {
                $query = "UPDATE certifications SET title=:title, description=:description, image=:image, stats=:stats, modules=:modules, inscription=:inscription, date_limite=:date_limite, date_debut=:date_debut, public_cible=:public_cible, methodologie=:methodologie, cout=:cout, duree=:duree, prerequis=:prerequis, debouches=:debouches, updated_at=NOW() WHERE id=:id";
            } else {
                $query = "UPDATE certifications SET title=:title, description=:description, stats=:stats, modules=:modules, inscription=:inscription, date_limite=:date_limite, date_debut=:date_debut, public_cible=:public_cible, methodologie=:methodologie, cout=:cout, duree=:duree, prerequis=:prerequis, debouches=:debouches, updated_at=NOW() WHERE id=:id";
            }
        } else {
            // Insertion
            $query = "INSERT INTO certifications (title, description, image, stats, modules, inscription, date_limite, date_debut, public_cible, methodologie, cout, duree, prerequis, debouches, created_at, updated_at) VALUES (:title, :description, :image, :stats, :modules, :inscription, :date_limite, :date_debut, :public_cible, :methodologie, :cout, :duree, :prerequis, :debouches, NOW(), NOW())";
        }
        
        $stmt = $db->prepare($query);
        
        if ($id) {
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        }
        
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':stats', $stats);
        $stmt->bindParam(':modules', $modules);
        $stmt->bindParam(':inscription', $inscription);
        $stmt->bindParam(':date_limite', $date_limite);
        $stmt->bindParam(':date_debut', $date_debut);
        $stmt->bindParam(':public_cible', $public_cible);
        $stmt->bindParam(':methodologie', $methodologie);
        $stmt->bindParam(':cout', $cout);
        $stmt->bindParam(':duree', $duree);
        $stmt->bindParam(':prerequis', $prerequis);
        $stmt->bindParam(':debouches', $debouches);
        
        if ($image_path) {
            $stmt->bindParam(':image', $image_path);
        } else if (!$id) {
            $null = null;
            $stmt->bindParam(':image', $null, PDO::PARAM_NULL);
        }
        
        if ($stmt->execute()) {
            $lastId = $id ?: $db->lastInsertId();
            echo json_encode([
                'success' => true, 
                'message' => 'Certification ' . ($id ? 'mise à jour' : 'ajoutée') . ' avec succès',
                'id' => $lastId
            ]);
        } else {
            throw new Exception('Erreur lors de la sauvegarde en base de données');
        }
    } catch (Exception $e) {
        // Supprimer l'image uploadée en cas d'erreur
        if (isset($image_path) && $image_path && file_exists($image_path)) {
            unlink($image_path);
        }
        error_log("Erreur saveCertification: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
    }
}

// Fonction pour supprimer une certification
function deleteCertification() {
    // Vérifier l'authentification
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Accès refusé. Authentification requise.']);
        return;
    }

    // Vérifier la méthode
    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
        return;
    }

    try {
        // Récupérer l'ID selon la méthode
        if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
            parse_str(file_get_contents("php://input"), $deleteVars);
            $id = $deleteVars['id'] ?? null;
        } else {
            $id = $_POST['id'] ?? $_GET['id'] ?? null;
        }
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID de certification manquant']);
            return;
        }
        
        // Valider que l'ID est un nombre
        $id = filter_var($id, FILTER_VALIDATE_INT);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID invalide']);
            return;
        }
        
        $db = getDBConnection();
        
        // Récupérer le chemin de l'image pour suppression
        $stmt = $db->prepare("SELECT image FROM certifications WHERE id = ?");
        $stmt->execute([$id]);
        $image_path = $stmt->fetchColumn();
        
        // Supprimer la certification
        $stmt = $db->prepare("DELETE FROM certifications WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Certification non trouvée']);
            return;
        }
        
        // Supprimer le fichier image s'il existe
        if ($image_path && file_exists($image_path)) {
            unlink($image_path);
        }
        
        echo json_encode(['success' => true, 'message' => 'Certification supprimée avec succès']);
        
    } catch (Exception $e) {
        error_log("Erreur deleteCertification: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression: ' . $e->getMessage()]);
    }
}