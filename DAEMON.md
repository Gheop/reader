# Feed Update Daemon

Remplace le cron par un daemon qui tourne en continu et met à jour les flux toutes les 10 minutes.

## Avantages vs Cron

✅ **Meilleur contrôle** : Gestion via systemd avec restart automatique
✅ **Meilleur logging** : Logs structurés dans journalctl
✅ **Pas de chevauchement** : Un seul processus à la fois
✅ **Auto-recovery** : Redémarre automatiquement en cas de crash
✅ **Monitoring facile** : Status en temps réel

## Installation

### Option 1 : Systemd (recommandé)

```bash
# Installer le service
sudo /www/reader/daemon-control.sh install-systemd

# Démarrer
sudo systemctl start reader-update-daemon

# Vérifier le status
sudo systemctl status reader-update-daemon

# Voir les logs en temps réel
sudo journalctl -u reader-update-daemon -f

# Arrêter
sudo systemctl stop reader-update-daemon
```

### Option 2 : Script manuel

```bash
# Démarrer
/www/reader/daemon-control.sh start

# Arrêter
/www/reader/daemon-control.sh stop

# Redémarrer
/www/reader/daemon-control.sh restart

# Status
/www/reader/daemon-control.sh status

# Voir les logs
/www/reader/daemon-control.sh log
```

### Option 3 : Direct (pour tests)

```bash
# Avant-plan (avec sortie console)
php /www/reader/update_daemon.php

# Arrière-plan
nohup php /www/reader/update_daemon.php >> /var/log/reader_update_daemon.log 2>&1 &
```

## Configuration

Éditer `/www/reader/update_daemon.php` :

```php
define('UPDATE_INTERVAL', 600);  // 10 minutes (en secondes)
define('MAX_RUNTIME', 86400);    // 24 heures avant redémarrage
```

## Migration depuis Cron

### 1. Vérifier le cron actuel

```bash
crontab -l | grep up.php
```

### 2. Installer le daemon

```bash
sudo /www/reader/daemon-control.sh install-systemd
sudo systemctl start reader-update-daemon
```

### 3. Vérifier que ça fonctionne

```bash
# Attendre quelques minutes puis vérifier les logs
sudo journalctl -u reader-update-daemon -f
```

### 4. Supprimer le cron

```bash
crontab -e
# Commenter ou supprimer la ligne avec up.php
```

## Logs

### Systemd (journalctl)

```bash
# Dernières 50 lignes
sudo journalctl -u reader-update-daemon -n 50

# Temps réel
sudo journalctl -u reader-update-daemon -f

# Depuis aujourd'hui
sudo journalctl -u reader-update-daemon --since today

# Dernière heure
sudo journalctl -u reader-update-daemon --since "1 hour ago"
```

### Fichier log

```bash
# Temps réel
tail -f /var/log/reader_update_daemon.log

# Dernières 100 lignes
tail -n 100 /var/log/reader_update_daemon.log

# Rechercher les erreurs
grep -i error /var/log/reader_update_daemon.log
```

## Monitoring

### Vérifier l'état

```bash
# Systemd
sudo systemctl is-active reader-update-daemon

# Script
/www/reader/daemon-control.sh status

# Processus
ps aux | grep update_daemon
```

### Statistiques

Le daemon log à chaque update :
```
[2025-11-14 15:30:00] Starting feed update...
[2025-11-14 15:32:15] Update completed in 135.23s - 321 feeds, 47 new articles, 3 errors
```

## Troubleshooting

### Le daemon ne démarre pas

```bash
# Vérifier les permissions
ls -la /www/reader/update_daemon.php

# Vérifier PHP CLI
which php
php --version

# Tester manuellement
php /www/reader/update_daemon.php
```

### Le daemon se termine tout seul

```bash
# Voir les logs d'erreur
sudo journalctl -u reader-update-daemon -p err

# Vérifier la mémoire
free -h

# Vérifier les processus PHP
ps aux | grep php
```

### Updates trop lentes

```bash
# Voir les logs de performance
grep "Update completed" /var/log/reader_update_daemon.log

# Ajuster la concurrence dans up_parallel.php
# Augmenter MAX_CONCURRENT de 20 à 30
```

### Trop d'erreurs

```bash
# Voir les erreurs récentes
grep "errors" /var/log/reader_update_daemon.log | tail -20

# Tester un update manuel
php /www/reader/up_parallel.php
```

## Désinstallation

### Systemd

```bash
sudo systemctl stop reader-update-daemon
sudo systemctl disable reader-update-daemon
sudo rm /etc/systemd/system/reader-update-daemon.service
sudo systemctl daemon-reload
```

### Script manuel

```bash
/www/reader/daemon-control.sh stop
rm -f /tmp/reader_update_daemon.pid
```

## Performance

### Ressources utilisées

- **CPU** : ~5-10% pendant les updates, 0% en attente
- **RAM** : ~100-200 MB
- **Réseau** : Dépend du nombre de flux (typiquement 1-5 Mbps)

### Limites systemd

Le service systemd est configuré avec :
- CPU : 50% max d'un core
- RAM : 512 MB max

Modifier dans `/etc/systemd/system/reader-update-daemon.service` si nécessaire.

## Alternatives

Si le daemon pose problème, tu peux revenir au cron :

```bash
# Arrêter le daemon
sudo systemctl stop reader-update-daemon
sudo systemctl disable reader-update-daemon

# Réactiver le cron
crontab -e
# Ajouter :
*/10 * * * * php /www/reader/up_parallel.php > /dev/null 2>&1
```

## See Also

- `up_parallel.php` - Script de mise à jour parallèle
- `PERFORMANCE.md` - Documentation sur les améliorations de performance
