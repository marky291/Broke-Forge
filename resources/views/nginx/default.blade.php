server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name _;
    root {{ $documentRoot }};
    index index.php index.html index.htm;

    # Logging
    access_log /var/log/nginx/default_access.log;
    error_log /var/log/nginx/default_error.log;

    # Character encoding
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP-FPM configuration
    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_pass unix:{{ $phpSocket }};
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Static assets caching
    location ~* \.(jpg|jpeg|png|gif|ico|css|js)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    # Deny access to hidden files (except .well-known)
    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # Gzip compression
    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml application/xml+rss text/javascript;
    gzip_vary on;

    # Max upload size
    client_max_body_size 100M;

    # Default error pages
    error_page 404 /404.html;
    error_page 500 502 503 504 /50x.html;

    location = /404.html {
        internal;
        root /var/www/default/public;
    }

    location = /50x.html {
        internal;
        root /var/www/default/public;
    }
}