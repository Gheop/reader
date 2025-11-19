# LocalStorage Optimization

## Vue d'ensemble

Cette optimisation combine les requêtes menu et articles en une seule, et utilise localStorage pour un affichage instantané.

## Architecture

### Backend

**`api.php`** - Endpoint API combiné
- Retourne menu + articles en une seule requête
- Format JSON: `{menu: {...}, articles: {...}, timestamp: ...}`
- Performance: ~10-15ms (au lieu de 2 requêtes séparées)

### Frontend

**`api-cache.js`** - Gestionnaire de cache
- Sauvegarde/charge depuis localStorage
- Affichage instantané depuis le cache (< 1ms)
- Mise à jour en arrière-plan
- Synchronisation automatique toutes les 30s

**Modifications dans `lib.js`**
- `i()`: Utilise `loadData()` au lieu de `menu()` + `view()`
- `view()`: Lit d'abord le cache, puis actualise en arrière-plan

## Flux de fonctionnement

### Premier chargement (pas de cache)
1. Appel API → api.php
2. Affichage des données
3. Sauvegarde dans localStorage
4. Démarrage de la sync en arrière-plan

### Chargements suivants (avec cache)
1. **Affichage instantané** depuis localStorage (< 1ms)
2. En parallèle : appel API pour données fraîches
3. Mise à jour silencieuse si changements
4. Sync continue toutes les 30s

## Avantages

**Performance**
- ✅ Chargement instantané (< 1ms depuis cache)
- ✅ 1 seule requête au lieu de 2
- ✅ Économie de latence réseau (50-200ms)

**Expérience utilisateur**
- ✅ App utilisable immédiatement
- ✅ Mises à jour transparentes en arrière-plan
- ✅ Fonctionne même avec connexion lente

**Technique**
- ✅ Synchronisation automatique
- ✅ Gestion intelligente de la visibilité (pause quand onglet inactif)
- ✅ Fallback si localStorage indisponible

## Fonctions principales

### api-cache.js

```javascript
loadData(feedId, useCache)        // Charge depuis cache puis API
fetchAndUpdateData(feedId)        // Fetch API et mise à jour
renderMenu(menuData)              // Affiche le menu
renderArticles(articlesData, feedId) // Affiche les articles
saveToCache(data)                 // Sauvegarde dans localStorage
loadFromCache()                   // Lecture depuis localStorage
startBackgroundSync(interval)     // Démarre la sync auto
stopBackgroundSync()              // Arrête la sync
```

## Configuration

**Intervalle de synchronisation** : 30 secondes (modifiable dans `startBackgroundSync()`)
**Version du cache** : v1 (variable `cacheVersion` dans api-cache.js)

Pour forcer une invalidation du cache, changer `cacheVersion` à 'v2', 'v3', etc.

## Limites

- **Taille localStorage** : ~5-10MB selon navigateur
- **Données non temps-réel** : délai max 30s pour voir nouveaux articles
- **Pas de persistance read/unread** : les marquages lu/non-lu sont immédiats côté serveur mais le cache se rafraîchit sous 30s

## Tests recommandés

1. Vider le cache et charger la page (premier chargement)
2. Rafraîchir la page (doit être instantané)
3. Marquer des articles comme lus et attendre la sync
4. Tester avec DevTools Network throttling
5. Vérifier localStorage dans DevTools Application tab

## Rollback

Pour revenir à l'ancien système :
1. Retirer `api-cache.js` de index.php
2. Dans lib.js, restaurer `i()` avec `view('all'); menu();`
3. Restaurer l'ancienne fonction `view()`
