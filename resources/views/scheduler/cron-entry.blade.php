# BrokeForge Scheduled Task: {{ $task->name }}
# Task ID: {{ $task->id }}
# Command: {{ $task->command }}
# Frequency: {{ $task->frequency->value }}

SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin

{{ $cronExpression }} root /opt/brokeforge/scheduler/tasks/{{ $task->id }}.sh >> /var/log/brokeforge/scheduler.log 2>&1
