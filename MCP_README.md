# Gheop Reader MCP Server

Serveur MCP (Model Context Protocol) pour interagir avec le lecteur RSS Gheop via Claude Code ou d'autres clients MCP.

## Installation

```bash
npm install
```

## Configuration

### Option 1: Configuration locale (développement)

Créer `~/.config/claude-code/mcp.json`:

```json
{
  "mcpServers": {
    "gheop-reader": {
      "command": "node",
      "args": ["/www/reader/mcp-server.js"],
      "env": {
        "DB_HOST": "localhost",
        "DB_USER": "gheop",
        "DB_PASSWORD": "REDACTED",
        "DB_NAME": "gheop"
      }
    }
  }
}
```

### Option 2: Test manuel

```bash
node mcp-server.js
```

## Tools disponibles

### 1. `get_unread_stats`
Obtenir des statistiques sur les articles non lus.

**Paramètres:**
- `userId` (optionnel): ID utilisateur (défaut: 1)

**Exemple d'utilisation:**
```
"Combien d'articles non lus j'ai?"
"Quels sont mes flux avec le plus d'articles non lus?"
```

**Retourne:**
- Nombre total d'articles non lus
- Nombre de flux avec des articles non lus
- Article non lu le plus ancien/récent
- Top 10 des flux avec articles non lus

---

### 2. `search_articles`
Rechercher des articles par mots-clés dans le titre et la description.

**Paramètres:**
- `query` (requis): Termes de recherche
- `limit` (optionnel): Nombre max de résultats (défaut: 10)
- `userId` (optionnel): ID utilisateur (défaut: 1)

**Exemple d'utilisation:**
```
"Trouve-moi des articles sur Bitcoin"
"Recherche des articles parlant de sécurité"
"Y a-t-il des articles sur le VTT cette semaine?"
```

**Retourne:**
- Liste d'articles avec titre, lien, date, preview description
- Statut lu/non lu pour chaque article

---

### 3. `get_recent_articles`
Obtenir les articles les plus récents.

**Paramètres:**
- `limit` (optionnel): Nombre d'articles (défaut: 20)
- `unreadOnly` (optionnel): Seulement les non lus (défaut: false)
- `userId` (optionnel): ID utilisateur (défaut: 1)

**Exemple d'utilisation:**
```
"Montre-moi les 10 derniers articles"
"Quels sont mes articles non lus récents?"
"Qu'y a-t-il de nouveau aujourd'hui?"
```

**Retourne:**
- Articles triés par date de publication décroissante

---

### 4. `get_feed_stats`
Analyser les statistiques d'un ou plusieurs flux.

**Paramètres:**
- `feedId` (optionnel): ID du flux spécifique

**Exemple d'utilisation:**
```
"Quels sont mes flux les plus actifs?"
"Analyse le flux ID 777"
"Donne-moi des stats sur mes flux RSS"
```

**Retourne:**
- Pour tous les flux: nom, URL, nombre d'articles, dernier article
- Pour un flux spécifique: stats détaillées incluant longueur moyenne des descriptions

---

### 5. `find_dead_feeds`
Trouver les flux inactifs ou morts.

**Paramètres:**
- `daysInactive` (optionnel): Nombre de jours sans nouvel article (défaut: 30)

**Exemple d'utilisation:**
```
"Quels flux n'ont pas publié depuis 30 jours?"
"Trouve les flux morts que je devrais supprimer"
"Y a-t-il des flux qui ne marchent plus?"
```

**Retourne:**
- Liste des flux sans nouveaux articles depuis N jours
- Dernière date d'article pour chaque flux

---

### 6. `get_article_by_id`
Obtenir les détails complets d'un article spécifique.

**Paramètres:**
- `articleId` (requis): ID de l'article
- `userId` (optionnel): ID utilisateur (défaut: 1)

**Exemple d'utilisation:**
```
"Montre-moi l'article 2437801"
"Lis-moi le contenu de l'article ID 12345"
```

**Retourne:**
- Titre, auteur, lien, description complète, date de publication
- Informations sur le flux source
- Statut lu/non lu

---

## Exemples de workflows

### 1. Veille quotidienne
```
Utilisateur: "Qu'y a-t-il de nouveau aujourd'hui?"
MCP: get_recent_articles(limit=20, unreadOnly=true)
→ Liste des 20 derniers articles non lus

Utilisateur: "Y a-t-il des articles sur Bitcoin?"
MCP: search_articles(query="Bitcoin", limit=5)
→ Top 5 articles mentionnant Bitcoin
```

### 2. Nettoyage des flux
```
Utilisateur: "Trouve les flux morts depuis 60 jours"
MCP: find_dead_feeds(daysInactive=60)
→ Liste des flux inactifs

Utilisateur: "Montre-moi les stats du flux 777"
MCP: get_feed_stats(feedId=777)
→ Stats détaillées du flux Korben
```

### 3. Analyse de lecture
```
Utilisateur: "Combien d'articles non lus j'ai?"
MCP: get_unread_stats()
→ "Tu as 156 articles non lus dans 42 flux"

Utilisateur: "Quels sont mes flux les plus actifs?"
MCP: get_feed_stats()
→ Top 20 des flux par nombre d'articles
```

## Architecture

```
Claude Code / Client MCP
    ↓ (stdio)
mcp-server.js
    ↓ (mysql2)
MySQL Database (gheop.reader_*)
```

## Base de données

Le serveur accède aux tables:
- `reader_item`: Articles
- `reader_flux`: Flux RSS
- `reader_user_item`: Articles lus par utilisateur
- `reader_item_archive`: Articles archivés

## Développement

### Ajouter un nouveau tool

1. Créer la fonction dans `mcp-server.js`:
```javascript
async function myNewTool(param1, param2) {
  const [rows] = await pool.query('SELECT ...', [param1, param2]);
  return rows;
}
```

2. Déclarer le tool dans `ListToolsRequestSchema`:
```javascript
{
  name: 'my_new_tool',
  description: 'Description de ce que fait le tool',
  inputSchema: {
    type: 'object',
    properties: {
      param1: { type: 'string', description: '...' },
      param2: { type: 'number', description: '...' }
    },
    required: ['param1']
  }
}
```

3. Ajouter le handler dans `CallToolRequestSchema`:
```javascript
case 'my_new_tool':
  return {
    content: [{
      type: 'text',
      text: JSON.stringify(await myNewTool(args.param1, args.param2), null, 2)
    }]
  };
```

## Debugging

Le serveur écrit les erreurs sur stderr:
```bash
node mcp-server.js 2> debug.log
```

## Sécurité

- Le serveur utilise des prepared statements (protection SQL injection)
- Connexion locale MySQL uniquement (pas d'exposition réseau)
- Accès en lecture seule (pas de modification des données)

## Prochaines améliorations

- [ ] Tool pour marquer des articles comme lus
- [ ] Détection intelligente de doublons
- [ ] Recommandations personnalisées basées sur l'historique
- [ ] Résumé automatique d'articles longs
- [ ] Catégorisation automatique par topics
