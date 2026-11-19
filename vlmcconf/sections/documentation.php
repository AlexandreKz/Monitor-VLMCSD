<?php
// ============================================
// ФАЙЛ: sections/documentation.php
// ВЕРСИЯ: 6.0.0
// ДАТА: 2026-11-19
// @description: Секция "Документация": вкладки «Установка» (инструкция) и «Описание» (разделы и опции, из doc_desc.php), поиск, RU/EN
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
// Описание разделов (сгенерировано из docs/*.md скриптом docs/build_docs.py)
$docDesc = @include __DIR__ . '/doc_desc.php';
if (!is_array($docDesc)) {
    $docDesc = ['ru' => [], 'en' => []];
}

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
.doc-nav { flex: none; width: 240px; overflow-y: auto; border: 1px solid <?= $themeCSS['border'] ?>; border-radius: 12px; padding: 8px; background: <?= $themeCSS['card'] ?>; }
.doc-nav a { display: block; padding: 7px 10px; border-radius: 6px; color: <?= $themeCSS['text'] ?>; font-size: 14px; cursor: pointer; text-decoration: none; }
.doc-nav a:hover { background: <?= $themeCSS['hover'] ?>; }
.doc-nav a.on { background: <?= $themeCSS['primary'] ?>33; font-weight: 600; color: <?= $themeCSS['accentText'] ?>; }
.doc-content {
    flex: 1;
    min-width: 0;
    min-height: 0;
    overflow-x: auto;
    overflow-y: auto;
    padding: 20px;
    background: <?= $themeCSS['card'] ?>;
    border-radius: 12px;
}

/* Карточки внутри содержимого */
.doc-content .section-card {
    background: <?= $themeCSS['input'] ?>;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 25px;
    border: 2px solid <?= $themeCSS['border'] ?>;
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
