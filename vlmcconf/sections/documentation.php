<?php
// ============================================
// ФАЙЛ: sections/documentation.php
// ВЕРСИЯ: 5.0.0
// ДАТА: 2026-05-31
// @description: Секция "Документация" с полной инструкцией по установке
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
        
        <p><strong>Для Ubuntu 22.04 LTS (PHP 8.1):</strong></p>
        <div class="code-block"><pre>sudo apt update
sudo apt install apache2 php8.1 php8.1-curl php8.1-common libapache2-mod-php8.1</pre></div>
        
        <p><strong>Для Ubuntu 24.04 LTS (PHP 8.3):</strong></p>
        <div class="code-block"><pre>sudo apt update
sudo apt install apache2 php8.3 php8.3-curl php8.3-common libapache2-mod-php8.3</pre></div>
        
        <p><strong>Для Ubuntu 26.04 LTS (PHP 8.5):</strong></p>
        <div class="code-block"><pre>sudo apt update
sudo apt install apache2 php8.5 php8.5-curl php8.5-common libapache2-mod-php8.5</pre></div>
        
        <div class="note">💡 <strong>Примечание:</strong> Расширения <code>json</code> и <code>session</code> встроены в PHP, отдельно устанавливать не нужно.</div>
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
        <div class="code-block"><pre># Для Ubuntu 22.04, 24.04, 26.04
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
    
    <!-- Структура проекта -->
    <div class="section-card">
        <div class="section-title"><span>📂</span> Структура проекта</div>
        <div class="file-tree">
            <div>📁 /var/www/html/</div>
            <div style="margin-left: 20px;">├── 📄 vlmc.php — главная страница мониторинга</div>
            <div style="margin-left: 20px;">├── 📄 index.php — редирект</div>
            <div style="margin-left: 20px;">├── 📄 .htaccess — защита конфигурационных файлов</div>
            <div style="margin-left: 20px;">├── 📁 vlmcconf/ — панель управления</div>
            <div style="margin-left: 40px;">│   ├── 📄 vlmcconf.php</div>
            <div style="margin-left: 40px;">│   ├── 📄 login.php</div>
            <div style="margin-left: 40px;">│   ├── 📄 logout.php</div>
            <div style="margin-left: 40px;">│   ├── 📄 vlmcgeoip.php</div>
            <div style="margin-left: 40px;">│   ├── 📄 vlmcloghandler.php</div>
            <div style="margin-left: 40px;">│   ├── 📄 vlmctheme.php</div>
            <div style="margin-left: 40px;">│   ├── 📄 flags.php</div>
            <div style="margin-left: 40px;">│   ├── 📄 vlmcconf_config.json — настройки</div>
            <div style="margin-left: 40px;">│   ├── 📄 users.json — пользователи</div>
            <div style="margin-left: 40px;">│   ├── 📁 cache/ — кэш геолокации (создаётся автоматически)</div>
            <div style="margin-left: 40px;">│   ├── 📁 locale/ — языковые файлы</div>
            <div style="margin-left: 40px;">│   ├── 📁 sections/ — секции панели управления</div>
            <div style="margin-left: 40px;">│   └── 📁 vlmcinc/ — вспомогательные библиотеки</div>
            <div style="margin-left: 20px;">└── 📁 pic/ — иконки</div>
        </div>
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
        
        <p><strong>For Ubuntu 22.04 LTS (PHP 8.1):</strong></p>
        <div class="code-block"><pre>sudo apt update
sudo apt install apache2 php8.1 php8.1-curl php8.1-common libapache2-mod-php8.1</pre></div>
        
        <p><strong>For Ubuntu 24.04 LTS (PHP 8.3):</strong></p>
        <div class="code-block"><pre>sudo apt update
sudo apt install apache2 php8.3 php8.3-curl php8.3-common libapache2-mod-php8.3</pre></div>
        
        <p><strong>For Ubuntu 26.04 LTS (PHP 8.5):</strong></p>
        <div class="code-block"><pre>sudo apt update
sudo apt install apache2 php8.5 php8.5-curl php8.5-common libapache2-mod-php8.5</pre></div>
        
        <div class="note">💡 <strong>Note:</strong> The <code>json</code> and <code>session</code> extensions are built into PHP, no separate installation required.</div>
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
        <div class="code-block"><pre># For Ubuntu 22.04, 24.04, 26.04
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
    
    <!-- Project structure -->
    <div class="section-card">
        <div class="section-title"><span>📂</span> Project structure</div>
        <div class="file-tree">
            <div>📁 /var/www/html/</div>
            <div style="margin-left: 20px;">├── 📄 vlmc.php — main monitoring page</div>
            <div style="margin-left: 20px;">├── 📄 index.php — redirect</div>
            <div style="margin-left: 20px;">├── 📄 .htaccess — config file protection</div>
            <div style="margin-left: 20px;">├── 📁 vlmcconf/ — control panel</div>
            <div style="margin-left: 40px;">│   ├── 📄 vlmcconf.php</div>
            <div style="margin-left: 40px;">│   ├── 📄 login.php</div>
            <div style="margin-left: 40px;">│   ├── 📄 logout.php</div>
            <div style="margin-left: 40px;">│   ├── 📄 vlmcgeoip.php</div>
            <div style="margin-left: 40px;">│   ├── 📄 vlmcloghandler.php</div>
            <div style="margin-left: 40px;">│   ├── 📄 vlmctheme.php</div>
            <div style="margin-left: 40px;">│   ├── 📄 flags.php</div>
            <div style="margin-left: 40px;">│   ├── 📄 vlmcconf_config.json — settings</div>
            <div style="margin-left: 40px;">│   ├── 📄 users.json — users</div>
            <div style="margin-left: 40px;">│   ├── 📁 cache/ — geolocation cache (auto-created)</div>
            <div style="margin-left: 40px;">│   ├── 📁 locale/ — language files</div>
            <div style="margin-left: 40px;">│   ├── 📁 sections/ — control panel sections</div>
            <div style="margin-left: 40px;">│   └── 📁 vlmcinc/ — helper libraries</div>
            <div style="margin-left: 20px;">└── 📁 pic/ — icons</div>
        </div>
    </div>
</div>
HTML_WEB_EN;

// ============================================
// ПОЛНЫЙ HTML ДЛЯ СКАЧИВАНИЯ
// ============================================
function getFullDownloadHtml() {
    global $docWebRu, $docWebEn;
    
    $webRuJson = json_encode($docWebRu);
    $webEnJson = json_encode($docWebEn);
    
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
        <div class="version">Версия 5.0.0 | Полное руководство по развёртыванию | Май 2026</div>
    </div>
    
    <div class="tabs-wrapper">
        <div class="lang-tabs">
            <button class="lang-btn active" data-lang="ru">🇷🇺 Русский</button>
            <button class="lang-btn" data-lang="en">🇬🇧 English</button>
        </div>
    </div>
    
    <div id="docContent" class="doc-content"></div>
    
    <footer>
        KMS Monitor v5.0.0 — Полное руководство по развёртыванию<br>
        © 2025-2026
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
    <div class="section-title" style="display: flex; justify-content: space-between; align-items: center; flex-shrink: 0;">
        <span>📚 <?= __('doc_title') ?></span>
        <button id="downloadDocBtn" class="btn btn-primary" style="font-size: 12px; padding: 6px 12px;">📥 <?= __('doc_download') ?></button>
    </div>
    
    <!-- Переключение языка -->
    <div class="doc-tabs-wrapper" style="flex-shrink: 0; margin-bottom: 15px;">
        <div class="doc-lang-tabs" style="display: inline-flex; gap: 10px; background: <?= $themeCSS['input'] ?>; padding: 8px 12px; border-radius: 8px;">
            <button class="doc-lang-btn active" data-lang="ru" style="background: none; border: none; cursor: pointer; font-weight: 600; padding: 4px 12px; border-radius: 6px;">🇷🇺 Русский</button>
            <span style="color: <?= $themeCSS['border'] ?>;">|</span>
            <button class="doc-lang-btn" data-lang="en" style="background: none; border: none; cursor: pointer; font-weight: 600; padding: 4px 12px; border-radius: 6px;">🇬🇧 English</button>
        </div>
    </div>
    
    <!-- Контейнер для содержимого -->
    <div id="docContent" class="doc-content">
        <?= $docWebRu ?>
    </div>
</div>

<style>
.doc-content {
    background: <?= $themeCSS['card'] ?>;
    border-radius: 12px;
    overflow-x: auto;
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    padding: 20px;
}

.doc-tabs-wrapper {
    flex-shrink: 0;
    margin-bottom: 15px;
}

.doc-lang-btn.active {
    color: <?= $themeCSS['primary'] ?> !important;
    background: <?= $themeCSS['card'] ?> !important;
}

.doc-lang-btn:hover {
    opacity: 0.8;
    background: <?= $themeCSS['card'] ?>;
}

/* Стили для внутреннего контента */
.doc-content .section-card {
    background: <?= $themeCSS['input'] ?>;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 25px;
    border: 2px solid <?= $themeCSS['border'] ?>;
}

.doc-content .section-title {
    font-size: 22px;
    font-weight: 600;
    margin-bottom: 20px;
    color: <?= $themeCSS['primary'] ?>;
    display: flex;
    align-items: center;
    gap: 10px;
    border-bottom: 1px solid <?= $themeCSS['border'] ?>;
    padding-bottom: 12px;
}

.doc-content .section-title span {
    font-size: 26px;
}

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

.doc-content .note {
    background: <?= $themeCSS['warning'] ?>20;
    border-left: 4px solid <?= $themeCSS['warning'] ?>;
    padding: 10px 15px;
    margin: 12px 0;
    border-radius: 6px;
}

.doc-content .warning {
    background: <?= $themeCSS['danger'] ?>20;
    border-left: 4px solid <?= $themeCSS['danger'] ?>;
    padding: 10px 15px;
    margin: 12px 0;
    border-radius: 6px;
}

.doc-content .success {
    background: <?= $themeCSS['success'] ?>20;
    border-left: 4px solid <?= $themeCSS['success'] ?>;
    padding: 10px 15px;
    margin: 12px 0;
    border-radius: 6px;
}

.doc-content .file-tree {
    background: #0a0f1a;
    padding: 12px 16px;
    border-radius: 8px;
    font-family: monospace;
    font-size: 12px;
    line-height: 1.7;
}

#section-documentation.active {
    display: flex;
    flex-direction: column;
    height: 100%;
    min-height: 0;
}

.btn {
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-primary {
    background: <?= $themeCSS['primary'] ?>;
    color: white;
}
.btn-primary:hover {
    background: #2563eb;
    transform: translateY(-1px);
}
</style>

<script>
// Содержимое для разных языков
const contentMap = {
    ru: <?= json_encode($docWebRu) ?>,
    en: <?= json_encode($docWebEn) ?>
};

let currentLang = 'ru';

function renderDocumentation() {
    const container = document.getElementById('docContent');
    if (container) {
        container.innerHTML = contentMap[currentLang];
    }
    
    document.querySelectorAll('.doc-lang-btn').forEach(btn => {
        btn.classList.remove('active');
        if (btn.dataset.lang === currentLang) {
            btn.classList.add('active');
            btn.style.color = '<?= $themeCSS['primary'] ?>';
        } else {
            btn.style.color = '<?= $themeCSS['text'] ?>';
        }
    });
}

// Переключение языка
document.querySelectorAll('.doc-lang-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        currentLang = this.dataset.lang;
        renderDocumentation();
    });
});

// Скачивание полного документа
document.getElementById('downloadDocBtn').addEventListener('click', function() {
    const fullHtml = <?= json_encode(getFullDownloadHtml()) ?>;
    const blob = new Blob([fullHtml], { type: 'text/html' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `KMS_Monitor_Full_Guide_${new Date().toISOString().slice(0,10)}.html`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
});

// Инициализация при загрузке секции
document.addEventListener('DOMContentLoaded', function() {
    const docSection = document.getElementById('section-documentation');
    if (docSection && docSection.classList.contains('active')) {
        renderDocumentation();
    }
});

// Наблюдатель за активацией секции
const docObserver = new MutationObserver(function(mutations) {
    mutations.forEach(function(mutation) {
        if (mutation.attributeName === 'class') {
            const target = mutation.target;
            if (target.id === 'section-documentation' && target.classList.contains('active')) {
                renderDocumentation();
                docObserver.disconnect();
            }
        }
    });
});

const docSection = document.getElementById('section-documentation');
if (docSection) {
    docObserver.observe(docSection, { attributes: true });
}
</script>
<?php
?>