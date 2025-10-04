[Unit]
Description=BrokeForge Monitoring - Metrics Collection
After=network.target

[Service]
Type=oneshot
ExecStart=/opt/brokeforge/monitoring/collect-metrics.sh
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
