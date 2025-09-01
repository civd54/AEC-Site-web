<?php
// blog_api.php

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
define('DB_HOST', 'localhost');
define('DB_NAME', 'aec_blog');
define('DB_USER', 'root');
define('DB_PASS', '');

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

// Gérer les différentes actions
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

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
        
        // Formater les tags (stockés en JSON)
        foreach ($posts as &$post) {
            if (!empty($post['tags'])) {
                $post['tags'] = json_decode($post['tags']);
            }
        }
        
        echo json_encode(['success' => true, 'data' => $posts]);
    } catch (PDOException $e) {
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
            if (!empty($post['tags'])) {
                $post['tags'] = json_decode($post['tags']);
            }
            echo json_encode(['success' => true, 'data' => $post]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Article non trouvé']);
        }
    } catch (PDOException $e) {
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
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la récupération des catégories: ' . $e->getMessage()]);
    }
}

// Récupérer les tags avec le nombre d'articles
function getTags() {
    $pdo = connectDB();
    
    try {
        $stmt = $pdo->query("
            SELECT tag as name, COUNT(*) as count 
            FROM (
                SELECT JSON_UNQUOTE(JSON_EXTRACT(tags, CONCAT('$[', idx, ']'))) as tag
                FROM blog_posts
                JOIN (SELECT 0 as idx UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 
                      UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) numbers
                WHERE JSON_EXTRACT(tags, CONCAT('$[', idx, ']')) IS NOT NULL
                AND tags IS NOT NULL AND tags != '[]'
            ) tags_expanded
            GROUP BY tag 
            ORDER BY count DESC
            LIMIT 20
        ");
        $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $tags]);
    } catch (PDOException $e) {
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
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la récupération des articles récents: ' . $e->getMessage()]);
    }
}

// Sauvegarder un article (création ou modification)
function saveBlogPost() {
    $pdo = connectDB();
    
    // Récupérer les données
    $id = $_POST['id'] ?? null;
    $title = $_POST['title'] ?? '';
    $author = $_POST['author'] ?? '';
    $category = $_POST['category'] ?? '';
    $tags = $_POST['tags'] ?? '';
    $excerpt = $_POST['excerpt'] ?? '';
    $content = $_POST['content'] ?? '';
    
    // Valider les données requises
    if (empty($title) || empty($author) || empty($excerpt) || empty($content)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Tous les champs obligatoires doivent être remplis']);
        return;
    }
    
    // Traiter les tags (séparés par des virgules)
    $tagsArray = [];
    if (!empty($tags)) {
        $tagsArray = array_map('trim', explode(',', $tags));
        $tagsArray = array_filter($tagsArray); // Supprimer les éléments vides
        $tagsArray = array_slice($tagsArray, 0, 10); // Limiter à 10 tags
    }
    $tagsJson = json_encode($tagsArray);
    
    // Gérer l'upload de l'image
    $imagePath = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['image'];
        
        // Vérifier le type de fichier
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file['type'], $allowedTypes)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Type de fichier non autorisé. Seules les images JPEG, PNG, GIF et WebP sont acceptées']);
            return;
        }
        
        // Vérifier la taille du fichier (max 5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Le fichier est trop volumineux. Taille maximale: 5MB']);
            return;
        }
        
        // Générer un nom de fichier unique
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '.' . $extension;
        $destination = UPLOAD_DIR . $filename;
        
        // Déplacer le fichier uploadé
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            $imagePath = $destination;
            
            // Si on modifie un article existant, supprimer l'ancienne image
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
            return;
        }
    }
    
    try {
        if ($id) {
            // Mise à jour d'un article existant
            if ($imagePath) {
                $stmt = $pdo->prepare("
                    UPDATE blog_posts 
                    SET title = ?, author = ?, category = ?, tags = ?, excerpt = ?, content = ?, image = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$title, $author, $category, $tagsJson, $excerpt, $content, $imagePath, $id]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE blog_posts 
                    SET title = ?, author = ?, category = ?, tags = ?, excerpt = ?, content = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$title, $author, $category, $tagsJson, $excerpt, $content, $id]);
            }
            
            $message = 'Article mis à jour avec succès';
        } else {
            // Création d'un nouvel article
            $stmt = $pdo->prepare("
                INSERT INTO blog_posts (title, author, category, tags, excerpt, content, image, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([$title, $author, $category, $tagsJson, $excerpt, $content, $imagePath]);
            
            $id = $pdo->lastInsertId();
            $message = 'Article créé avec succès';
        }
        
        echo json_encode(['success' => true, 'message' => $message, 'id' => $id]);
    } catch (PDOException $e) {
        // En cas d'erreur, supprimer l'image uploadée
        if ($imagePath && file_exists($imagePath)) {
            unlink($imagePath);
        }
        
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la sauvegarde de l\'article: ' . $e->getMessage()]);
    }
}

// Supprimer un article
function deleteBlogPost() {
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
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression de l\'article: ' . $e->getMessage()]);
    }
}

// Script de création de la table (à exécuter une seule fois)
function createBlogTable() {
    $pdo = connectDB();
    
    $sql = "
    CREATE TABLE IF NOT EXISTS blog_posts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        author VARCHAR(100) NOT NULL,
        category VARCHAR(100),
        tags TEXT,
        excerpt TEXT,
        content LONGTEXT,
        image VARCHAR(255),
        created_at DATETIME,
        updated_at DATETIME
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    
    try {
        $pdo->exec($sql);
        echo "Table 'blog_posts' créée avec succès ou déjà existante.";
    } catch (PDOException $e) {
        echo "Erreur lors de la création de la table: " . $e->getMessage();
    }
}

// Décommenter la ligne suivante pour créer la table (à exécuter une seule fois)
// createBlogTable();
