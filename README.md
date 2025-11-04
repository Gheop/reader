# Gheop Reader

[![CI Status](https://github.com/Gheop/reader/workflows/CI/badge.svg)](https://github.com/Gheop/reader/actions)
[![codecov](https://codecov.io/gh/Gheop/reader/branch/master/graph/badge.svg)](https://codecov.io/gh/Gheop/reader)
[![Valid OPML](https://validator.w3.org/feed/images/valid-opml.gif)](https://validator.w3.org/feed/check.cgi?url=https%3A%2F%2Freader.gheop.com%2Fopml.opml)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D7.4-8892BF.svg)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Self Hosted](https://img.shields.io/badge/self--hosted-yes-blue.svg)](https://github.com/Gheop/reader)
[![Live Demo](https://img.shields.io/badge/demo-online-brightgreen.svg)](https://reader.gheop.com)
[![Maintenance](https://img.shields.io/badge/Maintained%3F-yes-green.svg)](https://github.com/Gheop/reader/graphs/commit-activity)

A lightweight, self-hosted RSS/Atom feed reader built with PHP and JavaScript. Follow all your favorite sites, blogs, and content sources from one unified interface.

## Features

- **RSS/Atom Feed Support** - Subscribe to and read content from any RSS or Atom feed
- **Custom Scrapers** - Built-in scrapers for Twitter, YouTube, Bloomberg, and other popular sites
- **Clean Reading Experience** - Distraction-free article view with readability mode
- **AI-Powered Features** - Article summarization and automatic tagging
- **Keyboard Navigation** - Full keyboard shortcuts for efficient browsing
- **Responsive Design** - Works seamlessly on desktop and mobile devices
- **Theme Support** - Light and dark themes with easy switching
- **Mark as Read** - Automatic and manual article read tracking
- **Search** - Full-text search across all your feeds
- **Self-Hosted** - Own your data and keep full control
- **Resizable Sidebar** - Customize your layout to suit your needs

## Demo

Try it out at [https://reader.gheop.com](https://reader.gheop.com)
Demo credentials: `demo` / `demo`

## Requirements

- PHP 7.4 or higher
- MySQL/MariaDB database
- Web server (Apache, Nginx, etc.)
- Modern web browser with JavaScript enabled

## Installation

1. Clone the repository:
```bash
git clone https://github.com/Gheop/reader.git
cd reader
```

2. Create a MySQL database and import the schema (SQL file not included in repo - contact maintainer)

3. Configure your database connection in `/www/conf.php`:
```php
<?php
session_start();
$mysqli = new mysqli("localhost", "username", "password", "database");
$_SESSION['mysqli'] = $mysqli;
?>
```

4. Set up your web server to serve the application directory

5. Navigate to the application in your browser and log in

## Usage

### Adding Feeds

1. Click the `+` icon next to "All" in the sidebar
2. Enter the RSS/Atom feed URL
3. The feed will be added and articles will be fetched

### Keyboard Shortcuts

- `N` or `→` - Next article
- `P` or `←` - Previous article
- `↑` / `↓` - Scroll up/down
- `PgUp` / `PgDn` - Page up/down
- `G` - Search selected text on Google
- `W` - Search selected text on Wikipedia
- `O` - Open active article in new tab

### Features

- **Readability Mode** - Click the document icon to extract full article content
- **Summarize** - Click the brain icon to generate an AI summary of long articles
- **Print** - Print articles with a clean, formatted layout
- **Mark All as Read** - Right-click on a feed to mark all articles as read
- **Unsubscribe** - Remove feeds you no longer want to follow

## Custom Scrapers

The `/scraping` directory contains custom PHP scrapers for sites that don't provide standard RSS feeds or require special handling:

- Twitter/X feeds
- YouTube channels
- Bloomberg articles
- And more...

To add a custom scraper, create a PHP file in the `/scraping` directory that returns structured article data.

## Configuration

### Theme

Switch between light and dark themes using the moon icon in the top-right corner. The preference is saved automatically.

### Sidebar Width

Drag the sidebar resizer to adjust the width. The setting is saved in localStorage.

## Technology Stack

- **Frontend**: Vanilla JavaScript, Font Awesome icons
- **Backend**: PHP with MySQLi
- **Database**: MySQL/MariaDB
- **Features**: IntersectionObserver API, Shadow DOM, Custom Elements

## Privacy

- All data is stored on your own server
- No third-party tracking or analytics
- You can export your feeds at any time
- Completely self-hosted and open source

## License

Free and open source software. Feel free to use, modify, and host your own instance.

## Support

For bugs, feature requests, or questions, please open an issue on GitHub.

## Development

### Running Tests

```bash
# Install dependencies
composer install

# Run PHPUnit tests
composer test

# Run tests with coverage
composer test-coverage

# Run static analysis
composer phpstan

# Run code style checker
composer phpcs

# Fix code style issues
composer phpcbf
```

### Code Quality

This project uses:
- **PHPUnit** for unit testing
- **PHPStan** for static analysis
- **PHP_CodeSniffer** for code style (PSR-12)
- **ESLint** for JavaScript linting
- **Codecov** for code coverage tracking

All pushes and pull requests are automatically tested via GitHub Actions CI/CD pipeline.

## Contributing

Contributions are welcome! Please feel free to submit pull requests or open issues for bugs and feature requests.

### Contribution Guidelines

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes
4. Run tests and linting (`composer test && composer phpcs`)
5. Commit your changes (`git commit -m 'Add amazing feature'`)
6. Push to the branch (`git push origin feature/amazing-feature`)
7. Open a Pull Request
