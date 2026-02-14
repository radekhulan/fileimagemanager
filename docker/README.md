# Docker - File Image Manager

Multi-stage Docker image: **Node 22** (frontend build) → **PHP 8.4-FPM + Nginx** (production).

## Quick Start

### PowerShell

```powershell
# Build only
.\docker\build.ps1

# Build and run on port 8080
.\docker\build.ps1 -Run

# Custom tag and port
.\docker\build.ps1 -Run -Tag myfim -Port 9090
```

### Bash / Linux / macOS

```bash
# Build only
./docker/build.sh

# Build and run on port 8080
./docker/build.sh --run

# Custom tag and port
./docker/build.sh --run --tag myfim --port 9090
```

### Docker Compose

```bash
docker compose up -d          # build + start in background
docker compose logs -f        # follow logs
docker compose down           # stop
```

## Structure

```
docker/
├── build.ps1          # Build script (PowerShell)
├── build.sh           # Build script (Bash)
├── nginx.conf         # Nginx configuration
├── supervisord.conf   # Supervisor (PHP-FPM + Nginx)
└── README.md          # This file

Dockerfile             # Multi-stage build definition
docker-compose.yml     # Compose configuration
.dockerignore          # Files excluded from build context
```

## Image Contents

| Layer | Contents |
|---|---|
| OS | Alpine Linux |
| PHP 8.4-FPM | + extensions: gd (freetype, jpeg, webp, avif), mbstring, curl, fileinfo, opcache |
| Nginx | Rewrite rules, security headers (CSP, X-Frame-Options, X-Content-Type-Options) |
| Supervisor | Manages PHP-FPM and Nginx processes |
| Application | PHP backend, compiled Vue frontend assets |

## Ports and Volumes

- **Port 80** (in container) → mapped to host port (default 8080)
- **Volume `media/`** — persistent storage for uploaded files

## Configuration

The config file `config/filemanager.php` can be mounted as a read-only volume:

```bash
docker run -p 8080:80 \
    -v ./config/filemanager.php:/var/www/html/config/filemanager.php:ro \
    -v ./media:/var/www/html/media \
    fileimagemanager
```

### PHP Limits (defaults in image)

| Parameter | Value |
|---|---|
| `upload_max_filesize` | 64 MB |
| `post_max_size` | 64 MB |
| `memory_limit` | 256 MB |
| `max_execution_time` | 120 s |
| `client_max_body_size` (Nginx) | 64 MB |

Override with a custom `.ini` file:

```bash
docker run -p 8080:80 \
    -v ./my-php.ini:/usr/local/etc/php/conf.d/zzz-custom.ini:ro \
    fileimagemanager
```
