#!/bin/bash
# Gheop Reader Update Daemon Control Script

DAEMON_SCRIPT="/www/reader/update_daemon.php"
PID_FILE="/tmp/reader_update_daemon.pid"
LOG_FILE="/var/log/reader_update_daemon.log"

case "$1" in
    start)
        if [ -f "$PID_FILE" ]; then
            PID=$(cat "$PID_FILE")
            if ps -p $PID > /dev/null 2>&1; then
                echo "Daemon already running (PID: $PID)"
                exit 1
            else
                echo "Removing stale PID file"
                rm -f "$PID_FILE"
            fi
        fi

        echo "Starting feed update daemon..."
        nohup php "$DAEMON_SCRIPT" >> "$LOG_FILE" 2>&1 &
        sleep 2

        if [ -f "$PID_FILE" ]; then
            PID=$(cat "$PID_FILE")
            echo "Daemon started (PID: $PID)"
        else
            echo "Failed to start daemon"
            exit 1
        fi
        ;;

    stop)
        if [ ! -f "$PID_FILE" ]; then
            echo "Daemon not running (no PID file)"
            exit 1
        fi

        PID=$(cat "$PID_FILE")
        echo "Stopping daemon (PID: $PID)..."
        kill $PID

        # Wait for graceful shutdown
        for i in {1..10}; do
            if ! ps -p $PID > /dev/null 2>&1; then
                echo "Daemon stopped"
                exit 0
            fi
            sleep 1
        done

        # Force kill if still running
        echo "Forcing shutdown..."
        kill -9 $PID
        rm -f "$PID_FILE"
        echo "Daemon killed"
        ;;

    restart)
        $0 stop
        sleep 2
        $0 start
        ;;

    status)
        if [ ! -f "$PID_FILE" ]; then
            echo "Daemon not running (no PID file)"
            exit 1
        fi

        PID=$(cat "$PID_FILE")
        if ps -p $PID > /dev/null 2>&1; then
            echo "Daemon running (PID: $PID)"
            echo ""
            ps aux | grep $PID | grep -v grep
        else
            echo "Daemon not running (stale PID file)"
            rm -f "$PID_FILE"
            exit 1
        fi
        ;;

    log)
        if [ -f "$LOG_FILE" ]; then
            tail -f "$LOG_FILE"
        else
            echo "No log file found"
            exit 1
        fi
        ;;

    install-systemd)
        if [ "$EUID" -ne 0 ]; then
            echo "Please run as root (sudo)"
            exit 1
        fi

        echo "Installing systemd service..."
        cp /www/reader/systemd/reader-update-daemon.service /etc/systemd/system/
        systemctl daemon-reload
        systemctl enable reader-update-daemon
        echo "Service installed and enabled"
        echo "Start with: sudo systemctl start reader-update-daemon"
        echo "Status: sudo systemctl status reader-update-daemon"
        echo "Logs: sudo journalctl -u reader-update-daemon -f"
        ;;

    *)
        echo "Usage: $0 {start|stop|restart|status|log|install-systemd}"
        exit 1
        ;;
esac
