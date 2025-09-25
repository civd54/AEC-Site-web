<?php
// blog_api.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

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
define('UPLOAD_DIR', 'uploads/blog/');

// Fonction de connexion à la base de données
function connectDB() {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur de connexion à la base de données: ' . $e->getMessage()]);
        exit();
    }
}

// Créer le répertoire de téléchargement s'il n'existe pas
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
}

// Démarrer la session
session_start();

// Gérer les différentes actions
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// Debug: logger l'action demandée
error_log("Action demandée: " . $action);

switch ($action) {
    case 'get_posts':
        getBlogPosts();
        break;
    case 'get_post':
        getBlogPost();
        break;
    case 'get_categories':
        getCategories();
        break;
    case 'get_tags':
        getTags();
        break;
    case 'get_recent_posts':
        getRecentPosts();
        break;
    case 'save_post':
        saveBlogPost();
        break;
    case 'delete_post':
        deleteBlogPost();
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Action non spécifiée ou non valide']);
        break;
}

// Récupérer tous les articles de blog
function getBlogPosts() {
    $pdo = connectDB();
    
    try {
        $stmt = $pdo->query("SELECT * FROM blog_posts ORDER BY created_at DESC");
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Debug: logger le nombre de posts récupérés
        error_log("Nombre de posts récupérés: " . count($posts));
        
        echo json_encode(['success' => true, 'data' => $posts]);
    } catch (PDOException $e) {
        // Debug: logger l'erreur
        error_log("Erreur getBlogPosts: " . $e->getMessage());
        
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la récupération des articles: ' . $e->getMessage()]);
    }
}

// Récupérer un article spécifique
function getBlogPost() {
    if (!isset($_GET['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID non spécifié']);
        return;
    }
    
    $pdo = connectDB();
    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID invalide']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE id = ?");
        $stmt->execute([$id]);
        $post = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($post) {
            echo json_encode(['success' => true, 'data' => $post]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Article non trouvé']);
        }
    } catch (PDOException $e) {
        error_log("Erreur getBlogPost: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la récupération de l\'article: ' . $e->getMessage()]);
    }
}

// Récupérer les catégories avec le nombre d'articles
function getCategories() {
    $pdo = connectDB();
    
    try {
        $stmt = $pdo->query("
            SELECT category as name, COUNT(*) as count 
            FROM blog_posts 
            WHERE category IS NOT NULL AND category != '' 
            GROUP BY category 
            ORDER BY count DESC
        ");
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $categories]);
    } catch (PDOException $e) {
        error_log("Erreur getCategories: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la récupération des catégories: ' . $e->getMessage()]);
    }
}

// Récupérer les tags avec le nombre d'articles
function getTags() {
    $pdo = connectDB();
    
    try {
        // Version simplifiée pour les tags séparés par des virgules
        $stmt = $pdo->query("
            SELECT tags 
            FROM blog_posts 
            WHERE tags IS NOT NULL AND tags != ''
        ");
        
        $allTags = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $tags = explode(',', $row['tags']);
            foreach ($tags as $tag) {
                $tag = trim($tag);
                if (!empty($tag)) {
                    $allTags[] = $tag;
                }
            }
        }
        
        // Compter les occurrences
        $tagCounts = array_count_values($allTags);
        arsort($tagCounts);
        
        // Formater pour le retour
        $tagsData = [];
        foreach ($tagCounts as $tag => $count) {
            $tagsData[] = ['name' => $tag, 'count' => $count];
        }
        
        // Limiter à 20 tags
        $tagsData = array_slice($tagsData, 0, 20);
        
        echo json_encode(['success' => true, 'data' => $tagsData]);
    } catch (PDOException $e) {
        error_log("Erreur getTags: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la récupération des tags: ' . $e->getMessage()]);
    }
}

// Récupérer les articles récents
function getRecentPosts() {
    $limit = isset($_GET['limit']) ? filter_var($_GET['limit'], FILTER_VALIDATE_INT) : 5;
    
    if (!$limit || $limit < 1) {
        $limit = 5;
    }
    
    $pdo = connectDB();
    
    try {
        $stmt = $pdo->prepare("SELECT id, title, image, created_at FROM blog_posts ORDER BY created_at DESC LIMIT ?");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $posts]);
    } catch (PDOException $e) {
        error_log("Erreur getRecentPosts: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la récupération des articles récents: ' . $e->getMessage()]);
    }
}

// Sauvegarder un article (création ou modification)
function saveBlogPost() {
    // Vérifier si c'est une requête POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
        exit;
    }

    $pdo = connectDB();

    // Vérifier l'authentification
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Accès refusé. Authentification requise.']);
        exit;
    }

    // Récupérer les données
    $id = $_POST['id'] ?? null;
    $title = trim($_POST['title'] ?? '');
    $author = trim($_POST['author'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $tags = $_POST['tags'] ?? '';
    $excerpt = trim($_POST['excerpt'] ?? '');
    $content = trim($_POST['content'] ?? '');

    // Valider les champs obligatoires
    if (empty($title) || empty($author) || empty($excerpt) || empty($content)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Tous les champs obligatoires doivent être remplis']);
        exit;
    }

    // Traiter les tags (chaîne séparée par des virgules)
    $tagsArray = [];
    if (!empty($tags)) {
        $tagsArray = array_map('trim', explode(',', $tags));
        $tagsArray = array_filter($tagsArray); // supprimer les vides
        $tagsArray = array_slice($tagsArray, 0, 10); // limiter à 10 tags
        $tagsString = implode(',', $tagsArray);
    } else {
        $tagsString = '';
    }

    // Gérer l'upload de l'image
    $imagePath = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['image'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        if (!in_array($file['type'], $allowedTypes)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Type de fichier non autorisé']);
            exit;
        }

        if ($file['size'] > 5 * 1024 * 1024) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Fichier trop volumineux']);
            exit;
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '.' . $extension;
        $destination = UPLOAD_DIR . $filename;

        if (move_uploaded_file($file['tmp_name'], $destination)) {
            $imagePath = $destination;

            // Supprimer ancienne image si modification
            if ($id) {
                $stmt = $pdo->prepare("SELECT image FROM blog_posts WHERE id = ?");
                $stmt->execute([$id]);
                $oldImage = $stmt->fetchColumn();
                if ($oldImage && file_exists($oldImage)) {
                    unlink($oldImage);
                }
            }
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erreur lors du téléchargement de l\'image']);
            exit;
        }
    }

    try {
        if ($id) {
            // Mise à jour
            if ($imagePath) {
                $stmt = $pdo->prepare("
                    UPDATE blog_posts 
                    SET title=?, author=?, category=?, tags=?, excerpt=?, content=?, image=?, updated_at=NOW() 
                    WHERE id=?
                ");
                $stmt->execute([$title, $author, $category, $tagsString, $excerpt, $content, $imagePath, $id]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE blog_posts 
                    SET title=?, author=?, category=?, tags=?, excerpt=?, content=?, updated_at=NOW() 
                    WHERE id=?
                ");
                $stmt->execute([$title, $author, $category, $tagsString, $excerpt, $content, $id]);
            }
            $message = 'Article mis à jour avec succès';
        } else {
            // Création
            $stmt = $pdo->prepare("
                INSERT INTO blog_posts (title, author, category, tags, excerpt, content, image, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([$title, $author, $category, $tagsString, $excerpt, $content, $imagePath]);
            $id = $pdo->lastInsertId();
            $message = 'Article créé avec succès';
        }

        echo json_encode([
            'success' => true,
            'message' => $message,
            'id' => $id,
            'tags' => $tagsArray
        ]);
    } catch (PDOException $e) {
        error_log("Erreur saveBlogPost: " . $e->getMessage());
        if ($imagePath && file_exists($imagePath)) {
            unlink($imagePath);
        }
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la sauvegarde: ' . $e->getMessage()]);
    }
}

// Supprimer un article
function deleteBlogPost() {
    // Vérifier la méthode
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
        exit;
    }

    // Vérifier l'authentification
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Accès refusé. Authentification requise.']);
        exit;
    }

    if (!isset($_GET['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID non spécifié']);
        return;
    }
    
    $pdo = connectDB();
    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID invalide']);
        return;
    }
    
    try {
        // Récupérer le chemin de l'image pour la supprimer
        $stmt = $pdo->prepare("SELECT image FROM blog_posts WHERE id = ?");
        $stmt->execute([$id]);
        $imagePath = $stmt->fetchColumn();
        
        // Supprimer l'article
        $stmt = $pdo->prepare("DELETE FROM blog_posts WHERE id = ?");
        $stmt->execute([$id]);
        
        // Supprimer l'image associée si elle existe
        if ($imagePath && file_exists($imagePath)) {
            unlink($imagePath);
        }
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Article supprimé avec succès']);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Article non trouvé']);
        }
    } catch (PDOException $e) {
        error_log("Erreur deleteBlogPost: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression de l\'article: ' . $e->getMessage()]);
    }
}