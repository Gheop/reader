# Configuration de l'API YouTube pour récupérer les descriptions

## Pourquoi une clé API ?

Le système de récupération des descriptions YouTube nécessite une clé API YouTube Data v3. Cette clé est **gratuite** et permet jusqu'à **10,000 requêtes par jour** (largement suffisant pour un lecteur RSS personnel).

## Obtenir une clé API YouTube (gratuit)

### 1. Créer un projet Google Cloud

1. Aller sur https://console.cloud.google.com/
2. Se connecter avec un compte Google
3. Cliquer sur "Sélectionner un projet" → "Nouveau projet"
4. Nommer le projet (ex: "RSS Reader")
5. Cliquer sur "Créer"

### 2. Activer l'API YouTube Data v3

1. Dans le menu, aller à "APIs et services" → "Bibliothèque"
2. Rechercher "YouTube Data API v3"
3. Cliquer dessus et cliquer sur "Activer"

### 3. Créer une clé API

1. Aller à "APIs et services" → "Identifiants"
2. Cliquer sur "+ CRÉER DES IDENTIFIANTS"
3. Sélectionner "Clé API"
4. Copier la clé générée (format: `AIzaSy...`)

### 4. Sécuriser la clé (recommandé)

1. Cliquer sur "Modifier la clé API"
2. Dans "Restrictions relatives aux applications", sélectionner "Adresses IP"
3. Ajouter l'IP de votre serveur (pour n'autoriser que ce serveur)
4. Dans "Restrictions relatives aux API", sélectionner "Restreindre la clé"
5. Sélectionner uniquement "YouTube Data API v3"
6. Sauvegarder

### 5. Configurer la clé dans le reader

Ajouter cette ligne dans `/www/conf.php` :

```php
define('YOUTUBE_API_KEY', 'VOTRE_CLE_API_ICI');
```

## Test

Pour tester si la clé fonctionne :

```bash
php -r "
define('YOUTUBE_API_KEY', 'VOTRE_CLE_API');
include('/www/reader/up.php');
\$desc = get_youtube_description('dQw4w9WgXcQ');
echo \$desc ? 'OK: ' . substr(\$desc, 0, 100) : 'ERREUR';
"
```

## Quota et limites

- **10,000 unités par jour** (gratuit)
- Une requête vidéo = **1 unité**
- Donc 10,000 vidéos par jour peuvent être traitées
- Le quota se réinitialise à minuit (heure du Pacifique)

## Sans clé API

Si aucune clé n'est configurée, le système continue de fonctionner normalement mais sans les descriptions YouTube. Les vidéos s'affichent quand même, simplement sans description en dessous.

## Alternative : Invidious API (gratuit, sans clé)

Une alternative est d'utiliser l'API Invidious (front-end YouTube open-source) :

```php
// Dans get_youtube_description()
$url = 'https://invidious.fdn.fr/api/v1/videos/' . $videoId;
// Parse le JSON et récupère description
```

Cependant, cette solution dépend de services tiers qui peuvent être instables.
