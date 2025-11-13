# Comparatif : RSSHub vs RSS-Bridge vs Scraper maison

## Vue d'ensemble rapide

| Critère | RSSHub | RSS-Bridge | Ton scraper actuel |
|---------|--------|------------|-------------------|
| **Nombre de sites** | 1000+ routes | 447 bridges | ~30 scrapers |
| **LinkedIn** | ❌ Non supporté | ❌ Non supporté | ✅ Fonctionne (avec cookies) |
| **Twitter/X** | ✅ Oui (nécessite tokens) | ⚠️ Payant (API Basic $100/mois) | ✅ Fonctionne |
| **Instagram** | ✅ Via Picnob | ⚠️ Limité (rate limiting) | ✅ Via Picuki |
| **Facebook** | ❌ Non supporté | ⚠️ Problèmes publics | ❌ Non implémenté |
| **YouTube** | ✅ Excellent | ✅ Excellent | ✅ RSS natif |
| **Reddit** | ❌ Non trouvé | ✅ Oui | ✅ RSS natif |
| **TikTok** | ❌ Non trouvé | ✅ Oui | ❌ Non implémenté |
| **Installation locale** | ✅ Oui (Node.js + Docker) | ✅ Oui (PHP) | ✅ Déjà fait |
| **Complexité** | Moyenne-Haute | Basse | Très basse |
| **Maintenance** | Auto (communauté) | Auto (communauté) | Manuel |

## Détails par solution

### 1. RSSHub

**Technologie :** Node.js (JavaScript)

**Points forts :**
- ✅ **1000+ routes** : Énorme couverture de sites
- ✅ Très actif sur GitHub (30k+ stars)
- ✅ Focus sur les médias asiatiques (Weibo, Bilibili, Xiaohongshu)
- ✅ Twitter/X bien supporté
- ✅ YouTube complet (channels, playlists, community posts)
- ✅ Instagram via Picnob
- ✅ Documentation excellente
- ✅ Docker ready

**Points faibles :**
- ❌ **Pas de LinkedIn**
- ❌ **Pas de Facebook**
- ❌ Consomme plus de ressources (Node.js + Redis + Puppeteer)
- ⚠️ Twitter nécessite des tokens API
- ⚠️ Configuration plus complexe

**Sites principaux supportés :**
- Twitter/X (user timeline, media, lists, keywords, trends)
- Instagram (via Picnob proxy)
- YouTube (channels, playlists, community)
- Telegram (channels, stickers, stories)
- Weibo, Bilibili, Xiaohongshu (plateformes chinoises)
- Pixiv (artistes japonais)

**Installation :**
```bash
# Docker (recommandé)
docker run -d --name rsshub -p 1200:1200 diygod/rsshub:latest

# Ou avec docker-compose (inclut Redis + Puppeteer)
git clone https://github.com/DIYgod/RSSHub.git
cd RSSHub
docker-compose up -d

# Accès : http://localhost:1200
```

**Exemple d'usage :**
```
http://localhost:1200/twitter/user/elonmusk
http://localhost:1200/youtube/user/@MrBeast
http://localhost:1200/instagram/user/cristiano
```

---

### 2. RSS-Bridge

**Technologie :** PHP

**Points forts :**
- ✅ **447 bridges** disponibles
- ✅ Très léger (PHP pur, 2MB)
- ✅ Installation ultra-simple (unzip + done)
- ✅ Fonctionne sur serveur web classique
- ✅ Reddit bien supporté
- ✅ TikTok supporté
- ✅ Mastodon supporté
- ✅ Configuration via fichier ini simple

**Points faibles :**
- ❌ **Pas de LinkedIn**
- ⚠️ **Twitter payant** (API Basic = $100/mois minimum)
- ⚠️ **Facebook problématique** sur instances publiques
- ⚠️ **Instagram limité** (rate limiting, nécessite compte privé)
- ⚠️ Moins de routes que RSSHub
- ⚠️ Maintenance communautaire inégale selon les bridges

**Sites principaux supportés :**
- Reddit (user, subreddit, filtering)
- TikTok (user videos)
- YouTube (channel, playlist, search)
- Mastodon (user timeline)
- Telegram (public channels)
- Twitch (videos from channel)
- Twitter/X (nécessite API payante depuis Feb 2023)
- Instagram (nécessite instance privée)
- Facebook (nécessite instance privée)

**Installation :**
```bash
# Méthode simple
wget https://github.com/RSS-Bridge/rss-bridge/archive/refs/heads/master.zip
unzip master.zip
# Copier dans /var/www/html/rss-bridge

# Ou Docker
docker run -d -p 8080:80 rssbridge/rss-bridge

# Accès : http://localhost:8080
```

**Configuration requise :**
- PHP 7.4+ minimum
- Extensions : curl, json, mbstring, simplexml, openssl

**Exemple d'usage :**
```
http://localhost:8080/?action=display&bridge=Reddit&context=multi&r=programming&format=Atom
http://localhost:8080/?action=display&bridge=TikTok&username=nasa&format=Atom
```

---

### 3. Ton scraper actuel (Gheop Reader)

**Technologie :** PHP personnalisé

**Points forts :**
- ✅ **LinkedIn fonctionne** (avec cookies)
- ✅ **Twitter fonctionne** via Nitter
- ✅ **Instagram fonctionne** via Picuki
- ✅ Totalement sous ton contrôle
- ✅ Déjà intégré dans ton lecteur
- ✅ Optimisé pour tes besoins spécifiques
- ✅ Pas de dépendances lourdes
- ✅ Scrapers ciblés et légers

**Points faibles :**
- ❌ Maintenance manuelle quand sites changent
- ❌ Moins de sites couverts (~30 vs 447-1000+)
- ❌ Pas de communauté pour maintenir
- ⚠️ Cookies LinkedIn à renouveler régulièrement

**Sites actuellement supportés :**
```
YouTube, Twitter (Nitter), Instagram (Picuki), Reddit,
Medium, Dailymotion, Bloomberg, Coinbase, Binance,
Commit Strip, Les Joies du Code, Le Gorafi, GitHub,
+ scrapers torrents et crypto
```

---

## Recommandation selon ton besoin

### Scénario 1 : Tu veux juste LinkedIn
**→ Garde ton scraper actuel avec `linkedin_auth.php`**
- RSSHub et RSS-Bridge ne supportent pas LinkedIn
- Ta solution fonctionne déjà
- Pas besoin d'installer un système complexe pour 1 site

### Scénario 2 : Tu veux un max de sites automatiques
**→ Installe RSSHub en complément**
- 1000+ routes vs tes ~30 scrapers
- Maintenance communautaire
- Twitter, Instagram, YouTube, + plateformes asiatiques
- Garde tes scrapers pour LinkedIn, Bloomberg, etc.

### Scénario 3 : Tu veux une solution légère
**→ Installe RSS-Bridge en complément**
- PHP comme ton setup actuel
- 2MB, facile à déployer
- Reddit, TikTok, Mastodon
- Moins de sites que RSSHub mais plus simple

### Scénario 4 : Maximum de couverture
**→ Architecture hybride (recommandé)**
```
┌─────────────────────────────────────┐
│     Gheop Reader (interface)        │
└─────────────────────────────────────┘
                 ▼
    ┌────────────┬────────────┬────────────┐
    │ Scrapers   │  RSSHub    │ RSS-Bridge │
    │ maison     │ (Node.js)  │ (PHP)      │
    └────────────┴────────────┴────────────┘
    │            │            │
    ▼            ▼            ▼
LinkedIn     Twitter/X    Reddit
Bloomberg    Instagram    TikTok
Custom       YouTube      Mastodon
Twitter      Weibo
             Bilibili
```

**Avantages :**
- Couverture maximale (~1500+ sites combinés)
- LinkedIn via tes scrapers
- Maintenance auto via RSSHub/RSS-Bridge
- Fallback si un service tombe

**Installation :**
1. Garde tes scrapers actuels pour LinkedIn, Bloomberg, etc.
2. Installe RSSHub sur un port (ex: 1200)
3. Installe RSS-Bridge sur un autre port (ex: 8080)
4. Modifie `add_flux.php` pour router selon le site :
   - LinkedIn → scraper maison
   - Twitter → RSSHub (si tokens API) ou scraper maison
   - Reddit → RSS-Bridge
   - TikTok → RSS-Bridge
   - Instagram → RSSHub (Picnob)
   - etc.

---

## Installation recommandée (hybride)

### Étape 1 : Installer RSSHub

```bash
# Créer docker-compose.yml
cat > /tmp/rsshub-docker-compose.yml <<'EOF'
version: '3'
services:
  rsshub:
    image: diygod/rsshub:latest
    restart: always
    ports:
      - '1200:1200'
    environment:
      NODE_ENV: production
      CACHE_TYPE: redis
      REDIS_URL: 'redis://redis:6379/'
      PUPPETEER_WS_ENDPOINT: 'ws://browserless:3000'
    depends_on:
      - redis
      - browserless

  redis:
    image: redis:alpine
    restart: always
    volumes:
      - redis-data:/data

  browserless:
    image: browserless/chrome
    restart: always
    environment:
      MAX_CONCURRENT_SESSIONS: 3

volumes:
  redis-data:
EOF

cd /tmp
docker-compose -f rsshub-docker-compose.yml up -d
```

### Étape 2 : Installer RSS-Bridge

```bash
# Méthode 1 : Docker
docker run -d \
  --name rss-bridge \
  -p 8080:80 \
  -v /www/reader/rss-bridge-config:/config \
  --restart always \
  rssbridge/rss-bridge

# Méthode 2 : PHP classique (plus simple)
cd /www
wget https://github.com/RSS-Bridge/rss-bridge/releases/latest/download/rss-bridge.zip
unzip rss-bridge.zip -d rss-bridge
chown -R www-data:www-data rss-bridge
```

### Étape 3 : Router intelligemment dans add_flux.php

```php
function searchRSSUrlSpecialSite($url) {
    // LinkedIn → scraper maison (seule solution)
    if(preg_match('/linkedin\.com\/in\/([^\/]+)/', $url, $m)) {
        return 'https://reader.gheop.com/scraping/linkedin.com.php?f=' . urlencode($m[1]);
    }

    // Reddit → RSS natif (gratuit) ou RSS-Bridge si besoin de filtres
    if(preg_match('/reddit\.com\/(.*)/', $url, $m)) {
        return $url . '.rss'; // RSS natif gratuit
        // Ou RSS-Bridge pour filtres avancés :
        // return 'http://localhost:8080/?action=display&bridge=Reddit&r=...'
    }

    // TikTok → RSS-Bridge (seule option)
    if(preg_match('/tiktok\.com\/@([^\/]+)/', $url, $m)) {
        return 'http://localhost:8080/?action=display&bridge=TikTok&username=' .
               urlencode($m[1]) . '&format=Atom';
    }

    // Twitter → RSSHub si tokens API, sinon scraper maison
    if(preg_match('/twitter\.com\/([^\/]+)/', $url, $m)) {
        // Option 1 : RSSHub (nécessite tokens)
        // return 'http://localhost:1200/twitter/user/' . urlencode($m[1]);

        // Option 2 : Scraper maison (Nitter)
        return 'https://reader.gheop.com/scraping/twitter.com.php?f=' . urlencode($m[1]);
    }

    // Instagram → RSSHub Picnob
    if(preg_match('/instagram\.com\/([^\/]+)/', $url, $m)) {
        return 'http://localhost:1200/picnob/' . urlencode($m[1]);
    }

    // YouTube → RSS natif (gratuit et fiable)
    if(preg_match('/youtube\.com\/channel\/([^\/]+)/', $url, $m)) {
        return 'https://www.youtube.com/feeds/videos.xml?channel_id=' . $m[1];
    }

    // ... autres sites existants ...
}
```

---

## Verdict final

### Pour LinkedIn spécifiquement :
**→ Aucune des 3 solutions ne lève les limitations LinkedIn**
- RSSHub : ❌ Pas de support
- RSS-Bridge : ❌ Pas de support
- API LinkedIn : ❌ Permission fermée + impossible de lire d'autres profils

**→ Ta solution actuelle avec cookies reste la meilleure**

### Pour maximiser la couverture de sites :
**→ Architecture hybride recommandée**

1. **Garde tes scrapers** pour :
   - LinkedIn (uniquement toi)
   - Sites custom (Bloomberg, etc.)
   - Fallback si RSSHub/Bridge cassent

2. **Ajoute RSSHub** pour :
   - 1000+ sites automatiques
   - Twitter (si tu as tokens API)
   - Instagram (via Picnob)
   - Plateformes asiatiques

3. **Ajoute RSS-Bridge** pour :
   - TikTok
   - Reddit avec filtres avancés
   - Mastodon
   - Bridges spécifiques non dans RSSHub

### Ressources système :

```
Scraper maison     : ~0 MB RAM (juste PHP)
RSS-Bridge         : ~50 MB RAM (PHP)
RSSHub complet     : ~300-500 MB RAM (Node + Redis + Puppeteer)
RSSHub minimal     : ~100 MB RAM (Node seul)
```

---

## Conclusion

**Pour LinkedIn : Garde ta solution actuelle, c'est la seule qui marche.**

**Pour tout le reste : Installe RSSHub et/ou RSS-Bridge en complément pour bénéficier de 1000+ sites maintenus par la communauté.**

L'architecture hybride te donne le meilleur des 3 mondes :
- ✅ LinkedIn fonctionne (toi seul)
- ✅ 1500+ sites disponibles (RSSHub + RSS-Bridge)
- ✅ Contrôle total sur les sites critiques (scrapers maison)
- ✅ Maintenance minimale (communauté s'occupe de la majorité)
