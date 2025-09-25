<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

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
// Dossier de stockage des images
define('UPLOAD_DIR', 'uploads/gallery/');

// Créer le dossier de téléchargement s'il n'existe pas
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
}

// Connexion à la base de données
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur de connexion à la base de données: ' . $e->getMessage()]);
    exit;
}

// Démarrer la session pour l'authentification
session_start();

// Traitement des actions
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

switch ($action) {
    case 'get_gallery_items':
        getGalleryItems();
        break;
    case 'save_gallery_item':
        saveGalleryItem();
        break;
    case 'delete_gallery_item':
        deleteGalleryItem();
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
        break;
}

function getGalleryItems() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT * FROM gallery ORDER BY date DESC, created_at DESC");
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $items]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la récupération des données: ' . $e->getMessage()]);
    }
}

function saveGalleryItem() {
    global $pdo;
    
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
    
    $id = $_POST['id'] ?? null;
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $date = trim($_POST['date'] ?? '');
    $location = trim($_POST['location'] ?? '');
    
    // Validation des données
    if (empty($title) || empty($category) || empty($date)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Titre, catégorie et date sont obligatoires']);
        return;
    }
    
    try {
        // Traitement de l'upload d'image
        $imagePath = null;
        
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['image'];
            
            // Validation du type de fichier
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
            if (!in_array($file['type'], $allowedTypes)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Type de fichier non autorisé. Formats acceptés: JPG, PNG, GIF, WEBP, SVG']);
                return;
            }
            
            // Validation de la taille (max 10MB)
            if ($file['size'] > 10 * 1024 * 1024) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Fichier trop volumineux (max 10MB)']);
                return;
            }
            
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '.' . $extension;
            $destination = UPLOAD_DIR . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $imagePath = $destination;
                
                // Si on modifie un élément existant, supprimer l'ancienne image
                if ($id) {
                    $stmt = $pdo->prepare("SELECT image_path FROM gallery WHERE id = ?");
                    $stmt->execute([$id]);
                    $oldImage = $stmt->fetchColumn();
                    
                    if ($oldImage && file_exists($oldImage)) {
                        unlink($oldImage);
                    }
                }
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Erreur lors du téléchargement de l\'image']);
                return;
            }
        } elseif (!$id) {
            // Nouvel élément mais aucune image fournie
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Une image est requise pour un nouvel élément']);
            return;
        }
        
        if ($id) {
            // Mise à jour d'un élément existant
            if ($imagePath) {
                $stmt = $pdo->prepare("UPDATE gallery SET image_path = ?, title = ?, description = ?, category = ?, date = ?, location = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$imagePath, $title, $description, $category, $date, $location, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE gallery SET title = ?, description = ?, category = ?, date = ?, location = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$title, $description, $category, $date, $location, $id]);
            }
            
            $message = 'Image mise à jour avec succès';
        } else {
            // Insertion d'un nouvel élément
            $stmt = $pdo->prepare("INSERT INTO gallery (image_path, title, description, category, date, location, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
            $stmt->execute([$imagePath, $title, $description, $category, $date, $location]);
            $id = $pdo->lastInsertId();
            
            $message = 'Image ajoutée avec succès';
        }
        
        echo json_encode([
            'success' => true, 
            'message' => $message,
            'id' => $id
        ]);
        
    } catch (PDOException $e) {
        // Supprimer l'image uploadée en cas d'erreur
        if ($imagePath && file_exists($imagePath)) {
            unlink($imagePath);
        }
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la sauvegarde: ' . $e->getMessage()]);
    }
}

function deleteGalleryItem() {
    global $pdo;
    
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
    
    // Récupérer l'ID depuis différentes sources selon la méthode
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        parse_str(file_get_contents("php://input"), $deleteVars);
        $id = $deleteVars['id'] ?? null;
    } else {
        $id = $_POST['id'] ?? $_GET['id'] ?? null;
    }
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID manquant']);
        return;
    }
    
    // Valider que l'ID est un nombre
    $id = filter_var($id, FILTER_VALIDATE_INT);
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID invalide']);
        return;
    }
    
    try {
        // Récupérer le chemin de l'image avant suppression
        $stmt = $pdo->prepare("SELECT image_path FROM gallery WHERE id = ?");
        $stmt->execute([$id]);
        $imagePath = $stmt->fetchColumn();
        
        if (!$imagePath) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Élément non trouvé']);
            return;
        }
        
        // Supprimer l'entrée de la base de données
        $stmt = $pdo->prepare("DELETE FROM gallery WHERE id = ?");
        $stmt->execute([$id]);
        
        // Supprimer le fichier image
        if (file_exists($imagePath)) {
            unlink($imagePath);
        }
        
        echo json_encode(['success' => true, 'message' => 'Image supprimée avec succès']);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression: ' . $e->getMessage()]);
    }
}