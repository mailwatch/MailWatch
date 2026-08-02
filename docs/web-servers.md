# Web server configuration

MailWatch uses `public_html/index.php` as its public entry point. Configure the
web server document root as the repository's `public_html` directory. Source
code, configuration, Composer packages, tests and command-line tools remain
outside the document root.

The front controller dispatches the web interface through an explicit list of
page routes and exposes these API routes:

| Method | Path | Purpose |
|---|---|---|
| `POST` | `/api/messages` | Store a message processed by MailScanner |
| `GET` | `/api/allow-block-list` | Download the allow/block list snapshot |
| `GET` | `/api/spam-settings` | Download SpamAssassin and no-scan settings |

Only `index.php` and static assets are stored inside `public_html`. Application
code and configuration remain outside the document root and cannot be selected
by turning an arbitrary URL into a PHP filename.

## Writable directories

MailWatch compiles its templates into `var/cache/twig`, beside `public_html`
rather than inside it. Making that directory writable by the PHP-FPM user is
worth doing:

```bash
install -d -o www-data -g www-data -m 750 /var/www/mailwatch/var/cache/twig
```

It is an optimisation, not a requirement. If the directory cannot be created or
written, MailWatch compiles the templates on every request instead — slower, but
working, and nothing needs to be changed for a read-only installation.

MailWatch recompiles a template whenever the file behind it changes, so an
upgrade needs no cache clearing. Deleting the contents of the directory is
always safe.

Do not move this directory inside the document root: compiled templates are PHP.

## Static files

Stylesheets, JavaScript, images, `favicon.ico` and `robots.txt` are stored under
`public_html` and must be served directly by the web server. Requests for files
that exist on disk do not pass through `index.php`; only application routes and
unknown paths reach PHP. A custom `skin.css`, when used, belongs in
`public_html/skin.css` for the same reason.

Replace `/var/www/mailwatch` and the PHP-FPM socket in the examples with paths
appropriate for the installation.

## Apache

Enable `mod_rewrite`, then configure the virtual host:

```apache
<VirtualHost *:443>
    ServerName mailwatch.example.com
    DocumentRoot /var/www/mailwatch/public_html

    <Directory /var/www/mailwatch/public_html>
        Options -Indexes
        AllowOverride All
        Require all granted
    </Directory>

    <FilesMatch \.php$>
        SetHandler "proxy:unix:/run/php/php8.3-fpm.sock|fcgi://localhost/"
    </FilesMatch>
</VirtualHost>
```

The tracked `public_html/.htaccess` checks `-f` and `-d`, so existing static files
and directories bypass PHP. It sends only requests that do not match either to
`index.php`. If `AllowOverride` is disabled, copy the rewrite rules from that
file into the virtual host configuration.

## Nginx

```nginx
server {
    listen 443 ssl;
    server_name mailwatch.example.com;
    root /var/www/mailwatch/public_html;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        try_files $uri =404;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~ /\. {
        deny all;
    }
}
```

The first `try_files` serves an existing static file or directory immediately;
the fallback invokes `index.php` only when no matching public path exists.

## Caddy

```caddyfile
mailwatch.example.com {
    root * /var/www/mailwatch/public_html
    encode zstd gzip

    php_fastcgi unix//run/php/php8.3-fpm.sock
    file_server {
        hide .htaccess js/Chart.js/.htaccess
    }
}
```

Together, `php_fastcgi` and `file_server` serve existing static files directly
and apply the front-controller fallback to `index.php` only when the requested
path is not a public file.

## PHP development server

The front controller can also act as the router script during local development:

```console
php -S 127.0.0.1:8080 -t public_html public_html/index.php
```

In this mode `index.php` returns existing files to the development server, which
then serves them without running the application dispatcher.
