#!/usr/bin/env bash
# ============================================
# ФАЙЛ: install.sh
# ВЕРСИЯ: 1.0.1
# ДАТА: 2026-11-18
# @description: Установщик KMS Monitor для Ubuntu 26.04+ (Apache + PHP): пакеты, скачивание с GitHub, папка данных, права
# ============================================
#
# Установка одной командой:
#   curl -fsSL https://raw.githubusercontent.com/AlexandreKz/Monitor-VLMCSD/main/install.sh | sudo bash
#
# Скрипт не перечисляет файлы проекта: он копирует всё дерево репозитория, кроме служебных путей
# (те же, что не трогает обновление из панели). Новые файлы подхватываются автоматически.
# Повторный запуск безопасен: данные и настройки панели не перезаписываются.

set -Eeuo pipefail

REPO="AlexandreKz/Monitor-VLMCSD"
MIN_UBUNTU="26.04"

# Значения по умолчанию (меняются ключами)
WEB_DIR="/var/www/html"
DATA_DIR="/var/lib/vlmc-monitor"
DOMAIN=""
EMAIL=""
RELEASE=""          # тег релиза; пусто = последний релиз
BRANCH=""           # ветка (например main); имеет приоритет над релизом
SOURCE=""           # локальная папка с исходниками вместо скачивания (для проверки без сети)
KMS_LOG=""          # путь к логу vlmcsd: права и ссылка в веб-папке
NO_CRON=0
DRY_RUN=0
SKIP_OS_CHECK=0

# Можно переопределить через окружение (для проверки установщика)
WEB_USER="${WEB_USER:-www-data}"
APACHE_ETC="${APACHE_ETC:-/etc/apache2}"
CRON_DIR="${CRON_DIR:-/etc/cron.d}"
LOG_FILE="${LOG_FILE:-/var/log/kms-monitor-install.log}"

# Пути, которые не копируются из репозитория (как в обновлении из панели)
EXCLUDES=(./.git ./.github ./.gitignore ./.gitattributes ./screen ./pic ./README.md ./LICENSE ./install.sh \
          ./backups ./cache ./vlmcconf/cache ./vlmcconf/backups ./vlmcconf/data_dir.local.php \
          ./vlmcconf/users.json ./vlmcconf/vlmcconf_config.json)

usage() {
    cat <<'EOF'
KMS Monitor — установщик (Ubuntu 26.04 и новее, Apache + PHP)

Использование: sudo bash install.sh [ключи]

  --dir ПУТЬ         куда ставить панель (по умолчанию /var/www/html)
  --data-dir ПУТЬ    папка данных вне веб-папки (по умолчанию /var/lib/vlmc-monitor)
  --domain ИМЯ       создать виртуальный хост и получить сертификат Let's Encrypt
  --email АДРЕС      email для Let's Encrypt (без него регистрация без email)
  --release ТЕГ      поставить конкретный релиз (например 6.0.0); по умолчанию последний
  --branch ВЕТКА     поставить ветку (например main, ранний доступ)
  --kms-log ПУТЬ     лог vlmcsd: выставить права и создать ссылку vlmcsd.log в веб-папке
  --no-cron          не создавать задание проверки обновлений по расписанию
  --dry-run          только показать, что будет сделано
  -h, --help         эта справка

KMS-сервер vlmcsd скрипт не устанавливает.
EOF
}

# ---------- вывод ----------
if [ -t 1 ]; then C_OK=$'\e[32m'; C_ERR=$'\e[31m'; C_DIM=$'\e[2m'; C_OFF=$'\e[0m'; else C_OK=""; C_ERR=""; C_DIM=""; C_OFF=""; fi
step() { printf '%s==>%s %s\n' "$C_OK" "$C_OFF" "$*"; }
info() { printf '    %s\n' "$*"; }
warn() { printf '%s!%s %s\n' "$C_ERR" "$C_OFF" "$*" >&2; }
die()  { printf '%sОшибка:%s %s\n' "$C_ERR" "$C_OFF" "$*" >&2; exit 1; }

run() {
    if [ "$DRY_RUN" = 1 ]; then printf '%s[dry-run]%s %s\n' "$C_DIM" "$C_OFF" "$*"; return 0; fi
    "$@"
}

# ---------- разбор ключей ----------
while [ $# -gt 0 ]; do
    case "$1" in
        --dir)        WEB_DIR="${2:?нужен путь}"; shift 2 ;;
        --data-dir)   DATA_DIR="${2:?нужен путь}"; shift 2 ;;
        --domain)     DOMAIN="${2:?нужно имя}"; shift 2 ;;
        --email)      EMAIL="${2:?нужен адрес}"; shift 2 ;;
        --release)    RELEASE="${2:?нужен тег}"; shift 2 ;;
        --branch)     BRANCH="${2:?нужна ветка}"; shift 2 ;;
        --source)     SOURCE="${2:?нужен путь}"; shift 2 ;;
        --kms-log)    KMS_LOG="${2:?нужен путь}"; shift 2 ;;
        --no-cron)    NO_CRON=1; shift ;;
        --dry-run)    DRY_RUN=1; shift ;;
        --skip-os-check) SKIP_OS_CHECK=1; shift ;;
        -h|--help)    usage; exit 0 ;;
        *)            usage >&2; die "неизвестный ключ: $1" ;;
    esac
done

case "$WEB_DIR" in /*) ;; *) die "--dir должен быть абсолютным путём" ;; esac
case "$DATA_DIR" in /*) ;; *) die "--data-dir должен быть абсолютным путём" ;; esac
WEB_DIR="${WEB_DIR%/}"; DATA_DIR="${DATA_DIR%/}"
[ -n "$WEB_DIR" ] || die "--dir не может быть корнем /"
if [ -n "$DOMAIN" ] && ! [[ "$DOMAIN" =~ ^[A-Za-z0-9]([A-Za-z0-9.-]*[A-Za-z0-9])?$ ]]; then die "некорректное имя домена: $DOMAIN"; fi
if [ -n "$RELEASE" ] && ! [[ "$RELEASE" =~ ^[A-Za-z0-9._-]+$ ]]; then die "некорректный тег: $RELEASE"; fi
if [ -n "$BRANCH" ] && ! [[ "$BRANCH" =~ ^[A-Za-z0-9._/-]+$ ]]; then die "некорректное имя ветки: $BRANCH"; fi

# ---------- журнал установки ----------
if [ "$DRY_RUN" = 0 ]; then
    mkdir -p "$(dirname "$LOG_FILE")" 2>/dev/null || true
    exec > >(tee -a "$LOG_FILE") 2>&1
fi
trap 'warn "Установка прервана (строка $LINENO). Подробности: $LOG_FILE"' ERR

# ---------- проверки ----------
step "Проверка системы"
if [ "$DRY_RUN" = 0 ] && [ "$(id -u)" -ne 0 ]; then die "запустите от root: sudo bash install.sh"; fi

if [ "$SKIP_OS_CHECK" = 0 ]; then
    [ -r /etc/os-release ] || die "не найден /etc/os-release"
    # shellcheck disable=SC1091
    . /etc/os-release
    [ "${ID:-}" = "ubuntu" ] || die "поддерживается только Ubuntu (найдено: ${PRETTY_NAME:-неизвестно})"
    if [ "$(printf '%s\n%s\n' "$MIN_UBUNTU" "${VERSION_ID:-0}" | sort -V | head -n1)" != "$MIN_UBUNTU" ]; then
        die "нужна Ubuntu $MIN_UBUNTU или новее (найдено: ${VERSION_ID:-?})"
    fi
    info "Ubuntu ${VERSION_ID} — подходит"
else
    warn "проверка версии ОС пропущена (--skip-os-check): конфигурация не поддерживается"
fi

# порт 80 не должен быть занят чужим веб-сервером
if command -v ss >/dev/null 2>&1; then
    owner="$(ss -ltnpH 'sport = :80' 2>/dev/null | head -n1 || true)"
    if [ -n "$owner" ] && ! printf '%s' "$owner" | grep -q 'apache2'; then
        die "порт 80 занят другим сервисом: ${owner}. Остановите его или используйте Apache."
    fi
fi

# ---------- пакеты ----------
step "Установка пакетов (Apache, PHP)"
PKGS=(apache2 php php-curl php-mbstring libapache2-mod-php curl ca-certificates tar)
if [ -n "$DOMAIN" ]; then PKGS+=(certbot python3-certbot-apache); fi
export DEBIAN_FRONTEND=noninteractive
run apt-get update -y
run apt-get install -y "${PKGS[@]}"

# ---------- загрузка исходников ----------
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
SRC="$TMP/src"
mkdir -p "$SRC"

step "Получение файлов панели"
if [ -n "$SOURCE" ]; then
    [ -d "$SOURCE" ] || die "--source: папка не найдена: $SOURCE"
    info "локальный источник: $SOURCE"
    tar -C "$SOURCE" --exclude=./.git -cf - . | tar -C "$SRC" -xf -
    VERSION_LABEL="локальная копия"
else
    if [ -n "$BRANCH" ]; then
        URL="https://github.com/${REPO}/archive/refs/heads/${BRANCH}.tar.gz"
        VERSION_LABEL="ветка ${BRANCH}"
    else
        TAG="$RELEASE"
        if [ -z "$TAG" ]; then
            TAG="$(curl -fsSL "https://api.github.com/repos/${REPO}/releases/latest" 2>/dev/null \
                   | sed -n 's/.*"tag_name"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' | head -n1 || true)"
        fi
        if [ -n "$TAG" ]; then
            URL="https://github.com/${REPO}/archive/refs/tags/${TAG}.tar.gz"
            VERSION_LABEL="релиз ${TAG}"
        else
            warn "последний релиз определить не удалось, берём ветку main"
            URL="https://github.com/${REPO}/archive/refs/heads/main.tar.gz"
            VERSION_LABEL="ветка main"
        fi
    fi
    info "источник: ${VERSION_LABEL}"
    if [ "$DRY_RUN" = 1 ]; then
        printf '%s[dry-run]%s скачать %s\n' "$C_DIM" "$C_OFF" "$URL"
    else
        curl -fsSL "$URL" -o "$TMP/pkg.tar.gz" || die "не удалось скачать $URL"
        tar -xzf "$TMP/pkg.tar.gz" -C "$SRC" --strip-components=1 || die "архив повреждён"
    fi
fi

if [ "$DRY_RUN" = 0 ] && { [ ! -f "$SRC/vlmc.php" ] || [ ! -d "$SRC/vlmcconf" ]; }; then
    die "в источнике нет vlmc.php и vlmcconf/ — это не архив панели"
fi

# ---------- копирование ----------
MODE="установка"
[ -f "$WEB_DIR/vlmcconf/vlmcconf.php" ] && MODE="обновление файлов"
step "Копирование файлов в $WEB_DIR ($MODE)"
run mkdir -p "$WEB_DIR"
if [ "$DRY_RUN" = 0 ]; then
    EXCL_ARGS=()
    for e in "${EXCLUDES[@]}"; do EXCL_ARGS+=("--exclude=$e"); done
    tar -C "$SRC" "${EXCL_ARGS[@]}" -cf - . | tar -C "$WEB_DIR" -xf -
else
    info "[dry-run] копирование всего дерева, кроме: ${EXCLUDES[*]}"
fi

# ---------- папка данных ----------
step "Папка данных: $DATA_DIR"
run install -d -o "$WEB_USER" -g "$WEB_USER" -m 750 "$DATA_DIR"

# ---------- права ----------
step "Права доступа"
if [ "$DRY_RUN" = 0 ]; then
    for p in "$WEB_DIR/vlmc.php" "$WEB_DIR/vlmcconf"; do
        [ -e "$p" ] || continue
        chown -R "$WEB_USER:$WEB_USER" "$p"
        if [ -d "$p" ]; then
            find "$p" -type d -exec chmod 755 {} +
            find "$p" -type f -exec chmod 644 {} +
        else
            chmod 644 "$p"
        fi
    done
else
    info "[dry-run] владелец $WEB_USER, 755 для папок и 644 для файлов (vlmc.php, vlmcconf/)"
fi

# ---------- лог vlmcsd ----------
if [ -n "$KMS_LOG" ]; then
    step "Лог vlmcsd: $KMS_LOG"
    if [ -f "$KMS_LOG" ] || [ "$DRY_RUN" = 1 ]; then
        run chmod 644 "$KMS_LOG"
        run chown "$WEB_USER:$WEB_USER" "$KMS_LOG"
        run ln -sfn "$KMS_LOG" "$WEB_DIR/vlmcsd.log"
    else
        warn "файл $KMS_LOG не найден — пропускаю (путь к логу можно указать в настройках панели)"
    fi
fi

# ---------- Apache ----------
step "Настройка Apache"
run a2enmod rewrite
CONF="$APACHE_ETC/conf-available/kms-monitor.conf"
if [ "$DRY_RUN" = 0 ]; then
    mkdir -p "$APACHE_ETC/conf-available"
    {
        echo "# Создано install.sh (KMS Monitor)"
        echo "<Directory ${WEB_DIR}>"
        echo "    AllowOverride All"
        echo "    Require all granted"
        echo "</Directory>"
        if [ "$DATA_DIR" != "/var/lib/vlmc-monitor" ]; then
            echo "SetEnv VLMC_DATA_DIR ${DATA_DIR}"
        fi
    } > "$CONF"
else
    info "[dry-run] записать $CONF (AllowOverride All для $WEB_DIR)"
fi
run a2enconf kms-monitor

if [ -n "$DOMAIN" ]; then
    VHOST="$APACHE_ETC/sites-available/kms-monitor.conf"
    if [ "$DRY_RUN" = 0 ]; then
        mkdir -p "$APACHE_ETC/sites-available"
        cat > "$VHOST" <<EOF
# Создано install.sh (KMS Monitor)
<VirtualHost *:80>
    ServerName ${DOMAIN}
    DocumentRoot ${WEB_DIR}
    ErrorLog \${APACHE_LOG_DIR}/kms-error.log
    CustomLog \${APACHE_LOG_DIR}/kms-access.log combined
</VirtualHost>
EOF
    else
        info "[dry-run] записать $VHOST для $DOMAIN"
    fi
    run a2ensite kms-monitor
fi

if [ "$DRY_RUN" = 0 ]; then
    command -v apache2ctl >/dev/null 2>&1 && { apache2ctl configtest 2>&1 || die "ошибка в конфигурации Apache (см. вывод выше)"; }
fi
run systemctl enable apache2
run systemctl reload-or-restart apache2

# ---------- SSL ----------
if [ -n "$DOMAIN" ]; then
    step "Сертификат Let's Encrypt для $DOMAIN"
    CB=(certbot --apache -d "$DOMAIN" --non-interactive --agree-tos --redirect)
    if [ -n "$EMAIL" ]; then CB+=(-m "$EMAIL"); else CB+=(--register-unsafely-without-email); fi
    if ! run "${CB[@]}"; then
        warn "сертификат получить не удалось (проверьте, что домен указывает на этот сервер и порт 80 открыт). Панель работает по http."
    fi
fi

# ---------- cron ----------
if [ "$NO_CRON" = 0 ]; then
    step "Проверка обновлений по расписанию"
    if [ "$DRY_RUN" = 0 ]; then
        PHP_BIN="$(command -v php || echo /usr/bin/php)"
        mkdir -p "$CRON_DIR"
        echo "0 */6 * * * ${WEB_USER} ${PHP_BIN} ${WEB_DIR}/vlmcconf/update_check.php >/dev/null 2>&1" > "$CRON_DIR/kms-monitor"
        chmod 644 "$CRON_DIR/kms-monitor"
    else
        info "[dry-run] записать $CRON_DIR/kms-monitor (каждые 6 часов)"
    fi
fi

# ---------- проверка ----------
step "Проверка"
if [ "$DRY_RUN" = 0 ] && command -v curl >/dev/null 2>&1 && [ -z "${VLMC_NO_SELFTEST:-}" ]; then
    code="$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1/vlmcconf/login.php 2>/dev/null || true)"
    if [ "$code" = "200" ]; then info "страница входа отвечает (HTTP 200)"; else warn "страница входа ответила кодом ${code:-нет ответа}: проверьте $APACHE_ETC и журнал Apache"; fi
fi

# ---------- итог ----------
HOST_ADDR="${DOMAIN:-$(hostname -I 2>/dev/null | awk '{print $1}')}"
[ -n "$HOST_ADDR" ] || HOST_ADDR="IP-сервера"
SCHEME="http"; [ -n "$DOMAIN" ] && SCHEME="https"
echo
printf '%sГотово.%s (%s)\n' "$C_OK" "$C_OFF" "${VERSION_LABEL:-dry-run}"
echo "  Главная страница:  ${SCHEME}://${HOST_ADDR}/vlmc.php"
echo "  Панель управления: ${SCHEME}://${HOST_ADDR}/vlmcconf/login.php"
echo "  Первый вход:       root / root — панель потребует сменить пароль"
echo "  Данные панели:     ${DATA_DIR}"
echo "  Журнал установки:  ${LOG_FILE}"
echo "  KMS-сервер vlmcsd скриптом не устанавливается: путь к его логу укажите в «Общие настройки»."
