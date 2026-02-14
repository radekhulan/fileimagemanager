# Docker - File Image Manager

Multi-stage Docker image: **Node 22** (frontend build) → **PHP 8.4-FPM + Nginx** (production).

## Rychlý start

### PowerShell

```powershell
# Jen build
.\docker\build.ps1

# Build a spuštění na portu 8080
.\docker\build.ps1 -Run

# Vlastní tag a port
.\docker\build.ps1 -Run -Tag myfim -Port 9090
```

### Bash / Linux / macOS

```bash
# Jen build
./docker/build.sh

# Build a spuštění na portu 8080
./docker/build.sh --run

# Vlastní tag a port
./docker/build.sh --run --tag myfim --port 9090
```

### Docker Compose

```bash
docker compose up -d          # build + start na pozadí
docker compose logs -f        # sledovat logy
docker compose down           # zastavit
```

## Struktura

```
docker/
├── build.ps1          # Build skript (PowerShell)
├── build.sh           # Build skript (Bash)
├── nginx.conf         # Nginx konfigurace
├── supervisord.conf   # Supervisor (PHP-FPM + Nginx)
└── README.md          # Tento soubor

Dockerfile             # Multi-stage build definice
docker-compose.yml     # Compose konfigurace
.dockerignore          # Vyloučené soubory z kontextu
```

## Co image obsahuje

| Vrstva | Obsah |
|---|---|
| OS | Alpine Linux |
| PHP 8.4-FPM | + extensions: gd (freetype, jpeg, webp, avif), mbstring, curl, fileinfo, opcache |
| Nginx | Rewrite pravidla, security headers (CSP, X-Frame-Options, X-Content-Type-Options) |
| Supervisor | Řídí souběh PHP-FPM a Nginx procesů |
| Aplikace | PHP backend, zkompilované Vue frontend assets |

## Porty a volume

- **Port 80** (v kontejneru) → mapován na hostitelský port (výchozí 8080)
- **Volume `media/`** — persistentní úložiště pro nahrané soubory

## Konfigurace

Konfigurační soubor `config/filemanager.php` je možné připojit jako read-only volume:

```bash
docker run -p 8080:80 \
    -v ./config/filemanager.php:/var/www/html/config/filemanager.php:ro \
    -v fim_media:/var/www/html/media \
    fileimagemanager
```

### PHP limity (výchozí v image)

| Parametr | Hodnota |
|---|---|
| `upload_max_filesize` | 64 MB |
| `post_max_size` | 64 MB |
| `memory_limit` | 256 MB |
| `max_execution_time` | 120 s |
| `client_max_body_size` (Nginx) | 64 MB |

Přepsat lze vlastním `.ini` souborem:

```bash
docker run -p 8080:80 \
    -v ./my-php.ini:/usr/local/etc/php/conf.d/zzz-custom.ini:ro \
    fileimagemanager
```

