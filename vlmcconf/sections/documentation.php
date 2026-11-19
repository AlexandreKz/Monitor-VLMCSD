<?php
// ============================================
// ФАЙЛ: sections/documentation.php
// ВЕРСИЯ: 6.0.3
// ДАТА: 2026-11-19
// @description: Секция "Документация": вкладки «Установка» (инструкция) и «Описание» (разделы и опции), поиск, RU/EN
// ============================================

if (basename($_SERVER['PHP_SELF']) === 'documentation.php') {
    http_response_code(403);
    exit('Access denied');
}

// ============================================
// РУССКАЯ ВЕРСИЯ
// ============================================
$docWebRu = <<<'HTML_WEB_RU'
<div class="doc-content-inner">
    
    <!-- Введение -->
    <div class="section-card">
        <div class="section-title"><span>📌</span> О проекте</div>
        <p><strong>KMS Monitor</strong> — это веб-интерфейс для мониторинга и управления KMS-сервером на базе <strong>vlmcsd</strong>.</p>
        <p>Инструкция разбита на логические этапы. Выполняйте их последовательно.</p>
        <p>Поддерживается <strong>Ubuntu 26.04 и новее</strong> (веб-сервер Apache, PHP 8.5).</p>
    </div>

    <!-- Быстрая установка -->
    <div class="section-card">
        <div class="section-title"><span>⚡</span> Быстрая установка одной командой</div>
        <p>Установщик сам поставит Apache и PHP, скачает панель с GitHub, создаст папку данных, выставит права и настроит Apache. KMS-сервер vlmcsd он не устанавливает: его нужно подготовить по этапам 1–3 ниже.</p>
        <div class="code-block"><pre>curl -fsSL https://raw.githubusercontent.com/AlexandreKz/Monitor-VLMCSD/main/install.sh | sudo bash</pre></div>
        <p>Если хотите сначала посмотреть скрипт:</p>
        <div class="code-block"><pre>curl -fsSLO https://raw.githubusercontent.com/AlexandreKz/Monitor-VLMCSD/main/install.sh
less install.sh
sudo bash install.sh</pre></div>
        <p>Полезные ключи: <code>--domain example.com</code> (виртуальный хост и сертификат Let's Encrypt), <code>--dir</code> (папка установки, по умолчанию /var/www/html), <code>--data-dir</code> (папка данных вне веб-папки, по умолчанию /var/lib/vlmc-monitor), <code>--email</code> (адрес для Let's Encrypt), <code>--release 6.1.0</code> (конкретная версия), <code>--branch main</code> (ранний доступ), <code>--kms-log /var/log/vlmcsd.log</code> (права на лог и ссылка), <code>--no-cron</code> (не создавать задание проверки обновлений), <code>--dry-run</code> (только показать действия). Справка: <code>bash install.sh --help</code>.</p>
        <div class="note">💡 Повторный запуск безопасен: файлы панели обновятся, а настройки, пользователи и данные останутся. После установки перейдите к этапу «Первый вход в панель управления». Если вы ставите вручную, пропустите этот блок и следуйте этапам ниже.</div>
    </div>
    
    <!-- Этап 1: Загрузка и подготовка файлов KMS сервера -->
    <div class="section-card">
        <div class="section-title"><span>1️⃣</span> Загрузка и подготовка файлов KMS сервера</div>
        
        <p>Скачайте архив с бинарными файлами:</p>
        <div class="code-block"><pre><a href="https://www.upload.ee/files/11363704/vlmcsd-1113-2020-03-28-Hotbird64.7z.html" target="_blank">https://www.upload.ee/files/11363704/vlmcsd-1113-2020-03-28-Hotbird64.7z.html</a></pre></div>
        
        <p>Распакуйте архив на вашем компьютере. Внутри вы найдёте следующие папки и файлы:</p>
        <div class="code-block"><pre>📁 etc/
   ├── 📄 vlmcsd.ini      - конфигурационный файл сервера
   └── 📄 vlmcsd.kmd      - файл данных активаций

📁 binaries/Linux/intel/glibc/
   ├── 📄 vlmcsd-x64-glibc - для 64-битных систем
   ├── 📄 vlmcsd-x86-glibc - для 32-битных систем
   └── ... другие версии для ARM и т.д.</pre></div>
        
        <p><strong>Загрузите файлы на сервер (через WinSCP / FileZilla / SCP):</strong></p>
        <div class="code-block"><pre>1. Подключитесь к серверу по SFTP (порт 22)
2. Перейдите в папку /usr/local/vlmcsd/ (создайте её, если нет)
3. Скопируйте файлы из папки etc архива в /usr/local/vlmcsd/:
   - vlmcsd.ini
   - vlmcsd.kmd
4. Скопируйте бинарный файл из папки binaries/Linux/intel/glibc в /usr/local/vlmcsd/:
   - Для 64-битных систем: vlmcsd-x64-glibc
   - Для 32-битных систем: vlmcsd-x86-glibc
5. Переименуйте скопированный бинарный файл в vlmcsd</pre></div>
        
        <div class="note">💡 <strong>Выбор бинарного файла:</strong><br>
        • Для 64-битных систем (стандартные серверы): <code>vlmcsd-x64-glibc</code><br>
        • Для 32-битных систем (старые серверы): <code>vlmcsd-x86-glibc</code><br>
        • Для ARM (Raspberry Pi и т.п.): <code>vlmcsd-armv7l-glibc</code> или <code>vlmcsd-aarch64-glibc</code>
        </div>
    </div>
    
    <!-- Этап 2: Настройка прав и ручной запуск KMS сервера -->
    <div class="section-card">
        <div class="section-title"><span>2️⃣</span> Настройка прав и ручной запуск KMS сервера</div>
        
        <p>После копирования файлов выполните команды на сервере:</p>
        <div class="code-block"><pre># Сделайте бинарный файл исполняемым (без этого сервер не запустится)
sudo chmod +x /usr/local/vlmcsd/vlmcsd

# Запустите сервер вручную для проверки
sudo /usr/local/vlmcsd/vlmcsd -i /usr/local/vlmcsd/vlmcsd.ini -l /var/log/vlmcsd.log</pre></div>
        
        <p>Проверьте, что сервер запустился:</p>
        <div class="code-block"><pre># Проверьте, слушается ли порт 1688
sudo netstat -tlnp | grep 1688

# Посмотрите лог (должны быть строки "Listening on" и "started successfully")
sudo tail -20 /var/log/vlmcsd.log</pre></div>
        
        <div class="success">✅ Успешный запуск показывает в логе:<br>
        <code>Listening on [::]:1688</code><br>
        <code>Listening on 0.0.0.0:1688</code><br>
        <code>vlmcsd started successfully</code>
        </div>
        
        <div class="warning">⚠️ После проверки остановите сервер комбинацией <code>Ctrl+C</code>, чтобы перейти к настройке автозапуска.</div>
    </div>
    
    <!-- Этап 3: Настройка автозапуска KMS сервера -->
    <div class="section-card">
        <div class="section-title"><span>3️⃣</span> Настройка автозапуска (systemd)</div>
        
        <p>Создайте файл сервиса:</p>
        <div class="code-block"><pre>sudo nano /etc/systemd/system/vlmcsd.service</pre></div>
        
        <p>Вставьте содержимое:</p>
        <div class="code-block"><pre>[Unit]
Description=vlmcsd KMS Server
After=network.target

[Service]
Type=simple
ExecStart=/usr/local/vlmcsd/vlmcsd -i /usr/local/vlmcsd/vlmcsd.ini -l /var/log/vlmcsd.log
Restart=no
User=root

[Install]
WantedBy=multi-user.target</pre></div>
        
        <p>Запустите и включите автозапуск:</p>
        <div class="code-block"><pre># Перезагрузить конфигурацию systemd
sudo systemctl daemon-reload

# Включить автозапуск при загрузке системы
sudo systemctl enable vlmcsd

# Запустить сервис сейчас
sudo systemctl start vlmcsd

# Проверить статус
sudo systemctl status vlmcsd</pre></div>
        
        <div class="note">💡 <strong>Примечание:</strong> Если статус показывает <code>activating (auto-restart)</code> — это означает, что сервер работает, но systemd не может определить его состояние. Это нормально для vlmcsd. Проверьте наличие порта 1688 командой <code>sudo netstat -tlnp | grep 1688</code>.</div>
        
        <div class="warning">⚠️ Если сервис не запускается, попробуйте альтернативный способ автозапуска через <code>crontab @reboot</code>:</div>
        <div class="code-block"><pre>sudo crontab -e
# Добавьте строку:
@reboot /usr/local/vlmcsd/vlmcsd -i /usr/local/vlmcsd/vlmcsd.ini -l /var/log/vlmcsd.log</pre></div>
    </div>
    
    <!-- Этап 4: Установка веб-сервера и PHP -->
    <div class="section-card">
        <div class="section-title"><span>4️⃣</span> Установка веб-сервера и PHP</div>
        
        
        
        <p><strong>Для Ubuntu 26.04 LTS (PHP 8.5):</strong></p>
        <div class="code-block"><pre>sudo apt update
sudo apt install apache2 php8.5 php8.5-curl php8.5-common php8.5-mbstring libapache2-mod-php8.5</pre></div>
        
        <div class="note">💡 <strong>Примечание:</strong> Расширения <code>json</code> и <code>session</code> встроены в PHP, отдельно устанавливать не нужно.</div>
        <div class="note">💡 <strong>Для входа через AD/LDAP</strong> нужно расширение <code>ldap</code>: <code>sudo apt install php8.5-ldap</code> и перезапуск Apache. Без него раздел AD/LDAP покажет предупреждение, остальная панель работает. Расширение <code>ftp</code> (для хранилищ FTP/FTPS в Cloud Storage) обычно входит в <code>php8.5-common</code>.</div>
    </div>
    
    <!-- Этап 5: Настройка Apache и включение mod_rewrite -->
    <div class="section-card">
        <div class="section-title"><span>5️⃣</span> Настройка Apache</div>
        
        <p>Включите модуль <code>mod_rewrite</code> (обязательно! Без него будет ошибка 500):</p>
        <div class="code-block"><pre>sudo a2enmod rewrite
sudo systemctl restart apache2</pre></div>
        
        <p>Создайте виртуальный хост:</p>
        <div class="code-block"><pre>sudo nano /etc/apache2/sites-available/kms-monitor.conf</pre></div>
        
        <p>Вставьте содержимое (замените <code>example.com</code> на ваш домен или IP):</p>
        <div class="code-block"><pre>&lt;VirtualHost *:80&gt;
    ServerName example.com
    DocumentRoot /var/www/html
    
    &lt;Directory /var/www/html&gt;
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    &lt;/Directory&gt;
    
    ErrorLog ${APACHE_LOG_DIR}/kms-error.log
    CustomLog ${APACHE_LOG_DIR}/kms-access.log combined
&lt;/VirtualHost&gt;</pre></div>
        
        <p>Активируйте сайт и перезапустите Apache:</p>
        <div class="code-block"><pre>sudo a2ensite kms-monitor.conf
sudo a2dissite 000-default.conf
sudo systemctl reload apache2</pre></div>
    </div>
    
    <!-- Этап 6: Установка веб-оболочки -->
    <div class="section-card">
        <div class="section-title"><span>6️⃣</span> Установка веб-оболочки</div>
        
        <p>Скопируйте файлы проекта на сервер через <strong>WinSCP / FileZilla</strong> в папку <code>/var/www/html/</code>.</p>
        <p>После копирования настройте права доступа:</p>
        <div class="code-block"><pre># Установите владельца (www-data — пользователь Apache)
sudo chown -R www-data:www-data /var/www/html

# Установите права на файлы (644 для файлов, 755 для папок)
sudo find /var/www/html -type f -exec chmod 644 {} \;
sudo find /var/www/html -type d -exec chmod 755 {} \;</pre></div>
        
        <div class="note">💡 Папка <code>vlmcconf/cache</code> создастся автоматически при первом обращении к геолокации. Права на родительскую папку уже установлены.</div>
    </div>
    
    <!-- Этап 7: Настройка доступа к логу KMS сервера -->
    <div class="section-card">
        <div class="section-title"><span>7️⃣</span> Настройка доступа к логу</div>
        
        <p><strong>Важно:</strong> Этот шаг выполняется <strong>ПОСЛЕ</strong> того, как KMS сервер уже запущен (этап 2 или 3).</p>
        
        <div class="code-block"><pre># Установите права на лог-файл (веб-сервер должен иметь возможность читать лог)
sudo chmod 644 /var/log/vlmcsd.log
sudo chown www-data:www-data /var/log/vlmcsd.log

# Создайте символическую ссылку в папке веб-оболочки
sudo ln -s /var/log/vlmcsd.log /var/www/html/vlmcsd.log</pre></div>
        
        <div class="note">💡 Альтернативно, путь к логу можно указать в настройках панели управления после первого входа (в разделе «Общие настройки» → «Файл лога»).</div>
    </div>
    
    <!-- Этап 8: Настройка SSL/HTTPS (необязательно) -->
    <div class="section-card">
        <div class="section-title"><span>8️⃣</span> Настройка SSL/HTTPS (необязательно)</div>
        
        <p>Для работы сайта по HTTPS можно использовать бесплатный сертификат от Let's Encrypt.</p>
        
        <h4>Установка Certbot:</h4>
        <div class="code-block"><pre># Для Ubuntu 26.04
sudo apt update
sudo apt install certbot python3-certbot-apache</pre></div>
        
        <h4>Получение сертификата:</h4>
        <div class="code-block"><pre># Замените example.com на ваш домен
sudo certbot --apache -d example.com</pre></div>
        
        <h4>Ручная настройка SSL (если Certbot не подходит):</h4>
        <p>Создайте самоподписанный сертификат:</p>
        <div class="code-block"><pre>sudo openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout /etc/ssl/private/example.com.key \
  -out /etc/ssl/certs/example.com.crt</pre></div>
        
        <p>Добавьте конфигурацию для HTTPS в <code>/etc/apache2/sites-available/kms-monitor.conf</code>:</p>
        <div class="code-block"><pre># Включите модуль SSL
sudo a2enmod ssl

# Отредактируйте конфиг
sudo nano /etc/apache2/sites-available/kms-monitor.conf</pre></div>
        
        <p>Замените содержимое на:</p>
        <div class="code-block"><pre>&lt;VirtualHost *:443&gt;
    ServerName example.com
    DocumentRoot /var/www/html
    
    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/example.com.crt
    SSLCertificateKeyFile /etc/ssl/private/example.com.key
    
    &lt;Directory /var/www/html&gt;
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    &lt;/Directory&gt;
    
    ErrorLog ${APACHE_LOG_DIR}/kms-error.log
    CustomLog ${APACHE_LOG_DIR}/kms-access.log combined
&lt;/VirtualHost&gt;

# Редирект с HTTP на HTTPS
&lt;VirtualHost *:80&gt;
    ServerName example.com
    Redirect permanent / https://example.com/
&lt;/VirtualHost&gt;</pre></div>
        
        <p>Перезапустите Apache:</p>
        <div class="code-block"><pre>sudo systemctl reload apache2</pre></div>
    </div>
    
    <!-- Этап 9: Первый вход -->
    <div class="section-card">
        <div class="section-title"><span>9️⃣</span> Первый вход в панель управления</div>
        
        <ol>
            <li>Откройте браузер и перейдите по адресу: <code>http://example.com/vlmcconf/login.php</code> (или <code>https://...</code> если настроили SSL)</li>
            <li>Введите учётные данные по умолчанию:
                <ul>
                    <li><strong>Логин:</strong> <code>root</code></li>
                    <li><strong>Пароль:</strong> <code>root</code></li>
                </ul>
            </li>
            <li>Система <strong>обязательно предложит сменить пароль</strong> при первом входе.</li>
            <li>Установите новый пароль (требования: минимум 8 символов, заглавные и строчные буквы, цифры, спецсимволы).</li>
            <li>После смены пароля откроется панель управления.</li>
        </ol>
        
        <div class="success">🎉 <strong>Готово!</strong> Теперь можно настраивать группы, добавлять устройства и управлять пользователями.</div>
    </div>
    
    <!-- Возможные ошибки -->
    <div class="section-card">
        <div class="section-title"><span>⚠️</span> Возможные ошибки и их решение</div>
        
        <h4>❌ Ошибка 500 Internal Server Error</h4>
        <p><strong>Причина:</strong> Не включён модуль <code>mod_rewrite</code>.</p>
        <div class="code-block"><pre>sudo a2enmod rewrite
sudo systemctl restart apache2</pre></div>
        
        <h4>❌ Permission denied при запуске KMS сервера</h4>
        <p><strong>Причина:</strong> Бинарный файл не имеет прав на выполнение.</p>
        <div class="code-block"><pre>sudo chmod +x /usr/local/vlmcsd/vlmcsd</pre></div>
        
        <h4>❌ Файл лога не найден или не читается</h4>
        <p><strong>Причина:</strong> Лог-файл не создан или неправильные права.</p>
        <div class="code-block"><pre>sudo touch /var/log/vlmcsd.log
sudo chmod 644 /var/log/vlmcsd.log
sudo chown www-data:www-data /var/log/vlmcsd.log
sudo ln -s /var/log/vlmcsd.log /var/www/html/vlmcsd.log</pre></div>
        
        <h4>❌ Не открывается страница /vlmcconf/login.php</h4>
        <p><strong>Причина:</strong> Неправильные права на файлы или ошибка в .htaccess.</p>
        <div class="code-block"><pre>sudo chown -R www-data:www-data /var/www/html
sudo find /var/www/html -type f -exec chmod 644 {} \;
sudo find /var/www/html -type d -exec chmod 755 {} \;</pre></div>
        
        <h4>❌ Сервис vlmcsd не запускается через systemd (Ubuntu 26.04)</h4>
        <p><strong>Причина:</strong> Иногда systemd не может корректно определить состояние vlmcsd.</p>
        <p><strong>Решение 1:</strong> Используйте автозапуск через crontab:</p>
        <div class="code-block"><pre>sudo crontab -e
# Добавьте строку:
@reboot /usr/local/vlmcsd/vlmcsd -i /usr/local/vlmcsd/vlmcsd.ini -l /var/log/vlmcsd.log</pre></div>
        <p><strong>Решение 2:</strong> Проверьте, что сервер всё равно работает:</p>
        <div class="code-block"><pre>sudo netstat -tlnp | grep 1688</pre></div>
        
        <h4>❌ Не загружаются флаги стран в блоке "Подозрительные IP"</h4>
        <p><strong>Причина:</strong> Нет интернета или заблокирован CDN.</p>
        <p>Проверьте подключение к интернету. Флаги загружаются с CDN <code>cdn.jsdelivr.net</code>.</p>
        
        <h4>❌ Ошибка 404 при открытии vlmc.php</h4>
        <p><strong>Причина:</strong> Неправильное имя файла. Правильный адрес: <code>/vlmc.php</code> (без буквы 's').</p>
    </div>
    
    <!-- Хранение данных -->
    <div class="section-card">
        <div class="section-title"><span>🗄️</span> Хранение данных панели</div>
        <p>Файлы <code>users.json</code> (пользователи и хеши паролей) и <code>vlmcconf_config.json</code> (настройки панели) хранятся <strong>вне веб-папки</strong>, чтобы их нельзя было скачать по прямой ссылке на любом веб-сервере (Apache, nginx, IIS).</p>
        <p><strong>Расположение по умолчанию:</strong></p>
        <ul>
            <li>Ubuntu: <code>/var/lib/vlmc-monitor/</code></li>
            <li>Windows: <code>%PROGRAMDATA%\vlmc-monitor\</code></li>
        </ul>
        <p>Создайте папку и дайте права веб-серверу (Ubuntu):</p>
        <div class="code-block"><pre>sudo install -d -o www-data -g www-data -m 750 /var/lib/vlmc-monitor</pre></div>
        <p>Если папки нет или нет прав на запись, панель продолжает работать по-старому (файлы в <code>vlmcconf/</code>) и показывает предупреждение с готовой командой.</p>
        <p><strong>Свой путь:</strong> переменная окружения <code>VLMC_DATA_DIR</code> (Apache: <code>SetEnv VLMC_DATA_DIR /путь</code>; PHP-FPM: <code>env[VLMC_DATA_DIR] = /путь</code>) либо файл <code>vlmcconf/data_dir.local.php</code> с содержимым <code>&lt;?php return '/путь/к/папке';</code>. Этого файла нет в репозитории, поэтому обновления панели его не затрагивают.</p>
    </div>
    
    <!-- SSL -->
    <div class="section-card">
        <div class="section-title"><span>🔒</span> Проверка SSL-сертификатов</div>
        <p>Панель обращается к GitHub (обновление) и к сервисам геолокации по <code>https://</code> и <strong>всегда проверяет сертификат сервера</strong>. Без проверки любой, кто оказался между панелью и сервером, мог бы подсунуть чужой код при обновлении. Для проверки PHP нужен список доверенных сертификатов (CA).</p>
        <p><strong>Ubuntu:</strong> список уже есть в системе. Если проверка не проходит, обновите его:</p>
        <div class="code-block"><pre>sudo apt update &amp;&amp; sudo apt install --reinstall ca-certificates &amp;&amp; sudo update-ca-certificates</pre></div>
        <p><strong>Windows (настраивается один раз):</strong></p>
        <ol>
            <li>Скачайте файл <code>https://curl.se/ca/cacert.pem</code> (официальный список сертификатов проекта curl) и сохраните его как <code>C:\php\extras\ssl\cacert.pem</code>. Создайте папки, если их нет.</li>
            <li>Найдите <code>php.ini</code>: команда <code>php --ini</code> покажет строку «Loaded Configuration File». Если она пуста, скопируйте <code>php.ini-development</code> в <code>php.ini</code> в папке PHP.</li>
            <li>В <code>php.ini</code> найдите строки <code>curl.cainfo</code> и <code>openssl.cafile</code>, уберите <code>;</code> в начале и впишите путь:</li>
        </ol>
        <div class="code-block"><pre>curl.cainfo = "C:\php\extras\ssl\cacert.pem"
openssl.cafile = "C:\php\extras\ssl\cacert.pem"</pre></div>
        <ol start="4">
            <li>Убедитесь, что включены расширения (без <code>;</code> в начале): <code>extension=curl</code> и <code>extension=openssl</code>.</li>
            <li>Перезапустите PHP (остановите и снова запустите <code>php -S</code> либо перезапустите Apache/IIS).</li>
        </ol>
        <p><strong>Проверка:</strong> «Инструменты → Обновление → 🌐 Проверить GitHub» должна ответить «GitHub доступен, проверка SSL пройдена». Пока сертификаты не подключены, обновление и определение стран по IP работать не будут: в интерфейсе появится эта же подсказка.</p>
    </div>
    
</div>
HTML_WEB_RU;

// ============================================
// АНГЛИЙСКАЯ ВЕРСИЯ
// ============================================
$docWebEn = <<<'HTML_WEB_EN'
<div class="doc-content-inner">
    
    <!-- Introduction -->
    <div class="section-card">
        <div class="section-title"><span>📌</span> About the project</div>
        <p><strong>KMS Monitor</strong> is a web interface for monitoring and managing a KMS server based on <strong>vlmcsd</strong>.</p>
        <p>The instructions are divided into logical steps. Follow them sequentially.</p>
        <p>Supported: <strong>Ubuntu 26.04 and newer</strong> (Apache web server, PHP 8.5).</p>
    </div>

    <!-- Quick install -->
    <div class="section-card">
        <div class="section-title"><span>⚡</span> One-command quick install</div>
        <p>The installer sets up Apache and PHP, downloads the panel from GitHub, creates the data folder, sets permissions and configures Apache. It does not install the vlmcsd KMS server: prepare it using steps 1–3 below.</p>
        <div class="code-block"><pre>curl -fsSL https://raw.githubusercontent.com/AlexandreKz/Monitor-VLMCSD/main/install.sh | sudo bash</pre></div>
        <p>To review the script first:</p>
        <div class="code-block"><pre>curl -fsSLO https://raw.githubusercontent.com/AlexandreKz/Monitor-VLMCSD/main/install.sh
less install.sh
sudo bash install.sh</pre></div>
        <p>Useful options: <code>--domain example.com</code> (virtual host and Let's Encrypt certificate), <code>--dir</code> (install folder, default /var/www/html), <code>--data-dir</code> (data folder outside the web root, default /var/lib/vlmc-monitor), <code>--email</code> (address for Let's Encrypt), <code>--release 6.1.0</code> (specific version), <code>--branch main</code> (early access), <code>--kms-log /var/log/vlmcsd.log</code> (log permissions and link), <code>--no-cron</code> (do not create the scheduled update check), <code>--dry-run</code> (only show actions). Help: <code>bash install.sh --help</code>.</p>
        <div class="note">💡 Running it again is safe: panel files are updated while settings, users and data are kept. After installation go to the “First login to control panel” step. If you install manually, skip this block and follow the steps below.</div>
    </div>
    
    <!-- Step 1: Download and prepare KMS server files -->
    <div class="section-card">
        <div class="section-title"><span>1️⃣</span> Download and prepare KMS server files</div>
        
        <p>Download the archive with binary files:</p>
        <div class="code-block"><pre><a href="https://www.upload.ee/files/11363704/vlmcsd-1113-2020-03-28-Hotbird64.7z.html" target="_blank">https://www.upload.ee/files/11363704/vlmcsd-1113-2020-03-28-Hotbird64.7z.html</a></pre></div>
        
        <p>Extract the archive on your computer. Inside you will find the following folders and files:</p>
        <div class="code-block"><pre>📁 etc/
   ├── 📄 vlmcsd.ini      - server configuration file
   └── 📄 vlmcsd.kmd      - activation data file

📁 binaries/Linux/intel/glibc/
   ├── 📄 vlmcsd-x64-glibc - for 64-bit systems
   ├── 📄 vlmcsd-x86-glibc - for 32-bit systems
   └── ... other versions for ARM, etc.</pre></div>
        
        <p><strong>Upload files to the server (via WinSCP / FileZilla / SCP):</strong></p>
        <div class="code-block"><pre>1. Connect to the server via SFTP (port 22)
2. Navigate to /usr/local/vlmcsd/ (create it if doesn't exist)
3. Copy files from the etc folder of the archive to /usr/local/vlmcsd/:
   - vlmcsd.ini
   - vlmcsd.kmd
4. Copy the binary file from binaries/Linux/intel/glibc to /usr/local/vlmcsd/:
   - For 64-bit systems: vlmcsd-x64-glibc
   - For 32-bit systems: vlmcsd-x86-glibc
5. Rename the copied binary file to vlmcsd</pre></div>
        
        <div class="note">💡 <strong>Choosing the binary:</strong><br>
        • For 64-bit systems (standard servers): <code>vlmcsd-x64-glibc</code><br>
        • For 32-bit systems (older servers): <code>vlmcsd-x86-glibc</code><br>
        • For ARM (Raspberry Pi, etc.): <code>vlmcsd-armv7l-glibc</code> or <code>vlmcsd-aarch64-glibc</code>
        </div>
    </div>
    
    <!-- Step 2: Set permissions and manual KMS server startup -->
    <div class="section-card">
        <div class="section-title"><span>2️⃣</span> Set permissions and manual KMS server startup</div>
        
        <p>After copying the files, run the following commands on the server:</p>
        <div class="code-block"><pre># Make the binary executable (the server won't start without this)
sudo chmod +x /usr/local/vlmcsd/vlmcsd

# Start the server manually for testing
sudo /usr/local/vlmcsd/vlmcsd -i /usr/local/vlmcsd/vlmcsd.ini -l /var/log/vlmcsd.log</pre></div>
        
        <p>Check that the server started:</p>
        <div class="code-block"><pre># Check if port 1688 is listening
sudo netstat -tlnp | grep 1688

# View the log (should contain "Listening on" and "started successfully")
sudo tail -20 /var/log/vlmcsd.log</pre></div>
        
        <div class="success">✅ Successful startup shows in log:<br>
        <code>Listening on [::]:1688</code><br>
        <code>Listening on 0.0.0.0:1688</code><br>
        <code>vlmcsd started successfully</code>
        </div>
        
        <div class="warning">⚠️ After testing, stop the server with <code>Ctrl+C</code> before proceeding to autostart configuration.</div>
    </div>
    
    <!-- Step 3: Configure KMS server autostart -->
    <div class="section-card">
        <div class="section-title"><span>3️⃣</span> Configure KMS server autostart (systemd)</div>
        
        <p>Create the service file:</p>
        <div class="code-block"><pre>sudo nano /etc/systemd/system/vlmcsd.service</pre></div>
        
        <p>Insert the following content:</p>
        <div class="code-block"><pre>[Unit]
Description=vlmcsd KMS Server
After=network.target

[Service]
Type=simple
ExecStart=/usr/local/vlmcsd/vlmcsd -i /usr/local/vlmcsd/vlmcsd.ini -l /var/log/vlmcsd.log
Restart=no
User=root

[Install]
WantedBy=multi-user.target</pre></div>
        
        <p>Start and enable autostart:</p>
        <div class="code-block"><pre># Reload systemd configuration
sudo systemctl daemon-reload

# Enable autostart on system boot
sudo systemctl enable vlmcsd

# Start the service now
sudo systemctl start vlmcsd

# Check status
sudo systemctl status vlmcsd</pre></div>
        
        <div class="note">💡 <strong>Note:</strong> If the status shows <code>activating (auto-restart)</code>, this means the server is running but systemd cannot determine its state. This is normal for vlmcsd. Check if port 1688 is listening with <code>sudo netstat -tlnp | grep 1688</code>.</div>
        
        <div class="warning">⚠️ If the service doesn't start, try an alternative autostart method via <code>crontab @reboot</code>:</div>
        <div class="code-block"><pre>sudo crontab -e
# Add the line:
@reboot /usr/local/vlmcsd/vlmcsd -i /usr/local/vlmcsd/vlmcsd.ini -l /var/log/vlmcsd.log</pre></div>
    </div>
    
    <!-- Step 4: Install web server and PHP -->
    <div class="section-card">
        <div class="section-title"><span>4️⃣</span> Install web server and PHP</div>
        
        
        
        <p><strong>For Ubuntu 26.04 LTS (PHP 8.5):</strong></p>
        <div class="code-block"><pre>sudo apt update
sudo apt install apache2 php8.5 php8.5-curl php8.5-common php8.5-mbstring libapache2-mod-php8.5</pre></div>
        
        <div class="note">💡 <strong>Note:</strong> The <code>json</code> and <code>session</code> extensions are built into PHP, no separate installation required.</div>
        <div class="note">💡 <strong>For AD/LDAP sign-in</strong> the <code>ldap</code> extension is required: <code>sudo apt install php8.5-ldap</code> and restart Apache. Without it the AD/LDAP section shows a warning; the rest of the panel works. The <code>ftp</code> extension (for FTP/FTPS storages in Cloud Storage) is usually part of <code>php8.5-common</code>.</div>
    </div>
    
    <!-- Step 5: Configure Apache and enable mod_rewrite -->
    <div class="section-card">
        <div class="section-title"><span>5️⃣</span> Configure Apache</div>
        
        <p>Enable the <code>mod_rewrite</code> module (required! Without it you'll get error 500):</p>
        <div class="code-block"><pre>sudo a2enmod rewrite
sudo systemctl restart apache2</pre></div>
        
        <p>Create a virtual host:</p>
        <div class="code-block"><pre>sudo nano /etc/apache2/sites-available/kms-monitor.conf</pre></div>
        
        <p>Insert the following content (replace <code>example.com</code> with your domain or IP):</p>
        <div class="code-block"><pre>&lt;VirtualHost *:80&gt;
    ServerName example.com
    DocumentRoot /var/www/html
    
    &lt;Directory /var/www/html&gt;
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    &lt;/Directory&gt;
    
    ErrorLog ${APACHE_LOG_DIR}/kms-error.log
    CustomLog ${APACHE_LOG_DIR}/kms-access.log combined
&lt;/VirtualHost&gt;</pre></div>
        
        <p>Enable the site and restart Apache:</p>
        <div class="code-block"><pre>sudo a2ensite kms-monitor.conf
sudo a2dissite 000-default.conf
sudo systemctl reload apache2</pre></div>
    </div>
    
    <!-- Step 6: Install web interface -->
    <div class="section-card">
        <div class="section-title"><span>6️⃣</span> Install web interface</div>
        
        <p>Copy the project files to the server using <strong>WinSCP / FileZilla</strong> to the <code>/var/www/html/</code> folder.</p>
        <p>After copying, set the permissions:</p>
        <div class="code-block"><pre># Set owner (www-data is the Apache user)
sudo chown -R www-data:www-data /var/www/html

# Set file permissions (644 for files, 755 for directories)
sudo find /var/www/html -type f -exec chmod 644 {} \;
sudo find /var/www/html -type d -exec chmod 755 {} \;</pre></div>
        
        <div class="note">💡 The <code>vlmcconf/cache</code> folder will be created automatically on the first geolocation request. Parent folder permissions are already set.</div>
    </div>
    
    <!-- Step 7: Configure log access -->
    <div class="section-card">
        <div class="section-title"><span>7️⃣</span> Configure log access</div>
        
        <p><strong>Important:</strong> This step must be performed <strong>AFTER</strong> the KMS server has been started (step 2 or 3).</p>
        
        <div class="code-block"><pre># Set log file permissions (web server must be able to read the log)
sudo chmod 644 /var/log/vlmcsd.log
sudo chown www-data:www-data /var/log/vlmcsd.log

# Create symbolic link in web directory
sudo ln -s /var/log/vlmcsd.log /var/www/html/vlmcsd.log</pre></div>
        
        <div class="note">💡 Alternatively, the log path can be set in the control panel settings after first login (in "General Settings" → "Log file path").</div>
    </div>
    
    <!-- Step 8: SSL/HTTPS configuration (optional) -->
    <div class="section-card">
        <div class="section-title"><span>8️⃣</span> SSL/HTTPS configuration (optional)</div>
        
        <p>To run the site over HTTPS, you can use a free Let's Encrypt certificate.</p>
        
        <h4>Install Certbot:</h4>
        <div class="code-block"><pre># For Ubuntu 26.04
sudo apt update
sudo apt install certbot python3-certbot-apache</pre></div>
        
        <h4>Obtain a certificate:</h4>
        <div class="code-block"><pre># Replace example.com with your domain
sudo certbot --apache -d example.com</pre></div>
        
        <h4>Manual SSL configuration (if Certbot doesn't work):</h4>
        <p>Create a self-signed certificate:</p>
        <div class="code-block"><pre>sudo openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout /etc/ssl/private/example.com.key \
  -out /etc/ssl/certs/example.com.crt</pre></div>
        
        <p>Add HTTPS configuration to <code>/etc/apache2/sites-available/kms-monitor.conf</code>:</p>
        <div class="code-block"><pre># Enable SSL module
sudo a2enmod ssl

# Edit the config
sudo nano /etc/apache2/sites-available/kms-monitor.conf</pre></div>
        
        <p>Replace the content with:</p>
        <div class="code-block"><pre>&lt;VirtualHost *:443&gt;
    ServerName example.com
    DocumentRoot /var/www/html
    
    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/example.com.crt
    SSLCertificateKeyFile /etc/ssl/private/example.com.key
    
    &lt;Directory /var/www/html&gt;
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    &lt;/Directory&gt;
    
    ErrorLog ${APACHE_LOG_DIR}/kms-error.log
    CustomLog ${APACHE_LOG_DIR}/kms-access.log combined
&lt;/VirtualHost&gt;

# Redirect HTTP to HTTPS
&lt;VirtualHost *:80&gt;
    ServerName example.com
    Redirect permanent / https://example.com/
&lt;/VirtualHost&gt;</pre></div>
        
        <p>Restart Apache:</p>
        <div class="code-block"><pre>sudo systemctl reload apache2</pre></div>
    </div>
    
    <!-- Step 9: First login -->
    <div class="section-card">
        <div class="section-title"><span>9️⃣</span> First login to control panel</div>
        
        <ol>
            <li>Open your browser and go to: <code>http://example.com/vlmcconf/login.php</code> (or <code>https://...</code> if you configured SSL)</li>
            <li>Enter default credentials:
                <ul>
                    <li><strong>Username:</strong> <code>root</code></li>
                    <li><strong>Password:</strong> <code>root</code></li>
                </ul>
            </li>
            <li>The system <strong>will require a password change</strong> on first login.</li>
            <li>Set a new password (requirements: minimum 8 characters, uppercase and lowercase letters, digits, special characters).</li>
            <li>After changing the password, the control panel will open.</li>
        </ol>
        
        <div class="success">🎉 <strong>Done!</strong> Now you can configure groups, add devices, and manage users.</div>
    </div>
    
    <!-- Troubleshooting -->
    <div class="section-card">
        <div class="section-title"><span>⚠️</span> Troubleshooting</div>
        
        <h4>❌ Error 500 Internal Server Error</h4>
        <p><strong>Cause:</strong> <code>mod_rewrite</code> module is not enabled.</p>
        <div class="code-block"><pre>sudo a2enmod rewrite
sudo systemctl restart apache2</pre></div>
        
        <h4>❌ Permission denied when starting KMS server</h4>
        <p><strong>Cause:</strong> Binary file does not have execute permissions.</p>
        <div class="code-block"><pre>sudo chmod +x /usr/local/vlmcsd/vlmcsd</pre></div>
        
        <h4>❌ Log file not found or not readable</h4>
        <p><strong>Cause:</strong> Log file does not exist or has incorrect permissions.</p>
        <div class="code-block"><pre>sudo touch /var/log/vlmcsd.log
sudo chmod 644 /var/log/vlmcsd.log
sudo chown www-data:www-data /var/log/vlmcsd.log
sudo ln -s /var/log/vlmcsd.log /var/www/html/vlmcsd.log</pre></div>
        
        <h4>❌ Page /vlmcconf/login.php not opening</h4>
        <p><strong>Cause:</strong> Incorrect file permissions or .htaccess error.</p>
        <div class="code-block"><pre>sudo chown -R www-data:www-data /var/www/html
sudo find /var/www/html -type f -exec chmod 644 {} \;
sudo find /var/www/html -type d -exec chmod 755 {} \;</pre></div>
        
        <h4>❌ vlmcsd service won't start via systemd (Ubuntu 26.04)</h4>
        <p><strong>Cause:</strong> Sometimes systemd cannot correctly determine vlmcsd's state.</p>
        <p><strong>Solution 1:</strong> Use crontab for autostart:</p>
        <div class="code-block"><pre>sudo crontab -e
# Add the line:
@reboot /usr/local/vlmcsd/vlmcsd -i /usr/local/vlmcsd/vlmcsd.ini -l /var/log/vlmcsd.log</pre></div>
        <p><strong>Solution 2:</strong> Check if the server is still running:</p>
        <div class="code-block"><pre>sudo netstat -tlnp | grep 1688</pre></div>
        
        <h4>❌ Country flags not loading in "Suspicious IPs" block</h4>
        <p><strong>Cause:</strong> No internet connection or CDN blocked.</p>
        <p>Check your internet connection. Flags are loaded from CDN <code>cdn.jsdelivr.net</code>.</p>
        
        <h4>❌ Error 404 when opening vlmc.php</h4>
        <p><strong>Cause:</strong> Incorrect filename. The correct URL is: <code>/vlmc.php</code> (without the letter 's').</p>
    </div>
    
    <!-- Data storage -->
    <div class="section-card">
        <div class="section-title"><span>🗄️</span> Panel data storage</div>
        <p>The files <code>users.json</code> (users and password hashes) and <code>vlmcconf_config.json</code> (panel settings) are stored <strong>outside the web root</strong>, so they cannot be downloaded by a direct link on any web server (Apache, nginx, IIS).</p>
        <p><strong>Default location:</strong></p>
        <ul>
            <li>Ubuntu: <code>/var/lib/vlmc-monitor/</code></li>
            <li>Windows: <code>%PROGRAMDATA%\vlmc-monitor\</code></li>
        </ul>
        <p>Create the folder and give the web server access (Ubuntu):</p>
        <div class="code-block"><pre>sudo install -d -o www-data -g www-data -m 750 /var/lib/vlmc-monitor</pre></div>
        <p>If the folder does not exist or is not writable, the panel keeps working the old way (files in <code>vlmcconf/</code>) and shows a warning with a ready-to-run command.</p>
        <p><strong>Custom path:</strong> the <code>VLMC_DATA_DIR</code> environment variable (Apache: <code>SetEnv VLMC_DATA_DIR /path</code>; PHP-FPM: <code>env[VLMC_DATA_DIR] = /path</code>) or the file <code>vlmcconf/data_dir.local.php</code> containing <code>&lt;?php return '/path/to/folder';</code>. This file is not in the repository, so panel updates do not touch it.</p>
    </div>
    
    <!-- SSL -->
    <div class="section-card">
        <div class="section-title"><span>🔒</span> SSL certificate verification</div>
        <p>The panel talks to GitHub (updates) and to geolocation services over <code>https://</code> and <strong>always verifies the server certificate</strong>. Without verification anyone between the panel and the server could slip foreign code into an update. PHP needs a list of trusted certificate authorities (CA) for this.</p>
        <p><strong>Ubuntu:</strong> the list is already part of the system. If verification fails, refresh it:</p>
        <div class="code-block"><pre>sudo apt update &amp;&amp; sudo apt install --reinstall ca-certificates &amp;&amp; sudo update-ca-certificates</pre></div>
        <p><strong>Windows (configured once):</strong></p>
        <ol>
            <li>Download <code>https://curl.se/ca/cacert.pem</code> (the official certificate bundle of the curl project) and save it as <code>C:\php\extras\ssl\cacert.pem</code>. Create the folders if they do not exist.</li>
            <li>Find <code>php.ini</code>: the command <code>php --ini</code> shows the line "Loaded Configuration File". If it is empty, copy <code>php.ini-development</code> to <code>php.ini</code> in the PHP folder.</li>
            <li>In <code>php.ini</code> find the lines <code>curl.cainfo</code> and <code>openssl.cafile</code>, remove the leading <code>;</code> and set the path:</li>
        </ol>
        <div class="code-block"><pre>curl.cainfo = "C:\php\extras\ssl\cacert.pem"
openssl.cafile = "C:\php\extras\ssl\cacert.pem"</pre></div>
        <ol start="4">
            <li>Make sure these extensions are enabled (no leading <code>;</code>): <code>extension=curl</code> and <code>extension=openssl</code>.</li>
            <li>Restart PHP (stop and start <code>php -S</code> again, or restart Apache/IIS).</li>
        </ol>
        <p><strong>Check:</strong> "Tools → Update → 🌐 Check GitHub" should answer "GitHub is reachable, SSL verification passed". Until certificates are configured, updates and country lookup by IP will not work: the interface shows this same hint.</p>
    </div>
    
</div>
HTML_WEB_EN;

// ============================================
// ПОЛНЫЙ HTML ДЛЯ СКАЧИВАНИЯ
// ============================================
// Описание разделов и опций панели (вкладка «Описание»). Каждый раздел: id, значок, заголовок, HTML.
// Ссылки на другие разделы: <a href="#" data-doc="d12">…</a>.
$docDesc = [
    'ru' => [
        [
            'id' => 'd00', 'icon' => '🏠', 'title' => 'Главная страница',
            'html' => <<<'HTML'
<p>🌐 Доступ: открывается без входа по адресу <code>/vlmc.php</code>.</p>
<h2>Что это</h2>
<p>Страница мониторинга: кто и когда обращался к KMS-серверу, какие продукты активировались, какие адреса выглядят подозрительно.</p>
<h2>Логика работы</h2>
<ol>
<li>Сервер vlmcsd пишет каждое обращение в свой лог.</li>
<li>Панель читает лог и каждые несколько секунд обновляет страницу.</li>
<li>По логу строятся списки продуктов, неизвестных устройств и подозрительных IP.</li>
<li>Устройство из списка неизвестных можно добавить в панель, адрес из подозрительных — внести в белый список.</li>
</ol>
<h2>Что на странице</h2>
<ul>
<li><strong>Лог запросов.</strong> Строки журнала vlmcsd: время, устройство, IP-адрес, продукт. Обновляется автоматически.</li>
<li><strong>Поиск и фильтры.</strong></li>
<li>Поле «Поиск по тексту» и кнопки «Найти» и «Сброс».</li>
<li>Фильтр по типу записей: «Все события», «Только подключения», «Только запросы».</li>
<li>Фильтр «Все группы»: показывает только устройства выбранной группы.</li>
<li><strong>Продукты.</strong> Список продуктов Microsoft, которые активировались, с числом устройств и запросов (подсказки «Устройств», «Запросов», «Доля», «Последний»).</li>
<li><strong>Состояние сервера.</strong> Индикатор по времени работы сервера: «Недавно запущен» (меньше суток), «Активен» (от суток до 30 дней), «Стабильно» (больше 30 дней), «Нет данных». Подсказка показывает время работы.</li>
<li><strong>Подозрительные IP.</strong> Адреса, с которых обращаются устройства, не добавленные в панель, или адреса с необычным поведением. Для каждого видны страна, число подключений, временной промежуток и действия:</li>
<li>копировать IP;</li>
<li>информация об адресе (страна, регион, город, провайдер, организация, часовой пояс);</li>
<li>добавить адрес в белый список (он перестанет попадать в этот блок; нужно право «IP Whitelist: управление»);</li>
<li>заблокировать адрес в фаерволе (кнопка 🚫 появляется только когда Firewall подключён; сейчас функция в разработке).</li>
<li><strong>Неизвестные устройства.</strong> Устройства, которые обращаются к серверу, но не добавлены в панель: имя, число запросов, последний IP, первый и последний запрос. Кнопка ➕ («Добавить в известные») открывает окно «Добавить устройство»: имя, группа и комментарий. Без входа в панель вместо кнопки стоит замок 🔒.</li>
<li><strong>Переключатель языка</strong> (русский и английский) и значок «Настройки», который ведёт в панель управления.</li>
</ul>
<h2>Скрытие IP-адресов</h2>
<p>Если в «Безопасность → Пользователи → Скрытие IP-адресов» включена настройка «Скрывать IP-адреса от неавторизованных», то без входа в панель адреса в логе и в списке неизвестных устройств заменяются размытой заглушкой. Настоящие адреса при этом в страницу не попадают. Блок «Подозрительные IP» остаётся открытым.</p>
<h2>Геолокация</h2>
<p>Страна и данные об адресе берутся из внешних сервисов и кэшируются в папке <code>vlmcconf/cache/</code>. Очистка и обновление кэша находятся в «Инструменты → Управление кэшем».</p>
HTML
        ],
        [
            'id' => 'd01', 'icon' => '⚙️', 'title' => 'Общие настройки',
            'html' => <<<'HTML'
<h2>Что это</h2>
<p>Внешний вид панели и путь к логу vlmcsd.</p>
<h2>Тема оформления</h2>
<p>Выбор из 16 тем: «Тёмная», «Светлая», «Серая», «Пепел и пламя», «Ночной эльф», «Корпоративная», «Бизнес-классика», «Серебряный стандарт», «Тёмный кварц», «Каберне Совиньон», «Весенняя», «Зимняя», «Летняя», «Осенняя», «Покер», «Корпоративный синий».</p>
<p>Под списком блок «Предпросмотр темы» (кнопка, сообщения об успехе и ошибке, пример текста). Тема применяется кнопкой «Применить тему» и сохраняется для панели и главной страницы.</p>
<h2>Язык интерфейса</h2>
<p>«Русский» или «English». Применяется кнопкой «Сохранить язык». Тексты фоновых задач и уведомлений также берутся из выбранного языка.</p>
<h2>Файл лога</h2>
<ul>
<li><strong>Путь к файлу лога</strong> — путь к журналу vlmcsd на сервере (по умолчанию <code>/var/log/vlmcsd.log</code>, либо ссылка <code>vlmcsd.log</code> в веб-папке).</li>
<li><strong>Статус</strong> — «Файл найден» или сообщение об ошибке (нет файла, нет прав на чтение).</li>
<li>Кнопка «Сохранить путь». Изменение пути пишется в «Журнал событий» и может вызвать уведомление «Изменён путь к журналу KMS».</li>
</ul>
HTML
        ],
        [
            'id' => 'd02', 'icon' => '👥', 'title' => 'Группы',
            'html' => <<<'HTML'
<h2>Что это</h2>
<p>Группа объединяет устройства (например, «Домашние», «Рабочие», «Клиенты») и задаёт им значок и цвет.</p>
<h2>Логика работы</h2>
<ol>
<li>Создайте группу: название, значок, цвет.</li>
<li>Добавляйте в неё устройства в разделе «Устройства».</li>
<li>Цвет и значок группы видны на главной странице и в отчётах.</li>
<li>При удалении группы устройства переносятся в скрытую группу, а не удаляются.</li>
</ol>
<h2>Добавить группу</h2>
<ul>
<li><strong>Название группы.</strong></li>
<li><strong>Иконка</strong> — эмодзи; кнопка открывает окно «Выберите иконку».</li>
<li><strong>Цвет группы.</strong></li>
<li>Кнопка «Добавить».</li>
</ul>
<h2>Существующие группы</h2>
<p>Карточка каждой группы показывает иконку, название, цвет и число устройств. Кнопка ✎ открывает окно редактирования:</p>
<ul>
<li>изменить название, иконку и цвет;</li>
<li>удалить группу.</li>
</ul>
<p>При переименовании устройства остаются в группе, а фильтры по группе в правилах уведомлений переходят на новое название.</p>
<h2>Удаление группы</h2>
<p>Перед удалением панель просит подтверждение. Устройства удаляемой группы не пропадают: они переносятся в служебную группу <code>__orphaned__</code>, которая не показывается в списке групп. Вернуть их можно, переместив в нужную группу в разделе «Устройства».</p>
<p>Текст подтверждения в панели говорит то же самое: устройства не удаляются, а переносятся в скрытую группу.</p>
<p>Действия с группами пишутся в «Журнал событий» и могут вызывать уведомления: «Создана группа», «Группа переименована», «Группа удалена».</p>
HTML
        ],
        [
            'id' => 'd03', 'icon' => '📱', 'title' => 'Устройства',
            'html' => <<<'HTML'
<h2>Что это</h2>
<p>Устройство — это компьютер или сервер, который активируется на вашем KMS-сервере. Имя берётся из лога vlmcsd.</p>
<h2>Логика работы</h2>
<ol>
<li>Устройство обращается к KMS-серверу, и имя попадает в лог.</li>
<li>Пока устройство не добавлено в панель, оно показывается как «неизвестное» на главной странице.</li>
<li>Добавьте его с именем, группой и комментарием.</li>
<li>Дальше его можно искать, фильтровать, править и переносить между группами.</li>
</ol>
<h2>Добавить устройство</h2>
<ul>
<li><strong>Имя устройства</strong> — как оно записано в логе.</li>
<li><strong>Группа</strong> — одна из существующих групп.</li>
<li><strong>Комментарий</strong> — необязательно, например кому принадлежит устройство.</li>
<li>Кнопка «Добавить». Повторное имя в той же группе не принимается («Устройство уже существует»).</li>
</ul>
<p>Добавить устройство можно и с главной страницы кнопкой ➕ в списке неизвестных устройств.</p>
<h2>Список всех устройств</h2>
<ul>
<li><strong>Фильтр</strong> по группе («Все группы» или конкретная).</li>
<li><strong>Сортировка:</strong> по имени, по группе, по дате добавления (старые сначала или новые сначала).</li>
<li>Счётчик «Показано N из M».</li>
<li>У каждого устройства кнопки редактирования (имя, группа, комментарий) и удаления.</li>
</ul>
<h2>Массовое перемещение</h2>
<ol>
<li>Отметьте устройства флажками или нажмите «Выделить всё» («Снять всё»).</li>
<li>Выберите целевую группу в списке «Переместить в…».</li>
<li>Нажмите «Переместить» и подтвердите. Счётчик «Выбрано: N» показывает число отмеченных устройств.</li>
</ol>
<p>Перемещение сохраняет комментарии и дату добавления устройств.</p>
<h2>Журнал и уведомления</h2>
<p>Добавление, изменение, перемещение и удаление пишутся в «Журнал событий». Для них есть события уведомлений: «Добавлено устройство», «Изменено устройство», «Устройства перемещены в группу», «Удалено устройство». Устройства, которые появились в логе, но не добавлены в панель, отслеживает отдельное событие «Новое устройство в логе» (фоновая проверка).</p>
HTML
        ],
        [
            'id' => 'd04', 'icon' => '🔒', 'title' => 'Безопасность',
            'html' => <<<'HTML'
<h2>Что это</h2>
<p>Учётные записи и права пользователей, защита входа и ограничение доступа к панели по IP.</p>
<h2>Логика работы</h2>
<ol>
<li>Администратор создаёт пользователей и задаёт им права по разделам.</li>
<li>Вход защищён требованиями к паролю, таймаутом и ограничением неудачных попыток.</li>
<li>Дополнительно доступ к панели можно ограничить списком адресов и скрыть IP на главной странице от неавторизованных.</li>
<li>Все изменения пишутся в «Журнал событий».</li>
</ol>
<h2>Вкладка «Пользователи»</h2>
<h3>Управление пользователями</h3>
<p>Таблица: логин, роль (набор прав по разделам), дата создания, действия. Текущий пользователь помечен «Текущий».</p>
<ul>
<li><strong>Добавить пользователя:</strong> логин, пароль и права по разделам.</li>
<li><strong>✎</strong> — изменить права пользователя. <strong>🔑</strong> — сменить пароль. <strong>✕</strong> — удалить пользователя.</li>
<li>Права по разделам показаны значками. Для большинства разделов три уровня: нет доступа 🚫, просмотр 👁️, редактирование ✏️. Для белых IP и уведомлений два состояния: ✅ и ❌. Что открывает каждое право, описано в <a href="#" data-doc="d12">«Права пользователей»</a>.</li>
<li>Пользователь, у которого есть все права, считается полным администратором. Только он видит раздел «API».</li>
<li>Изменения пользователей пишутся в «Журнал событий» (пароли и токены туда не попадают).</li>
</ul>
<h3>Требования к паролю</h3>
<p>Минимум 8 символов, заглавные и строчные буквы, цифры и спецсимволы. При первом входе под <code>root</code>/<code>root</code> панель требует сменить пароль.</p>
<h3>Таймаут входа</h3>
<p>Через сколько минут без действий вход завершается: от 5 до 1440 (24 часа), по умолчанию 30. Применяется сразу, уже вошедшие пользователи продолжают работу с новым значением.</p>
<h3>Скрытие IP-адресов</h3>
<p>Настройка «Скрывать IP-адреса от неавторизованных». При включении главная страница без входа показывает размытые заглушки вместо адресов в логе и в списке «Неизвестные устройства». Реальные адреса в страницу не передаются. Блок «Подозрительные IP» остаётся открытым. Менять может только администратор.</p>
<h3>Не подозрительные (белые IP)</h3>
<p>Адреса из списка «Не подозрительные» не попадают в блок «Подозрительные IP» на главной. Добавить адрес можно кнопкой «➕ Добавить IP» (поле «IP-адрес» и комментарий) или с главной страницы. Нужно право «IP Whitelist: управление».</p>
<h2>Вкладка «Доступ и IP»</h2>
<h3>Белый список IP для панели</h3>
<p>Переключатель «Открывать панель управления только с указанных адресов». Когда он включён, страницы панели и вход открываются только с адресов и сетей из списка (и с самого сервера). Остальные получают ошибку 403 со страницей, на которой видно их адрес. Главная страница с логом и API не затрагиваются.</p>
<ul>
<li>Показывается «Ваш адрес». Кнопка «📍 Добавить мой адрес» вносит его в список, чтобы не закрыть себе доступ.</li>
<li>Список «Разрешённые адреса и сети»: адрес или сеть (IPv4 и IPv6, формат CIDR) и комментарий.</li>
<li>Панель не даст сохранить настройку так, чтобы вы потеряли доступ сами.</li>
<li>Отказы пишутся в «Журнал событий» (адрес, страница, браузер; не чаще раза в 10 минут на адрес).</li>
<li>Адрес определяется по соединению (<code>REMOTE_ADDR</code>). За обратным прокси (nginx, Cloudflare) все посетители видны с адреса прокси, поэтому там ограничение лучше задавать на самом прокси.</li>
</ul>
<h3>Аварийное отключение</h3>
<p>Если вы закрыли себе доступ, на сервере создайте пустой файл <code>panel_whitelist.off</code> в папке данных (команда <code>touch</code> показана в панели) или задайте переменную окружения <code>VLMC_PANEL_WHITELIST_OFF=1</code>. Ограничение сразу перестанет действовать. После правки списка файл нужно удалить вручную (команда <code>rm</code> тоже показана).</p>
<h2>Вкладка «Firewall»</h2>
<p>Функция блокировки IP из блока «Подозрительные IP» находится в разработке: вкладка закрыта пометкой «В разработке», в «Информация → Статус» строка Firewall показывает «В разработке».</p>
<p>В коде уже есть: выбор механизма (ufw, nftables, iptables), область блокировки (все порты или только порт панели), список заблокированных адресов, кнопки «Применить неприменённые» и «Применить все заново». Веб-серверу нельзя менять правила фаервола напрямую, поэтому нужен скрипт-помощник и правило sudo; подробности и причина паузы описаны в пункте 30 бэклога.</p>
<h2>Важно</h2>
<ul>
<li>Не закрывайте себе доступ: перед включением «Открывать панель управления только с указанных адресов» добавьте свой адрес кнопкой «📍 Добавить мой адрес». Если всё же закрыли, снимите ограничение файлом <code>panel_whitelist.off</code>.</li>
<li>Первый вход под <code>root</code>/<code>root</code> требует смены пароля: не оставляйте стандартный пароль.</li>
<li>За обратным прокси (nginx, Cloudflare) все посетители видны с адреса прокси: ограничение по IP задавайте на самом прокси.</li>
</ul>
HTML
        ],
        [
            'id' => 'd05', 'icon' => '🛠️', 'title' => 'Инструменты',
            'html' => <<<'HTML'
<h2>Что это</h2>
<p>Обслуживание панели: выгрузка проекта, обновление, очистка лога и кэшей.</p>
<h2>Логика работы</h2>
<ol>
<li>Вкладка «Управление проектом» выгружает архив и сбрасывает настройки.</li>
<li>Вкладка «Обновление» проверяет GitHub и устанавливает новую версию.</li>
<li>Вкладки «Управление логом» и «Управление кэшем» очищают данные.</li>
<li>Опасные действия просят подтверждение и пишутся в «Журнал событий».</li>
</ol>
<h2>Управление проектом</h2>
<h3>Экспорт проекта</h3>
<p>Скачивание архива проекта. Три варианта:</p>
<ul>
<li><strong>Чистый проект</strong> — только структура файлов, без логов и настроек.</li>
<li><strong>С настройками</strong> — вместе с конфигом и списком пользователей, без логов.</li>
<li><strong>Полный бэкап</strong> — всё, включая лог-файлы.</li>
</ul>
<p>Выгрузка пишется в «Журнал событий» и может вызывать уведомление «Выгружен проект (резервная копия панели)». В архив с настройками попадают пароли в виде хешей: храните его как секретный.</p>
<h3>Сбросить настройки</h3>
<p>Возвращает настройки к значениям по умолчанию (тема, язык, группы, устройства). Действие необратимо, поэтому панель просит подтверждение. Событие «Сброшена конфигурация панели» имеет высокую важность в уведомлениях.</p>
<h2>Обновление</h2>
<ul>
<li><strong>Блок сведений:</strong> репозиторий (<code>AlexandreKz/Monitor-VLMCSD</code>), ветка, текущая версия, время последней проверки, статус.</li>
<li><strong>Кнопки:</strong> «Проверить обновления», «Обновить проект» (открывает окно подтверждения со списком файлов, затем «Да, обновить»), «Проверить GitHub» (проверка доступа и сертификата).</li>
<li><strong>Тип обновления</strong> (два канала):</li>
<li>«Релиз (стабильный)» — файлы из тега последнего опубликованного релиза;</li>
<li>«Ранний доступ (по SHA)» — файлы из ветки <code>main</code>; обновлением считаются новые и изменённые файлы.</li>
<li><strong>Токен GitHub (необязательно):</strong> повышает лимит запросов к GitHub с 60 до 5000 в час. Хранится в зашифрованном виде.</li>
<li><strong>Автоматически проверять обновления (оба канала):</strong> флажок «Включить автопроверку» и выбор интервала в часах.</li>
<li><strong>Автоматическое обновление</strong> — в разработке (пункт 62 бэклога).</li>
<li><strong>Как идёт установка:</strong> файлы скачиваются во временную папку и сверяются с репозиторием по SHA, затем делается резервная копия заменяемых файлов (в <code>backups/update_…</code> папки данных) и файлы заменяются. Если что-то пошло не так, выполняется откат. Папки <code>pic/</code> и <code>screen/</code>, настройки и данные панели не обновляются, файлы, которых нет в репозитории, не удаляются.</li>
<li><strong>Проверка по расписанию:</strong> <code>update_check.php</code> завершается с кодом 0 (обновлений нет), 10 (есть обновление) или 1 (ошибка проверки). Для него в cron можно добавить <code>0 */6 * * * www-data php /var/www/html/vlmcconf/update_check.php &gt;/dev/null 2&gt;&amp;1</code>.</li>
<li>Если вы вносили в файлы панели собственные правки, ранний доступ предложит заменить их версией из репозитория: сначала загрузите свои правки в репозиторий.</li>
<li><strong>Консоль</strong> внизу показывает ход проверки и установки.</li>
<li>Если проверка сертификатов не проходит, раздел показывает подсказку «Как подключить проверку сертификатов».</li>
</ul>
<p>События «Доступна новая версия панели», «Панель обновлена», «Ошибка обновления панели» подписываются в «Уведомления».</p>
<h2>Управление логом</h2>
<ul>
<li>Блок сведений: файл лога, размер, статус, дата первого события.</li>
<li><strong>Очистка по датам:</strong> поля «С» и «По» и кнопка «Очистить».</li>
<li><strong>Полная очистка:</strong> флажок «Очистить всё», флажок «Резервная копия» (сначала сохранить копию) и кнопка «Полная очистка».</li>
<li>Очистка лога необратима, поэтому сначала сделайте резервную копию. Действие пишется в «Журнал событий», есть событие уведомления «Очищен лог KMS-сервера».</li>
</ul>
<h2>Управление кэшем</h2>
<ul>
<li><strong>Кэш геолокации:</strong> число файлов и объём кэша; кнопки «Очистить кэш», «Обновить кэш» (заново запросить данные для всех IP из лога, может занять время: окно предупреждает об этом) и «Проверить» (статистика кэша).</li>
<li><strong>Кэш эмодзи:</strong> статус кэша, «Очистить кэш» (удалить файл) и «Обновить кэш» (загрузить актуальный список эмодзи из интернета).</li>
</ul>
<h2>Важно</h2>
<ul>
<li>Сброс настроек и очистка лога необратимы: перед ними сделайте «Полный бэкап».</li>
<li>В архиве «С настройками» лежат хеши паролей: храните его как секретный.</li>
<li>Не запускайте обновление, пока не сделали резервную копию: при сбое панель откатит файлы, но данные лучше сохранить заранее.</li>
</ul>
HTML
        ],
        [
            'id' => 'd06', 'icon' => '🔔', 'title' => 'Уведомления',
            'html' => <<<'HTML'
<p>🔒 Доступ: право «Уведомления: изменение» или полный администратор. Для остальных пункт меню серый.</p>
<h2>Что это</h2>
<p>Сообщения о событиях в панели и на KMS-сервере по электронной почте, в Telegram и в Битрикс24.</p>
<h2>Логика работы</h2>
<ol>
<li>Событие возникает в панели или находится фоновой проверкой.</li>
<li>Правило решает, о каких событиях, куда и как часто сообщать.</li>
<li>Канал знает, как доставить сообщение (почта, Telegram, Битрикс24).</li>
<li>Результат каждой отправки виден на вкладке «История».</li>
</ol>
<h2>Вкладка «Каналы»</h2>
<p>Канал — это способ доставки. Кнопка «Создать канал» открывает окно. В таблице: название, тип, получатель, статус («Активно» или выключен), кнопки проверки и удаления.</p>
<p>Тип канала выбирается в окне:</p>
<ul>
<li><strong>Email (SMTP):</strong> «SMTP-сервер», «Порт», «Шифрование» (STARTTLS, SSL/TLS или без шифрования), «Логин», «Пароль», «Отправитель», «Получатели» (несколько адресов через запятую).</li>
<li><strong>Telegram:</strong> «Токен бота» и «Chat ID» (числовой идентификатор чата или @канал; бот должен быть добавлен в чат). Необязательное поле <strong>«Прокси»</strong> нужно там, где Telegram заблокирован: например <code>socks5h://127.0.0.1:40000</code> (допустимы <code>socks5://</code>, <code>socks5h://</code>, <code>http://</code>, <code>https://</code>). Прокси применяется только к Telegram, остальные запросы панели идут напрямую. Для прокси нужно расширение PHP curl. Настройка WARP описана в отдельной инструкции.</li>
<li><strong>Битрикс24:</strong> «Входящий вебхук» (адрес вида Битрикс24 → Разработчикам → Другое → Входящий вебхук, только <code>https://</code>) и «Получатель» (ID пользователя или чат вида <code>chat123</code>).</li>
<li>Флажок «Канал включён».</li>
<li>Кнопка «Тест» отправляет проверочное сообщение, результат (успех или текст ошибки) виден в окне и в истории.</li>
</ul>
<p>Пароли, токены и вебхуки хранятся в зашифрованном виде (ключ <code>notify.key</code> в папке данных).</p>
<h2>Вкладка «Правила»</h2>
<p>Правило отвечает на вопросы «о чём», «куда» и «как часто». Кнопка «Создать правило» открывает окно. В таблице: название, события, каналы, частота, тихие часы, статус.</p>
<p>В окне правила:</p>
<ul>
<li><strong>Название.</strong></li>
<li><strong>События.</strong> Список по группам (группы сворачиваются, по умолчанию раскрыта «Безопасность и пользователи»). Есть поиск по названию события справа от заголовка «События». У некоторых событий есть пороги (например, число неудачных входов и за сколько минут).</li>
<li><strong>Каналы доставки</strong> — один или несколько (каналы создаются на вкладке «Каналы»).</li>
<li><strong>Область действия</strong> — «Группы» и «Устройства»: необязательный фильтр для событий, связанных с группами и устройствами. Если ничего не выбрано, правило действует для любых групп и устройств.</li>
<li><strong>Пороги</strong> — настройка событий с числовыми условиями (например, число неудачных входов и период в минутах).</li>
<li><strong>Интервал между уведомлениями, мин.</strong> Не чаще одного уведомления за этот срок для одного правила и события; 0 — без ограничений. Сообщения, подавленные в этот период, считаются и добавляются к следующему как «(+N)».</li>
<li><strong>Тихие часы</strong> — поля «с» и «по»: в этот промежуток сообщения не отправляются.</li>
<li><strong>Правило активно</strong> — выключатель, чтобы остановить правило, не удаляя его.</li>
</ul>
<p>По умолчанию панель предлагает 13 готовых правил (выключены, без каналов). Удалённые готовые правила не возвращаются.</p>
<h2>Вкладка «История»</h2>
<p>Журнал отправок: время, правило, событие, каналы, результат (успех или причина ошибки). Проверочные сообщения тоже попадают в историю.</p>
<h2>Вкладка «Шаблоны»</h2>
<p>Заглушка «Раздел в разработке». Вкладка оставлена намеренно как напоминание о доработке (пункт 69 бэклога).</p>
<h2>События</h2>
<p>Всего 44 события в шести группах. Важность («высокая», «средняя», «низкая») помогает отбирать главное.</p>
<h3>Безопасность и пользователи</h3>
<table>
<thead>
<tr>
<th>Событие</th>
<th>Важность</th>
</tr>
</thead>
<tbody>
<tr>
<td>Серия неудачных входов</td>
<td>высокая</td>
</tr>
<tr>
<td>Отказ в доступе</td>
<td>средняя</td>
</tr>
<tr>
<td>Добавлен пользователь</td>
<td>средняя</td>
</tr>
<tr>
<td>Удалён пользователь</td>
<td>высокая</td>
</tr>
<tr>
<td>Изменён пользователь или его права</td>
<td>средняя</td>
</tr>
<tr>
<td>Создан ключ API</td>
<td>средняя</td>
</tr>
<tr>
<td>Удалён ключ API</td>
<td>средняя</td>
</tr>
<tr>
<td>Изменено ограничение доступа к панели по IP</td>
<td>высокая</td>
</tr>
<tr>
<td>Изменён пароль пользователя</td>
<td>средняя</td>
</tr>
<tr>
<td>Успешный вход в панель</td>
<td>низкая</td>
</tr>
<tr>
<td>Изменён файрвол (блокировка IP, настройки)</td>
<td>высокая</td>
</tr>
<tr>
<td>Изменён список разрешённых IP</td>
<td>средняя</td>
</tr>
<tr>
<td>Изменены настройки панели</td>
<td>средняя</td>
</tr>
</tbody>
</table>
<h3>Изменения в панели</h3>
<table>
<thead>
<tr>
<th>Событие</th>
<th>Важность</th>
</tr>
</thead>
<tbody>
<tr>
<td>Добавлено устройство</td>
<td>низкая</td>
</tr>
<tr>
<td>Устройства перемещены в группу</td>
<td>низкая</td>
</tr>
<tr>
<td>Группа переименована</td>
<td>низкая</td>
</tr>
<tr>
<td>Группа удалена</td>
<td>средняя</td>
</tr>
<tr>
<td>Удалено устройство</td>
<td>средняя</td>
</tr>
<tr>
<td>Сброшена конфигурация панели</td>
<td>высокая</td>
</tr>
<tr>
<td>Создана группа</td>
<td>низкая</td>
</tr>
<tr>
<td>Изменено устройство</td>
<td>низкая</td>
</tr>
<tr>
<td>Изменён путь к журналу KMS</td>
<td>средняя</td>
</tr>
<tr>
<td>Выгружен проект (резервная копия панели)</td>
<td>высокая</td>
</tr>
<tr>
<td>Очищен лог KMS-сервера</td>
<td>средняя</td>
</tr>
<tr>
<td>Очищен журнал событий</td>
<td>высокая</td>
</tr>
</tbody>
</table>
<h3>Обновления</h3>
<table>
<thead>
<tr>
<th>Событие</th>
<th>Важность</th>
</tr>
</thead>
<tbody>
<tr>
<td>Доступна новая версия панели</td>
<td>низкая</td>
</tr>
<tr>
<td>Панель обновлена</td>
<td>низкая</td>
</tr>
<tr>
<td>Ошибка обновления панели</td>
<td>средняя</td>
</tr>
</tbody>
</table>
<h3>Устройства</h3>
<table>
<thead>
<tr>
<th>Событие</th>
<th>Важность</th>
</tr>
</thead>
<tbody>
<tr>
<td>Новое устройство в логе</td>
<td>низкая</td>
</tr>
<tr>
<td>Устройство давно не появлялось</td>
<td>низкая</td>
</tr>
<tr>
<td>Устройство с множеством IP</td>
<td>средняя</td>
</tr>
<tr>
<td>Множество устройств с одного IP</td>
<td>средняя</td>
</tr>
<tr>
<td>Обращение из новой страны</td>
<td>средняя</td>
</tr>
</tbody>
</table>
<h3>Служба и система</h3>
<table>
<thead>
<tr>
<th>Событие</th>
<th>Важность</th>
</tr>
</thead>
<tbody>
<tr>
<td>Служба KMS остановлена</td>
<td>высокая</td>
</tr>
<tr>
<td>Служба KMS снова работает</td>
<td>низкая</td>
</tr>
<tr>
<td>Лог давно не обновлялся</td>
<td>средняя</td>
</tr>
<tr>
<td>Лог слишком большой</td>
<td>низкая</td>
</tr>
<tr>
<td>Мало места на диске с данными</td>
<td>средняя</td>
</tr>
<tr>
<td>Сбой фоновой задачи</td>
<td>средняя</td>
</tr>
</tbody>
</table>
<h3>Интеграции</h3>
<table>
<thead>
<tr>
<th>Событие</th>
<th>Важность</th>
</tr>
</thead>
<tbody>
<tr>
<td>AD/LDAP недоступен</td>
<td>высокая</td>
</tr>
<tr>
<td>AD/LDAP снова доступен</td>
<td>низкая</td>
</tr>
<tr>
<td>Служебная запись AD/LDAP не принята</td>
<td>средняя</td>
</tr>
<tr>
<td>Изменена интеграция (вебхук, облако, сервер)</td>
<td>средняя</td>
</tr>
<tr>
<td>Изменены настройки AD/LDAP</td>
<td>средняя</td>
</tr>
</tbody>
</table>
<h2>Откуда берутся события</h2>
<ul>
<li><strong>Журнал событий.</strong> Большинство событий связано с действиями пользователей: вход, изменение настроек, групп, устройств, ключей API, интеграций.</li>
<li><strong>Фоновая проверка <code>notify_watch</code></strong> (раз в 5 минут): новые устройства, давно не появлявшиеся устройства, несколько IP у одного устройства, несколько устройств с одного IP, обращение из новой страны, служба KMS остановлена или снова работает, лог не обновляется или слишком большой, мало места на диске. Включается только если есть правило на такие события.</li>
<li><strong>Проверка обновлений:</strong> «Доступна новая версия панели» срабатывает один раз на версию, отдельно для релиза и раннего доступа.</li>
<li><strong>Сбой фоновой задачи</strong> и события AD/LDAP.</li>
<li>Те же события можно отправлять во внешние системы через Webhook (вкладка «Интеграции»).</li>
</ul>
<p>Чтобы фоновая проверка шла без захода в панель, настройте запуск по расписанию ОС (см. «Журнал событий → Фоновые»).</p>
<h2>Важно</h2>
<ul>
<li>Ключ шифрования <code>notify.key</code> лежит в папке данных. Без него сохранённые пароли, токены и вебхуки не расшифровываются: сохраняйте его вместе с копиями данных.</li>
<li>Фоновые события («Новое устройство в логе» и другие) работают только при включённой проверке по расписанию ОС или при открытой панели (см. «Журнал событий → Фоновые»).</li>
</ul>
HTML
        ],
        [
            'id' => 'd07', 'icon' => '🔌', 'title' => 'Интеграции',
            'html' => <<<'HTML'
<h2>Что это</h2>
<p>Связь панели с внешними системами: каталог пользователей, хранилища копий, вебхуки и другие панели.</p>
<h2>Логика работы</h2>
<ol>
<li>Выберите вкладку нужной интеграции и включите её.</li>
<li>Укажите параметры подключения и нажмите проверку.</li>
<li>Включённая интеграция работает в фоне, её состояние видно в «Информация → Статус».</li>
</ol>
<h2>Active Directory / LDAP</h2>
<p>Вход доменных пользователей и импорт учётных записей из Active Directory, OpenLDAP и других LDAP-серверов. Нужно расширение PHP <code>ldap</code> (<code>sudo apt install php8.5-ldap</code> и перезапуск Apache). Без него вверху вкладки показывается предупреждение, а в «Информация → Статус» отметка «Предупреждение».</p>
<p>Вкладка делится на три части: «Подключение и вход», «Пользователи», «Журнал».</p>
<h3>Подключение</h3>
<ul>
<li><strong>Включить интеграцию с каталогом.</strong></li>
<li><strong>LDAP сервер</strong> — полное имя или IP контроллера домена.</li>
<li><strong>Защита и порт</strong> — без шифрования, StartTLS или LDAPS, порт.</li>
<li><strong>Проверять сертификат сервера.</strong></li>
<li><strong>Base DN</strong> — корень поиска пользователей.</li>
<li><strong>Служебная запись</strong> и пароль — учётная запись только для чтения каталога.</li>
<li><strong>Резервные записи</strong> (до 5) — пробуются по порядку, если основная отклонена.</li>
<li><strong>Домен (UPN), необязательно</strong> — нужен, если служебная запись не задана или логин у пользователей в виде имя@домен.</li>
<li>Кнопки «Сохранить» и «Проверить связь». Проверка идёт по каждой служебной записи отдельно: рядом с записью ✅ или ❌, подробности в журнале.</li>
</ul>
<h3>Вход и пользователи</h3>
<ul>
<li><strong>Разрешить вход доменных пользователей.</strong> Сначала проверяется локальная база панели, затем каталог. Локальные учётные записи (например, <code>root</code>) доменным пользователем занять нельзя.</li>
<li><strong>Создавать пользователя при первом входе.</strong> Если выключено, войти смогут только импортированные.</li>
<li><strong>Права новых пользователей</strong> — шаблон прав («Просмотр: лог и устройства», «Просмотр: лог, устройства, группы, информация», «Работа: просмотр и изменение устройств и групп»). Права можно изменить позже в разделе «Безопасность».</li>
<li><strong>Атрибут логина.</strong></li>
<li><strong>Обязательная группа.</strong> Кнопка «Выбрать группы…» открывает окно со списком групп каталога (поиск по имени, ручной ввод имени или DN). Вход разрешён членам любой из выбранных групп. Если ничего не выбрано, пускаются все пользователи каталога.</li>
<li><strong>Дополнительный фильтр</strong> — условие LDAP-поиска, например <code>(department=IT)</code>.</li>
<li><strong>Таймаут</strong> в секундах.</li>
</ul>
<h3>Пользователи каталога</h3>
<p>Поиск по имени, логину или почте, кнопки «Импортировать выбранных» и «Обновить данные». В таблице: логин, имя, почта, группы, признак «В панели».</p>
<h3>Журнал</h3>
<p>Журнал проверок и синхронизации: время, операция, результат, подробности (причина ошибки). Раз в 12 часов фоновая задача проверяет связь и обновляет состояние «Подключен» или «Не подключен» в «Информация → Статус».</p>
<h2>Cloud Storage</h2>
<p>Внешние хранилища для резервных копий: до 10 хранилищ типов SFTP, FTP, FTPS, WebDAV и S3.</p>
<p>Поля: «Название», «Тип хранилища», «Сервер» (для WebDAV и S3 «URL сервера»), «Порт», «Пользователь / Access key», «Пароль / Secret key», «Каталог», «Bucket» и «Регион» (для S3), «Что копировать» (настройки, пользователи, лог KMS, журнал событий), «Расписание», «Время», «Хранить копий», «Включено».</p>
<p>Пароль хранится в зашифрованном виде. Есть проверка подключения.</p>
<p>Сейчас работают настройки и проверка подключения. Автоматическое создание копий и отправка по расписанию пока не готовы (пункт 31 бэклога). Для FTP и FTPS нужно расширение PHP <code>ftp</code>.</p>
<h2>Webhook</h2>
<p>Панель отправляет POST-запрос на ваш адрес при выбранных событиях. До 20 адресов.</p>
<p>Поля: «Название», «URL (http/https)», «Формат» (JSON, Slack или обычный текст), «Секрет для подписи (необязательно)», «Таймаут, с», «События», «Включён».</p>
<ul>
<li>Если задан секрет, тело запроса подписывается (HMAC-SHA256), подпись передаётся в заголовке <code>X-KMS-Signature</code>.</li>
<li>Список событий тот же, что в «Уведомления» (44 события).</li>
<li>В таблице видно время и результат последней отправки. Есть тестовая отправка.</li>
<li>Для отправки в Битрикс24 используйте канал уведомлений «Битрикс24».</li>
</ul>
<h2>Мультисервер</h2>
<p>Вкладка закрыта пометкой «В разработке». В неё заложено добавление других панелей KMS Monitor: при добавлении панель подключается к указанному серверу и входит под вашим логином и паролем (поля «Название», «Адрес панели», «Логин», «Пароль»), в таблице видны версия и время проверки. Это набросок, раздел появится в одной из следующих версий.</p>
<h2>Важно</h2>
<ul>
<li>Cloud Storage пока не создаёт копии автоматически: работают настройки и проверка подключения.</li>
<li>«Мультисервер» и «Firewall» в разработке: вкладки закрыты пометкой.</li>
<li>Для AD/LDAP нужно расширение PHP <code>ldap</code>.</li>
</ul>
HTML
        ],
        [
            'id' => 'd08', 'icon' => '🖥️', 'title' => 'API',
            'html' => <<<'HTML'
<p>🔒 Доступ: только полный администратор.</p>
<h2>Что это</h2>
<p>REST API только для чтения: данные панели можно получать из Zabbix, Grafana и своих скриптов.</p>
<p>Адрес API: <code>https://ваш-сервер/vlmcconf/api.php/v1</code>. Используйте только HTTPS: ключ передаётся открытым текстом.</p>
<h2>Логика работы</h2>
<ol>
<li>Включите API на вкладке «Настройки».</li>
<li>Создайте ключ на вкладке «Ключи» и сохраните его: он показывается один раз.</li>
<li>Передайте ключ в заголовке запроса и получите данные в JSON.</li>
<li>Вкладка «Документация» описывает все методы с примерами.</li>
</ol>
<h2>Вкладка «Ключи»</h2>
<p>Таблица ключей: название, ключ (показывается начало), область доступа, разрешённые адреса, срок, последнее использование, статус. Кнопка «Новый ключ» открывает окно:</p>
<ul>
<li><strong>Название</strong> (например, Zabbix, Grafana).</li>
<li><strong>Доступ</strong> — области: «Состояние», «Устройства», «Группы», «Лог», «Статистика», «Отчёты», «Безопасность», «Журнал событий».</li>
<li><strong>Разрешённые адреса</strong> — IP-адреса и сети (CIDR), с которых можно использовать ключ; пусто — без ограничения.</li>
<li><strong>Действует до</strong> — срок действия (календарь).</li>
</ul>
<p>После создания ключ вида <code>kms_…</code> показывается один раз, потом его не восстановить: в панели хранится только хеш. Ключи можно отключать, включать и удалять. Создание, изменение и удаление пишутся в «Журнал событий».</p>
<h2>Вкладка «Настройки»</h2>
<ul>
<li><strong>Включить API</strong> — общий выключатель. Применяется сразу.</li>
<li><strong>Лимит запросов</strong> — число запросов в минуту на ключ (по умолчанию 60, 0 — без ограничения). При превышении ответ 429 с заголовком <code>Retry-After</code>; остаток виден в заголовках <code>X-RateLimit-*</code>.</li>
<li><strong>Разрешённые источники (CORS)</strong> — по одному адресу в строке или <code>*</code>. Нужно только для запросов из браузера, по умолчанию закрыто.</li>
</ul>
<p>Защита от перебора: 20 неверных ключей в минуту с одного адреса блокируют адрес. Ограничение панели по IP на API не распространяется: у ключей свои списки адресов.</p>
<h2>Как пользоваться</h2>
<p>Ключ передаётся в заголовке <code>Authorization: Bearer kms_…</code> или <code>X-API-Key</code>. Пример:</p>
<pre><code class="language-text">curl -H &quot;Authorization: Bearer kms_…&quot; https://ваш-сервер/vlmcconf/api.php/v1/status
</code></pre>
<p>Поддерживаются только запросы GET.</p>
<h2>Вкладка «Документация»</h2>
<p>Блок «Поиск и фильтры» (поиск по методам, фильтры по типу метода и по области доступа, кнопка <code>openapi.json</code> для Swagger Editor и Postman) и блок «Методы» (описание, пример запроса и ответа с кнопкой копирования).</p>
<h3>Методы (22)</h3>
<table>
<thead>
<tr>
<th>Область</th>
<th>Методы</th>
</tr>
</thead>
<tbody>
<tr>
<td>Состояние</td>
<td><code>/v1/status</code> (версия, состояние лога), <code>/v1/health</code> (результат вкладки «Статус», удобно для мониторинга)</td>
</tr>
<tr>
<td>Устройства</td>
<td><code>/v1/devices</code>, <code>/v1/devices/{name}</code>, <code>/v1/devices/{name}/ips</code></td>
</tr>
<tr>
<td>Группы</td>
<td><code>/v1/groups</code>, <code>/v1/groups/{name}</code></td>
</tr>
<tr>
<td>Лог</td>
<td><code>/v1/log</code> (фильтры device, since, limit)</td>
</tr>
<tr>
<td>Статистика</td>
<td><code>/v1/stats</code></td>
</tr>
<tr>
<td>Безопасность</td>
<td><code>/v1/unknown-devices</code>, <code>/v1/whitelist-ips</code></td>
</tr>
<tr>
<td>Журнал событий</td>
<td><code>/v1/audit</code></td>
</tr>
<tr>
<td>Отчёты</td>
<td><code>/v1/reports/device-ips</code>, <code>ip-devices</code>, <code>multi-ip-devices</code>, <code>shared-ips</code>, <code>top-devices</code>, <code>inactive-devices</code>, <code>new-devices</code>, <code>products</code>, <code>countries</code>, <code>by-country</code></td>
</tr>
</tbody>
</table>
<p>Описание открыто без ключа: <code>/vlmcconf/api.php/v1/openapi.json</code>.</p>
<h2>Важно</h2>
<ul>
<li>Ключ показывается один раз и потом не восстанавливается: сохраните его сразу.</li>
<li>Используйте API только по HTTPS: ключ передаётся открытым текстом.</li>
<li>Не выдавайте ключам лишних областей доступа и ограничивайте их по IP.</li>
</ul>
HTML
        ],
        [
            'id' => 'd09', 'icon' => '📊', 'title' => 'Статистика и отчёты',
            'html' => <<<'HTML'
<h2>Что это</h2>
<p>Сводка по логу vlmcsd: общие цифры, графики активности и готовые отчёты.</p>
<h2>Логика работы</h2>
<ol>
<li>Панель считает данные из лога.</li>
<li>На вкладке «Статистика» видны итоги, топы и графики.</li>
<li>На вкладке «Отчёты» выберите отчёт, задайте параметр и нажмите «Показать».</li>
<li>Результат можно выгрузить в XLSX или PDF.</li>
</ol>
<h2>Вкладка «Статистика»</h2>
<ul>
<li><strong>Карточки:</strong> «Всего устройств», «Всего групп», «Всего запросов».</li>
<li><strong>По группам</strong> — сколько устройств в каждой группе.</li>
<li><strong>Топ IP адресов</strong> и <strong>Топ стран</strong> — самые частые источники запросов. Страна определяется по IP и берётся из кэша геолокации (его можно очистить и обновить в «Инструменты → Управление кэшем»). Если данных нет, показывается «Нет данных об IP» или «Нет данных о странах».</li>
<li><strong>Общая активность</strong> — график запросов по периодам. Переключатель «Дни», «Недели», «Месяцы».</li>
<li><strong>По устройству</strong> — тот же график для выбранного устройства.</li>
</ul>
<h2>Вкладка «Отчёты»</h2>
<p>Выберите «Отчёт», при необходимости укажите параметр и нажмите «Показать». Под таблицей кнопки «XLSX» и «PDF» для выгрузки результата.</p>
<table>
<thead>
<tr>
<th>Отчёт</th>
<th>Что показывает</th>
<th>Параметр</th>
</tr>
</thead>
<tbody>
<tr>
<td>IP-адреса устройства</td>
<td>Все адреса, с которых устройство обращалось к серверу</td>
<td>Имя устройства (подсказки берутся из лога)</td>
</tr>
<tr>
<td>Устройства на IP</td>
<td>Все устройства, обращавшиеся с выбранного адреса</td>
<td>IP-адрес</td>
</tr>
<tr>
<td>Устройства с несколькими IP</td>
<td>Устройства, менявшие адрес: смена сети, VPN, ноутбук в разных офисах</td>
<td>—</td>
</tr>
<tr>
<td>IP с несколькими устройствами</td>
<td>Адреса, за которыми несколько устройств: NAT, офис или подозрительный источник</td>
<td>—</td>
</tr>
<tr>
<td>Топ устройств по запросам</td>
<td>50 самых активных устройств</td>
<td>—</td>
</tr>
<tr>
<td>Давно не появлявшиеся устройства</td>
<td>Устройства, которых нет в логе дольше выбранного срока</td>
<td>Срок</td>
</tr>
<tr>
<td>Новые устройства</td>
<td>Впервые появившиеся в логе за выбранный срок</td>
<td>Срок</td>
</tr>
<tr>
<td>Продукты</td>
<td>Сколько уникальных устройств и запросов на каждый продукт</td>
<td>—</td>
</tr>
<tr>
<td>По стране</td>
<td>IP-адреса выбранной страны и устройства с них</td>
<td>Страна (из кэша геолокации)</td>
</tr>
</tbody>
</table>
<p>Если в логе ничего не найдено, показывается «В логе ничего не найдено». Все отчёты доступны и через <a href="#" data-doc="d08">«API»</a>.</p>
HTML
        ],
        [
            'id' => 'd10', 'icon' => '📜', 'title' => 'Журнал событий',
            'html' => <<<'HTML'
<h2>Что это</h2>
<p>Журнал действий пользователей и состояние фоновых задач панели.</p>
<h2>Логика работы</h2>
<ol>
<li>Панель записывает входы, изменения настроек и другие действия.</li>
<li>На вкладке «Общий» записи можно искать, фильтровать и выгружать в CSV.</li>
<li>Старые записи удаляются по сроку хранения.</li>
<li>На вкладке «Фоновые» видно, как выполняются периодические задачи.</li>
</ol>
<h2>Вкладка «Общий»</h2>
<p>Таблица: «Время», «Пользователь», «IP», «Действие», «Подробности», «Результат».</p>
<ul>
<li><strong>Фильтры:</strong> «Поиск» (по пользователю, IP, действию, описанию), «Пользователь», «Действие», «Результат», «Период» (сегодня, 7 дней, 30 дней, весь период или свои даты), «Сбросить».</li>
<li><strong>Результат:</strong> «Успешно», «Ошибка», «Отказано».</li>
<li><strong>Обновление:</strong> кнопка 🔄 и флажок «Автообновление». «Записей на странице» меняет размер страницы.</li>
<li><strong>Экспорт CSV</strong> — выгрузка найденных записей.</li>
<li>Внизу: «Найдено записей», постраничная навигация.</li>
</ul>
<p>Что попадает в журнал: вход, выход и неудачные попытки входа, смена пароля, темы и языка, действия с группами и устройствами, пользователями и правами, сброс настроек, очистка лога и кэшей, выгрузка проекта, изменение интеграций и API-ключей, обновление панели, отказы по белому списку IP. Пароли и токены в журнал не пишутся.</p>
<h3>Срок хранения записей</h3>
<p>Сколько дней хранить записи (по умолчанию 90), кнопка «Сохранить». Старые записи удаляются автоматически фоновой задачей. Под настройкой видно число записей, размер и срок хранения.</p>
<h3>Очистить журнал</h3>
<p>Кнопка «Очистить журнал» открывает окно подтверждения. Очистка необратима, сама очистка записывается в новый журнал.</p>
<h2>Вкладка «Фоновые»</h2>
<h3>Фоновые задачи</h3>
<p>Задачи выполняются автоматически: при открытии панели (не чаще раза в минуту) и по расписанию ОС. В таблице: «Задача», «Период», «Последний запуск», «Результат», «Следующий».</p>
<table>
<thead>
<tr>
<th>Задача</th>
<th>Период</th>
<th>Что делает</th>
</tr>
</thead>
<tbody>
<tr>
<td>Проверка обновлений</td>
<td>по настройке в «Инструменты → Обновление»</td>
<td>ищет новую версию панели</td>
</tr>
<tr>
<td>Статус AD/LDAP</td>
<td>12 часов</td>
<td>проверяет связь с каталогом</td>
</tr>
<tr>
<td>Наблюдение за уведомлениями</td>
<td>15 минут</td>
<td>новые устройства в логе, подозрительные IP, остановка vlmcsd и т. п.</td>
</tr>
<tr>
<td>Очистка журнала</td>
<td>раз в сутки</td>
<td>удаляет записи старше срока хранения</td>
</tr>
</tbody>
</table>
<h3>Запуск по расписанию ОС</h3>
<p>Показывает команду для <code>cron</code> (файл <code>/etc/cron.d/kms-monitor</code>, запуск <code>cron.php</code> каждые 15 минут). Её можно скопировать или записать кнопкой в панели. Панель пишет тот же файл, что и установщик, и заменяет его: это нормально, потому что <code>cron.php</code> сам выполняет проверку обновлений.</p>
<p>Без <code>cron</code> задачи выполняются только когда кто-нибудь открывает панель. В «Информация → Статус» строка «Планировщик ОС (cron)» тогда показывает «Замечание».</p>
<h3>История запусков</h3>
<p>«Время», «Задача», «Результат», «Откуда» (панель или cron), «мс» (длительность).</p>
<h2>Важно</h2>
<ul>
<li>Очистка журнала необратима.</li>
<li>Без запуска по расписанию ОС фоновые задачи идут только при открытой панели.</li>
</ul>
HTML
        ],
        [
            'id' => 'd11', 'icon' => 'ℹ️', 'title' => 'Информация',
            'html' => <<<'HTML'
<h2>Что это</h2>
<p>Справка о сервере и проекте: структура файлов, файлы данных и проверка состояния компонентов.</p>
<h2>Сервер</h2>
<p>ОС, ядро, хост, IP сервера, веб-сервер, версия PHP, uptime, время сервера.</p>
<h2>Структура проекта</h2>
<p>Дерево файлов проекта: имя, тип, размер, дата. Вверху строка «Обновлено в …: файлов N, изменилось N» и кнопка обновления. Цветные подсказки показывают готовность функций: 95–100% стабильно, 85–94% работает, ниже 85% в разработке.</p>
<h2>Файлы данных</h2>
<p>Каталог данных и список служебных файлов: имя, назначение, полный путь, статус «не создан» или размер. Среди них:</p>
<ul>
<li><code>vlmcconf_config.json</code> — настройки панели;</li>
<li><code>users.json</code> — учётные записи и права;</li>
<li><code>audit.log</code> — журнал событий;</li>
<li><code>notify.json</code>, <code>notify.key</code>, <code>notify_history.jsonl</code> — каналы и правила уведомлений, ключ шифрования секретов, история отправок;</li>
<li><code>update_state.json</code>, <code>github_token.json</code>, <code>update.lock</code> — состояние обновлений, токен GitHub (зашифрован), блокировка;</li>
<li><code>config_changes.log</code>, <code>users_changes.log</code> — трассировка изменений;</li>
<li><code>backups/</code> — резервные копии настроек и пользователей.</li>
</ul>
<p>Ключ <code>notify.key</code> нужен для расшифровки секретов: не теряйте его при переносе данных.</p>
<h3>Перенос, откат и резервные копии</h3>
<ul>
<li><strong>Перенос.</strong> При первом открытии панели после создания папки данных файлы <code>users.json</code> и <code>vlmcconf_config.json</code> копируются, проверяются и удаляются из старого места (<code>vlmcconf/</code>). Копия «до переноса» остаётся в подпапке <code>backups/</code> новой папки.</li>
<li><strong>Обновление и откат.</strong> Обновление панели данные не затрагивает: они лежат вне папки проекта. Не возвращайтесь на версию панели, которая не знает о новой папке: она не найдёт <code>users.json</code> в старом месте и создаст нового пользователя <code>root</code>. Для резервной копии сохраняйте папку данных целиком.</li>
<li><strong>Копии перед изменениями.</strong> Перед каждым изменением настроек и списка пользователей панель сохраняет копию в <code>backups/config/</code> и <code>backups/users/</code> (последние 50 каждого). Если файл повреждён, панель восстановит его из последней копии, а повреждённый сохранит рядом (<code>*.broken</code>). Каждое сохранение записывается в <code>config_changes.log</code> и <code>users_changes.log</code> (метка SHRINK означает уменьшение числа записей).</li>
<li>Если <code>users.json</code> пропал, но копии есть, пользователи восстанавливаются из копии. Чтобы осознанно сбросить пароль <code>root</code>, удалите <code>users.json</code> и папку <code>backups/users</code>.</li>
<li>Каталог <code>vlmcconf/cache</code> пока остаётся на прежнем месте.</li>
</ul>
<h2>Статус</h2>
<p>Кнопка «Проверить заново». Таблица: «Компонент», «Статус», «Подробности», «Параметры». Статусы: «Работает», «Замечание», «Ошибка», «Отключено».</p>
<table>
<thead>
<tr>
<th>Группа</th>
<th>Компоненты</th>
</tr>
</thead>
<tbody>
<tr>
<td>Ядро</td>
<td>KMS-сервер (vlmcsd, порт 1688), лог vlmcsd, папка данных, конфигурация и пользователи, PHP и расширения</td>
</tr>
<tr>
<td>Интеграции</td>
<td>Active Directory / LDAP, Webhook, Cloud Storage, API, Мультисервер, Уведомления</td>
</tr>
<tr>
<td>Безопасность</td>
<td>Ограничение доступа к панели, Firewall («В разработке»)</td>
</tr>
<tr>
<td>Обслуживание</td>
<td>Планировщик ОС (cron), фоновые задачи, обновления, журнал событий</td>
</tr>
</tbody>
</table>
<p>Если vlmcsd не отвечает на порту 1688, строка «KMS-сервер» покажет «Ошибка» и причину.</p>
HTML
        ],
        [
            'id' => 'd12', 'icon' => '🔑', 'title' => 'Права пользователей',
            'html' => <<<'HTML'
<h2>Что это</h2>
<p>Таблица прав: какое право что открывает в панели.</p>
<p>Права выдаются при добавлении пользователя и меняются кнопкой ✎. Для большинства разделов три уровня: 🚫 нет доступа, 👁️ только просмотр, ✏️ полный доступ. Для белых IP и уведомлений два состояния: ✅ и ❌.</p>
<h2>Таблица прав</h2>
<table>
<thead>
<tr>
<th>Право</th>
<th>Что открывает</th>
</tr>
</thead>
<tbody>
<tr>
<td>Группы: просмотр / редактирование</td>
<td>Раздел «Группы»: список групп; создание, переименование, цвета и значки, удаление</td>
</tr>
<tr>
<td>Устройства: просмотр / редактирование</td>
<td>Раздел «Устройства»: список и фильтры; добавление, правка, удаление, массовое перемещение</td>
</tr>
<tr>
<td>Логи: просмотр / редактирование</td>
<td>Журнал событий, «Статистика и отчёты», просмотр лога на главной</td>
</tr>
<tr>
<td>Пользователи: просмотр / редактирование</td>
<td>Раздел «Безопасность»: таблица пользователей; создание, права, пароли, удаление</td>
</tr>
<tr>
<td>Информация: просмотр</td>
<td>Раздел «Информация»</td>
</tr>
<tr>
<td>Инструменты: просмотр / редактирование</td>
<td>Раздел «Инструменты»: просмотр; экспорт, сброс, очистка лога и кэшей, обновление</td>
</tr>
<tr>
<td>IP Whitelist: управление</td>
<td>Список «Не подозрительные» и добавление адресов с главной страницы</td>
</tr>
<tr>
<td>Интеграции: просмотр / редактирование</td>
<td>Просмотр интеграций и настроек; изменение настроек AD, синхронизация пользователей, управление Webhook</td>
</tr>
<tr>
<td>API: просмотр / редактирование</td>
<td>Раздел «API»: документация; управление ключами и настройками</td>
</tr>
<tr>
<td>Уведомления</td>
<td>Раздел «Уведомления»: просмотр и изменение каналов доставки и правил</td>
</tr>
</tbody>
</table>
<h2>Полный администратор</h2>
<p>Пользователь со всеми правами считается полным администратором. Только он видит раздел «API» и меняет настройку «Скрывать IP-адреса от неавторизованных». Учётная запись <code>root</code> создаётся при установке с паролем <code>root</code>, и панель требует сменить его при первом входе.</p>
<h2>Права для доменных пользователей</h2>
<p>При входе через Active Directory новому пользователю выдаётся шаблон из «Интеграции → Active Directory / LDAP → Права новых пользователей»: «Просмотр: лог и устройства», «Просмотр: лог, устройства, группы, информация» или «Работа: просмотр и изменение устройств и групп». Потом права можно поменять вручную.</p>
HTML
        ],
    ],
    'en' => [
        [
            'id' => 'd00', 'icon' => '🏠', 'title' => 'Main page',
            'html' => <<<'HTML'
<p>🌐 Access: opens without login at <code>/vlmc.php</code>.</p>
<h2>What it is</h2>
<p>The monitoring page: who contacted the KMS server and when, which products were activated and which addresses look suspicious.</p>
<h2>How it works</h2>
<ol>
<li>The vlmcsd server writes every request to its log.</li>
<li>The panel reads the log and refreshes the page every few seconds.</li>
<li>Lists of products, unknown devices and suspicious IPs are built from the log.</li>
<li>A device from the unknown list can be added to the panel; an address from the suspicious list can be put on the whitelist.</li>
</ol>
<h2>What is on the page</h2>
<ul>
<li><strong>Request log.</strong> vlmcsd log lines: time, device, IP address, product. Updates automatically.</li>
<li><strong>Search and filters.</strong></li>
<li>The “Search text...” field and the “Search” and reset buttons.</li>
<li>Record type filter: “All events”, “Only connections”, “Only requests”.</li>
<li>The “All groups” filter shows only the devices of the selected group.</li>
<li><strong>Products.</strong> Microsoft products that were activated, with the number of devices and requests.</li>
<li><strong>Server status.</strong> An indicator based on server uptime: “Recently started” (under a day), “Active” (from a day to 30 days), “Stable” (over 30 days), “No data”. The tooltip shows the uptime.</li>
<li><strong>Suspicious IPs.</strong> Addresses used by devices that are not added to the panel, or addresses with unusual behaviour. Each row shows the country, number of connections, time span and actions:</li>
<li>copy the IP;</li>
<li>address information (country, region, city, provider, organization, time zone);</li>
<li>add the address to the whitelist (it stops appearing in this block; requires the “IP Whitelist: manage” permission);</li>
<li>block the address in the firewall (the 🚫 button appears only when the firewall is connected; the feature is currently under development).</li>
<li><strong>Unknown devices.</strong> Devices that contact the server but are not added to the panel: name, number of requests, last IP, first and last request. The ➕ button opens the “Add device” window: name, group and comment. Without logging in, a 🔒 lock is shown instead of the button.</li>
<li><strong>Language switch</strong> (Russian and English) and the “Settings” icon that leads to the control panel.</li>
</ul>
<h2>Hiding IP addresses</h2>
<p>If “Hide IP addresses from unauthorized visitors” is enabled in “Security → Users”, addresses in the log and in the unknown devices list are replaced with a blurred placeholder for visitors who are not logged in. Real addresses are not sent to the page at all. The “Suspicious IPs” block stays open.</p>
<h2>Geolocation</h2>
<p>The country and address data come from external services and are cached in <code>vlmcconf/cache/</code>. Clearing and refreshing the cache is in “Tools → Cache Management”.</p>
HTML
        ],
        [
            'id' => 'd01', 'icon' => '⚙️', 'title' => 'General Settings',
            'html' => <<<'HTML'
<h2>What it is</h2>
<p>The look of the panel and the path to the vlmcsd log.</p>
<h2>Theme</h2>
<p>16 themes: Dark, Light, Gray, Ash &amp; Flame, Night Elf, Corporate, Business Classic, Silver Standard, Dark Quartz, Cabernet Sauvignon, Spring, Winter, Summer, Autumn, Poker, Corporate Blue.</p>
<p>Below the list is the “Theme Preview” block (a button, success and error messages, sample text). The theme is applied with the “Apply Theme” button and is saved for both the panel and the main page.</p>
<h2>Language</h2>
<p>“Русский” or “English”. Applied with the “Save Language” button. Texts of background tasks and notifications also use the selected language.</p>
<h2>Log File</h2>
<ul>
<li><strong>Log file path</strong> — the path to the vlmcsd log on the server (by default <code>/var/log/vlmcsd.log</code>, or a <code>vlmcsd.log</code> link in the web folder).</li>
<li><strong>Status</strong> — “File found” or an error message (no file, no read permission).</li>
<li>The “Save Path” button. A path change is written to the “Event log” and can trigger the “KMS log path changed” notification.</li>
</ul>
HTML
        ],
        [
            'id' => 'd02', 'icon' => '👥', 'title' => 'Groups',
            'html' => <<<'HTML'
<h2>What it is</h2>
<p>A group joins devices (for example “Home”, “Work”, “Clients”) and gives them an icon and a colour.</p>
<h2>How it works</h2>
<ol>
<li>Create a group: name, icon, colour.</li>
<li>Add devices to it in the “Devices” section.</li>
<li>The group colour and icon are shown on the main page and in reports.</li>
<li>When a group is deleted, its devices are moved to a hidden group, not deleted.</li>
</ol>
<h2>Add Group</h2>
<ul>
<li><strong>Group name.</strong></li>
<li><strong>Icon</strong> — an emoji; the button opens the icon picker window.</li>
<li><strong>Group color.</strong></li>
<li>The “Add” button.</li>
</ul>
<h2>Existing Groups</h2>
<p>Each group card shows the icon, name, color and number of devices. The ✎ button opens the edit window:</p>
<ul>
<li>change the name, icon and color;</li>
<li>delete the group.</li>
</ul>
<p>When a group is renamed, its devices stay in it, and group filters in notification rules switch to the new name.</p>
<h2>Deleting a group</h2>
<p>The panel asks for confirmation first. Devices of the deleted group are not lost: they are moved to the service group <code>__orphaned__</code>, which is not shown in the group list. You can bring them back by moving them to a group in the “Devices” section.</p>
<p>The confirmation text in the panel says the same: devices are not deleted but moved to a hidden group.</p>
<p>Group actions are written to the “Event log” and can trigger notifications: “Group created”, “Group renamed”, “Group deleted”.</p>
HTML
        ],
        [
            'id' => 'd03', 'icon' => '📱', 'title' => 'Devices',
            'html' => <<<'HTML'
<h2>What it is</h2>
<p>A device is a computer or server that activates against your KMS server. The name comes from the vlmcsd log.</p>
<h2>How it works</h2>
<ol>
<li>A device contacts the KMS server and its name appears in the log.</li>
<li>Until it is added to the panel it is shown as “unknown” on the main page.</li>
<li>Add it with a name, a group and a comment.</li>
<li>Then you can search, filter, edit and move it between groups.</li>
</ol>
<h2>Add Device</h2>
<ul>
<li><strong>Device name</strong> — as written in the log.</li>
<li><strong>Group</strong> — one of the existing groups.</li>
<li><strong>Comment</strong> — optional, for example who owns the device.</li>
<li>The “Add” button. A repeated name in the same group is rejected.</li>
</ul>
<p>A device can also be added from the main page with the ➕ button in the unknown devices list.</p>
<h2>All Devices</h2>
<ul>
<li><strong>Filter</strong> by group (all groups or a specific one).</li>
<li><strong>Sort:</strong> by name, by group, by added date (oldest first or newest first).</li>
<li>The “Showing N of M” counter.</li>
<li>Each device has edit (name, group, comment) and delete buttons.</li>
</ul>
<h2>Bulk move</h2>
<ol>
<li>Tick devices or press “Select all”.</li>
<li>Choose the target group in the move list.</li>
<li>Press “Move” and confirm. The “Selected: N” counter shows how many devices are ticked.</li>
</ol>
<p>Moving keeps device comments and added dates.</p>
<h2>Log and notifications</h2>
<p>Adding, editing, moving and deleting are written to the “Event log”. Notification events exist for them: “Device added”, “Device edited”, “Devices moved to a group”, “Device deleted”. Devices that appear in the log but are not added to the panel are tracked by a separate event, “New device in the log” (background check).</p>
HTML
        ],
        [
            'id' => 'd04', 'icon' => '🔒', 'title' => 'Security',
            'html' => <<<'HTML'
<h2>What it is</h2>
<p>User accounts and permissions, login protection and restriction of panel access by IP.</p>
<h2>How it works</h2>
<ol>
<li>An administrator creates users and sets their permissions per section.</li>
<li>Login is protected by password requirements, a timeout and a limit on failed attempts.</li>
<li>In addition, panel access can be restricted to a list of addresses, and IPs on the main page can be hidden from unauthorized visitors.</li>
<li>All changes are written to the “Event log”.</li>
</ol>
<h2>“Users” tab</h2>
<h3>User Management</h3>
<p>A table: username, role (a set of permissions per section), created date, actions. The current user is marked “Current”.</p>
<ul>
<li><strong>Add User:</strong> username, password and permissions per section.</li>
<li><strong>✎</strong> — edit the user's permissions. <strong>🔑</strong> — change password. <strong>✕</strong> — delete the user.</li>
<li>Permissions are shown as icons. Most sections have three levels: no access 🚫, view 👁️, edit ✏️. IP whitelist and notifications have two states: ✅ and ❌. What each permission opens is described in <a href="#" data-doc="d12">“User permissions”</a>.</li>
<li>A user who has all permissions is a full administrator. Only that user sees the “API” section.</li>
<li>User changes are written to the “Event log” (passwords and tokens are never logged).</li>
</ul>
<h3>Password requirements</h3>
<p>At least 8 characters, uppercase and lowercase letters, digits and special characters. On the first login as <code>root</code>/<code>root</code> the panel requires a password change.</p>
<h3>Login timeout</h3>
<p>How many minutes without activity before the session ends: from 5 to 1440 (24 hours), default 30. Applied immediately; users who are already logged in continue with the new value.</p>
<h3>IP address privacy</h3>
<p>The “Hide IP addresses from unauthorized visitors” setting. When enabled, the main page without login shows blurred placeholders instead of addresses in the log and in the “Unknown devices” list. Real addresses are not sent to the page. The “Suspicious IPs” block stays open. Only an administrator can change this.</p>
<h3>Not suspicious (IP whitelist)</h3>
<p>Addresses from the “Not suspicious” list do not appear in the “Suspicious IPs” block on the main page. Add an address with the “➕ Add IP” button (“IP address” field and a comment) or from the main page. Requires the “IP Whitelist: manage” permission.</p>
<h2>“Access &amp; IP” tab</h2>
<h3>Panel IP whitelist</h3>
<p>The switch “Open the control panel only from the listed addresses”. When enabled, panel pages and login open only from the addresses and networks in the list (and from the server itself). Everyone else gets a 403 error with a page showing their address. The main page with the log and the API are not affected.</p>
<ul>
<li>“Your address” is shown. The “📍 Add my address” button adds it to the list so you do not lock yourself out.</li>
<li>The “Allowed addresses and networks” list: an address or network (IPv4 and IPv6, CIDR format) and a comment.</li>
<li>The panel will not let you save a setting that would cut off your own access.</li>
<li>Denials are written to the “Event log” (address, page, browser; no more than once per 10 minutes per address).</li>
<li>The address is taken from the connection (<code>REMOTE_ADDR</code>). Behind a reverse proxy (nginx, Cloudflare) all visitors appear with the proxy's address, so set the restriction on the proxy itself.</li>
</ul>
<h3>Emergency switch-off</h3>
<p>If you locked yourself out, create an empty file <code>panel_whitelist.off</code> in the data folder on the server (the <code>touch</code> command is shown in the panel) or set the environment variable <code>VLMC_PANEL_WHITELIST_OFF=1</code>. The restriction stops working at once. After fixing the list, delete the file manually (the <code>rm</code> command is shown too).</p>
<h2>“Firewall” tab</h2>
<p>Blocking IPs from the “Suspicious IPs” block is under development: the tab is covered by an “Under development” notice, and in “Information → Status” the Firewall row shows “Under development”.</p>
<p>The code already has: a backend choice (ufw, nftables, iptables), the blocking scope (all ports or only the panel port), a blocked address list, and the “Apply pending” and “Re-apply all” buttons. The web server must not change firewall rules directly, so a helper script and a sudo rule are needed; details and the reason for the pause are in backlog item 30.</p>
<h2>Important</h2>
<ul>
<li>Do not lock yourself out: before enabling “Open the control panel only from the listed addresses” add your address with “📍 Add my address”. If you still locked yourself out, lift the restriction with the <code>panel_whitelist.off</code> file.</li>
<li>The first login as <code>root</code>/<code>root</code> requires a password change: do not keep the default password.</li>
<li>Behind a reverse proxy (nginx, Cloudflare) all visitors appear with the proxy's address: set the IP restriction on the proxy itself.</li>
</ul>
HTML
        ],
        [
            'id' => 'd05', 'icon' => '🛠️', 'title' => 'Tools',
            'html' => <<<'HTML'
<h2>What it is</h2>
<p>Panel maintenance: project export, update, clearing the log and caches.</p>
<h2>How it works</h2>
<ol>
<li>The “Project Management” tab exports an archive and resets settings.</li>
<li>The “Update” tab checks GitHub and installs a new version.</li>
<li>The “Log Management” and “Cache Management” tabs clear data.</li>
<li>Dangerous actions ask for confirmation and are written to the “Event log”.</li>
</ol>
<h2>Project Management</h2>
<h3>Export Project</h3>
<p>Download a project archive. Three variants:</p>
<ul>
<li><strong>Clean project</strong> — file structure only, no logs and no settings.</li>
<li><strong>With settings</strong> — with the config and the user list, no logs.</li>
<li><strong>Full backup</strong> — everything, including log files.</li>
</ul>
<p>An export is written to the “Event log” and can trigger the “Project exported (panel backup)” notification. The archive with settings contains password hashes: keep it secret.</p>
<h3>Reset settings</h3>
<p>Returns settings to defaults (theme, language, groups, devices). The action cannot be undone, so the panel asks for confirmation. The “Panel configuration reset” event has high importance in notifications.</p>
<h2>Update</h2>
<ul>
<li><strong>Info block:</strong> repository (<code>AlexandreKz/Monitor-VLMCSD</code>), branch, current version, time of the last check, status.</li>
<li><strong>Buttons:</strong> “Check for updates”, “Update project” (opens a confirmation window with the file list, then “Yes, update”), “Check GitHub” (checks access and the certificate).</li>
<li><strong>Update type</strong> (two channels):</li>
<li>“Release (stable)” — files from the tag of the latest published release;</li>
<li>“Early access (by SHA)” — files from the <code>main</code> branch; new and changed files count as an update.</li>
<li><strong>GitHub token (optional):</strong> raises the GitHub request limit from 60 to 5000 per hour. Stored encrypted.</li>
<li><strong>Check for updates automatically (both channels):</strong> the “Enable automatic check” checkbox and an interval in hours.</li>
<li><strong>Automatic update</strong> — under development (backlog item 62).</li>
<li><strong>How installation goes:</strong> files are downloaded to a temporary folder and checked against the repository by SHA, then a backup of the files being replaced is made (in <code>backups/update_…</code> of the data folder) and the files are replaced. If something goes wrong, a rollback is performed. The <code>pic/</code> and <code>screen/</code> folders, settings and panel data are not updated, and files that are not in the repository are not deleted.</li>
<li><strong>Scheduled check:</strong> <code>update_check.php</code> exits with code 0 (no updates), 10 (an update is available) or 1 (check error). A cron line for it: <code>0 */6 * * * www-data php /var/www/html/vlmcconf/update_check.php &gt;/dev/null 2&gt;&amp;1</code>.</li>
<li>If you made your own edits to the panel files, early access will offer to replace them with the repository version: push your edits to the repository first.</li>
<li>The <strong>console</strong> at the bottom shows the progress of checks and installation.</li>
<li>If certificate verification fails, the section shows the “How to enable certificate verification” hint.</li>
</ul>
<p>The events “New panel version available”, “Panel updated” and “Panel update failed” can be subscribed to in “Notifications”.</p>
<h2>Log Management</h2>
<ul>
<li>Info block: log file, size, status, date of the first event.</li>
<li><strong>Clear by date:</strong> the “From” and “To” fields and the “Clear” button.</li>
<li><strong>Full clear:</strong> the “Clear all” checkbox, the “Log Backup” checkbox (save a copy first) and the “Full clear” button.</li>
<li>Clearing the log cannot be undone, so make a backup first. The action is written to the “Event log”; there is a notification event “KMS server log cleared”.</li>
</ul>
<h2>Cache Management</h2>
<ul>
<li><strong>GeoIP Cache:</strong> number of files and cache size; buttons “Clear Cache”, “Refresh Cache” (re-request data for all IPs from the log; may take a while, the window warns about it) and “Check” (cache statistics).</li>
<li><strong>Emoji Cache:</strong> cache status, “Clear Cache” (delete the file) and “Refresh Cache” (download the current emoji list from the internet).</li>
</ul>
<h2>Important</h2>
<ul>
<li>Resetting settings and clearing the log cannot be undone: make a “Full backup” first.</li>
<li>The “With settings” archive contains password hashes: keep it secret.</li>
<li>Make a backup before updating: if something fails the panel rolls the files back, but it is better to save the data in advance.</li>
</ul>
HTML
        ],
        [
            'id' => 'd06', 'icon' => '🔔', 'title' => 'Notifications',
            'html' => <<<'HTML'
<p>🔒 Access: the “Notifications: edit” permission or a full administrator. For other users the menu item is greyed out.</p>
<h2>What it is</h2>
<p>Messages about events in the panel and on the KMS server by email, Telegram and Bitrix24.</p>
<h2>How it works</h2>
<ol>
<li>An event occurs in the panel or is found by a background check.</li>
<li>A rule decides which events to report, where and how often.</li>
<li>A channel knows how to deliver the message (email, Telegram, Bitrix24).</li>
<li>The result of every delivery is visible on the “History” tab.</li>
</ol>
<h2>“Channels” tab</h2>
<p>A channel is a delivery method. The “Create channel” button opens a window. The table shows name, type, recipient, status (“Active” or disabled), and test and delete buttons.</p>
<p>The channel type is chosen in the window:</p>
<ul>
<li><strong>Email (SMTP):</strong> “SMTP server”, “Port”, “Encryption” (STARTTLS, SSL/TLS or none), “Login”, “Password”, “Sender”, “Recipients” (several addresses separated by commas).</li>
<li><strong>Telegram:</strong> “Bot token” and “Chat ID” (numeric chat ID or @channel; the bot must be added to the chat). The optional <strong>“Proxy (optional)”</strong> field is for places where Telegram is blocked: for example <code>socks5h://127.0.0.1:40000</code> (<code>socks5://</code>, <code>socks5h://</code>, <code>http://</code> and <code>https://</code> are accepted). The proxy applies only to Telegram; all other panel requests go directly. The PHP curl extension is required for a proxy. Setting up WARP is described in a separate guide.</li>
<li><strong>Bitrix24:</strong> “Incoming webhook” (an address from Bitrix24 → Developer resources → Other → Incoming webhook, <code>https://</code> only) and “Recipient” (a user ID or a chat like <code>chat123</code>).</li>
<li>The “Channel enabled” checkbox.</li>
<li>The “Test” button sends a test message; the result (success or the error text) is visible in the window and in the history.</li>
</ul>
<p>Passwords, tokens and webhooks are stored encrypted (the <code>notify.key</code> key in the data folder).</p>
<h2>“Rules” tab</h2>
<p>A rule answers “what about”, “where to” and “how often”. The “Create rule” button opens a window. The table shows name, events, channels, frequency, quiet hours, status.</p>
<p>In the rule window:</p>
<ul>
<li><strong>Name.</strong></li>
<li><strong>Events.</strong> A list by group (groups collapse; “Security and users” is expanded by default). There is a “Search events” field next to the “Events” heading. Some events have thresholds (for example the number of failed logins and in how many minutes).</li>
<li><strong>Delivery channels</strong> — one or more (channels are created on the “Channels” tab).</li>
<li><strong>Scope</strong> — “Groups” and “Devices”: an optional filter for events related to groups and devices. If nothing is selected, the rule applies to all groups and devices.</li>
<li><strong>Thresholds</strong> — settings for events with numeric conditions.</li>
<li><strong>Interval between notifications, min.</strong> At most one notification per this period for one rule and event; 0 — unlimited. Messages suppressed during the period are counted and added to the next one as “(+N)”.</li>
<li><strong>Quiet hours</strong> — “from” and “to” fields: no messages are sent in this period.</li>
<li><strong>Rule is active</strong> — a switch to stop a rule without deleting it.</li>
</ul>
<p>By default the panel offers 13 ready-made rules (disabled, no channels). Deleted ready-made rules do not come back.</p>
<h2>“History” tab</h2>
<p>A log of deliveries: time, rule, event, channels, result (success or the reason for the error). Test messages also appear in the history.</p>
<h2>“Templates” tab</h2>
<p>A “Section under development” placeholder. The tab is left on purpose as a reminder of future work (backlog item 69).</p>
<h2>Events</h2>
<p>44 events in six groups. Importance (“high”, “medium”, “low”) helps to pick the main ones.</p>
<h3>Security and users</h3>
<table>
<thead>
<tr>
<th>Event</th>
<th>Importance</th>
</tr>
</thead>
<tbody>
<tr>
<td>Series of failed logins</td>
<td>high</td>
</tr>
<tr>
<td>Access denied</td>
<td>medium</td>
</tr>
<tr>
<td>User added</td>
<td>medium</td>
</tr>
<tr>
<td>User deleted</td>
<td>high</td>
</tr>
<tr>
<td>User or permissions changed</td>
<td>medium</td>
</tr>
<tr>
<td>API key created</td>
<td>medium</td>
</tr>
<tr>
<td>API key deleted</td>
<td>medium</td>
</tr>
<tr>
<td>Panel IP restriction changed</td>
<td>high</td>
</tr>
<tr>
<td>User password changed</td>
<td>medium</td>
</tr>
<tr>
<td>Successful panel sign-in</td>
<td>low</td>
</tr>
<tr>
<td>Firewall changed (IP block, settings)</td>
<td>high</td>
</tr>
<tr>
<td>Allowed IP list changed</td>
<td>medium</td>
</tr>
<tr>
<td>Panel settings changed</td>
<td>medium</td>
</tr>
</tbody>
</table>
<h3>Panel changes</h3>
<table>
<thead>
<tr>
<th>Event</th>
<th>Importance</th>
</tr>
</thead>
<tbody>
<tr>
<td>Device added</td>
<td>low</td>
</tr>
<tr>
<td>Devices moved to a group</td>
<td>low</td>
</tr>
<tr>
<td>Group renamed</td>
<td>low</td>
</tr>
<tr>
<td>Group deleted</td>
<td>medium</td>
</tr>
<tr>
<td>Device deleted</td>
<td>medium</td>
</tr>
<tr>
<td>Panel configuration reset</td>
<td>high</td>
</tr>
<tr>
<td>Group created</td>
<td>low</td>
</tr>
<tr>
<td>Device edited</td>
<td>low</td>
</tr>
<tr>
<td>KMS log path changed</td>
<td>medium</td>
</tr>
<tr>
<td>Project exported (panel backup)</td>
<td>high</td>
</tr>
<tr>
<td>KMS server log cleared</td>
<td>medium</td>
</tr>
<tr>
<td>Event log cleared</td>
<td>high</td>
</tr>
</tbody>
</table>
<h3>Updates</h3>
<table>
<thead>
<tr>
<th>Event</th>
<th>Importance</th>
</tr>
</thead>
<tbody>
<tr>
<td>New panel version available</td>
<td>low</td>
</tr>
<tr>
<td>Panel updated</td>
<td>low</td>
</tr>
<tr>
<td>Panel update failed</td>
<td>medium</td>
</tr>
</tbody>
</table>
<h3>Devices</h3>
<table>
<thead>
<tr>
<th>Event</th>
<th>Importance</th>
</tr>
</thead>
<tbody>
<tr>
<td>New device in the log</td>
<td>low</td>
</tr>
<tr>
<td>Device not seen for a long time</td>
<td>low</td>
</tr>
<tr>
<td>Device with many IPs</td>
<td>medium</td>
</tr>
<tr>
<td>Many devices from one IP</td>
<td>medium</td>
</tr>
<tr>
<td>Request from a new country</td>
<td>medium</td>
</tr>
</tbody>
</table>
<h3>Service and system</h3>
<table>
<thead>
<tr>
<th>Event</th>
<th>Importance</th>
</tr>
</thead>
<tbody>
<tr>
<td>KMS service stopped</td>
<td>high</td>
</tr>
<tr>
<td>KMS service is running again</td>
<td>low</td>
</tr>
<tr>
<td>Log not updated for a long time</td>
<td>medium</td>
</tr>
<tr>
<td>Log is too big</td>
<td>low</td>
</tr>
<tr>
<td>Low disk space for panel data</td>
<td>medium</td>
</tr>
<tr>
<td>Background task failed</td>
<td>medium</td>
</tr>
</tbody>
</table>
<h3>Integrations</h3>
<table>
<thead>
<tr>
<th>Event</th>
<th>Importance</th>
</tr>
</thead>
<tbody>
<tr>
<td>AD/LDAP unavailable</td>
<td>high</td>
</tr>
<tr>
<td>AD/LDAP available again</td>
<td>low</td>
</tr>
<tr>
<td>AD/LDAP service account rejected</td>
<td>medium</td>
</tr>
<tr>
<td>Integration changed (webhook, cloud, server)</td>
<td>medium</td>
</tr>
<tr>
<td>AD/LDAP settings changed</td>
<td>medium</td>
</tr>
</tbody>
</table>
<h2>Where events come from</h2>
<ul>
<li><strong>The event log.</strong> Most events relate to user actions: login, changes to settings, groups, devices, API keys, integrations.</li>
<li><strong>The <code>notify_watch</code> background check</strong> (every 5 minutes): new devices, devices not seen for a long time, several IPs for one device, several devices from one IP, a request from a new country, KMS service stopped or running again, log not updated or too big, low disk space. It is enabled only if there is a rule for such events.</li>
<li><strong>The update check:</strong> “New panel version available” fires once per version, separately for the release and early access channels.</li>
<li><strong>Background task failures</strong> and AD/LDAP events.</li>
<li>The same events can be sent to external systems through Webhook (the “Integrations” tab).</li>
</ul>
<p>For the background check to run without opening the panel, set up an OS scheduler job (see “Event log → Background”).</p>
<h2>Important</h2>
<ul>
<li>The encryption key <code>notify.key</code> lives in the data folder. Without it saved passwords, tokens and webhooks cannot be decrypted: keep it together with data backups.</li>
<li>Background events (“New device in the log” and others) work only with the OS scheduler job or while the panel is open (see “Event log → Background”).</li>
</ul>
HTML
        ],
        [
            'id' => 'd07', 'icon' => '🔌', 'title' => 'Integrations',
            'html' => <<<'HTML'
<h2>What it is</h2>
<p>Connections between the panel and external systems: a user directory, backup storages, webhooks and other panels.</p>
<h2>How it works</h2>
<ol>
<li>Choose the tab of the integration you need and enable it.</li>
<li>Enter the connection parameters and press the test.</li>
<li>An enabled integration works in the background; its state is visible in “Information → Status”.</li>
</ol>
<h2>Active Directory / LDAP</h2>
<p>Login of domain users and import of accounts from Active Directory, OpenLDAP and other LDAP servers. The PHP <code>ldap</code> extension is required (<code>sudo apt install php8.5-ldap</code> and an Apache restart). Without it a warning is shown at the top of the tab, and “Information → Status” shows “Warning”.</p>
<p>The tab has three parts: “Connection and sign-in”, “Users”, “Log”.</p>
<h3>Connection</h3>
<ul>
<li><strong>Enable directory integration.</strong></li>
<li><strong>LDAP Server</strong> — the full name or IP of the domain controller.</li>
<li><strong>Security and port</strong> — no encryption, StartTLS or LDAPS, and the port.</li>
<li><strong>Verify the server certificate.</strong></li>
<li><strong>Base DN</strong> — the root for user search.</li>
<li><strong>Service account</strong> and its password — a read-only account for the directory.</li>
<li><strong>Backup accounts</strong> (up to 5) — tried in order if the main one is rejected.</li>
<li><strong>Domain (UPN), optional</strong> — needed when no service account is set or users log in as name@domain.</li>
<li>Buttons “Save” and “Test connection”. The test runs for each service account separately: ✅ or ❌ next to each account, details in the log.</li>
</ul>
<h3>Login and users</h3>
<ul>
<li><strong>Allow login of domain users.</strong> The local panel database is checked first, then the directory. A domain user cannot take over a local account (for example <code>root</code>).</li>
<li><strong>Create the user at first login.</strong> When off, only imported users can log in.</li>
<li><strong>Rights of new users</strong> — a permission template (“View: log and devices”, “View: log, devices, groups, information”, “Work: view and edit devices and groups”). Permissions can be changed later in “Security”.</li>
<li><strong>Login attribute.</strong></li>
<li><strong>Required group.</strong> The “Select groups…” button opens a window with directory groups (search by name, or enter a name or DN by hand). Members of any selected group may sign in. If nothing is selected, any directory user is allowed.</li>
<li><strong>Extra filter</strong> — an LDAP search condition, for example <code>(department=IT)</code>.</li>
<li><strong>Timeout</strong> in seconds.</li>
</ul>
<h3>Directory users</h3>
<p>Search by name, login or email, with the “Import selected” and “Refresh data” buttons. The table shows login, name, email, groups and the “In panel” mark.</p>
<h3>Log</h3>
<p>A log of checks and sync: time, operation, result, details (the reason for an error). Every 12 hours a background task checks the connection and updates the “Connected” or “Not connected” state in “Information → Status”.</p>
<h2>Cloud Storage</h2>
<p>External storage for backups: up to 10 storages of types SFTP, FTP, FTPS, WebDAV and S3.</p>
<p>Fields: name, storage type, server (“Server URL” for WebDAV and S3), port, user / access key, password / secret key, folder, bucket and region (for S3), what to back up (settings, users, KMS log, event log), schedule, time, number of copies to keep, enabled.</p>
<p>The password is stored encrypted. There is a connection test.</p>
<p>Currently the settings and the connection test work. Automatic backup creation and upload on schedule are not ready yet (backlog item 31). FTP and FTPS need the PHP <code>ftp</code> extension.</p>
<h2>Webhook</h2>
<p>The panel sends a POST request to your address on the selected events. Up to 20 addresses.</p>
<p>Fields: name, URL (http/https), format (JSON, Slack or plain text), secret for signing (optional), timeout in seconds, events, enabled.</p>
<ul>
<li>If a secret is set, the request body is signed (HMAC-SHA256) and the signature is sent in the <code>X-KMS-Signature</code> header.</li>
<li>The event list is the same as in “Notifications” (44 events).</li>
<li>The table shows the time and result of the last delivery. There is a test delivery.</li>
<li>To send to Bitrix24 use the “Bitrix24” notification channel.</li>
</ul>
<h2>Multi-server</h2>
<p>The tab is covered by an “Under development” notice. It is designed for adding other KMS Monitor panels: on adding, the panel connects to the given server and signs in with your login and password (fields: name, panel address, login, password), and the table shows the version and the check time. This is a draft; the section will appear in one of the next versions.</p>
<h2>Important</h2>
<ul>
<li>Cloud Storage does not create backups automatically yet: only settings and the connection test work.</li>
<li>“Multi-server” and “Firewall” are under development: the tabs are covered by a notice.</li>
<li>AD/LDAP needs the PHP <code>ldap</code> extension.</li>
</ul>
HTML
        ],
        [
            'id' => 'd08', 'icon' => '🖥️', 'title' => 'API',
            'html' => <<<'HTML'
<p>🔒 Access: full administrator only.</p>
<h2>What it is</h2>
<p>A read-only REST API: panel data can be pulled from Zabbix, Grafana and your own scripts.</p>
<p>API address: <code>https://your-server/vlmcconf/api.php/v1</code>. Use HTTPS only: the key is sent in clear text.</p>
<h2>How it works</h2>
<ol>
<li>Enable the API on the “Settings” tab.</li>
<li>Create a key on the “Keys” tab and save it: it is shown once.</li>
<li>Pass the key in a request header and get data as JSON.</li>
<li>The “Documentation” tab describes all methods with examples.</li>
</ol>
<h2>“Keys” tab</h2>
<p>A key table: name, key (the beginning is shown), access scope, allowed addresses, expiry, last used, status. The “New key” button opens a window:</p>
<ul>
<li><strong>Name</strong> (for example Zabbix, Grafana).</li>
<li><strong>Access</strong> — scopes: Status, Devices, Groups, Log, Statistics, Reports, Security, Event journal.</li>
<li><strong>Allowed addresses</strong> — IP addresses and networks (CIDR) from which the key may be used; empty means no restriction.</li>
<li><strong>Expires</strong> — an expiry date (calendar).</li>
</ul>
<p>After creation the key (<code>kms_…</code>) is shown once and cannot be recovered later: the panel stores only a hash. Keys can be disabled, enabled and deleted. Creating, changing and deleting are written to the “Event log”.</p>
<h2>“Settings” tab</h2>
<ul>
<li><strong>Enable the API</strong> — the main switch. Applied immediately.</li>
<li><strong>Rate limit</strong> — requests per minute per key (default 60, 0 — unlimited). When exceeded, the answer is 429 with a <code>Retry-After</code> header; the remainder is visible in the <code>X-RateLimit-*</code> headers.</li>
<li><strong>Allowed origins (CORS)</strong> — one address per line or <code>*</code>. Needed only for requests from a browser; closed by default.</li>
</ul>
<p>Brute-force protection: 20 wrong keys per minute from one address block that address. The panel IP restriction does not apply to the API: keys have their own address lists.</p>
<h2>How to use</h2>
<p>Pass the key in the <code>Authorization: Bearer kms_…</code> or <code>X-API-Key</code> header. Example:</p>
<pre><code class="language-text">curl -H &quot;Authorization: Bearer kms_…&quot; https://your-server/vlmcconf/api.php/v1/status
</code></pre>
<p>Only GET requests are supported.</p>
<h2>“Documentation” tab</h2>
<p>A “Search and filters” block (search by methods, filters by method type and by access scope, an <code>openapi.json</code> button for Swagger Editor and Postman) and a “Methods” block (description, a request and response example with a copy button).</p>
<h3>Methods (22)</h3>
<table>
<thead>
<tr>
<th>Scope</th>
<th>Methods</th>
</tr>
</thead>
<tbody>
<tr>
<td>Status</td>
<td><code>/v1/status</code> (version, log state), <code>/v1/health</code> (the result of the “Status” tab, handy for monitoring)</td>
</tr>
<tr>
<td>Devices</td>
<td><code>/v1/devices</code>, <code>/v1/devices/{name}</code>, <code>/v1/devices/{name}/ips</code></td>
</tr>
<tr>
<td>Groups</td>
<td><code>/v1/groups</code>, <code>/v1/groups/{name}</code></td>
</tr>
<tr>
<td>Log</td>
<td><code>/v1/log</code> (filters device, since, limit)</td>
</tr>
<tr>
<td>Statistics</td>
<td><code>/v1/stats</code></td>
</tr>
<tr>
<td>Security</td>
<td><code>/v1/unknown-devices</code>, <code>/v1/whitelist-ips</code></td>
</tr>
<tr>
<td>Event journal</td>
<td><code>/v1/audit</code></td>
</tr>
<tr>
<td>Reports</td>
<td><code>/v1/reports/device-ips</code>, <code>ip-devices</code>, <code>multi-ip-devices</code>, <code>shared-ips</code>, <code>top-devices</code>, <code>inactive-devices</code>, <code>new-devices</code>, <code>products</code>, <code>countries</code>, <code>by-country</code></td>
</tr>
</tbody>
</table>
<p>The description is open without a key: <code>/vlmcconf/api.php/v1/openapi.json</code>.</p>
<h2>Important</h2>
<ul>
<li>The key is shown once and cannot be recovered later: save it at once.</li>
<li>Use the API over HTTPS only: the key is sent in clear text.</li>
<li>Do not give keys more access scopes than needed and restrict them by IP.</li>
</ul>
HTML
        ],
        [
            'id' => 'd09', 'icon' => '📊', 'title' => 'Statistics and reports',
            'html' => <<<'HTML'
<h2>What it is</h2>
<p>A summary of the vlmcsd log: totals, activity charts and ready-made reports.</p>
<h2>How it works</h2>
<ol>
<li>The panel calculates data from the log.</li>
<li>The “Statistics” tab shows totals, tops and charts.</li>
<li>On the “Reports” tab choose a report, set a parameter and press “Show”.</li>
<li>The result can be exported to XLSX or PDF.</li>
</ol>
<h2>“Statistics” tab</h2>
<ul>
<li><strong>Cards:</strong> “Total devices”, “Total groups”, “Total requests”.</li>
<li><strong>By groups</strong> — how many devices are in each group.</li>
<li><strong>Top IP addresses</strong> and <strong>Top countries</strong> — the most frequent request sources. The country is determined by IP and taken from the geolocation cache (it can be cleared and refreshed in “Tools → Cache Management”). If there is no data, “No IP data available” or “No country data available” is shown.</li>
<li><strong>Overall activity</strong> — a chart of requests by period. The switch is “Days”, “Weeks”, “Months”.</li>
<li><strong>By device</strong> — the same chart for a selected device.</li>
</ul>
<h2>“Reports” tab</h2>
<p>Choose a “Report”, enter a parameter if needed and press “Show”. Under the table are the “XLSX” and “PDF” buttons for exporting the result.</p>
<table>
<thead>
<tr>
<th>Report</th>
<th>What it shows</th>
<th>Parameter</th>
</tr>
</thead>
<tbody>
<tr>
<td>IP addresses of a device</td>
<td>All addresses the device connected from</td>
<td>Device name (suggestions come from the log)</td>
</tr>
<tr>
<td>Devices on an IP</td>
<td>All devices that connected from the selected address</td>
<td>IP address</td>
</tr>
<tr>
<td>Devices with several IPs</td>
<td>Devices that changed IP: network change, VPN, a laptop in different offices</td>
<td>—</td>
</tr>
<tr>
<td>IPs with several devices</td>
<td>Addresses with several devices behind them: NAT, an office or a suspicious source</td>
<td>—</td>
</tr>
<tr>
<td>Top devices by requests</td>
<td>The 50 most active devices</td>
<td>—</td>
</tr>
<tr>
<td>Long-inactive devices</td>
<td>Devices missing from the log for longer than the chosen period</td>
<td>Period</td>
</tr>
<tr>
<td>New devices</td>
<td>Devices that first appeared in the log within the chosen period</td>
<td>Period</td>
</tr>
<tr>
<td>Products</td>
<td>Unique devices and requests per product</td>
<td>—</td>
</tr>
<tr>
<td>By country</td>
<td>IP addresses of the selected country and the devices from them</td>
<td>Country (from the geolocation cache)</td>
</tr>
</tbody>
</table>
<p>If nothing is found in the log, “Nothing found in the log” is shown. All reports are also available through the <a href="#" data-doc="d08">API</a>.</p>
HTML
        ],
        [
            'id' => 'd10', 'icon' => '📜', 'title' => 'Event log',
            'html' => <<<'HTML'
<h2>What it is</h2>
<p>A log of user actions and the state of the panel's background tasks.</p>
<h2>How it works</h2>
<ol>
<li>The panel records logins, settings changes and other actions.</li>
<li>On the “General” tab records can be searched, filtered and exported to CSV.</li>
<li>Old records are removed after the retention period.</li>
<li>The “Background” tab shows how periodic tasks are running.</li>
</ol>
<h2>“General” tab</h2>
<p>A table: Time, User, IP, Action, Details, Result.</p>
<ul>
<li><strong>Filters:</strong> “Search” (by user, IP, action, description), “User”, “Action”, “Result”, “Period” (today, 7 days, 30 days, all time or your own dates), reset.</li>
<li><strong>Result:</strong> “Success”, “Error”, “Denied”.</li>
<li><strong>Refresh:</strong> the 🔄 button and the “Auto-refresh” checkbox. “Records per page” changes the page size.</li>
<li><strong>Export CSV</strong> — export of the found records.</li>
<li>At the bottom: “Records found” and page navigation.</li>
</ul>
<p>What goes into the log: login, logout and failed login attempts, password, theme and language changes, actions with groups, devices, users and permissions, configuration reset, clearing of the log and caches, project export, changes to integrations and API keys, panel updates, IP whitelist denials. Passwords and tokens are never logged.</p>
<h3>Retention period</h3>
<p>How many days to keep records (default 90) and the “Save” button. Old records are removed automatically by a background task. Below the setting you can see the number of records, the size and the retention.</p>
<h3>Clear log</h3>
<p>The “Clear log” button opens a confirmation window. Clearing cannot be undone; the clearing itself is written to the new log.</p>
<h2>“Background” tab</h2>
<h3>Background tasks</h3>
<p>Tasks run automatically: when the panel is opened (only overdue ones, at most once a minute) and on the OS schedule. The table shows: Task, Period, Last run, Result, Next.</p>
<table>
<thead>
<tr>
<th>Task</th>
<th>Period</th>
<th>What it does</th>
</tr>
</thead>
<tbody>
<tr>
<td>Update check</td>
<td>as set in “Tools → Update”</td>
<td>looks for a new panel version</td>
</tr>
<tr>
<td>AD/LDAP status</td>
<td>12 hours</td>
<td>checks the connection to the directory</td>
</tr>
<tr>
<td>Notification watch</td>
<td>15 minutes</td>
<td>new devices in the log, suspicious IPs, vlmcsd stopped and so on</td>
</tr>
<tr>
<td>Log pruning</td>
<td>once a day</td>
<td>removes records older than the retention period</td>
</tr>
</tbody>
</table>
<h3>Run on OS schedule</h3>
<p>Shows the <code>cron</code> command (the <code>/etc/cron.d/kms-monitor</code> file, running <code>cron.php</code> every 15 minutes). It can be copied or written with the button in the panel. The panel writes the same file as the installer and replaces it: this is fine, because <code>cron.php</code> runs the update check itself.</p>
<p>Without <code>cron</code> tasks run only when somebody opens the panel. In “Information → Status” the “OS scheduler (cron)” row then shows “Warning”.</p>
<h3>Run history</h3>
<p>“Time”, “Task”, “Result”, “From” (panel or cron), “ms” (duration).</p>
<h2>Important</h2>
<ul>
<li>Clearing the log cannot be undone.</li>
<li>Without the OS scheduler job, background tasks run only while the panel is open.</li>
</ul>
HTML
        ],
        [
            'id' => 'd11', 'icon' => 'ℹ️', 'title' => 'Information',
            'html' => <<<'HTML'
<h2>What it is</h2>
<p>A reference about the server and the project: file structure, data files and a component health check.</p>
<h2>Server</h2>
<p>OS, kernel, host, server IP, web server, PHP version, uptime, server time.</p>
<h2>Project structure</h2>
<p>The project file tree: name, type, size, date. At the top is the line “Updated at …: files N, changed N” and a refresh button. Colour hints show feature readiness: 95–100% stable, 85–94% working, below 85% under development.</p>
<h2>Data files</h2>
<p>The data folder and a list of service files: name, purpose, full path, status “not created” or size. Among them:</p>
<ul>
<li><code>vlmcconf_config.json</code> — panel settings;</li>
<li><code>users.json</code> — accounts and permissions;</li>
<li><code>audit.log</code> — the event log;</li>
<li><code>notify.json</code>, <code>notify.key</code>, <code>notify_history.jsonl</code> — notification channels and rules, the secret encryption key, delivery history;</li>
<li><code>update_state.json</code>, <code>github_token.json</code>, <code>update.lock</code> — update state, the GitHub token (encrypted), a lock;</li>
<li><code>config_changes.log</code>, <code>users_changes.log</code> — change traces;</li>
<li><code>backups/</code> — backups of settings and users.</li>
</ul>
<p>The <code>notify.key</code> key is needed to decrypt secrets: do not lose it when moving data.</p>
<h3>Migration, rollback and backups</h3>
<ul>
<li><strong>Migration.</strong> On the first opening of the panel after the data folder is created, <code>users.json</code> and <code>vlmcconf_config.json</code> are copied, verified and removed from the old place (<code>vlmcconf/</code>). A “before migration” copy stays in the <code>backups/</code> subfolder of the new folder.</li>
<li><strong>Update and rollback.</strong> A panel update does not touch the data: it lives outside the project folder. Do not go back to a panel version that does not know about the new folder: it will not find <code>users.json</code> in the old place and will create a new <code>root</code> user. For a backup, save the whole data folder.</li>
<li><strong>Copies before changes.</strong> Before each change to the settings and the user list the panel saves a copy to <code>backups/config/</code> and <code>backups/users/</code> (the last 50 of each). If a file is damaged, the panel restores it from the latest copy and keeps the damaged one next to it (<code>*.broken</code>). Every save is written to <code>config_changes.log</code> and <code>users_changes.log</code> (the SHRINK mark means the number of entries decreased).</li>
<li>If <code>users.json</code> is missing but copies exist, users are restored from a copy. To deliberately reset the <code>root</code> password, delete <code>users.json</code> and the <code>backups/users</code> folder.</li>
<li>The <code>vlmcconf/cache</code> folder still stays in its old place.</li>
</ul>
<h2>Status</h2>
<p>The “Check again” button. A table: Component, Status, Details, Parameters. Statuses: “Working”, “Warning”, “Error”, “Disabled”.</p>
<table>
<thead>
<tr>
<th>Group</th>
<th>Components</th>
</tr>
</thead>
<tbody>
<tr>
<td>Core</td>
<td>KMS server (vlmcsd, port 1688), vlmcsd log, data folder, configuration and users, PHP and extensions</td>
</tr>
<tr>
<td>Integrations</td>
<td>Active Directory / LDAP, Webhook, Cloud Storage, API, Multi-server, Notifications</td>
</tr>
<tr>
<td>Security</td>
<td>Panel access restriction, Firewall (“Under development”)</td>
</tr>
<tr>
<td>Maintenance</td>
<td>OS scheduler (cron), background tasks, updates, event log</td>
</tr>
</tbody>
</table>
<p>If vlmcsd does not answer on port 1688, the “KMS server” row shows “Error” and the reason.</p>
HTML
        ],
        [
            'id' => 'd12', 'icon' => '🔑', 'title' => 'User permissions',
            'html' => <<<'HTML'
<h2>What it is</h2>
<p>A table of permissions: which permission opens what in the panel.</p>
<p>Permissions are given when adding a user and changed with the ✎ button. Most sections have three levels: 🚫 no access, 👁️ view only, ✏️ full access. IP whitelist and notifications have two states: ✅ and ❌.</p>
<h2>Permissions table</h2>
<table>
<thead>
<tr>
<th>Permission</th>
<th>What it opens</th>
</tr>
</thead>
<tbody>
<tr>
<td>Groups: view / edit</td>
<td>The “Groups” section: the group list; creating, renaming, colours and icons, deleting</td>
</tr>
<tr>
<td>Devices: view / edit</td>
<td>The “Devices” section: the list and filters; adding, editing, deleting, bulk move</td>
</tr>
<tr>
<td>Logs: view / edit</td>
<td>The event log, “Statistics and reports”, viewing the log on the main page</td>
</tr>
<tr>
<td>Users: view / edit</td>
<td>The “Security” section: the user table; creating, permissions, passwords, deleting</td>
</tr>
<tr>
<td>Information: view</td>
<td>The “Information” section</td>
</tr>
<tr>
<td>Tools: view / edit</td>
<td>The “Tools” section: viewing; export, reset, clearing the log and caches, update</td>
</tr>
<tr>
<td>IP Whitelist: manage</td>
<td>The “Not suspicious” list and adding addresses from the main page</td>
</tr>
<tr>
<td>Integrations: view / edit</td>
<td>Viewing integrations and settings; changing AD settings, syncing users, managing webhooks</td>
</tr>
<tr>
<td>API: view / edit</td>
<td>The “API” section: documentation; managing keys and settings</td>
</tr>
<tr>
<td>Notifications</td>
<td>The “Notifications” section: viewing and changing delivery channels and rules</td>
</tr>
</tbody>
</table>
<h2>Full administrator</h2>
<p>A user with all permissions is a full administrator. Only they see the “API” section and can change the “Hide IP addresses from unauthorized visitors” setting. The <code>root</code> account is created at installation with the password <code>root</code>, and the panel requires changing it on the first login.</p>
<h2>Permissions of domain users</h2>
<p>When logging in through Active Directory a new user gets the template from “Integrations → Active Directory / LDAP → Rights of new users”: “View: log and devices”, “View: log, devices, groups, information” or “Work: view and edit devices and groups”. Permissions can be changed manually afterwards.</p>
HTML
        ],
    ],
];

// Карточки описания разделов для скачиваемого файла
function docDescPartHtml($lang) {
    global $docDesc;
    $items = $docDesc[$lang] ?? [];
    if (!$items) {
        return '';
    }
    $head = $lang === 'en' ? 'Panel sections and options' : 'Разделы и опции панели';
    $out = '<div class="section-title" style="margin: 40px 0 20px;"><span>📖</span> ' . $head . '</div>';
    foreach ($items as $d) {
        $out .= '<div class="section-card"><div class="section-title"><span>' . $d['icon'] . '</span> '
            . htmlspecialchars($d['title'], ENT_QUOTES, 'UTF-8') . '</div><div class="doc-desc">' . $d['html'] . '</div></div>';
    }
    return $out;
}

function getFullDownloadHtml() {
    global $docWebRu, $docWebEn;
    
    $webRuJson = json_encode($docWebRu . docDescPartHtml('ru'));
    $webEnJson = json_encode($docWebEn . docDescPartHtml('en'));
    
    return '<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KMS Monitor — Полное руководство по развёртыванию</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        html, body {
            height: 100%;
            overflow: hidden;
        }
        
        body {
            background: #1a2634;
            font-family: \'Segoe UI\', \'Inter\', -apple-system, BlinkMacSystemFont, Roboto, sans-serif;
            padding: 20px;
            color: #e1e9f0;
            line-height: 1.6;
            height: 100%;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: #1f2e3c;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
            border: 1px solid #33485d;
            display: flex;
            flex-direction: column;
            height: 100%;
            overflow: hidden;
        }
        
        .header {
            padding: 24px 30px 0 30px;
            flex-shrink: 0;
        }
        
        h1 {
            font-size: 28px;
            margin-bottom: 10px;
            color: #fff;
            border-left: 4px solid #3b82f6;
            padding-left: 20px;
        }
        
        .version {
            color: #8aa0bb;
            margin-bottom: 20px;
            padding-left: 24px;
            font-size: 14px;
        }
        
        .tabs-wrapper {
            padding: 0 30px;
            flex-shrink: 0;
        }
        
        .lang-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        
        .lang-btn {
            background: none;
            border: none;
            padding: 8px 20px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            color: #8aa0bb;
            border-radius: 6px;
            transition: all 0.2s;
        }
        
        .lang-btn.active {
            background: #3b82f6;
            color: white;
        }
        
        .lang-btn:hover:not(.active) {
            background: #2d3f52;
            color: #e1e9f0;
        }
        
        .doc-content {
            background: #0f1a2f;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #33485d;
            margin: 0 30px 20px 30px;
            overflow-y: auto;
            flex: 1;
        }
        
        .doc-content::-webkit-scrollbar {
            width: 8px;
        }
        .doc-content::-webkit-scrollbar-track {
            background: #1f2e3c;
            border-radius: 4px;
        }
        .doc-content::-webkit-scrollbar-thumb {
            background: #3b82f6;
            border-radius: 4px;
        }
        .doc-content::-webkit-scrollbar-thumb:hover {
            background: #2563eb;
        }
        
        .section-card {
            background: #0f1a2f;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 25px;
            border: 2px solid #33485d;
        }
        
        .section-title {
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #3b82f6;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid #33485d;
            padding-bottom: 12px;
        }
        
        .section-title span {
            font-size: 26px;
        }
        
        .code-block {
            background: #0a0f1a;
            border: 1px solid #2d3f52;
            border-radius: 8px;
            padding: 14px;
            margin: 12px 0;
            overflow-x: auto;
            font-family: \'JetBrains Mono\', monospace;
            font-size: 13px;
            color: #d6e2f0;
        }
        
        .note {
            background: #2d3f52;
            border-left: 4px solid #f39c12;
            padding: 12px 16px;
            margin: 15px 0;
            border-radius: 6px;
        }
        
        .warning {
            background: #4a2a2a;
            border-left: 4px solid #e74c3c;
            padding: 12px 16px;
            margin: 15px 0;
            border-radius: 6px;
        }
        
        .success {
            background: #1e3a2b;
            border-left: 4px solid #2ecc71;
            padding: 12px 16px;
            margin: 15px 0;
            border-radius: 6px;
        }
        
        .file-tree {
            background: #0a0f1a;
            padding: 16px 20px;
            border-radius: 8px;
            font-family: monospace;
            font-size: 12px;
            line-height: 1.8;
        }
        
        .doc-desc { line-height: 1.6; font-size: 14.5px; }
        .doc-desc h2 { font-size: 17px; margin: 26px 0 12px; padding-bottom: 8px; border-bottom: 1px solid #33485d; color: #e1e9f0; }
        .doc-desc h2:first-child { margin-top: 0; }
        .doc-desc h3 { font-size: 15px; margin: 18px 0 6px; }
        .doc-desc p { margin: 8px 0; }
        .doc-desc ul, .doc-desc ol { margin: 8px 0; padding-left: 24px; }
        .doc-desc code { background: #2d3f52; padding: 1px 5px; border-radius: 4px; font-size: 13px; font-family: monospace; }
        .doc-desc pre { background: #0a0f1a; border: 1px solid #2d3f52; padding: 12px; border-radius: 8px; overflow-x: auto; }
        .doc-desc pre code { background: none; padding: 0; }
        .doc-desc table { border-collapse: collapse; margin: 10px 0; display: block; overflow-x: auto; }
        .doc-desc th, .doc-desc td { border: 1px solid #33485d; padding: 6px 10px; text-align: left; vertical-align: top; }
        .doc-desc th { background: #1f2e3c; }
        
        a { color: #3b82f6; text-decoration: none; }
        a:hover { text-decoration: underline; }
        
        footer {
            padding: 15px 30px;
            text-align: center;
            color: #6b8ba4;
            font-size: 12px;
            border-top: 1px solid #33485d;
            flex-shrink: 0;
            background: #1f2e3c;
        }
        
        @media (max-width: 768px) {
            body { padding: 10px; }
            .header { padding: 16px 20px 0 20px; }
            .tabs-wrapper { padding: 0 20px; }
            .doc-content { margin: 0 20px 16px 20px; padding: 16px; }
            footer { padding: 12px 20px; }
            .code-block { font-size: 11px; }
            .lang-btn { padding: 6px 12px; font-size: 12px; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>📦 KMS Monitor</h1>
        <div class="version">Версия ' . VLMC_VERSION . ' | Полное руководство по развёртыванию | ' . VLMC_DATE . '</div>
    </div>
    
    <div class="tabs-wrapper">
        <div class="lang-tabs">
            <button class="lang-btn active" data-lang="ru">🇷🇺 Русский</button>
            <button class="lang-btn" data-lang="en">🇬🇧 English</button>
        </div>
    </div>
    
    <div id="docContent" class="doc-content"></div>
    
    <footer>
        KMS Monitor v' . VLMC_VERSION . ' — Полное руководство по развёртыванию<br>
        ' . vlmcCopyright() . '
    </footer>
</div>

<script>
const contentMap = {
    ru: ' . $webRuJson . ',
    en: ' . $webEnJson . '
};

let currentLang = "ru";

function renderDocumentation() {
    const container = document.getElementById("docContent");
    if (container) {
        container.innerHTML = contentMap[currentLang];
    }
    
    document.querySelectorAll(".lang-btn").forEach(btn => {
        btn.classList.remove("active");
        if (btn.dataset.lang === currentLang) btn.classList.add("active");
    });
}

document.querySelectorAll(".lang-btn").forEach(btn => {
    btn.addEventListener("click", function() {
        currentLang = this.dataset.lang;
        renderDocumentation();
    });
});

renderDocumentation();
</script>
</body>
</html>';
}

?>

<div id="section-documentation" class="settings-section <?= $activeSection === 'documentation' ? 'active' : '' ?>">
    <div class="section-title sec-title doc-head">
        <span>📚 <?= __('doc_title') ?></span>
        <div class="sec-tabs">
            <button type="button" class="sec-tab-btn active" data-doc-tab="inst"></button>
            <button type="button" class="sec-tab-btn" data-doc-tab="desc"></button>
        </div>
    </div>

    <div class="doc-toolbar">
        <div class="doc-search">
            <svg class="doc-s-ic" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
            <input type="text" id="docSearchInput" class="doc-s-in" autocomplete="off" spellcheck="false">
            <button type="button" id="docSearchClear" class="doc-s-x" hidden aria-label="clear">✕</button>
            <kbd class="doc-s-k">/</kbd>
            <div id="docSearchRes" class="doc-s-res" hidden></div>
        </div>
        <div class="doc-langs">
            <button type="button" class="sec-tab-btn" data-doc-lang="ru"><span class="doc-fl doc-fl-ru"></span> RU</button>
            <button type="button" class="sec-tab-btn" data-doc-lang="en"><span class="doc-fl doc-fl-gb"></span> EN</button>
        </div>
        <button type="button" id="downloadDocBtn" class="sec-btn primary">📥 <?= __('doc_download') ?></button>
    </div>

    <div class="doc-layout">
        <nav id="docNav" class="doc-nav"></nav>
        <div id="docContent" class="doc-content"></div>
    </div>
</div>

<style>
#section-documentation.active {
    display: flex;
    flex-direction: column;
    height: 100%;
    min-height: 0;
}

/* Заголовок: название слева, вкладки справа */
#section-documentation .doc-head { flex-shrink: 0; }

/* Строка: поиск, язык, «Скачать» */
.doc-toolbar { display: flex; align-items: center; gap: 12px; margin: 0 0 12px; flex-shrink: 0; }
.doc-langs { display: flex; gap: 8px; flex: none; }
.doc-fl { display: inline-block; width: 18px; height: 13px; margin-right: 5px; vertical-align: -2px; border-radius: 2px; background-size: cover; background-position: center; }
.doc-fl-ru { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 4 3'%3E%3Crect width='4' height='3' fill='%23fff'/%3E%3Crect y='1' width='4' height='2' fill='%230039a6'/%3E%3Crect y='2' width='4' height='1' fill='%23d52b1e'/%3E%3C/svg%3E"); }
.doc-fl-gb { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 60 45'%3E%3Crect width='60' height='45' fill='%23012169'/%3E%3Cpath d='M0 0L60 45M60 0L0 45' stroke='%23fff' stroke-width='9'/%3E%3Cpath d='M0 0L60 45M60 0L0 45' stroke='%23c8102e' stroke-width='4'/%3E%3Cpath d='M30 0V45M0 22.5H60' stroke='%23fff' stroke-width='15'/%3E%3Cpath d='M30 0V45M0 22.5H60' stroke='%23c8102e' stroke-width='9'/%3E%3C/svg%3E"); }

/* Поиск */
.doc-search { position: relative; flex: 1; min-width: 0; }
.doc-s-in { width: 100%; height: 38px; box-sizing: border-box; padding: 0 64px 0 42px; border-radius: 19px; border: 1px solid <?= $themeCSS['border'] ?>; background: <?= $themeCSS['input'] ?>; color: <?= $themeCSS['text'] ?>; font: inherit; font-size: 14px; outline: none; transition: border-color .15s, box-shadow .15s; }
.doc-s-in::placeholder { color: <?= $themeCSS['muted'] ?>; }
.doc-s-in:focus { border-color: <?= $themeCSS['primary'] ?>; box-shadow: 0 0 0 3px <?= $themeCSS['primary'] ?>40; }
.doc-s-ic { position: absolute; left: 14px; top: 10px; color: <?= $themeCSS['muted'] ?>; pointer-events: none; }
.doc-search:focus-within .doc-s-ic { color: <?= $themeCSS['primary'] ?>; }
.doc-s-x { position: absolute; right: 12px; top: 7px; width: 24px; height: 24px; border: 0; border-radius: 50%; background: <?= $themeCSS['border'] ?>; color: <?= $themeCSS['text'] ?>; cursor: pointer; font-size: 11px; line-height: 24px; padding: 0; }
.doc-s-x:hover { background: <?= $themeCSS['hover'] ?>; }
.doc-s-k { position: absolute; right: 14px; top: 9px; border: 1px solid <?= $themeCSS['border'] ?>; border-radius: 5px; padding: 0 7px; font: 12px/18px ui-monospace, monospace; color: <?= $themeCSS['muted'] ?>; pointer-events: none; }
.doc-s-x:not([hidden]) ~ .doc-s-k { display: none; }
.doc-s-res { position: absolute; left: 0; right: 0; top: 46px; z-index: 50; max-height: 420px; overflow-y: auto; border-radius: 12px; border: 1px solid <?= $themeCSS['border'] ?>; background: <?= $themeCSS['card'] ?>; box-shadow: 0 14px 36px rgba(0,0,0,.45); padding: 6px; }
.doc-s-res[hidden] { display: none; }
.doc-r { display: block; padding: 9px 12px; border-radius: 8px; cursor: pointer; color: <?= $themeCSS['text'] ?>; }
.doc-r.sel, .doc-r:hover { background: <?= $themeCSS['hover'] ?>; }
.doc-r-t { display: flex; align-items: center; gap: 8px; font-weight: 600; font-size: 14px; }
.doc-r-b { font-size: 11px; font-weight: 500; padding: 1px 8px; border-radius: 10px; border: 1px solid <?= $themeCSS['border'] ?>; color: <?= $themeCSS['muted'] ?>; }
.doc-r-s { margin-top: 3px; font-size: 12.5px; color: <?= $themeCSS['muted'] ?>; line-height: 1.45; }
.doc-s-none { padding: 14px; text-align: center; color: <?= $themeCSS['muted'] ?>; font-size: 13.5px; }
.doc-r mark, mark.doc-hl { background: rgba(250,204,21,.4); color: inherit; border-radius: 3px; padding: 0 1px; }

/* Меню и содержимое */
.doc-layout { display: flex; gap: 16px; flex: 1; min-height: 0; }
.doc-nav { flex: none; width: 240px; overflow-y: auto; border: 1px solid <?= $themeCSS['border'] ?>; border-radius: 12px; padding: 8px; background: <?= $themeCSS['input'] ?>; }
.doc-nav a { display: block; padding: 7px 10px; border-radius: 6px; color: <?= $themeCSS['text'] ?>; font-size: 14px; cursor: pointer; text-decoration: none; }
.doc-nav a:hover { background: <?= $themeCSS['hover'] ?>; }
.doc-nav a.on { background: <?= $themeCSS['primary'] ?>33; font-weight: 600; color: <?= $themeCSS['accentText'] ?>; }
.doc-content {
    flex: 1;
    min-width: 0;
    min-height: 0;
    overflow-x: auto;
    overflow-y: auto;
    padding: 24px;
    background: <?= $themeCSS['input'] ?>;
    border: 2px solid <?= $themeCSS['border'] ?>;
    border-radius: 12px;
}

/* Карточки внутри содержимого */
.doc-content .section-card {
    background: none;
    border: 0;
    border-radius: 0;
    padding: 0;
    margin: 0;
}
.doc-content .section-title {
    justify-content: flex-start;
    font-size: 20px;
    font-weight: 600;
    margin-bottom: 20px;
    color: <?= $themeCSS['accentText'] ?>;
    display: flex;
    align-items: center;
    gap: 10px;
    border-bottom: 1px solid <?= $themeCSS['border'] ?>;
    padding-bottom: 12px;
}
.doc-content .section-title span { font-size: 26px; line-height: 1; }

.doc-content .code-block {
    background: #0a0f1a;
    border: 1px solid <?= $themeCSS['border'] ?>;
    border-radius: 8px;
    padding: 12px;
    margin: 10px 0;
    overflow-x: auto;
    font-family: 'JetBrains Mono', monospace;
    font-size: 12px;
}
.doc-content .note { background: <?= $themeCSS['warning'] ?>20; border-left: 4px solid <?= $themeCSS['warning'] ?>; padding: 10px 15px; margin: 12px 0; border-radius: 6px; }
.doc-content .warning { background: <?= $themeCSS['danger'] ?>20; border-left: 4px solid <?= $themeCSS['danger'] ?>; padding: 10px 15px; margin: 12px 0; border-radius: 6px; }
.doc-content .success { background: <?= $themeCSS['success'] ?>20; border-left: 4px solid <?= $themeCSS['success'] ?>; padding: 10px 15px; margin: 12px 0; border-radius: 6px; }
.doc-content .file-tree { background: #0a0f1a; padding: 12px 16px; border-radius: 8px; font-family: monospace; font-size: 12px; line-height: 1.7; }

/* Описание разделов (из markdown) */
.doc-desc { line-height: 1.6; font-size: 14.5px; }
.doc-desc h2 { font-size: 17px; margin: 26px 0 12px; padding-bottom: 8px; border-bottom: 1px solid <?= $themeCSS['border'] ?>; color: <?= $themeCSS['text'] ?>; }
.doc-desc h2:first-child { margin-top: 0; }
.doc-desc h3 { font-size: 15px; margin: 18px 0 6px; color: <?= $themeCSS['text'] ?>; }
.doc-desc p { margin: 8px 0; }
.doc-desc ul, .doc-desc ol { margin: 8px 0; padding-left: 24px; }
.doc-desc li { margin: 3px 0; }
.doc-desc a { color: <?= $themeCSS['accentText'] ?>; cursor: pointer; }
.doc-desc code { background: <?= $themeCSS['border'] ?>66; padding: 1px 5px; border-radius: 4px; font-size: 13px; font-family: 'JetBrains Mono', Consolas, monospace; }
.doc-desc pre { background: #0a0f1a; border: 1px solid <?= $themeCSS['border'] ?>; padding: 12px; border-radius: 8px; overflow-x: auto; }
.doc-desc pre code { background: none; padding: 0; }
.doc-desc table { border-collapse: collapse; margin: 10px 0; display: block; overflow-x: auto; }
.doc-desc th, .doc-desc td { border: 1px solid <?= $themeCSS['border'] ?>; padding: 6px 10px; text-align: left; vertical-align: top; }
.doc-desc th { background: <?= $themeCSS['card'] ?>; }

@media (max-width: 900px) {
    .doc-layout { flex-direction: column; }
    .doc-nav { width: auto; max-height: 160px; }
    .doc-toolbar { flex-wrap: wrap; }
}
</style>

<script>
(function () {
    var INST_HTML = <?= json_encode(['ru' => $docWebRu, 'en' => $docWebEn]) ?>;
    var DESC = <?= json_encode($docDesc, JSON_UNESCAPED_UNICODE) ?>;
    var LABELS = {
        ru: { inst: '🚀 Установка', desc: '📖 Описание', ph: 'Поиск по документации…', none: 'Ничего не найдено', bInst: 'Установка', bDesc: 'Описание' },
        en: { inst: '🚀 Installation', desc: '📖 Description', ph: 'Search the documentation…', none: 'Nothing found', bInst: 'Installation', bDesc: 'Description' }
    };
    var state = { lang: <?= json_encode(($config['language'] ?? 'ru') === 'en' ? 'en' : 'ru') ?>, tab: 'inst', id: null };
    var model = null;

    function esc(s) { return String(s).replace(/[&<>]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]; }); }

    function plain(html) {
        var d = document.createElement('div');
        d.innerHTML = html.replace(/<\/(p|li|h2|h3|tr|td|th|div|pre)>/g, '</$1> ');
        return d.textContent.replace(/\s+/g, ' ');
    }

    function build() {
        model = {};
        ['ru', 'en'].forEach(function (lang) {
            var tpl = document.createElement('template');
            tpl.innerHTML = INST_HTML[lang];
            var inst = [].map.call(tpl.content.querySelectorAll('.section-card'), function (c, i) {
                var t = c.querySelector('.section-title'), ic = t.querySelector('span');
                var icon = ic ? ic.textContent.trim() : '';
                var title = t.textContent.replace(icon, '').trim();
                return { id: 'i' + i, icon: icon, title: title, html: c.outerHTML, text: plain(c.outerHTML) };
            });
            var desc = (DESC[lang] || []).map(function (d) {
                var html = '<div class="section-card"><div class="section-title"><span>' + d.icon + '</span> ' + esc(d.title) + '</div><div class="doc-desc">' + d.html + '</div></div>';
                return { id: d.id, icon: d.icon, title: d.title, html: html, text: d.title + ' ' + plain(d.html) };
            });
            model[lang] = { inst: inst, desc: desc };
        });
    }

    function items() { return model[state.lang][state.tab]; }

    function renderChrome() {
        var L = LABELS[state.lang];
        document.querySelectorAll('[data-doc-tab]').forEach(function (b) {
            b.textContent = L[b.dataset.docTab];
            b.classList.toggle('active', b.dataset.docTab === state.tab);
        });
        document.querySelectorAll('[data-doc-lang]').forEach(function (b) {
            b.classList.toggle('active', b.dataset.docLang === state.lang);
        });
        document.getElementById('docSearchInput').placeholder = L.ph;
    }

    function renderNav() {
        var nav = document.getElementById('docNav');
        nav.innerHTML = items().map(function (it) {
            return '<a data-id="' + it.id + '" class="' + (it.id === state.id ? 'on' : '') + '">' + it.icon + ' ' + esc(it.title) + '</a>';
        }).join('');
    }

    function show(id, keepScroll) {
        var list = items();
        var it = list.filter(function (x) { return x.id === id; })[0] || list[0];
        if (!it) { return; }
        state.id = it.id;
        var box = document.getElementById('docContent');
        box.innerHTML = it.html;
        if (!keepScroll) { box.scrollTop = 0; }
        renderNav();
        var cur = document.querySelector('#docNav a.on');
        if (cur && cur.scrollIntoView) { cur.scrollIntoView({ block: 'nearest' }); }
    }

    function renderAll() { renderChrome(); show(state.id); clearHl(); searchUpdate(); }

    /* ---------- Поиск ---------- */
    var inp, res, clr, hits = [], sel = -1;
    function rx(q) {
        return new RegExp(q.trim().split(/\s+/).filter(Boolean).map(function (w) { return w.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }).join('|'), 'gi');
    }
    function clearHl() {
        document.querySelectorAll('mark.doc-hl').forEach(function (m) {
            var p = m.parentNode; p.replaceChild(document.createTextNode(m.textContent), m); p.normalize();
        });
    }
    function highlight(q) {
        clearHl();
        var root = document.getElementById('docContent'), r = rx(q), w = document.createTreeWalker(root, NodeFilter.SHOW_TEXT), nodes = [], n;
        while ((n = w.nextNode())) {
            if (n.parentNode.closest('pre,script,style')) { continue; }
            r.lastIndex = 0;
            if (r.test(n.nodeValue)) { nodes.push(n); }
        }
        nodes.forEach(function (n) {
            var f = document.createDocumentFragment(), t = n.nodeValue, i = 0, m;
            r.lastIndex = 0;
            while ((m = r.exec(t))) {
                if (m.index > i) { f.appendChild(document.createTextNode(t.slice(i, m.index))); }
                var k = document.createElement('mark'); k.className = 'doc-hl'; k.textContent = m[0]; f.appendChild(k);
                i = m.index + m[0].length;
                if (m[0].length === 0) { r.lastIndex++; }
            }
            if (i < t.length) { f.appendChild(document.createTextNode(t.slice(i))); }
            n.parentNode.replaceChild(f, n);
        });
        var first = root.querySelector('mark.doc-hl');
        if (first) { first.scrollIntoView({ block: 'center', behavior: 'smooth' }); }
    }
    function searchUpdate() {
        var q = inp.value.trim();
        clr.hidden = !q; sel = -1;
        if (q.length < 2) { res.hidden = true; hits = []; return; }
        var r = rx(q), L = LABELS[state.lang];
        hits = [];
        ['inst', 'desc'].forEach(function (tab) {
            model[state.lang][tab].forEach(function (it) {
                r.lastIndex = 0;
                var cnt = (it.text.match(r) || []).length;
                if (!cnt) { return; }
                r.lastIndex = 0;
                var m = r.exec(it.text), a = Math.max(0, m.index - 50), b = Math.min(it.text.length, m.index + 110);
                var snip = (a > 0 ? '…' : '') + it.text.slice(a, b) + (b < it.text.length ? '…' : '');
                r.lastIndex = 0;
                hits.push({ tab: tab, id: it.id, title: it.title, snip: snip, score: (r.test(it.title) ? 1000 : 0) + cnt });
            });
        });
        hits.sort(function (x, y) { return y.score - x.score; });
        hits = hits.slice(0, 12);
        if (!hits.length) { res.innerHTML = '<div class="doc-s-none">' + L.none + '</div>'; res.hidden = false; return; }
        var mk = function (s) { return esc(s).replace(rx(q), function (x) { return '<mark>' + x + '</mark>'; }); };
        res.innerHTML = hits.map(function (h, i) {
            return '<a class="doc-r" data-i="' + i + '"><div class="doc-r-t">' + mk(h.title) + '<span class="doc-r-b">' + (h.tab === 'inst' ? L.bInst : L.bDesc) + '</span></div><div class="doc-r-s">' + mk(h.snip) + '</div></a>';
        }).join('');
        res.hidden = false;
    }
    function openHit(i) {
        var h = hits[i]; if (!h) { return; }
        state.tab = h.tab; state.id = h.id;
        renderChrome(); show(h.id);
        res.hidden = true;
        highlight(inp.value);
    }
    function markSel() {
        res.querySelectorAll('.doc-r').forEach(function (e, i) { e.classList.toggle('sel', i === sel); });
        var e = res.querySelector('.doc-r.sel'); if (e) { e.scrollIntoView({ block: 'nearest' }); }
    }

    /* ---------- Скачивание ---------- */
    function download() {
        var fullHtml = <?= json_encode(getFullDownloadHtml()) ?>;
        var blob = new Blob([fullHtml], { type: 'text/html' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'KMS_Monitor_Full_Guide_' + new Date().toISOString().slice(0, 10) + '.html';
        document.body.appendChild(a); a.click(); document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }

    /* ---------- Запуск ---------- */
    var started = false;
    function start() {
        if (started) { return; }
        started = true;
        inp = document.getElementById('docSearchInput');
        res = document.getElementById('docSearchRes');
        clr = document.getElementById('docSearchClear');
        build();
        renderAll();

        document.querySelectorAll('[data-doc-tab]').forEach(function (b) {
            b.addEventListener('click', function () { state.tab = b.dataset.docTab; state.id = null; renderAll(); });
        });
        document.querySelectorAll('[data-doc-lang]').forEach(function (b) {
            b.addEventListener('click', function () { state.lang = b.dataset.docLang; state.id = null; renderAll(); });
        });
        document.getElementById('docNav').addEventListener('click', function (e) {
            var a = e.target.closest('a[data-id]');
            if (a) { clearHl(); show(a.dataset.id); }
        });
        document.getElementById('docContent').addEventListener('click', function (e) {
            var a = e.target.closest('a[data-doc]');
            if (a) { e.preventDefault(); state.tab = 'desc'; renderChrome(); show(a.dataset.doc); }
        });
        inp.addEventListener('input', searchUpdate);
        inp.addEventListener('focus', searchUpdate);
        inp.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown') { e.preventDefault(); sel = Math.min(hits.length - 1, sel + 1); markSel(); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); sel = Math.max(0, sel - 1); markSel(); }
            else if (e.key === 'Enter') { e.preventDefault(); openHit(sel < 0 ? 0 : sel); }
            else if (e.key === 'Escape') { res.hidden = true; inp.blur(); }
        });
        res.addEventListener('mousedown', function (e) {
            var a = e.target.closest('.doc-r');
            if (a) { e.preventDefault(); openHit(+a.dataset.i); }
        });
        clr.addEventListener('click', function () { inp.value = ''; clearHl(); searchUpdate(); inp.focus(); });
        document.addEventListener('click', function (e) { if (!e.target.closest('.doc-search')) { res.hidden = true; } });
        document.addEventListener('keydown', function (e) {
            var sec = document.getElementById('section-documentation');
            if (e.key === '/' && sec && sec.classList.contains('active') && !/INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName)) {
                e.preventDefault(); inp.focus();
            }
        });
        document.getElementById('downloadDocBtn').addEventListener('click', download);
    }

    var sec = document.getElementById('section-documentation');
    if (sec) {
        if (sec.classList.contains('active')) {
            start();
        } else {
            var obs = new MutationObserver(function () {
                if (sec.classList.contains('active')) { start(); obs.disconnect(); }
            });
            obs.observe(sec, { attributes: true, attributeFilter: ['class'] });
        }
    }
})();
</script>
<?php
?>
