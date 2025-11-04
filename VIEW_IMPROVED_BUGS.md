# Bugs dans view_improved_v2.php - Analyse

**Status**: Version improved ne retourne aucun résultat (ni par défaut ni pour flux spécifique)
**Date**: 2025-11-04

## 🐛 Bugs identifiés

### 1. Prepared statements avec LIMIT (Critique - probable cause)

**Problème** : Anciennes versions de MySQL/MariaDB ne supportent pas les placeholders (?) dans LIMIT
```php
// ❌ NE FONCTIONNE PAS sur MySQL < 5.5 ou anciennes versions MariaDB
LIMIT ?, ?
$stmt->bind_param('iiii', $userId, $userId, $offset, $limit);
```

**Preuve** :
- L'original view.php (ligne 11) construit `$lim` comme chaîne : `'50'` ou `'100, 50'`
- Puis l'injecte directement dans SQL : `limit $lim`
- Cela fonctionne car c'est une chaîne, pas un paramètre préparé

**Solution possible** :
```php
// ✅ Construire LIMIT comme chaîne après validation
$limit = min(100, max(1, (int)$_POST['nb']));
$offset = max(0, (int)$_POST['offset']);
$limitClause = "LIMIT $offset, $limit"; // Sécurisé car validé avec (int)

// Dans le SQL, ne pas utiliser ? pour LIMIT
$sql = "... ORDER BY I.pubdate DESC $limitClause";
```

### 2. Ordre des paramètres LIMIT

**Différence syntaxe** :
- Original (ligne 11) : `$_POST['nb'].', 50'` → `"100, 50"` signifie `LIMIT 100, 50` = OFFSET 100, LIMIT 50
- Improved (ligne 68) : `LIMIT ?, ?` avec `$offset, $limit` → LIMIT 0, 50 = OFFSET 0, LIMIT 50

**Interprétation MySQL** :
- `LIMIT offset, row_count` (2 paramètres)
- `LIMIT row_count` (1 paramètre)
- `LIMIT row_count OFFSET offset` (syntaxe alternative)

**Confusion dans l'original** :
```php
// Ligne 11 view.php
$lim = (isset($_POST['nb']) && is_numeric($_POST['nb']))
    ? $_POST['nb'].', 50'  // Si nb=100 → "100, 50" = OFFSET 100, LIMIT 50
    : '50';                 // Sinon → "50" = LIMIT 50
```

⚠️ **BUG dans l'original** : Si `$_POST['nb']=100`, ça fait `LIMIT 100, 50` ce qui signifie:
- Skip les 100 premiers résultats
- Retourne les 50 suivants
- Ce n'est probablement PAS l'intention !

### 3. Requête NOT IN vs LEFT JOIN

**Original** (ligne 14) :
```sql
and I.id not in (
    select id_item from reader_user_item as UI
    where UI.id_user='$_SESSION[user_id]'
    and UI.date > (now() - interval 15 day)
)
```

**Improved** (lignes 60-65) :
```sql
LEFT JOIN reader_user_item UI ON I.id = UI.id_item
    AND UI.id_user = ?
    AND UI.date > DATE_SUB(NOW(), INTERVAL 15 DAY)
WHERE ... AND UI.id_item IS NULL
```

**Impact** : LEFT JOIN est généralement plus performant, MAIS :
- La condition `UI.date > DATE_SUB(NOW(), INTERVAL 15 DAY)` dans le JOIN peut changer la sémantique
- Si UI.date existe mais est > 15 jours, le LEFT JOIN peut retourner la ligne alors que NOT IN ne le ferait pas

### 4. Binding de paramètres avec spread operator

**Code ligne 79-82** :
```php
if ($feedFilter) {
    $stmt->bind_param($bindTypes . 'ii', ...$bindParams, $offset, $limit);
} else {
    $stmt->bind_param($bindTypes . 'ii', ...$bindParams, $offset, $limit);
}
```

**Problèmes** :
- Les deux branches sont identiques (code dupliqué inutile)
- Le spread operator `...$bindParams` peut causer des problèmes avec bind_param
- bind_param attend des références dans certaines versions de PHP

### 5. Headers appelés trop tôt

**Improved** (ligne 12) :
```php
header('Content-Type: application/json');
```
Appelé dans le bloc de validation d'authentification

**Improved** (ligne 106) :
```php
header('Content-Type: application/json; charset=utf-8');
```
Appelé en cas de succès

**Problème** : Double header si l'authentification échoue

### 6. Variable $mysqli vs $_SESSION['mysqli']

**Original** (ligne 14) :
```php
$r = $mysqli->query("...");
```

**Improved** (ligne 71) :
```php
$stmt = $_SESSION['mysqli']->prepare($sql);
```

**Incohérence** : L'original utilise `$mysqli` mais ligne 4 c'est commenté : `//$mysqli = $_SESSION['mysqli'];`
- Cela signifie que `$mysqli` n'est probablement PAS défini dans l'original
- **MAIS** l'original fonctionne, donc `$mysqli` doit être défini dans `/www/conf.php`

**Hypothèse** : Dans conf.php, il y a probablement:
```php
$mysqli = $_SESSION['mysqli'];
// OU
$mysqli = new mysqli(...);
$_SESSION['mysqli'] = $mysqli;
```

## 🎯 Cause racine probable

**#1 - LIMIT avec prepared statements** est presque certainement le problème principal.

Les anciennes versions de MySQL/MariaDB (< 5.5) ne permettent pas les placeholders dans LIMIT.

## ✅ Solution recommandée

Créer une version qui :
1. Valide `$offset` et `$limit` avec `(int)` pour sécurité
2. Construit `$limitClause` comme chaîne (sécurisé car casté en int)
3. Utilise `NOT IN` comme l'original (pour compatibilité exacte)
4. Utilise `$mysqli` au lieu de `$_SESSION['mysqli']` (pour cohérence)
5. Fixe le bug LIMIT de l'original (ne pas confondre offset et limit)

## 📝 Version corrigée à tester

```php
<?php
header("Content-Type: application/json; charset=UTF-8");
include('/www/conf.php');

if(!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) exit;

$userId = (int)$_SESSION['user_id'];

// Validation sécurisée des paramètres
$limit = 50; // Default
if (isset($_POST['nb']) && is_numeric($_POST['nb'])) {
    $limit = min(100, max(1, (int)$_POST['nb'])); // Max 100, min 1
}

// Construction sécurisée de LIMIT (pas de prepared statement pour LIMIT)
$limitClause = "LIMIT " . $limit;

// Feed filter avec prepared statement
$feedFilter = '';
$bindTypes = 'ii';
$bindParams = [$userId, $userId];

if (isset($_POST['id']) && is_numeric($_POST['id'])) {
    $feedId = (int)$_POST['id'];
    $feedFilter = 'AND F.id = ?';
    $bindTypes = 'iii';
    $bindParams[] = $feedId;
}

// Requête avec prepared statement (sauf LIMIT)
$stmt = $mysqli->prepare("
    SELECT
        I.id, I.title, I.pubdate, I.author, I.description, I.link,
        I.id_flux, F.title as feed_title, F.description as feed_description, F.link as feed_link
    FROM reader_item I, reader_flux F, reader_user_flux U
    WHERE U.id_user = ?
        AND U.id_flux = I.id_flux
        AND I.id_flux = F.id
        $feedFilter
        AND I.id NOT IN (
            SELECT id_item FROM reader_user_item AS UI
            WHERE UI.id_user = ?
            AND UI.date > (NOW() - INTERVAL 15 DAY)
        )
        AND I.pubdate > (NOW() - INTERVAL 15 DAY)
    ORDER BY pubdate DESC
    $limitClause
");

if (!$stmt) {
    error_log('View prepare failed: ' . $mysqli->error);
    echo '{}';
    exit;
}

// Bind selon si on a un filtre ou non
if ($feedFilter) {
    $stmt->bind_param($bindTypes, $bindParams[0], $bindParams[1], $bindParams[2]);
} else {
    $stmt->bind_param($bindTypes, $bindParams[0], $bindParams[1]);
}

$stmt->execute();
$result = $stmt->get_result();

if (!$result) {
    error_log('View execute failed: ' . $stmt->error);
    echo '{}';
    exit;
}

// Construction du JSON
$articles = [];
while ($row = $result->fetch_assoc()) {
    $articles[(string)$row['id']] = [
        't' => $row['title'] ?? '',
        'p' => $row['pubdate'] ?? '',
        'd' => $row['description'] ?? '',
        'l' => $row['link'] ?? '',
        'a' => $row['author'] ?? '',
        'f' => $row['id_flux'] ?? '',
        'n' => $row['feed_title'] ?? '',
        'e' => $row['feed_description'] ?? '',
        'o' => $row['feed_link'] ?? ''
    ];
}

$stmt->close();

echo json_encode($articles, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
```

## 🔍 Tests à faire

1. Tester version corrigée sans paramètres (défaut)
2. Tester avec `$_POST['nb'] = 10`
3. Tester avec `$_POST['id'] = 123` (flux spécifique)
4. Vérifier les logs d'erreurs si ça ne marche pas
5. Comparer le JSON retourné avec l'original

## ⚠️ Note

Si même la version corrigée ne fonctionne pas, le problème pourrait être:
- La version de MySQL/MariaDB utilisée
- Des différences dans la configuration de conf.php
- Des problèmes de permissions ou de connexion DB
- La variable `$mysqli` n'est pas définie correctement
