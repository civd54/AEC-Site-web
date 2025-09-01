<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'votre_base_de_donnees');
define('DB_USER', 'votre_utilisateur');
define('DB_PASS', 'votre_mot_de_passe');

// Dossier de stockage des images
define('UPLOAD_DIR', 'uploads/gallery/');

// Créer le dossier de téléchargement s'il n'existe pas
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
}

// Connexion à la base de données
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur de connexion à la base de données: ' . $e->getMessage()]);
    exit;
}

// Créer la table si elle n'existe pas
$createTableQuery = "
CREATE TABLE IF NOT EXISTS gallery (
    id INT AUTO_INCREMENT PRIMARY KEY,
    image_path VARCHAR(255) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    category VARCHAR(100) NOT NULL,
    date DATE NOT NULL,
    location VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$pdo->exec($createTableQuery);

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
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la récupération des données: ' . $e->getMessage()]);
    }
}

function saveGalleryItem() {
    global $pdo;
    
    $id = $_POST['id'] ?? null;
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $category = $_POST['category'] ?? '';
    $date = $_POST['date'] ?? '';
    $location = $_POST['location'] ?? '';
    
    // Validation des données
    if (empty($title) || empty($description) || empty($category) || empty($date)) {
        echo json_encode(['success' => false, 'message' => 'Tous les champs obligatoires doivent être remplis']);
        return;
    }
    
    try {
        // Traitement de l'upload d'image
        $imagePath = '';
        
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['image'];
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
                echo json_encode(['success' => false, 'message' => 'Erreur lors du téléchargement de l\'image']);
                return;
            }
        } elseif (!$id) {
            // Nouvel élément mais aucune image fournie
            echo json_encode(['success' => false, 'message' => 'Une image est requise']);
            return;
        }
        
        if ($id) {
            // Mise à jour d'un élément existant
            if ($imagePath) {
                $stmt = $pdo->prepare("UPDATE gallery SET image_path = ?, title = ?, description = ?, category = ?, date = ?, location = ? WHERE id = ?");
                $stmt->execute([$imagePath, $title, $description, $category, $date, $location, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE gallery SET title = ?, description = ?, category = ?, date = ?, location = ? WHERE id = ?");
                $stmt->execute([$title, $description, $category, $date, $location, $id]);
            }
            
            $message = 'Image mise à jour avec succès';
        } else {
            // Insertion d'un nouvel élément
            $stmt = $pdo->prepare("INSERT INTO gallery (image_path, title, description, category, date, location) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$imagePath, $title, $description, $category, $date, $location]);
            
            $message = 'Image ajoutée avec succès';
        }
        
        echo json_encode(['success' => true, 'message' => $message]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la sauvegarde: ' . $e->getMessage()]);
    }
}

function deleteGalleryItem() {
    global $pdo;
    
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID manquant']);
        return;
    }
    
    try {
        // Récupérer le chemin de l'image avant suppression
        $stmt = $pdo->prepare("SELECT image_path FROM gallery WHERE id = ?");
        $stmt->execute([$id]);
        $imagePath = $stmt->fetchColumn();
        
        // Supprimer l'entrée de la base de données
        $stmt = $pdo->prepare("DELETE FROM gallery WHERE id = ?");
        $stmt->execute([$id]);
        
        // Supprimer le fichier image
        if ($imagePath && file_exists($imagePath)) {
            unlink($imagePath);
        }
        
        echo json_encode(['success' => true, 'message' => 'Image supprimée avec succès']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression: ' . $e->getMessage()]);
    }
}
?>
