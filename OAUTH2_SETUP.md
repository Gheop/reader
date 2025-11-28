# Configuration OAuth2

Ce document explique comment configurer l'authentification OAuth2 pour Gheop Reader.

## Vue d'ensemble

Gheop Reader supporte l'authentification via:
- **Google**
- **GitHub**
- **Twitter/X**

L'authentification traditionnelle (inscription/connexion Gheop) reste totalement fonctionnelle.

## Configuration des providers

### Google OAuth2

1. Aller sur [Google Cloud Console](https://console.cloud.google.com/)
2. Créer un nouveau projet ou en sélectionner un existant
3. Activer l'API "Google+ API"
4. Aller dans "Identifiants" > "Créer des identifiants" > "ID client OAuth 2.0"
5. Type d'application: **Application Web**
6. URI de redirection autorisées:
   ```
   https://reader.gheop.com/oauth_callback.php?provider=google
   ```
7. Copier le **Client ID** et le **Client Secret**
8. Définir les variables d'environnement:
   ```bash
   export GOOGLE_CLIENT_ID="votre-client-id.apps.googleusercontent.com"
   export GOOGLE_CLIENT_SECRET="votre-client-secret"
   ```

### GitHub OAuth2

1. Aller sur [GitHub Developer Settings](https://github.com/settings/developers)
2. Cliquer sur "New OAuth App"
3. Remplir:
   - **Application name**: Gheop Reader
   - **Homepage URL**: https://reader.gheop.com
   - **Authorization callback URL**: `https://reader.gheop.com/oauth_callback.php?provider=github`
4. Copier le **Client ID** et générer un **Client Secret**
5. Définir les variables d'environnement:
   ```bash
   export GITHUB_CLIENT_ID="votre-client-id"
   export GITHUB_CLIENT_SECRET="votre-client-secret"
   ```

### Twitter/X OAuth2

1. Aller sur [Twitter Developer Portal](https://developer.twitter.com/en/portal/dashboard)
2. Créer un nouveau projet ou en sélectionner un existant
3. Dans votre projet, créer une nouvelle application
4. Aller dans l'onglet "Keys and tokens"
5. Sous "OAuth 2.0 Client ID and Client Secret", cliquer sur "Generate"
6. Copier le **Client ID** et le **Client Secret**
7. Aller dans l'onglet "Settings" de votre application
8. Sous "User authentication settings", cliquer sur "Set up"
9. Configurer:
   - **App permissions**: Read
   - **Type of App**: Web App
   - **Callback URL**: `https://reader.gheop.com/oauth_callback.php?provider=twitter`
   - **Website URL**: https://reader.gheop.com
10. Définir les variables d'environnement:
   ```bash
   export TWITTER_CLIENT_ID="votre-client-id"
   export TWITTER_CLIENT_SECRET="votre-client-secret"
   ```

**Note**: Twitter OAuth2 utilise PKCE (Proof Key for Code Exchange) pour plus de sécurité, géré automatiquement par Gheop Reader.

## Méthode alternative: Configuration directe

Si vous ne pouvez pas utiliser les variables d'environnement, éditez directement `/www/reader/oauth_config.php`:

```php
return [
    'google' => [
        'client_id' => 'votre-client-id.apps.googleusercontent.com',
        'client_secret' => 'votre-client-secret',
        // ...
    ],
    // ...
];
```

**⚠️ Attention**: Ne committez jamais les secrets dans Git!

## Test

1. Se déconnecter de Gheop Reader
2. Actualiser la page d'accueil
3. Cliquer sur un des boutons OAuth (Google, GitHub, X/Twitter)
4. Autoriser l'application
5. Vous devriez être automatiquement connecté

## Fonctionnement

1. **Première connexion**: Un nouveau compte utilisateur est créé automatiquement
2. **Connexions suivantes**: Le système reconnaît votre compte OAuth et vous connecte
3. **Partage entre domaines**: La session est partagée sur tous les sous-domaines `.gheop.com`

## Sécurité

- Les mots de passe OAuth ne sont jamais stockés
- Seuls l'ID utilisateur du provider et l'email sont conservés
- Protection CSRF avec tokens d'état
- Communication chiffrée (HTTPS uniquement)
