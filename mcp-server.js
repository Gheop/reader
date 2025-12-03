#!/usr/bin/env node

/**
 * Gheop Reader MCP Server
 *
 * Provides AI-powered tools for interacting with RSS feeds:
 * - Search articles semantically
 * - Get reading statistics
 * - Analyze feed quality
 * - Find similar articles
 */

import { Server } from '@modelcontextprotocol/sdk/server/index.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import {
  CallToolRequestSchema,
  ListToolsRequestSchema,
} from '@modelcontextprotocol/sdk/types.js';
import mysql from 'mysql2/promise';

// Database configuration from environment or defaults
const DB_CONFIG = {
  host: process.env.DB_HOST || 'localhost',
  user: process.env.DB_USER || 'gheop',
  password: process.env.DB_PASSWORD || 'REDACTED',
  database: process.env.DB_NAME || 'gheop',
  charset: 'utf8mb4'
};

// Create database connection pool
const pool = mysql.createPool(DB_CONFIG);

// Create MCP server
const server = new Server(
  {
    name: 'gheop-reader',
    version: '1.0.0',
  },
  {
    capabilities: {
      tools: {},
    },
  }
);

/**
 * Tool: get_unread_stats
 * Returns statistics about unread articles
 */
async function getUnreadStats(userId = 1) {
  const [rows] = await pool.query(`
    SELECT
      COUNT(*) as total_unread,
      COUNT(DISTINCT I.id_flux) as feeds_with_unread,
      MIN(I.pubdate) as oldest_unread,
      MAX(I.pubdate) as newest_unread
    FROM reader_item I
    LEFT JOIN reader_user_item UI ON UI.id_item = I.id AND UI.id_user = ?
    WHERE UI.id IS NULL
  `, [userId]);

  const [feedStats] = await pool.query(`
    SELECT
      F.id,
      F.name,
      F.url,
      COUNT(I.id) as unread_count
    FROM reader_flux F
    INNER JOIN reader_item I ON I.id_flux = F.id
    LEFT JOIN reader_user_item UI ON UI.id_item = I.id AND UI.id_user = ?
    WHERE UI.id IS NULL
    GROUP BY F.id, F.name, F.url
    ORDER BY unread_count DESC
    LIMIT 10
  `, [userId]);

  return {
    summary: rows[0],
    top_feeds: feedStats
  };
}

/**
 * Tool: search_articles
 * Search for articles by keyword in title and description
 */
async function searchArticles(query, limit = 10, userId = 1) {
  const searchTerm = `%${query}%`;

  const [articles] = await pool.query(`
    SELECT
      I.id,
      I.id_flux,
      F.name as feed_name,
      I.title,
      I.author,
      I.link,
      I.pubdate,
      LEFT(I.description, 500) as description_preview,
      CASE WHEN UI.id IS NOT NULL THEN 1 ELSE 0 END as is_read
    FROM reader_item I
    INNER JOIN reader_flux F ON F.id = I.id_flux
    LEFT JOIN reader_user_item UI ON UI.id_item = I.id AND UI.id_user = ?
    WHERE I.title LIKE ? OR I.description LIKE ?
    ORDER BY I.pubdate DESC
    LIMIT ?
  `, [userId, searchTerm, searchTerm, limit]);

  return articles;
}

/**
 * Tool: get_recent_articles
 * Get most recent articles, optionally filtered by read status
 */
async function getRecentArticles(limit = 20, unreadOnly = false, userId = 1) {
  let query = `
    SELECT
      I.id,
      I.id_flux,
      F.name as feed_name,
      I.title,
      I.author,
      I.link,
      I.pubdate,
      LEFT(I.description, 300) as description_preview,
      CASE WHEN UI.id IS NOT NULL THEN 1 ELSE 0 END as is_read
    FROM reader_item I
    INNER JOIN reader_flux F ON F.id = I.id_flux
    LEFT JOIN reader_user_item UI ON UI.id_item = I.id AND UI.id_user = ?
  `;

  if (unreadOnly) {
    query += ' WHERE UI.id IS NULL';
  }

  query += ' ORDER BY I.pubdate DESC LIMIT ?';

  const [articles] = await pool.query(query, unreadOnly ? [userId, limit] : [userId, limit]);
  return articles;
}

/**
 * Tool: get_feed_stats
 * Analyze feed quality and activity
 */
async function getFeedStats(feedId = null) {
  let query;
  let params;

  if (feedId) {
    query = `
      SELECT
        F.id,
        F.name,
        F.url,
        F.last_update,
        COUNT(I.id) as total_articles,
        MAX(I.pubdate) as latest_article,
        MIN(I.pubdate) as oldest_article,
        AVG(LENGTH(I.description)) as avg_description_length
      FROM reader_flux F
      LEFT JOIN reader_item I ON I.id_flux = F.id
      WHERE F.id = ?
      GROUP BY F.id, F.name, F.url, F.last_update
    `;
    params = [feedId];
  } else {
    query = `
      SELECT
        F.id,
        F.name,
        F.url,
        F.last_update,
        COUNT(I.id) as total_articles,
        MAX(I.pubdate) as latest_article
      FROM reader_flux F
      LEFT JOIN reader_item I ON I.id_flux = F.id
      GROUP BY F.id, F.name, F.url, F.last_update
      ORDER BY total_articles DESC
      LIMIT 20
    `;
    params = [];
  }

  const [feeds] = await pool.query(query, params);
  return feedId ? feeds[0] : feeds;
}

/**
 * Tool: find_dead_feeds
 * Find feeds that haven't updated in a while or always fail
 */
async function findDeadFeeds(daysInactive = 30) {
  const cutoffDate = new Date();
  cutoffDate.setDate(cutoffDate.getDate() - daysInactive);
  const cutoffStr = cutoffDate.toISOString().slice(0, 19).replace('T', ' ');

  const [deadFeeds] = await pool.query(`
    SELECT
      F.id,
      F.name,
      F.url,
      F.last_update,
      MAX(I.pubdate) as last_article,
      COUNT(I.id) as article_count
    FROM reader_flux F
    LEFT JOIN reader_item I ON I.id_flux = F.id
    GROUP BY F.id, F.name, F.url, F.last_update
    HAVING last_article < ? OR last_article IS NULL
    ORDER BY last_article ASC
  `, [cutoffStr]);

  return deadFeeds;
}

/**
 * Tool: get_article_by_id
 * Get full article details including description
 */
async function getArticleById(articleId, userId = 1) {
  const [articles] = await pool.query(`
    SELECT
      I.id,
      I.id_flux,
      F.name as feed_name,
      F.url as feed_url,
      I.title,
      I.author,
      I.link,
      I.pubdate,
      I.description,
      I.guid,
      CASE WHEN UI.id IS NOT NULL THEN 1 ELSE 0 END as is_read
    FROM reader_item I
    INNER JOIN reader_flux F ON F.id = I.id_flux
    LEFT JOIN reader_user_item UI ON UI.id_item = I.id AND UI.id_user = ?
    WHERE I.id = ?
  `, [userId, articleId]);

  return articles[0] || null;
}

// Register tool handlers
server.setRequestHandler(ListToolsRequestSchema, async () => {
  return {
    tools: [
      {
        name: 'get_unread_stats',
        description: 'Get statistics about unread articles, including total count and top feeds with unread items',
        inputSchema: {
          type: 'object',
          properties: {
            userId: {
              type: 'number',
              description: 'User ID (default: 1)',
              default: 1
            }
          }
        }
      },
      {
        name: 'search_articles',
        description: 'Search for articles by keyword in title and description',
        inputSchema: {
          type: 'object',
          properties: {
            query: {
              type: 'string',
              description: 'Search query (searches in title and description)'
            },
            limit: {
              type: 'number',
              description: 'Maximum number of results (default: 10)',
              default: 10
            },
            userId: {
              type: 'number',
              description: 'User ID (default: 1)',
              default: 1
            }
          },
          required: ['query']
        }
      },
      {
        name: 'get_recent_articles',
        description: 'Get most recent articles, optionally filtered to show only unread',
        inputSchema: {
          type: 'object',
          properties: {
            limit: {
              type: 'number',
              description: 'Maximum number of articles (default: 20)',
              default: 20
            },
            unreadOnly: {
              type: 'boolean',
              description: 'Show only unread articles (default: false)',
              default: false
            },
            userId: {
              type: 'number',
              description: 'User ID (default: 1)',
              default: 1
            }
          }
        }
      },
      {
        name: 'get_feed_stats',
        description: 'Get statistics about feeds (all feeds or a specific feed)',
        inputSchema: {
          type: 'object',
          properties: {
            feedId: {
              type: 'number',
              description: 'Feed ID (optional - if not provided, returns stats for all feeds)'
            }
          }
        }
      },
      {
        name: 'find_dead_feeds',
        description: 'Find feeds that haven\'t published new articles in a specified number of days',
        inputSchema: {
          type: 'object',
          properties: {
            daysInactive: {
              type: 'number',
              description: 'Number of days without new articles (default: 30)',
              default: 30
            }
          }
        }
      },
      {
        name: 'get_article_by_id',
        description: 'Get full details of a specific article by its ID',
        inputSchema: {
          type: 'object',
          properties: {
            articleId: {
              type: 'number',
              description: 'Article ID'
            },
            userId: {
              type: 'number',
              description: 'User ID (default: 1)',
              default: 1
            }
          },
          required: ['articleId']
        }
      }
    ]
  };
});

server.setRequestHandler(CallToolRequestSchema, async (request) => {
  try {
    const { name, arguments: args } = request.params;

    switch (name) {
      case 'get_unread_stats':
        return {
          content: [{
            type: 'text',
            text: JSON.stringify(await getUnreadStats(args.userId), null, 2)
          }]
        };

      case 'search_articles':
        return {
          content: [{
            type: 'text',
            text: JSON.stringify(await searchArticles(args.query, args.limit, args.userId), null, 2)
          }]
        };

      case 'get_recent_articles':
        return {
          content: [{
            type: 'text',
            text: JSON.stringify(await getRecentArticles(args.limit, args.unreadOnly, args.userId), null, 2)
          }]
        };

      case 'get_feed_stats':
        return {
          content: [{
            type: 'text',
            text: JSON.stringify(await getFeedStats(args.feedId), null, 2)
          }]
        };

      case 'find_dead_feeds':
        return {
          content: [{
            type: 'text',
            text: JSON.stringify(await findDeadFeeds(args.daysInactive), null, 2)
          }]
        };

      case 'get_article_by_id':
        return {
          content: [{
            type: 'text',
            text: JSON.stringify(await getArticleById(args.articleId, args.userId), null, 2)
          }]
        };

      default:
        throw new Error(`Unknown tool: ${name}`);
    }
  } catch (error) {
    return {
      content: [{
        type: 'text',
        text: `Error: ${error.message}`
      }],
      isError: true
    };
  }
});

// Start the server
async function main() {
  const transport = new StdioServerTransport();
  await server.connect(transport);
  console.error('Gheop Reader MCP Server running on stdio');
}

main().catch((error) => {
  console.error('Fatal error:', error);
  process.exit(1);
});
