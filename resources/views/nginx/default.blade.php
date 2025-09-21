server {
    listen 80 default_server;
    listen [::]:80 default_server;

    server_name _;

    root /home/{{ $appUser }}/default/public;
    index index.php index.html index.htm;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php{{ $phpVersion }}-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    error_log /var/log/nginx/default-error.log;
    access_log /var/log/nginx/default-access.log;
}