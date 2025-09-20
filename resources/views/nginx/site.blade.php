server {
    listen 80;
    listen [::]:80;
    server_name {{ $domain }} www.{{ $domain }};
    root {{ $documentRoot }};
    index index.php index.html index.htm;

    # Logging
    access_log /var/log/nginx/{{ $domain }}/access.log;
    error_log /var/log/nginx/{{ $domain }}/error.log;

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
}

@if($ssl && $sslCertPath && $sslKeyPath)
# HTTPS configuration
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name {{ $domain }} www.{{ $domain }};
    root {{ $documentRoot }};
    index index.php index.html index.htm;

    # SSL configuration
    ssl_certificate {{ $sslCertPath }};
    ssl_certificate_key {{ $sslKeyPath }};
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers 'ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-AES256-GCM-SHA384';
    ssl_prefer_server_ciphers off;

    # Logging
    access_log /var/log/nginx/{{ $domain }}/access.log;
    error_log /var/log/nginx/{{ $domain }}/error.log;

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

    # Deny access to hidden files (except .well-known)
    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    # Gzip compression
    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml application/xml+rss text/javascript;
    gzip_vary on;

    # Max upload size
    client_max_body_size 100M;
}

# HTTP to HTTPS redirect
server {
    listen 80;
    listen [::]:80;
    server_name {{ $domain }} www.{{ $domain }};
    return 301 https://$server_name$request_uri;
}
@endif