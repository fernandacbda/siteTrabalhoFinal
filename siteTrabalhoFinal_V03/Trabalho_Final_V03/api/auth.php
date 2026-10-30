<?php
declare(strict_types=1);

require __DIR__ . DIRECTORY_SEPARATOR . 'demo_data.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

session_name('site_session');
session_set_cookie_params([
    'httponly' => true,
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Strict',
    'path' => '/',
]);
session_start();

function respond(int $status, array $data): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function getUnicodeLength(string $value): ?int
{
    $length = preg_match_all('/./us', $value);
    return $length === false ? null : $length;
}

function getDatabase(): PDO
{
    $dataDirectory = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.site_data';

    if (!is_dir($dataDirectory) && !mkdir($dataDirectory, 0700, true) && !is_dir($dataDirectory)) {
        throw new RuntimeException('Não foi possível criar o diretório do banco de dados.');
    }

    $database = new PDO(
        'sqlite:' . $dataDirectory . DIRECTORY_SEPARATOR . 'users.sqlite',
        null,
        null,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    $database->exec('PRAGMA busy_timeout = 5000');
    $database->exec('PRAGMA foreign_keys = ON');
    $database->exec(
        "CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL COLLATE NOCASE UNIQUE,
            password_hash TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            profile_photo TEXT,
            bio TEXT NOT NULL DEFAULT ''
        )"
    );

    $columns = $database->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('profile_photo', $columns, true)) {
        $database->exec('ALTER TABLE users ADD COLUMN profile_photo TEXT');
    }
    if (!in_array('bio', $columns, true)) {
        $database->exec("ALTER TABLE users ADD COLUMN bio TEXT NOT NULL DEFAULT ''");
    }

    $database->exec(
        "CREATE TABLE IF NOT EXISTS articles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            author_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            title TEXT NOT NULL,
            summary TEXT NOT NULL,
            content TEXT NOT NULL,
            cover_image TEXT,
            status TEXT NOT NULL CHECK (status IN ('draft', 'published')),
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            views INTEGER NOT NULL DEFAULT 0
        )"
    );
    $articleColumns = $database->query('PRAGMA table_info(articles)')->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('views', $articleColumns, true)) {
        $database->exec('ALTER TABLE articles ADD COLUMN views INTEGER NOT NULL DEFAULT 0');
    }
    $database->exec(
        "CREATE TABLE IF NOT EXISTS article_ratings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            article_id INTEGER NOT NULL REFERENCES articles(id) ON DELETE CASCADE,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            rating INTEGER NOT NULL CHECK (rating BETWEEN 1 AND 5),
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (article_id, user_id)
        )"
    );

    seedDemoData($database);

    return $database;
}

function validateArticleInput(): array
{
    $title = $_POST['title'] ?? null;
    $summary = $_POST['summary'] ?? null;
    $content = $_POST['content'] ?? null;
    $status = $_POST['status'] ?? null;

    $titleLength = is_string($title) ? getUnicodeLength($title) : null;
    if (!is_string($title) || trim($title) === '' || $titleLength === null || $titleLength > 100) {
        respond(422, ['message' => 'Informe um título com até 100 caracteres.']);
    }
    $summaryLength = is_string($summary) ? getUnicodeLength($summary) : null;
    if (!is_string($summary) || trim($summary) === '' || $summaryLength === null || $summaryLength > 200) {
        respond(422, ['message' => 'Informe um resumo com até 200 caracteres.']);
    }
    $contentLength = is_string($content) ? getUnicodeLength($content) : null;
    if (!is_string($content) || trim($content) === '' || $contentLength === null || $contentLength > 20000) {
        respond(422, ['message' => 'Escreva o artigo com até 20.000 caracteres.']);
    }
    if (!in_array($status, ['draft', 'published'], true)) {
        respond(422, ['message' => 'Escolha se deseja salvar como rascunho ou publicar.']);
    }

    return [
        'title' => trim($title),
        'summary' => trim($summary),
        'content' => trim($content),
        'status' => $status,
    ];
}

// Salva a capa enviada e retorna o caminho relativo, ou null quando nenhuma imagem foi enviada.
function saveArticleCover(): ?string
{
    $coverUpload = $_FILES['cover'] ?? null;
    if (!is_array($coverUpload) || $coverUpload['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($coverUpload['error'] !== UPLOAD_ERR_OK) {
        if (in_array($coverUpload['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            respond(413, ['message' => 'A imagem de capa deve ter no máximo 2 MB.']);
        }
        respond(400, ['message' => 'Não foi possível receber a imagem de capa.']);
    }

    if ($coverUpload['size'] > 2 * 1024 * 1024) {
        respond(413, ['message' => 'A imagem de capa deve ter no máximo 2 MB.']);
    }

    $imageInfo = getimagesize($coverUpload['tmp_name']);
    $mimeType = $imageInfo['mime'] ?? '';
    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!is_array($imageInfo) || !isset($allowedTypes[$mimeType])) {
        respond(422, ['message' => 'Use uma imagem JPG, PNG ou WebP para a capa.']);
    }

    $uploadDirectory = getArticleUploadDirectory();
    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
        throw new RuntimeException('Não foi possível preparar o diretório de capas.');
    }

    $fileName = bin2hex(random_bytes(16)) . '.' . $allowedTypes[$mimeType];
    if (!move_uploaded_file($coverUpload['tmp_name'], $uploadDirectory . DIRECTORY_SEPARATOR . $fileName)) {
        throw new RuntimeException('Não foi possível salvar a imagem de capa.');
    }

    return '../uploads/articles/' . $fileName;
}

function getArticleUploadDirectory(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'articles';
}

function deleteArticleCover(?string $coverPath): void
{
    if (!is_string($coverPath) || !str_starts_with($coverPath, '../uploads/articles/')) {
        return;
    }

    $filePath = getArticleUploadDirectory() . DIRECTORY_SEPARATOR . basename($coverPath);
    if (is_file($filePath)) {
        unlink($filePath);
    }
}

function findOwnArticle(PDO $database, mixed $articleId): array
{
    $articleId = filter_var($articleId, FILTER_VALIDATE_INT);
    if (!$articleId || $articleId < 1) {
        respond(400, ['message' => 'Informe um artigo válido.']);
    }

    $statement = $database->prepare(
        'SELECT id, cover_image, status FROM articles WHERE id = :id AND author_id = :author_id'
    );
    $statement->execute([':id' => $articleId, ':author_id' => $_SESSION['user']['id']]);
    $article = $statement->fetch();
    if (!$article) {
        respond(404, ['message' => 'Artigo não encontrado.']);
    }

    return $article;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? '';

        if ($action === 'session') {
            if (!isset($_SESSION['user']['id'])) {
                respond(200, ['authenticated' => false]);
            }

            $database = getDatabase();
            $statement = $database->prepare(
                'SELECT id, name, email, created_at, profile_photo, bio FROM users WHERE id = :id'
            );
            $statement->execute([':id' => $_SESSION['user']['id']]);
            $user = $statement->fetch();

            if (!$user) {
                unset($_SESSION['user']);
                respond(200, ['authenticated' => false]);
            }

            $_SESSION['user'] = [
                'id' => (int) $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'created_at' => $user['created_at'],
                'profile_photo' => $user['profile_photo'],
                'bio' => $user['bio'],
            ];

            respond(200, [
                'authenticated' => true,
                'user' => $_SESSION['user'],
            ]);
        }

        $database = getDatabase();

        if ($action === 'public_articles') {
            $source = $_GET['source'] ?? 'listing';
            $requestedSort = $_GET['sort'] ?? null;
            $sort = is_string($requestedSort) && in_array($requestedSort, ['recentes', 'avaliacao', 'titulo'], true)
                ? $requestedSort
                : ($source === 'homepage' ? 'recentes' : 'avaliacao');
            $limit = 50;
            $ratingFilter = $source === 'homepage'
                ? 'AND rating_summary.rating_count > 0'
                : '';
            $search = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
            $searchFilter = $search !== ''
                ? "AND (articles.title LIKE :search ESCAPE '\\' OR articles.summary LIKE :search ESCAPE '\\'
                        OR users.name LIKE :search ESCAPE '\\')"
                : '';
            $orderBy = match ($sort) {
                'titulo' => 'articles.title COLLATE NOCASE ASC, articles.id DESC',
                'recentes' => 'articles.created_at DESC, articles.id DESC',
                default => 'rating_average DESC, rating_count DESC, articles.created_at DESC, articles.id DESC',
            };
            $statement = $database->prepare(
                "SELECT articles.id, articles.title, articles.summary, articles.cover_image,
                        articles.created_at, users.name AS author_name,
                        COALESCE(rating_summary.rating_average, 0) AS rating_average,
                        COALESCE(rating_summary.rating_count, 0) AS rating_count,
                        viewer_rating.rating AS my_rating
                 FROM articles
                 JOIN users ON users.id = articles.author_id
                 LEFT JOIN (
                    SELECT article_id, AVG(rating) AS rating_average, COUNT(*) AS rating_count
                    FROM article_ratings
                    GROUP BY article_id
                 ) AS rating_summary ON rating_summary.article_id = articles.id
                 LEFT JOIN article_ratings AS viewer_rating
                    ON viewer_rating.article_id = articles.id
                   AND viewer_rating.user_id = :viewer_id
                 WHERE articles.status = 'published'
                   $ratingFilter
                   $searchFilter
                 ORDER BY $orderBy
                 LIMIT :limit"
            );
            if ($search !== '') {
                $statement->bindValue(':search', '%' . addcslashes($search, '\\%_') . '%');
            }
            $viewerId = $_SESSION['user']['id'] ?? null;
            $statement->bindValue(
                ':viewer_id',
                $viewerId,
                $viewerId === null ? PDO::PARAM_NULL : PDO::PARAM_INT
            );
            $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
            $statement->execute();
            respond(200, ['articles' => $statement->fetchAll()]);
        }

        if ($action === 'my_articles') {
            if (!isset($_SESSION['user']['id'])) {
                respond(401, ['message' => 'Entre na sua conta para ver seus artigos.']);
            }

            $statement = $database->prepare(
                'SELECT articles.id, articles.title, articles.summary, articles.cover_image,
                        articles.status, articles.created_at, articles.views,
                        COALESCE(rating_summary.rating_average, 0) AS rating_average,
                        COALESCE(rating_summary.rating_count, 0) AS rating_count
                 FROM articles
                 LEFT JOIN (
                    SELECT article_id, AVG(rating) AS rating_average, COUNT(*) AS rating_count
                    FROM article_ratings
                    GROUP BY article_id
                 ) AS rating_summary ON rating_summary.article_id = articles.id
                 WHERE articles.author_id = :author_id
                 ORDER BY articles.created_at DESC, articles.id DESC'
            );
            $statement->execute([':author_id' => $_SESSION['user']['id']]);
            $recentRatings = $database->prepare(
                "SELECT article_ratings.rating, article_ratings.updated_at,
                        users.name AS reviewer_name, articles.id AS article_id,
                        articles.title AS article_title
                 FROM article_ratings
                 JOIN users ON users.id = article_ratings.user_id
                 JOIN articles ON articles.id = article_ratings.article_id
                 WHERE articles.author_id = :author_id
                 ORDER BY article_ratings.updated_at DESC, article_ratings.id DESC
                 LIMIT 5"
            );
            $recentRatings->execute([':author_id' => $_SESSION['user']['id']]);
            respond(200, [
                'articles' => $statement->fetchAll(),
                'recent_ratings' => $recentRatings->fetchAll(),
            ]);
        }

        if ($action === 'article') {
            $articleId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$articleId || $articleId < 1) {
                respond(400, ['message' => 'Informe um artigo válido.']);
            }

            $statement = $database->prepare(
                "SELECT articles.id, articles.title, articles.summary, articles.content,
                        articles.cover_image, articles.status, articles.created_at,
                        users.id AS author_id, users.name AS author_name,
                        COALESCE((SELECT AVG(rating) FROM article_ratings
                                  WHERE article_id = articles.id), 0) AS rating_average,
                        (SELECT COUNT(*) FROM article_ratings
                         WHERE article_id = articles.id) AS rating_count,
                        (SELECT rating FROM article_ratings
                         WHERE article_id = articles.id AND user_id = :rating_user_id) AS my_rating
                 FROM articles
                 JOIN users ON users.id = articles.author_id
                 WHERE articles.id = :id
                   AND (articles.status = 'published' OR articles.author_id = :viewer_id)"
            );
            $statement->execute([
                ':id' => $articleId,
                ':viewer_id' => $_SESSION['user']['id'] ?? null,
                ':rating_user_id' => $_SESSION['user']['id'] ?? null,
            ]);
            $article = $statement->fetch();

            if (!$article) {
                respond(404, ['message' => 'Artigo não encontrado.']);
            }

            // Conta uma visualização por sessão, sem contar o próprio autor nem rascunhos.
            $viewedArticles = $_SESSION['viewed_articles'] ?? [];
            $isAuthor = (int) $article['author_id'] === ($_SESSION['user']['id'] ?? null);
            if ($article['status'] === 'published' && !$isAuthor && !in_array($articleId, $viewedArticles, true)) {
                $database->prepare('UPDATE articles SET views = views + 1 WHERE id = :id')
                    ->execute([':id' => $articleId]);
                $_SESSION['viewed_articles'] = [...$viewedArticles, $articleId];
            }

            respond(200, ['article' => $article]);
        }

        respond(404, ['message' => 'Ação não encontrada.']);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: GET, POST');
        respond(405, ['message' => 'Método não permitido.']);
    }

    if (($_POST['action'] ?? '') === 'upload_photo') {
        if (!isset($_SESSION['user']['id'])) {
            respond(401, ['message' => 'Entre na sua conta para enviar uma foto.']);
        }

        $upload = $_FILES['photo'] ?? null;
        if (!is_array($upload) || $upload['error'] !== UPLOAD_ERR_OK) {
            if (is_array($upload) && in_array($upload['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
                respond(413, ['message' => 'A imagem deve ter no máximo 2 MB.']);
            }

            respond(400, ['message' => 'Selecione uma imagem válida para enviar.']);
        }

        if ($upload['size'] > 2 * 1024 * 1024) {
            respond(413, ['message' => 'A imagem deve ter no máximo 2 MB.']);
        }

        $imageInfo = getimagesize($upload['tmp_name']);
        $mimeType = $imageInfo['mime'] ?? '';
        $allowedTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];

        if (!is_array($imageInfo) || !isset($allowedTypes[$mimeType])) {
            respond(422, ['message' => 'Use uma imagem JPG, PNG ou WebP.']);
        }

        $database = getDatabase();
        $uploadDirectory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'profiles';
        if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
            throw new RuntimeException('Não foi possível preparar o diretório de fotos.');
        }

        $fileName = bin2hex(random_bytes(16)) . '.' . $allowedTypes[$mimeType];
        $destination = $uploadDirectory . DIRECTORY_SEPARATOR . $fileName;
        if (!move_uploaded_file($upload['tmp_name'], $destination)) {
            throw new RuntimeException('Não foi possível salvar a foto enviada.');
        }

        $statement = $database->prepare(
            'UPDATE users SET profile_photo = :profile_photo WHERE id = :id'
        );
        $relativePath = '../uploads/profiles/' . $fileName;
        $oldPhotoStatement = $database->prepare(
            'SELECT profile_photo FROM users WHERE id = :id'
        );
        $oldPhotoStatement->execute([':id' => $_SESSION['user']['id']]);
        $oldPhoto = $oldPhotoStatement->fetchColumn();

        try {
            $statement->execute([
                ':profile_photo' => $relativePath,
                ':id' => $_SESSION['user']['id'],
            ]);
        } catch (Throwable $exception) {
            unlink($destination);
            throw $exception;
        }

        if ($statement->rowCount() === 0) {
            $exists = $database->prepare('SELECT 1 FROM users WHERE id = :id');
            $exists->execute([':id' => $_SESSION['user']['id']]);
            if (!$exists->fetchColumn()) {
                unlink($destination);
                unset($_SESSION['user']);
                respond(401, ['message' => 'Sua sessão expirou. Entre novamente.']);
            }
        }

        if (is_string($oldPhoto) && str_starts_with($oldPhoto, '../uploads/profiles/')) {
            $oldFileName = basename($oldPhoto);
            $oldPhotoPath = $uploadDirectory . DIRECTORY_SEPARATOR . $oldFileName;
            if ($oldFileName !== $fileName && is_file($oldPhotoPath)) {
                unlink($oldPhotoPath);
            }
        }

        $_SESSION['user']['profile_photo'] = $relativePath;
        respond(200, [
            'message' => 'Foto de perfil atualizada.',
            'profile_photo' => $relativePath,
        ]);
    }

    if (($_POST['action'] ?? '') === 'create_article') {
        if (!isset($_SESSION['user']['id'])) {
            respond(401, ['message' => 'Entre na sua conta para escrever um artigo.']);
        }

        $article = validateArticleInput();
        $database = getDatabase();
        $coverPath = saveArticleCover();

        $statement = $database->prepare(
            'INSERT INTO articles (author_id, title, summary, content, cover_image, status)
             VALUES (:author_id, :title, :summary, :content, :cover_image, :status)'
        );
        try {
            $statement->execute([
                ':author_id' => $_SESSION['user']['id'],
                ':title' => $article['title'],
                ':summary' => $article['summary'],
                ':content' => $article['content'],
                ':cover_image' => $coverPath,
                ':status' => $article['status'],
            ]);
        } catch (Throwable $exception) {
            deleteArticleCover($coverPath);
            throw $exception;
        }

        respond(201, [
            'message' => $article['status'] === 'published' ? 'Artigo publicado com sucesso.' : 'Rascunho salvo com sucesso.',
            'article' => [
                'id' => (int) $database->lastInsertId(),
                'title' => $article['title'],
                'status' => $article['status'],
            ],
        ]);
    }

    if (($_POST['action'] ?? '') === 'update_article') {
        if (!isset($_SESSION['user']['id'])) {
            respond(401, ['message' => 'Entre na sua conta para editar um artigo.']);
        }

        $database = getDatabase();
        $existingArticle = findOwnArticle($database, $_POST['article_id'] ?? null);
        $article = validateArticleInput();
        $newCoverPath = saveArticleCover();
        $coverPath = $newCoverPath ?? (($_POST['remove_cover'] ?? '') === '1' ? null : $existingArticle['cover_image']);

        // Ao publicar um rascunho, a data de publicação passa a ser o momento da publicação.
        $publishedNow = $existingArticle['status'] === 'draft' && $article['status'] === 'published';
        $statement = $database->prepare(
            'UPDATE articles
             SET title = :title, summary = :summary, content = :content,
                 cover_image = :cover_image, status = :status'
             . ($publishedNow ? ', created_at = CURRENT_TIMESTAMP' : '') .
            ' WHERE id = :id AND author_id = :author_id'
        );
        try {
            $statement->execute([
                ':title' => $article['title'],
                ':summary' => $article['summary'],
                ':content' => $article['content'],
                ':cover_image' => $coverPath,
                ':status' => $article['status'],
                ':id' => $existingArticle['id'],
                ':author_id' => $_SESSION['user']['id'],
            ]);
        } catch (Throwable $exception) {
            deleteArticleCover($newCoverPath);
            throw $exception;
        }

        if ($coverPath !== $existingArticle['cover_image']) {
            deleteArticleCover($existingArticle['cover_image']);
        }

        respond(200, [
            'message' => $article['status'] === 'published' ? 'Artigo atualizado com sucesso.' : 'Rascunho salvo com sucesso.',
            'article' => [
                'id' => (int) $existingArticle['id'],
                'title' => $article['title'],
                'status' => $article['status'],
            ],
        ]);
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (!is_array($input)) {
        respond(400, ['message' => 'Não foi possível ler os dados enviados.']);
    }

    $action = $input['action'] ?? '';

    if (!in_array($action, ['register', 'login', 'logout', 'update_profile', 'rate_article', 'delete_article'], true)) {
        respond(404, ['message' => 'Ação não encontrada.']);
    }

    if ($action === 'logout') {
        $_SESSION = [];
        $cookie = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $cookie['path'],
            'domain' => $cookie['domain'],
            'secure' => $cookie['secure'],
            'httponly' => $cookie['httponly'],
            'samesite' => $cookie['samesite'] ?? 'Strict',
        ]);
        session_destroy();
        respond(200, ['message' => 'Sessão encerrada.']);
    }

    $database = getDatabase();

    if ($action === 'delete_article') {
        if (!isset($_SESSION['user']['id'])) {
            respond(401, ['message' => 'Entre na sua conta para excluir um artigo.']);
        }

        $article = findOwnArticle($database, $input['article_id'] ?? null);
        $statement = $database->prepare('DELETE FROM articles WHERE id = :id AND author_id = :author_id');
        $statement->execute([':id' => $article['id'], ':author_id' => $_SESSION['user']['id']]);
        deleteArticleCover($article['cover_image']);

        respond(200, ['message' => 'Artigo excluído com sucesso.']);
    }

    if ($action === 'rate_article') {
        if (!isset($_SESSION['user']['id'])) {
            respond(401, ['message' => 'Entre na sua conta para avaliar um artigo.']);
        }

        $articleId = filter_var($input['article_id'] ?? null, FILTER_VALIDATE_INT);
        $rating = filter_var($input['rating'] ?? null, FILTER_VALIDATE_INT);
        if (!$articleId || $articleId < 1 || !$rating || $rating < 1 || $rating > 5) {
            respond(422, ['message' => 'Escolha uma avaliação entre 1 e 5 estrelas.']);
        }

        $articleExists = $database->prepare(
            "SELECT id FROM articles WHERE id = :id AND status = 'published'"
        );
        $articleExists->execute([':id' => $articleId]);
        if (!$articleExists->fetchColumn()) {
            respond(404, ['message' => 'Artigo publicado não encontrado.']);
        }

        $saveRating = $database->prepare(
            'INSERT INTO article_ratings (article_id, user_id, rating)
             VALUES (:article_id, :user_id, :rating)
             ON CONFLICT (article_id, user_id)
             DO UPDATE SET rating = excluded.rating, updated_at = CURRENT_TIMESTAMP'
        );
        $saveRating->execute([
            ':article_id' => $articleId,
            ':user_id' => $_SESSION['user']['id'],
            ':rating' => $rating,
        ]);

        $ratingSummary = $database->prepare(
            'SELECT AVG(rating) AS rating_average, COUNT(*) AS rating_count
             FROM article_ratings WHERE article_id = :article_id'
        );
        $ratingSummary->execute([':article_id' => $articleId]);
        $summary = $ratingSummary->fetch();
        respond(200, [
            'message' => 'Sua avaliação foi salva.',
            'rating' => $rating,
            'rating_average' => (float) $summary['rating_average'],
            'rating_count' => (int) $summary['rating_count'],
        ]);
    }

    if ($action === 'update_profile') {
        if (!isset($_SESSION['user']['id'])) {
            respond(401, ['message' => 'Entre na sua conta para editar o perfil.']);
        }

        $name = $input['name'] ?? null;
        $email = $input['email'] ?? null;
        $bio = $input['bio'] ?? null;

        if (!is_string($name) || trim($name) === '' || strlen(trim($name)) > 100) {
            respond(422, ['message' => 'Informe um nome com até 100 caracteres.']);
        }

        if (!is_string($email) || !filter_var(trim($email), FILTER_VALIDATE_EMAIL) || strlen(trim($email)) > 254) {
            respond(422, ['message' => 'Informe um endereço de e-mail válido.']);
        }

        if (!is_string($bio) || preg_match_all('/./us', $bio) > 500) {
            respond(422, ['message' => 'A biografia deve ter no máximo 500 caracteres.']);
        }

        $name = trim($name);
        $email = trim($email);
        $statement = $database->prepare(
            'UPDATE users SET name = :name, email = :email, bio = :bio WHERE id = :id'
        );

        try {
            $statement->execute([
                ':name' => $name,
                ':email' => $email,
                ':bio' => trim($bio),
                ':id' => $_SESSION['user']['id'],
            ]);
        } catch (PDOException $exception) {
            if (($exception->errorInfo[1] ?? null) === 19) {
                respond(409, ['message' => 'Este e-mail já está cadastrado.']);
            }

            throw $exception;
        }

        $_SESSION['user']['name'] = $name;
        $_SESSION['user']['email'] = $email;
        $_SESSION['user']['bio'] = trim($bio);

        respond(200, [
            'message' => 'Perfil atualizado com sucesso.',
            'user' => $_SESSION['user'],
        ]);
    }

    if ($action === 'register') {
        $name = $input['name'] ?? null;
        $email = $input['email'] ?? null;
        $password = $input['password'] ?? null;

        if (!is_string($name) || trim($name) === '' || strlen(trim($name)) > 100) {
            respond(422, ['message' => 'Informe um nome com até 100 caracteres.']);
        }

        if (!is_string($email) || !filter_var(trim($email), FILTER_VALIDATE_EMAIL) || strlen(trim($email)) > 254) {
            respond(422, ['message' => 'Informe um endereço de e-mail válido.']);
        }

        if (!is_string($password) || strlen($password) < 8 || strlen($password) > 72) {
            respond(422, ['message' => 'A senha deve ter entre 8 e 72 caracteres.']);
        }

        $name = trim($name);
        $email = trim($email);
        $statement = $database->prepare(
            'INSERT INTO users (name, email, password_hash) VALUES (:name, :email, :password_hash)'
        );

        try {
            $statement->execute([
                ':name' => $name,
                ':email' => $email,
                ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
        } catch (PDOException $exception) {
            if (($exception->errorInfo[1] ?? null) === 19) {
                respond(409, ['message' => 'Este e-mail já está cadastrado.']);
            }

            throw $exception;
        }

        respond(201, ['message' => 'Cadastro realizado com sucesso. Faça login para continuar.']);
    }

    if ($action === 'login') {
        $email = $input['email'] ?? null;
        $password = $input['password'] ?? null;

        if (!is_string($email) || !filter_var(trim($email), FILTER_VALIDATE_EMAIL) || !is_string($password) || $password === '') {
            respond(401, ['message' => 'E-mail ou senha inválidos.']);
        }

        $email = trim($email);
        $statement = $database->prepare(
            'SELECT id, name, email, password_hash, created_at, profile_photo, bio FROM users WHERE email = :email'
        );
        $statement->execute([':email' => $email]);
        $user = $statement->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            respond(401, ['message' => 'E-mail ou senha inválidos.']);
        }

        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'created_at' => $user['created_at'],
            'profile_photo' => $user['profile_photo'],
            'bio' => $user['bio'],
        ];

        respond(200, ['message' => 'Login realizado com sucesso.']);
    }

    respond(404, ['message' => 'Ação não encontrada.']);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    respond(500, ['message' => 'Não foi possível concluir a operação. Verifique a configuração do servidor.']);
}
