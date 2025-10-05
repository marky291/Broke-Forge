[Unit]
Description=BrokeForge Monitoring - Metrics Collection Timer
Requires=brokeforge-monitoring.service

[Timer]
# Run every {{ $intervalMinutes }} minutes
OnBootSec=1min
OnUnitActiveSec={{ $intervalMinutes }}min

[Install]
WantedBy=timers.target
