# Scraper LinkedIn pour Gheop Reader

## Utilisation basique

Pour ajouter un profil LinkedIn à votre lecteur RSS, utilisez simplement l'URL du profil dans `add_flux.php` :

```
https://www.linkedin.com/in/ruben-hassid/
```

Le système détectera automatiquement qu'il s'agit d'un profil LinkedIn et créera un flux RSS.

## Limitations

⚠️ **LinkedIn bloque le scraping non authentifié**

LinkedIn protège ses profils contre le scraping automatisé. Par défaut, le scraper générera un flux RSS informatif mais ne pourra pas récupérer les posts réels.

## Solutions

### Option 1 : Utiliser l'API officielle LinkedIn (Recommandé)

1. Créer une application sur [LinkedIn Developers](https://developer.linkedin.com/)
2. Obtenir les credentials OAuth 2.0
3. Implémenter l'authentification OAuth dans le scraper

### Option 2 : Scraper avec authentification (cookies)

1. Copier le fichier exemple :
   ```bash
   cp scraping/linkedin_auth.example.php scraping/linkedin_auth.php
   ```

2. Se connecter à linkedin.com dans votre navigateur

3. Récupérer vos cookies :
   - Ouvrir DevTools (F12)
   - Onglet Application → Cookies → linkedin.com
   - Copier les valeurs de :
     - `li_at` (cookie principal d'authentification)
     - `JSESSIONID` (ID de session)

4. Éditer `scraping/linkedin_auth.php` et remplir :
   ```php
   define('LINKEDIN_LI_AT', 'VOTRE_VALEUR_ICI');
   define('LINKEDIN_JSESSIONID', 'VOTRE_VALEUR_ICI');
   ```

5. Modifier `add_flux.php` ligne 170 :
   ```php
   // Avant :
   return 'https://reader.gheop.com/scraping/linkedin.com.php?f=' . urlencode($m[2]);

   // Après :
   return 'https://reader.gheop.com/scraping/linkedin_auth.php?f=' . urlencode($m[2]);
   ```

⚠️ **Note** : Les cookies expirent régulièrement (généralement après quelques mois). Vous devrez les mettre à jour périodiquement.

### Option 3 : Services tiers

Utiliser un service d'agrégation RSS qui gère l'authentification LinkedIn :

- **RSSHub** : https://docs.rsshub.app/en/social-media.html#linkedin
- **RSS Bridge** : https://github.com/RSS-Bridge/rss-bridge

Exemple avec RSSHub (si vous hébergez votre propre instance) :
```
https://rsshub.app/linkedin/in/ruben-hassid
```

## Mode Debug

Pour diagnostiquer les problèmes, ajouter `&debug=1` à l'URL du scraper :

```bash
curl "https://reader.gheop.com/scraping/linkedin.com.php?f=ruben-hassid&debug=1"
```

Cela affichera :
- Le code HTTP de réponse
- Les premiers 5000 caractères du HTML reçu
- Permet de vérifier si LinkedIn bloque la requête

## Structure du flux RSS généré

```xml
<?xml version="1.0" encoding="utf-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
  <channel>
    <title>LinkedIn - username</title>
    <description>Derniers posts de username sur LinkedIn</description>
    <link>https://www.linkedin.com/in/username/</link>

    <item>
      <title>Titre du post (128 premiers caractères)</title>
      <description>Contenu complet du post</description>
      <pubDate>Date de publication</pubDate>
      <link>Lien vers le post sur LinkedIn</link>
      <guid>Identifiant unique</guid>
    </item>
  </channel>
</rss>
```

## Sécurité

- ✅ Utilise HTTPS et vérifie les certificats SSL
- ✅ Échappe correctement le HTML dans le flux RSS
- ✅ Valide et nettoie les entrées utilisateur
- ⚠️ Les cookies d'authentification doivent être protégés (ne pas commit dans git)

## Dépendances

- `simple_html_dom.php` : Parser HTML
- `clean_text.php` : Nettoyage des chaînes de caractères
- PHP 7.4+ avec curl et simplexml

## Fichiers

- `linkedin.com.php` : Scraper basique (sans authentification)
- `linkedin_auth.example.php` : Template pour scraper authentifié
- `linkedin_auth.php` : Version configurée (à créer, ignoré par git)
- `LINKEDIN_README.md` : Cette documentation

## Troubleshooting

### Problème : "LinkedIn requiert une authentification"
→ C'est le comportement normal. Voir les solutions ci-dessus.

### Problème : "Aucun post trouvé"
→ Vérifier que :
- Le profil existe et est public
- Le username est correct
- L'utilisateur a des activités publiques récentes

### Problème : "Les cookies ont expiré"
→ Récupérer de nouveaux cookies et mettre à jour `linkedin_auth.php`

### Problème : "Structure HTML non reconnue"
→ LinkedIn change régulièrement sa structure HTML. Le scraper peut nécessiter une mise à jour des sélecteurs CSS.

## Améliorations futures

- [ ] Support de l'API officielle LinkedIn
- [ ] Rotation automatique des cookies
- [ ] Cache des posts pour réduire les requêtes
- [ ] Support des hashtags et recherches
- [ ] Extraction des images et vidéos
- [ ] Support multi-comptes
