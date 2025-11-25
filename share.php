<?php
/**
 * Share Target API Handler
 * Receives URLs shared from other apps via PWA Share Target
 */

header('Content-Type: text/html; charset=utf-8');

include('/www/conf.php');
include(__DIR__ . '/auth.php');

// Get shared data
$title = $_POST['title'] ?? $_GET['title'] ?? '';
$text = $_POST['text'] ?? $_GET['text'] ?? '';
$url = $_POST['url'] ?? $_GET['url'] ?? '';

// Clean and validate URL
$sharedUrl = trim($url);
if (empty($sharedUrl)) {
    // No URL shared, redirect to homepage
    header('Location: /');
    exit;
}

// Validate URL format
if (!filter_var($sharedUrl, FILTER_VALIDATE_URL)) {
    $error = 'URL invalide';
} else {
    $error = null;
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ajouter au Reader - Gheop Reader</title>
    <link rel="stylesheet" href="fontawesome/css/all.min.css">
    <link rel="stylesheet" href="themes/common.min.css">
    <link rel="stylesheet" href="themes/light.min.css">
    <style>
        body {
            padding: 20px;
            max-width: 600px;
            margin: 0 auto;
        }
        .share-container {
            background: #f5f5f5;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .share-info {
            margin-bottom: 15px;
        }
        .share-info label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
            color: #2c3e50;
        }
        .share-info input, .share-info textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .share-info textarea {
            min-height: 60px;
            resize: vertical;
        }
        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        button {
            flex: 1;
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.2s;
        }
        button.primary {
            background: #3498db;
            color: white;
        }
        button.primary:hover {
            background: #2980b9;
        }
        button.secondary {
            background: #95a5a6;
            color: white;
        }
        button.secondary:hover {
            background: #7f8c8d;
        }
        .error {
            background: #e74c3c;
            color: white;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .success {
            background: #27ae60;
            color: white;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <h1><i class="fa fa-share-alt"></i> Ajouter au Reader</h1>

    <?php if ($error): ?>
        <div class="error">
            <i class="fa fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <div class="share-container">
        <form method="POST" action="add_flux.php">
            <div class="share-info">
                <label for="url">URL à ajouter</label>
                <input type="url" id="url" name="rss" value="<?php echo htmlspecialchars($sharedUrl); ?>" required>
            </div>

            <?php if ($title): ?>
            <div class="share-info">
                <label for="title">Titre (optionnel)</label>
                <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($title); ?>">
            </div>
            <?php endif; ?>

            <?php if ($text): ?>
            <div class="share-info">
                <label for="text">Description (optionnel)</label>
                <textarea id="text" name="description" readonly><?php echo htmlspecialchars($text); ?></textarea>
            </div>
            <?php endif; ?>

            <div class="button-group">
                <button type="submit" class="primary">
                    <i class="fa fa-plus"></i> Ajouter ce flux
                </button>
                <button type="button" class="secondary" onclick="window.location='/'">
                    <i class="fa fa-times"></i> Annuler
                </button>
            </div>
        </form>
    </div>

    <p style="text-align: center; color: #7f8c8d; margin-top: 30px;">
        <i class="fa fa-info-circle"></i> Cette URL sera ajoutée comme nouveau flux RSS
    </p>

    <script>
        // Auto-detect if it's a feed URL or article URL
        const urlInput = document.getElementById('url');
        const url = urlInput.value;

        // Common feed patterns
        const feedPatterns = [
            /\/feed\/?$/i,
            /\/rss\/?$/i,
            /\.xml$/i,
            /\/atom\/?$/i
        ];

        const isFeed = feedPatterns.some(pattern => pattern.test(url));

        if (!isFeed && url) {
            // Try to guess feed URL
            const guessedFeed = url.replace(/\/$/, '') + '/feed';
            console.log('Not a feed URL, suggesting:', guessedFeed);
        }
    </script>
</body>
</html>
