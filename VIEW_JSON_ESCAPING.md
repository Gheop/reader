# Problème d'échappement JSON dans view.php

**Date**: 2025-11-04
**Symptôme**: Les vidéos YouTube et les images ne s'affichent plus avec json_encode()

## 🐛 Cause racine

Les descriptions dans la base de données sont **déjà échappées pour JSON** par `clean_text.php` avant insertion.

### Processus dans clean_text.php (lignes 180-185)

```php
$a = array('\\', '"', '<br>', '<br /><br />','<br><br>','<p>','<\p>','<b>','</b>');
$b = array('\\\\', '\"', '<br />', '<br />','<br />','','<br />','','','');
$v = str_replace($a, $b, $v);
```

**Résultat** : Dans la base de données, les descriptions contiennent :
- `\"` à la place de `"`
- `\\` à la place de `\`

### Pourquoi ? Parce que l'original construit le JSON avec MySQL CONCAT

L'ancien `view.legacy.php` ligne 14 utilise :
```sql
SELECT CONCAT('{',GROUP_CONCAT(CONCAT('"',I.id,'":{'),CONCAT('"t":"',I.title,'"'),...
```

MySQL CONCAT ne fait **AUCUN échappement automatique**. Donc `clean_text.php` doit pré-échapper les données.

## ❌ Problème avec json_encode()

Quand on utilise `json_encode()` sur des données déjà échappées :

**Exemple** :
1. Description dans DB : `<img src=\"https://example.com/image.jpg\">`
2. json_encode() produit : `"<img src=\\\"https://example.com/image.jpg\\\">"`
3. Après JSON.parse() : `<img src=\"https://example.com/image.jpg\">`
4. Le HTML est **cassé** car les guillemets sont échappés !

## ✅ Solution appliquée

**Construire le JSON manuellement** comme l'original :

```php
// Description (already escaped in DB by clean_text.php)
$json .= ',"d":"' . ($row['description'] ?? '') . '"';

// Title (needs escaping)
$json .= '"t":"' . str_replace(['"', '\\'], ['\"', '\\\\'], $row['title'] ?? '') . '"';
```

**Règle** :
- `description` : Ne PAS échapper (déjà fait par clean_text.php)
- Autres champs : Échapper manuellement avec `str_replace()`

## 🎯 Champs spéciaux dans les descriptions

Les descriptions contiennent du HTML traité par `clean_text.php` :

### Vidéos YouTube (ligne 167)
```php
$v = preg_replace('#<yt>([^<]*)</yt>#Ssi',
    "<iframe loading=\"lazy\" width=\"560\" height=\"315\" src=\"https://www.youtube.com/embed/$1\" frameborder=\"0\" allowfullscreen>$1</iframe>",
    $v);
```

Les balises `<yt>video_id</yt>` deviennent des iframes YouTube.

### Images (ligne 174)
```php
$v = @preg_replace_callback('#<\s*img[^>]+?src=["\' ]*([^> "\']*)["\' ]*.*?>#Ssi', "imgbase64", $v);
```

Les images sont :
1. Téléchargées
2. Redimensionnées (max 1680x1024)
3. Converties en WebP
4. Stockées localement
5. Remplacées par `<img loading="lazy" src="https://reader.gheop.com/tmp/...webp">`

## 📊 Comparaison des approches

| Approche | Avantages | Inconvénients |
|----------|-----------|---------------|
| **MySQL CONCAT** (original) | Simple, pas de préparation | Vulnérable SQL injection, pas de type safety |
| **json_encode()** (tenté) | Type-safe, propre | Double échappement sur données pré-échappées |
| **JSON manuel** (solution) | Compatible avec données existantes, type-safe sur IDs | Plus verbeux |

## 🔮 Solution future (idéale)

Pour éviter ce problème à long terme :

1. **Modifier up.php et clean_text.php** :
   - Ne PLUS échapper pour JSON avant insertion
   - Stocker le HTML brut dans la DB

2. **Modifier view.php** :
   - Utiliser `json_encode()` sur toutes les données
   - Échappement automatique et propre

3. **Migration des données existantes** :
   - Script SQL pour déséchapper toutes les descriptions
   - Remplacer `\"` par `"` et `\\\\` par `\\`

**Note** : Cette migration nécessiterait de traiter ~100k+ articles existants.

## 🎯 Pourquoi ça marchait avec MySQL CONCAT ?

Dans l'original, tout se passe côté SQL :

```sql
SELECT CONCAT('{"d":"', I.description, '"}') ...
```

1. MySQL lit `I.description` qui contient : `<img src=\"...\">`
2. CONCAT concatène tel quel (pas d'échappement)
3. Le JSON final contient : `{"d":"<img src=\"...\">"}`
4. JavaScript parse correctement
5. innerHTML reçoit : `<img src="...">`
6. Le HTML fonctionne !

Avec json_encode(), PHP échappe une deuxième fois les `\"` → cassé.

## 📝 Résumé

**Les descriptions sont pré-échappées pour JSON dans la DB.**
**Solution : Construire le JSON manuellement sans ré-échapper les descriptions.**
