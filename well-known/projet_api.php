<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Gestion des préreques CORS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// Configuration de la base de données
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'c2658673c_AEC_DATABASE');
define('DB_USER', 'c2658673c_Admin');
define('DB_PASS', '9XKZr@GTr3Uy9H8');
define('UPLOAD_DIR', 'uploads/projets/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

// Démarrer la session pour l'authentification
session_start();

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

// Création de la table projets si elle n'existe pas
$createTableQuery = "
CREATE TABLE IF NOT EXISTS projets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titre VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    image VARCHAR(255),
    statut ENUM('en_cours', 'termine', 'a_venir') NOT NULL DEFAULT 'en_cours',
    date_debut DATE,
    date_fin DATE,
    localisation VARCHAR(255),
    budget VARCHAR(100),
    objectifs TEXT,
    resultats TEXT,
    partenaires TEXT,
    beneficiaires TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

try {
    $pdo->exec($createTableQuery);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la création de la table: ' . $e->getMessage()]);
    exit;
}

// Gestion des actions
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

switch ($action) {
    case 'get_projets':
        getProjets($pdo);
        break;
    case 'save_projet':
        saveProjet($pdo);
        break;
    case 'delete_projet':
        deleteProjet($pdo);
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
        break;
}

function getProjets($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM projets ORDER BY created_at DESC");
        $projets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $projets]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la récupération des projets: ' . $e->getMessage()]);
    }
}

function saveProjet($pdo) {
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

    // Récupérer et valider les données
    $id = $_POST['id'] ?? null;
    $titre = trim($_POST['titre'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $statut = $_POST['statut'] ?? 'en_cours';
    $date_debut = !empty($_POST['date_debut']) ? $_POST['date_debut'] : null;
    $date_fin = !empty($_POST['date_fin']) ? $_POST['date_fin'] : null;
    $localisation = trim($_POST['localisation'] ?? '');
    $budget = trim($_POST['budget'] ?? '');
    $objectifs = trim($_POST['objectifs'] ?? '');
    $resultats = trim($_POST['resultats'] ?? '');
    $partenaires = trim($_POST['partenaires'] ?? '');
    $beneficiaires = trim($_POST['beneficiaires'] ?? '');
    
    // Validation des champs obligatoires
    if (empty($titre) || empty($description)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Le titre et la description sont obligatoires']);
        return;
    }
    
    // Validation du statut
    $allowed_status = ['en_cours', 'termine', 'a_venir'];
    if (!in_array($statut, $allowed_status)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Statut invalide']);
        return;
    }
    
    // Gestion de l'upload d'image
    $imagePath = null;
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
        $targetPath = UPLOAD_DIR . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            $imagePath = $targetPath;
            
            // Si mise à jour, supprimer l'ancienne image
            if ($id) {
                $stmt = $pdo->prepare("SELECT image FROM projets WHERE id = ?");
                $stmt->execute([$id]);
                $oldImage = $stmt->fetchColumn();
                
                if ($oldImage && file_exists($oldImage)) {
                    unlink($oldImage);
                }
            }
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erreur lors du téléchargement du fichier']);
            return;
        }
    } elseif (!$id && empty($_FILES['image']['name'])) {
        // Nouveau projet mais aucune image fournie
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Une image est requise pour un nouveau projet']);
        return;
    } elseif ($id && !empty($_POST['existing_image'])) {
        // Conserver l'image existante lors de la mise à jour
        $imagePath = $_POST['existing_image'];
    }
    
    try {
        if ($id) {
            // Mise à jour d'un projet existant
            if ($imagePath) {
                $sql = "UPDATE projets SET titre = ?, description = ?, image = ?, statut = ?, date_debut = ?, date_fin = ?, localisation = ?, budget = ?, objectifs = ?, resultats = ?, partenaires = ?, beneficiaires = ?, updated_at = NOW() WHERE id = ?";
                $params = [$titre, $description, $imagePath, $statut, $date_debut, $date_fin, $localisation, $budget, $objectifs, $resultats, $partenaires, $beneficiaires, $id];
            } else {
                $sql = "UPDATE projets SET titre = ?, description = ?, statut = ?, date_debut = ?, date_fin = ?, localisation = ?, budget = ?, objectifs = ?, resultats = ?, partenaires = ?, beneficiaires = ?, updated_at = NOW() WHERE id = ?";
                $params = [$titre, $description, $statut, $date_debut, $date_fin, $localisation, $budget, $objectifs, $resultats, $partenaires, $beneficiaires, $id];
            }
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            $message = "Projet mis à jour avec succès";
        } else {
            // Création d'un nouveau projet
            $sql = "INSERT INTO projets (titre, description, image, statut, date_debut, date_fin, localisation, budget, objectifs, resultats, partenaires, beneficiaires, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$titre, $description, $imagePath, $statut, $date_debut, $date_fin, $localisation, $budget, $objectifs, $resultats, $partenaires, $beneficiaires]);
            $id = $pdo->lastInsertId();
            
            $message = "Projet créé avec succès";
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
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la sauvegarde du projet: ' . $e->getMessage()]);
    }
}

function deleteProjet($pdo) {
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

    // Récupérer l'ID selon la méthode
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        parse_str(file_get_contents("php://input"), $deleteVars);
        $id = $deleteVars['id'] ?? null;
    } else {
        $id = $_POST['id'] ?? $_GET['id'] ?? null;
    }
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID du projet manquant']);
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
        // Récupérer le chemin de l'image pour la suppression
        $stmt = $pdo->prepare("SELECT image FROM projets WHERE id = ?");
        $stmt->execute([$id]);
        $imagePath = $stmt->fetchColumn();
        
        // Supprimer le projet de la base de données
        $stmt = $pdo->prepare("DELETE FROM projets WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Projet non trouvé']);
            return;
        }
        
        // Supprimer le fichier image s'il existe
        if ($imagePath && file_exists($imagePath)) {
            unlink($imagePath);
        }
        
        echo json_encode(['success' => true, 'message' => 'Projet supprimé avec succès']);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression du projet: ' . $e->getMessage()]);
    }
}