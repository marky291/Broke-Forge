[Unit]
Description=BrokeForge Monitoring - Metrics Collection Timer
Requires=brokeforge-monitoring.service

[Timer]
# Run every 5 minutes
OnBootSec=1min
OnUnitActiveSec=5min

[Install]
WantedBy=timers.target
