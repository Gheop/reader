<?php
/**
 * OAuth2 Configuration
 * Stores client IDs and secrets for OAuth providers
 *
 * IMPORTANT: Copy this file to oauth_config.php and fill in your own values
 * Do NOT commit oauth_config.php to version control!
 */

return [
    'google' => [
        'client_id' => 'your-client-id.apps.googleusercontent.com',
        'client_secret' => 'your-client-secret',
        'redirect_uri' => 'https://your-domain.com/oauth_callback.php?provider=google',
        'auth_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
        'token_url' => 'https://oauth2.googleapis.com/token',
        'userinfo_url' => 'https://www.googleapis.com/oauth2/v2/userinfo',
        'scopes' => ['openid', 'email', 'profile']
    ],
    'github' => [
        'client_id' => 'your-github-client-id',
        'client_secret' => 'your-github-client-secret',
        'redirect_uri' => 'https://your-domain.com/oauth_callback.php?provider=github',
        'auth_url' => 'https://github.com/login/oauth/authorize',
        'token_url' => 'https://github.com/login/oauth/access_token',
        'userinfo_url' => 'https://api.github.com/user',
        'scopes' => ['user:email']
    ],
    'twitter' => [
        'client_id' => 'your-twitter-client-id',
        'client_secret' => 'your-twitter-client-secret',
        'redirect_uri' => 'https://your-domain.com/oauth_callback.php?provider=twitter',
        'auth_url' => 'https://twitter.com/i/oauth2/authorize',
        'token_url' => 'https://api.twitter.com/2/oauth2/token',
        'userinfo_url' => 'https://api.twitter.com/2/users/me?user.fields=id,name,username',
        'scopes' => ['tweet.read', 'users.read', 'offline.access'],
        'use_pkce' => true  // Twitter requires PKCE
    ]
];
