<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Origin, Content-Type, X-Auth-Token');

// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'votre_base_de_donnees');
define('DB_USER', 'votre_utilisateur');
define('DB_PASS', 'votre_mot_de_passe');
define('UPLOAD_DIR', 'uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

// Démarrer la session
session_start();

// Connexion à la base de données
function getDBConnection() {
    try {
        $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $conn->exec("set names utf8");
        return $conn;
    } catch(PDOException $exception) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur de connexion à la base de données']);
        exit;
    }
}

// Vérifier et créer le dossier uploads s'il n'existe pas
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
}

// Récupérer l'action demandée
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

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
        $db = getDBConnection();
        $query = "SELECT * FROM certifications ORDER BY created_at DESC";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        $certifications = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $certifications[] = $row;
        }
        
        echo json_encode(['success' => true, 'data' => $certifications]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Fonction pour sauvegarder une certification
function saveCertification() {
    try {
        $db = getDBConnection();
        
        // Récupérer les données
        $id = isset($_POST['id']) ? $_POST['id'] : null;
        $title = $_POST['title'];
        $description = $_POST['description'];
        $stats = $_POST['stats'];
        $modules = $_POST['modules'];
        $inscription = $_POST['inscription'];
        $date_limite = $_POST['date_limite'];
        $date_debut = $_POST['date_debut'];
        $public_cible = $_POST['public_cible'];
        $methodologie = $_POST['methodologie'];
        $cout = $_POST['cout'];
        $duree = $_POST['duree'];
        $prerequis = $_POST['prerequis'];
        $debouchés = $_POST['debouchés'];
        
        // Gérer l'upload de l'image
        $image_path = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $file_name = time() . '_' . basename($_FILES['image']['name']);
            $target_path = UPLOAD_DIR . $file_name;
            
            // Vérifier le type de fichier
            $file_type = strtolower(pathinfo($target_path, PATHINFO_EXTENSION));
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (!in_array($file_type, $allowed_types)) {
                throw new Exception('Seules les images JPG, JPEG, PNG et GIF sont autorisées.');
            }
            
            // Vérifier la taille du fichier
            if ($_FILES['image']['size'] > MAX_FILE_SIZE) {
                throw new Exception('Le fichier est trop volumineux. Taille maximale: 5MB.');
            }
            
            // Déplacer le fichier
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                $image_path = $target_path;
            } else {
                throw new Exception('Erreur lors du téléchargement du fichier.');
            }
        }
        
        if ($id) {
            // Mise à jour
            if ($image_path) {
                $query = "UPDATE certifications SET title=:title, description=:description, image=:image, stats=:stats, modules=:modules, inscription=:inscription, date_limite=:date_limite, date_debut=:date_debut, public_cible=:public_cible, methodologie=:methodologie, cout=:cout, duree=:duree, prerequis=:prerequis, debouchés=:debouchés WHERE id=:id";
            } else {
                $query = "UPDATE certifications SET title=:title, description=:description, stats=:stats, modules=:modules, inscription=:inscription, date_limite=:date_limite, date_debut=:date_debut, public_cible=:public_cible, methodologie=:methodologie, cout=:cout, duree=:duree, prerequis=:prerequis, debouchés=:debouchés WHERE id=:id";
            }
        } else {
            // Insertion
            $query = "INSERT INTO certifications (title, description, image, stats, modules, inscription, date_limite, date_debut, public_cible, methodologie, cout, duree, prerequis, debouchés) VALUES (:title, :description, :image, :stats, :modules, :inscription, :date_limite, :date_debut, :public_cible, :methodologie, :cout, :duree, :prerequis, :debouchés)";
        }
        
        $stmt = $db->prepare($query);
        
        if ($id) {
            $stmt->bindParam(':id', $id);
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
        $stmt->bindParam(':debouchés', $debouchés);
        
        if ($image_path) {
            $stmt->bindParam(':image', $image_path);
        } else if (!$id) {
            $null = null;
            $stmt->bindParam(':image', $null);
        }
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Certification sauvegardée avec succès']);
        } else {
            throw new Exception('Erreur lors de la sauvegarde en base de données');
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Fonction pour supprimer une certification
function deleteCertification() {
    try {
        $id = isset($_GET['id']) ? $_GET['id'] : (isset($_POST['id']) ? $_POST['id'] : null);
        
        if (!$id) {
            throw new Exception('ID de certification manquant');
        }
        
        $db = getDBConnection();
        
        // Récupérer le chemin de l'image pour suppression
        $query = "SELECT image FROM certifications WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC) && $row['image']) {
            // Supprimer le fichier image
            if (file_exists($row['image'])) {
                unlink($row['image']);
            }
        }
        
        // Supprimer la certification
        $query = "DELETE FROM certifications WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Certification supprimée avec succès']);
        } else {
            throw new Exception('Erreur lors de la suppression de la certification');
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
