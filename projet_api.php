<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gestion des préreques CORS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Configuration de la base de données
$host = 'localhost';
$dbname = 'aec_database';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
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
)";

try {
    $pdo->exec($createTableQuery);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la création de la table: ' . $e->getMessage()]);
    exit;
}

// Gestion des actions
$action = $_GET['action'] ?? $_POST['action'] ?? '';

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
        echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
        break;
}

function getProjets($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM projets ORDER BY created_at DESC");
        $projets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $projets]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la récupération des projets: ' . $e->getMessage()]);
    }
}

function saveProjet($pdo) {
    $id = $_POST['id'] ?? null;
    $titre = $_POST['titre'] ?? '';
    $description = $_POST['description'] ?? '';
    $statut = $_POST['statut'] ?? 'en_cours';
    $date_debut = !empty($_POST['date_debut']) ? $_POST['date_debut'] : null;
    $date_fin = !empty($_POST['date_fin']) ? $_POST['date_fin'] : null;
    $localisation = $_POST['localisation'] ?? '';
    $budget = $_POST['budget'] ?? '';
    $objectifs = $_POST['objectifs'] ?? '';
    $resultats = $_POST['resultats'] ?? '';
    $partenaires = $_POST['partenaires'] ?? '';
    $beneficiaires = $_POST['beneficiaires'] ?? '';
    
    // Gestion de l'upload d'image
    $imagePath = null;
    if (!empty($_FILES['image']['name'])) {
        $uploadDir = 'uploads/projets/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileName = time() . '_' . basename($_FILES['image']['name']);
        $targetPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
            $imagePath = $targetPath;
        }
    } elseif (!empty($_POST['existing_image'])) {
        $imagePath = $_POST['existing_image'];
    }
    
    try {
        if ($id) {
            // Mise à jour d'un projet existant
            $sql = "UPDATE projets SET titre = ?, description = ?, statut = ?, date_debut = ?, date_fin = ?, localisation = ?, budget = ?, objectifs = ?, resultats = ?, partenaires = ?, beneficiaires = ?";
            $params = [$titre, $description, $statut, $date_debut, $date_fin, $localisation, $budget, $objectifs, $resultats, $partenaires, $beneficiaires];
            
            if ($imagePath) {
                $sql .= ", image = ?";
                $params[] = $imagePath;
            }
            
            $sql .= " WHERE id = ?";
            $params[] = $id;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            $message = "Projet mis à jour avec succès";
        } else {
            // Création d'un nouveau projet
            $sql = "INSERT INTO projets (titre, description, image, statut, date_debut, date_fin, localisation, budget, objectifs, resultats, partenaires, beneficiaires) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$titre, $description, $imagePath, $statut, $date_debut, $date_fin, $localisation, $budget, $objectifs, $resultats, $partenaires, $beneficiaires]);
            
            $message = "Projet créé avec succès";
        }
        
        echo json_encode(['success' => true, 'message' => $message]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la sauvegarde du projet: ' . $e->getMessage()]);
    }
}

function deleteProjet($pdo) {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID du projet manquant']);
        return;
    }
    
    try {
        // Récupérer le chemin de l'image pour la suppression
        $stmt = $pdo->prepare("SELECT image FROM projets WHERE id = ?");
        $stmt->execute([$id]);
        $projet = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($projet && $projet['image'] && file_exists($projet['image'])) {
            unlink($projet['image']);
        }
        
        // Supprimer le projet de la base de données
        $stmt = $pdo->prepare("DELETE FROM projets WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true, 'message' => 'Projet supprimé avec succès']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression du projet: ' . $e->getMessage()]);
    }
}
?>
