@php
    $sanitizedName = preg_replace('/[^a-zA-Z0-9-_]/', '_', $task->name);
@endphp
[program:{{ $sanitizedName }}]
command={{ $task->command }}
directory={{ $task->working_directory }}
user={{ $task->user }}
numprocs={{ $task->processes }}
autostart=true
autorestart={{ $task->auto_restart ? 'true' : 'false' }}
autorestart_unexpected={{ $task->autorestart_unexpected ? 'true' : 'false' }}
startsecs=1
startretries=3
exitcodes=0
stopsignal=TERM
stopwaitsecs=10
stopasgroup=true
killasgroup=true
stdout_logfile={{ $task->stdout_logfile ?? '/var/log/supervisor/' . $sanitizedName . '-stdout.log' }}
stdout_logfile_maxbytes=50MB
stdout_logfile_backups=10
stderr_logfile={{ $task->stderr_logfile ?? '/var/log/supervisor/' . $sanitizedName . '-stderr.log' }}
stderr_logfile_maxbytes=50MB
stderr_logfile_backups=10
