<?php
// ============================================
// ФАЙЛ: sections/documentation.php
// ВЕРСИЯ: 2.3.0
// ДАТА: 2026-04-01
// @description: Секция "Документация" с вкладками и полным скачиванием
// ============================================

if (basename($_SERVER['PHP_SELF']) === 'documentation.php') {
    http_response_code(403);
    exit('Access denied');
}

// ============================================
// РУССКАЯ ВЕРСИЯ — Веб-оболочка
// ============================================
$docWebRu = <<<'HTML_WEB_RU'
<div class="doc-content-inner">
    <div class="resources">
        <strong>📌 О проекте KMS Monitor:</strong><br>
        KMS Monitor — это веб-оболочка для мониторинга и управления KMS-сервером на базе <strong>vlmcsd</strong>.<br>
        Сам KMS-сервер распространяется отдельно.<br><br>
        <strong>Официальные ресурсы проекта vlmcsd:</strong><br>
        • Форум разработчика: <a href="https://forums.mydigitallife.net/threads/emulated-kms-servers-on-non-windows-platforms.50234/" target="_blank">My Digital Life Forums</a><br>
        • Дистрибутив (только исходный код): <a href="https://www.upload.ee/files/11363713/vlmcsd-1113-2020-03-28-Hotbird64-source-only.7z.html" target="_blank">Скачать</a><br>
        • Дистрибутив (исходный код + бинарные файлы): <a href="https://www.upload.ee/files/11363704/vlmcsd-1113-2020-03-28-Hotbird64.7z.html" target="_blank">Скачать</a>
    </div>
    
    <div class="section-card">
        <div class="section-title"><span>📋</span> 1. Требования к серверу</div>
        <ul>
            <li>Веб-сервер: <strong>Apache 2.4+</strong> с поддержкой <code>mod_rewrite</code></li>
            <li>PHP: <strong>7.4 — 8.3</strong> (рекомендуется 8.1+)</li>
            <li>Расширения PHP: <code>curl</code>, <code>json</code>, <code>session</code>, <code>fileinfo</code></li>
            <li>Доступ в интернет для геолокации IP (опционально)</li>
        </ul>
    </div>
    
    <div class="section-card">
        <div class="section-title"><span>📁</span> 2. Перенос файлов проекта</div>
        <p>Скопируй все файлы проекта в директорию <code>/var/www/html/</code>:</p>
        <div class="code-block"><pre># Если файлы уже есть на сервере — просто проверь
ls -la /var/www/html/

# Если переносишь с другого сервера — создай архив
cd /var/www
tar -czf kms-monitor.tar.gz html/

# Скопируй архив на новый сервер
scp kms-monitor.tar.gz user@new-server:/tmp/

# На новом сервере — распакуй
cd /var/www
sudo tar -xzf /tmp/kms-monitor.tar.gz

# Убедись, что файлы на месте
ls -la /var/www/html/vlmc.php</pre></div>
        <div class="note">💡 <strong>Важно:</strong> Корневая директория сайта — <code>/var/www/html/</code>. Все файлы проекта должны находиться здесь.</div>
    </div>
    
    <div class="section-card">
        <div class="section-title"><span>🔧</span> 3. Настройка Apache</div>
        <p>Создай конфигурационный файл для виртуального хоста:</p>
        <div class="code-block"><pre>sudo nano /etc/apache2/sites-available/kms-monitor.conf</pre></div>
        <p>Вставь следующее содержимое (замени <code>example.com</code> на твой домен или IP-адрес):</p>
        <div class="code-block"><pre>&lt;VirtualHost *:80&gt;
    ServerName example.com
    ServerAdmin admin@example.com
    DocumentRoot /var/www/html
    
    &lt;Directory /var/www/html&gt;
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    &lt;/Directory&gt;
    
    ErrorLog /var/log/apache2/kms-error.log
    CustomLog /var/log/apache2/kms-access.log combined
&lt;/VirtualHost&gt;</pre></div>
        <p>Активируй сайт и перезапусти Apache:</p>
        <div class="code-block"><pre># Включи сайт
sudo a2ensite kms-monitor.conf

# Отключи стандартный сайт (если используется)
sudo a2dissite 000-default.conf

# Проверь конфигурацию на ошибки
sudo apachectl configtest

# Перезапусти Apache
sudo systemctl reload apache2</pre></div>
        <div class="success">✅ После этого сайт должен быть доступен по адресу <code>http://example.com/vlmc.php</code></div>
    </div>
    
    <div class="section-card">
        <div class="section-title"><span>🔒</span> 4. Настройка прав доступа</div>
        <div class="code-block"><pre># Установи владельца (www-data — пользователь Apache)
sudo chown -R www-data:www-data /var/www/html

# Установи права на файлы
sudo find /var/www/html -type f -exec chmod 644 {} \;
sudo find /var/www/html -type d -exec chmod 755 {} \;

# Особые права для конфигурационных файлов (только чтение)
sudo chmod 640 /var/www/html/vlmcconf/vlmcconf_config.json
sudo chmod 640 /var/www/html/vlmcconf/users.json</pre></div>
        <div class="warning">⚠️ <strong>Важно:</strong> Файлы <code>vlmcconf_config.json</code> и <code>users.json</code> содержат пароли в хешированном виде. Убедись, что они защищены от прямого доступа через <code>.htaccess</code>.</div>
    </div>
    
    <div class="section-card">
        <div class="section-title"><span>📝</span> 5. Настройка доступа к логу KMS-сервера</div>
        <p>Веб-оболочка должна иметь доступ к лог-файлу KMS-сервера. Есть два способа:</p>
        
        <h4>Способ 1: Символическая ссылка (рекомендуется)</h4>
        <div class="code-block"><pre># Создай символическую ссылку на лог в папке html
sudo ln -s /var/log/vlmcsd.log /var/www/html/vlmcsd.log

# Убедись, что ссылка создалась
ls -la /var/www/html/vlmcsd.log</pre></div>
        <div class="note">💡 <strong>Преимущество:</strong> При изменении пути к логу в KMS-сервере достаточно обновить ссылку, настройки оболочки не меняются.</div>
        
        <h4>Способ 2: Указать путь в настройках оболочки</h4>
        <p>После первого входа в панель управления (<code>/vlmcconf/login.php</code>):</p>
        <ol>
            <li>Перейди в раздел <strong>Общие настройки</strong></li>
            <li>В поле <strong>Путь к файлу лога</strong> укажи <code>/var/log/vlmcsd.log</code></li>
            <li>Нажми <strong>Сохранить путь</strong></li>
        </ol>
        
        <div class="warning">⚠️ <strong>Важно:</strong> Убедись, что пользователь <code>www-data</code> имеет права на чтение лог-файла:
        <div class="code-block"><pre>sudo chmod 644 /var/log/vlmcsd.log
sudo chown www-data:www-data /var/log/vlmcsd.log</pre></div></div>
    </div>
    
    <div class="section-card">
        <div class="section-title"><span>🔐</span> 6. Настройка SSL (Let's Encrypt)</div>
        
        <h4>Для Ubuntu 22.04 LTS:</h4>
        <div class="code-block"><pre># Установи software-properties-common
sudo apt update
sudo apt install software-properties-common -y

# Добавь репозиторий certbot
sudo add-apt-repository ppa:certbot/certbot -y
sudo apt update

# Установи certbot и плагин для Apache
sudo apt install certbot python3-certbot-apache -y

# Получи сертификат (замени example.com на свой домен)
sudo certbot --apache -d example.com

# Проверь автообновление
sudo certbot renew --dry-run</pre></div>
        
        <h4>Для Ubuntu 24.04 LTS:</h4>
        <div class="code-block"><pre># Установи snapd
sudo apt update
sudo apt install snapd -y

# Установи certbot через snap
sudo snap install core
sudo snap refresh core
sudo snap install --classic certbot
sudo ln -s /snap/bin/certbot /usr/bin/certbot

# Получи сертификат (замени example.com на свой домен)
sudo certbot --apache -d example.com

# Проверь автообновление
sudo certbot renew --dry-run</pre></div>
        
        <div class="note">💡 Certbot автоматически настроит автообновление сертификата. Проверить можно командой: <code>sudo systemctl list-timers | grep certbot</code></div>
    </div>
    
    <div class="section-card">
        <div class="section-title"><span>🔐</span> 7. Первый вход в панель управления</div>
        <ol>
            <li>Открой браузер и перейди по адресу: <code>http://example.com/vlmcconf/login.php</code> (или <code>https://...</code> после настройки SSL)</li>
            <li>Введи учётные данные по умолчанию:
                <ul>
                    <li><strong>Логин:</strong> <code>root</code></li>
                    <li><strong>Пароль:</strong> <code>root</code></li>
                </ul>
            </li>
            <li>Система <strong>обязательно предложит сменить пароль</strong> при первом входе.</li>
            <li>Установи новый пароль (требования: минимум 8 символов, заглавные и строчные буквы, цифры, спецсимволы).</li>
            <li>После смены пароля откроется панель управления.</li>
        </ol>
        <div class="success">🎉 <strong>Готово!</strong> Теперь можно настраивать группы, добавлять устройства и управлять пользователями.</div>
    </div>
    
    <div class="section-card">
        <div class="section-title"><span>📂</span> 8. Полная структура файлов проекта</div>
        <div class="file-tree">
            <div><span class="dir">📁 /var/www/html/</span> <span class="comment">— корневая директория сайта</span></div>
            <div style="margin-left: 20px;">├── <span class="file">vlmc.php</span> <span class="comment">— главная страница мониторинга (доступна без авторизации)</span></div>
            <div style="margin-left: 20px;">├── <span class="file">.htaccess</span> <span class="comment">— защита конфигурационных файлов от прямого доступа</span></div>
            <div style="margin-left: 20px;">├── <span class="file">index.php</span> <span class="comment">— перенаправление на vlmc.php</span></div>
            <div style="margin-left: 20px;">├── <span class="dir">📁 vlmcconf/</span> <span class="comment">— панель управления (требуется авторизация)</span></div>
            <div style="margin-left: 40px;">│   ├── <span class="file">vlmcconf.php</span> <span class="comment">— главный файл панели управления</span></div>
            <div style="margin-left: 40px;">│   ├── <span class="file">login.php</span> <span class="comment">— страница авторизации</span></div>
            <div style="margin-left: 40px;">│   ├── <span class="file">logout.php</span> <span class="comment">— выход из панели</span></div>
            <div style="margin-left: 40px;">│   ├── <span class="file">vlmcgeoip.php</span> <span class="comment">— геолокация IP-адресов через внешние API</span></div>
            <div style="margin-left: 40px;">│   ├── <span class="file">vlmcloghandler.php</span> <span class="comment">— обработка и форматирование логов</span></div>
            <div style="margin-left: 40px;">│   ├── <span class="file">vlmctheme.php</span> <span class="comment">— библиотека тем оформления</span></div>
            <div style="margin-left: 40px;">│   ├── <span class="file">flags.php</span> <span class="comment">— маппинг названий стран на коды флагов</span></div>
            <div style="margin-left: 40px;">│   ├── <span class="file">vlmcconf_config.json</span> <span class="comment">— конфигурация (группы, устройства, тема, язык)</span></div>
            <div style="margin-left: 40px;">│   ├── <span class="file">users.json</span> <span class="comment">— база пользователей (логины, хеши паролей, права)</span></div>
            <div style="margin-left: 40px;">│   ├── <span class="dir">📁 vlmcinc/</span> <span class="comment">— вспомогательные PHP-библиотеки</span></div>
            <div style="margin-left: 60px;">│   │   ├── <span class="file">config.php</span> <span class="comment">— общие функции и утилиты</span></div>
            <div style="margin-left: 60px;">│   │   ├── <span class="file">auth.php</span> — функции авторизации</div>
            <div style="margin-left: 60px;">│   │   ├── <span class="file">users.php</span> — управление пользователями и правами доступа</div>
            <div style="margin-left: 60px;">│   │   ├── <span class="file">analytics.php</span> — функции статистики (активность, графики)</div>
            <div style="margin-left: 60px;">│   │   ├── <span class="file">structure.php</span> — динамическая структура проекта</div>
            <div style="margin-left: 60px;">│   │   ├── <span class="file">ajax.php</span> — AJAX-обработчики для динамических запросов</div>
            <div style="margin-left: 40px;">│   ├── <span class="dir">📁 sections/</span> <span class="comment">— отдельные страницы панели управления</span></div>
            <div style="margin-left: 60px;">│   │   ├── <span class="file">general.php</span> — общие настройки (тема, язык, путь к логу)</div>
            <div style="margin-left: 60px;">│   │   ├── <span class="file">groups.php</span> — управление группами устройств</div>
            <div style="margin-left: 60px;">│   │   ├── <span class="file">devices.php</span> — управление устройствами</div>
            <div style="margin-left: 60px;">│   │   ├── <span class="file">security.php</span> — безопасность, очистка логов, управление пользователями</div>
            <div style="margin-left: 60px;">│   │   ├── <span class="file">stats.php</span> — статистика и графики</div>
            <div style="margin-left: 60px;">│   │   ├── <span class="file">info.php</span> — информация о проекте и структура файлов</div>
            <div style="margin-left: 40px;">│   ├── <span class="dir">📁 locale/</span> <span class="comment">— языковые файлы</span></div>
            <div style="margin-left: 60px;">│   │   ├── <span class="file">ru.php</span> — русские переводы</div>
            <div style="margin-left: 60px;">│   │   ├── <span class="file">en.php</span> — английские переводы</div>
            <div style="margin-left: 40px;">│   ├── <span class="file">.htaccess</span> <span class="comment">— защита директории vlmcconf</span></div>
            <div style="margin-left: 20px;">├── <span class="dir">📁 pic/</span> <span class="comment">— иконки и изображения</span></div>
        </div>
    </div>
    
    <div class="section-card">
        <div class="section-title"><span>🛠️</span> 9. Устранение неполадок</div>
        
        <h4>❌ Ошибка 403 Forbidden</h4>
        <div class="code-block"><pre># Проверь права
sudo chown -R www-data:www-data /var/www/html
sudo chmod 755 /var/www/html
sudo chmod 644 /var/www/html/vlmc.php

# Проверь .htaccess
cat /var/www/html/.htaccess</pre></div>
        
        <h4>❌ Файл лога не найден</h4>
        <div class="code-block"><pre># Проверь, существует ли лог-файл
ls -la /var/log/vlmcsd.log

# Если нет — создай
sudo touch /var/log/vlmcsd.log
sudo chown www-data:www-data /var/log/vlmcsd.log
sudo chmod 644 /var/log/vlmcsd.log

# Создай символическую ссылку
sudo ln -s /var/log/vlmcsd.log /var/www/html/vlmcsd.log</pre></div>
        
        <h4>❌ Не сохраняются настройки</h4>
        <div class="code-block"><pre># Проверь права на запись
sudo chmod 664 /var/www/html/vlmcconf/vlmcconf_config.json
sudo chown www-data:www-data /var/www/html/vlmcconf/vlmcconf_config.json</pre></div>
        
        <h4>❌ Нет флагов в блоке "Подозрительные IP"</h4>
        <div class="code-block"><pre># Проверь подключение CSS flag-icons (открой консоль браузера F12 → вкладка Network)
# Если флаги не загружаются — проверь интернет-соединение</pre></div>
    </div>
</div>
HTML_WEB_RU;

// ============================================
// РУССКАЯ ВЕРСИЯ — KMS Сервер (ИСПРАВЛЕНА)
// ============================================
$docKmsRu = <<<'HTML_KMS_RU'
<div class="doc-content-inner">
    <div class="section-card">
        <div class="section-title"><span>📥</span> 1. Загрузка и подготовка файлов</div>
        <p>Перейди по ссылке и скачай архив с бинарными файлами:</p>
        <div class="code-block"><pre>🔗 <a href="https://www.upload.ee/files/11363704/vlmcsd-1113-2020-03-28-Hotbird64.7z.html" target="_blank">https://www.upload.ee/files/11363704/vlmcsd-1113-2020-03-28-Hotbird64.7z.html</a></pre></div>
        <p>После скачивания распакуй архив на своём компьютере. Внутри найди папку <code>binaries/Linux/</code>.</p>
        <p><strong>Важно:</strong> Файлы нужно загрузить на сервер через SFTP или другой способ. Ниже приведены команды для загрузки (выполняй с компьютера, где есть скачанный архив):</p>
        <div class="code-block"><pre># Загрузи архив на сервер (замени user и server на свои данные)
scp vlmcsd-1113-2020-03-28-Hotbird64.7z user@your-server:/tmp/

# Подключись по SSH и распакуй архив
ssh user@your-server
cd /tmp
sudo apt update
sudo apt install p7zip-full -y
7z x vlmcsd-1113-2020-03-28-Hotbird64.7z
cd binaries/Linux/</pre></div>
        
        <div class="note">💡 <strong>Выбор бинарного файла:</strong><br>
        • Для Ubuntu 22.04/24.04 x86_64: <code>cd intel/glibc/</code><br>
        • Файл: <code>vlmcsd-x64-glibc</code><br>
        • <strong>Важно:</strong> Обрати внимание на букву <strong>d</strong> в конце имени — это означает, что файл работает как демон (в фоне).</div>
        
        <div class="warning">⚠️ Если у тебя 32-битная система, выбери файл <code>vlmcsd-x86-glibc</code> в папке <code>intel/glibc/</code>.</div>
    </div>
    
    <div class="section-card">
        <div class="section-title"><span>📁</span> 2. Создание директории и копирование файлов</div>
        <p>Создай папку для KMS-сервера и скопируй три необходимых файла:</p>
        <div class="code-block"><pre># Создай папку для KMS-сервера
sudo mkdir -p /usr/local/vlmcsd

# Скопируй три необходимых файла (выполняй из папки, куда распакован архив)
sudo cp vlmcsd.ini /usr/local/vlmcsd/
sudo cp vlmcsd.kmd /usr/local/vlmcsd/
sudo cp vlmcsd-x64-glibc /usr/local/vlmcsd/vlmcsd

# Сделай файл исполняемым
sudo chmod +x /usr/local/vlmcsd/vlmcsd
sudo chmod 775 /usr/local/vlmcsd/vlmcsd

# Проверь, что файлы на месте
ls -la /usr/local/vlmcsd/</pre></div>
        <div class="note">📌 <strong>Что за файлы:</strong>
        <ul>
            <li><code>vlmcsd</code> — сам KMS-сервер (переименованный бинарный файл)</li>
            <li><code>vlmcsd.ini</code> — конфигурационный файл (из папки <code>\etc</code> архива)</li>
            <li><code>vlmcsd.kmd</code> — файл данных активаций (создаётся автоматически, но лучше скопировать из архива)</li>
        </ul>
        </div>
    </div>
    
    <div class="section-card">
        <div class="section-title"><span>⚙️</span> 3. Создание конфигурационного файла</div>
        <p>Отредактируй конфигурационный файл (или создай новый, если его нет):</p>
        <div class="code-block"><pre>sudo nano /usr/local/vlmcsd/vlmcsd.ini</pre></div>
        <p>Вставь следующее содержимое:</p>
        <div class="code-block"><pre>[General]
# Порт для прослушивания (стандартный KMS-порт)
Port = 1688

# Файл для хранения данных активаций
DataFile = /usr/local/vlmcsd/vlmcsd.kmd

# Уровень логирования (0-5, 3 — оптимально)
LogLevel = 3

# Файл лога
LogFile = /var/log/vlmcsd.log

# Запуск от определённого пользователя (безопасность)
RunAsUser = nobody
RunAsGroup = nogroup</pre></div>
        <div class="note">💡 Все настройки подробно описаны в файле <code>vlmcsd.ini</code> внутри архива.</div>
    </div>
    
    <div class="section-card">
        <div class="section-title"><span>🚀</span> 4. Запуск KMS-сервера</div>
        <div class="code-block"><pre># Ручной запуск для проверки
sudo /usr/local/vlmcsd/vlmcsd -i /usr/local/vlmcsd/vlmcsd.ini -l /var/log/vlmcsd.log</pre></div>
        <p>Проверь, что сервер запустился:</p>
        <div class="code-block"><pre># Проверь, что порт 1688 открыт
sudo netstat -tlnp | grep 1688
# Должен увидеть: tcp 0 0 0.0.0.0:1688 0.0.0.0:* LISTEN

# Посмотри лог
sudo tail -f /var/log/vlmcsd.log</pre></div>
        <div class="success">✅ Успешный запуск должен показать в логе:<br>
        <code>Listening on [::]:1688</code><br>
        <code>Listening on 0.0.0.0:1688</code><br>
        <code>vlmcsd started successfully</code></div>
        <div class="warning">⚠️ Если сервер не запускается, проверь права на файлы и что порт 1688 не занят.</div>
    </div>
    
    <div class="section-card">
        <div class="section-title"><span>🔄</span> 5. Автозагрузка (systemd)</div>
        <p>Для Ubuntu 22.04/24.04 используем systemd:</p>
        <div class="code-block"><pre># Создай файл сервиса
sudo nano /etc/systemd/system/vlmcsd.service</pre></div>
        <p>Вставь следующее содержимое:</p>
        <div class="code-block"><pre>[Unit]
Description=vlmcsd KMS Server
After=network.target

[Service]
Type=forking
ExecStart=/usr/local/vlmcsd/vlmcsd -i /usr/local/vlmcsd/vlmcsd.ini -l /var/log/vlmcsd.log
PIDFile=/run/vlmcsd.pid
Restart=on-failure
RestartSec=5
User=nobody
Group=nogroup

[Install]
WantedBy=multi-user.target</pre></div>
        <div class="code-block"><pre># Перезагрузи конфигурацию systemd
sudo systemctl daemon-reload

# Добавь в автозагрузку
sudo systemctl enable vlmcsd

# Запусти сервис
sudo systemctl start vlmcsd

# Проверь статус
sudo systemctl status vlmcsd</pre></div>
        <div class="note">📌 <strong>Важно:</strong> После настройки автозагрузки KMS-сервер будет автоматически запускаться при каждой перезагрузке сервера.</div>
    </div>
    
    <div class="section-card">
        <div class="section-title"><span>🔗</span> 6. Настройка доступа к логу для веб-оболочки</div>
        <p>Чтобы веб-оболочка могла читать лог KMS-сервера:</p>
        <div class="code-block"><pre># Установи права на лог-файл
sudo chmod 644 /var/log/vlmcsd.log
sudo chown www-data:www-data /var/log/vlmcsd.log

# Создай символическую ссылку в папке веб-оболочки
sudo ln -s /var/log/vlmcsd.log /var/www/html/vlmcsd.log

# Проверь
ls -la /var/www/html/vlmcsd.log</pre></div>
        <div class="warning">⚠️ Если лог-файл не читается, веб-оболочка не будет отображать данные. Убедись, что пользователь <code>www-data</code> имеет доступ к файлу.</div>
    </div>
    
    <div class="section-card">
        <div class="section-title"><span>🛠️</span> 7. Устранение неполадок KMS-сервера</div>
        
        <h4>❌ KMS сервер не запускается</h4>
        <div class="code-block"><pre># Проверь лог на ошибки
sudo tail -50 /var/log/vlmcsd.log

# Проверь права на файлы
ls -la /usr/local/vlmcsd/

# Проверь, не занят ли порт 1688 другим процессом
sudo lsof -i :1688</pre></div>
        
        <h4>❌ Ошибка "Address already in use"</h4>
        <div class="code-block"><pre># Найди процесс, использующий порт 1688
sudo lsof -i :1688
# Убей процесс (замени PID на реальный)
sudo kill -9 PID

# Или проверь, не запущен ли уже vlmcsd
sudo systemctl stop vlmcsd
sudo pkill vlmcsd</pre></div>
        
        <h4>❌ Клиенты не могут активироваться</h4>
        <div class="code-block"><pre># Проверь, что порт 1688 открыт в firewall
sudo ufw status
# Если нужно — открой порт
sudo ufw allow 1688/tcp

# Проверь, что сервер слушает все интерфейсы
sudo netstat -tlnp | grep 1688
# Должен быть 0.0.0.0:1688, а не 127.0.0.1:1688</pre></div>
    </div>
</div>
HTML_KMS_RU;

// ============================================
// АНГЛИЙСКАЯ ВЕРСИЯ — Web Interface
// ============================================
$docWebEn = <<<'HTML_WEB_EN'
<div class="doc-content-inner">
    <div class="resources">
        <strong>📌 About KMS Monitor:</strong><br>
        KMS Monitor is a web interface for monitoring and managing a KMS server based on <strong>vlmcsd</strong>.<br>
        The KMS server itself is distributed separately.<br><br>
        <strong>Official vlmcsd resources:</strong><br>
        • Developer Forum: <a href="https://forums.mydigitallife.net/threads/emulated-kms-servers-on-non-windows-platforms.50234/" target="_blank">My Digital Life Forums</a><br>
        • Distribution (source only): <a href="https://www.upload.ee/files/11363713/vlmcsd-1113-2020-03-28-Hotbird64-source-only.7z.html" target="_blank">Download</a><br>
        • Distribution (source + binaries): <a href="https://www.upload.ee/files/11363704/vlmcsd-1113-2020-03-28-Hotbird64.7z.html" target="_blank">Download</a>
    </div>
    
    <div class="section-card">
        <div class="section-title"><span>📋</span> 1. Server Requirements</div>
        <ul>
            <li>Web server: <strong>Apache 2.4+</strong> with <code>mod_rewrite</code> support</li>
            <li>PHP: <strong>7.4 — 8.3</strong> (recommended 8.1+)</li>
            <li>PHP extensions: <code>curl</code>, <code>json</code>, <code>session</code>, <code>fileinfo</code></li>
            <li>Internet access for IP geolocation (optional)</li>
        </ul>
    </div>
    
    <div class="section-card">
        <div class="section-title"><span>📁</span> 2. File Transfer</div>
        <p>Copy all project files to <code>/var/www/html/</code>:</p>
        <div class="code-block"><pre># If files are already on server — just verify
ls -la /var/www/html/

# If transferring from another server — create archive
cd /var/www
tar -czf kms-monitor.tar.gz html/

# Copy archive to new server
scp kms-monitor.tar.gz user@new-server:/tmp/

# Extract on new server
cd /var/www
sudo tar -xzf /tmp/kms-monitor.tar.gz

# Verify files are in place
ls -la /var/www/html/vlmc.php</pre></div>
        <div class="note">💡 <strong>Important:</strong> The web root directory is <code>/var/www/html/</code>. All project files must be placed here.</div>
    </div>
    
    <div class="section-card">
        <div class="section-title"><span>🔧</span> 3. Apache Configuration</div>
        <p>Create a virtual host configuration file:</p>
        <div class="code-block"><pre>sudo nano /etc/apache2/sites-available/kms-monitor.conf</pre></div>
        <p>Insert the following content (replace <code>example.com</code> with your domain or IP):</p>
        <div class="code-block"><pre>&lt;VirtualHost *:80&gt;
    ServerName example.com
    ServerAdmin admin@example.com
    DocumentRoot /var/www/html
    
    &lt;Directory /var/www/html&gt;
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    &lt;/Directory&gt;
    
    ErrorLog /var/log/apache2/kms-error.log
    CustomLog /var/log/apache2/kms-access.log combined
&lt;/VirtualHost&gt;</pre></div>
        <p>Enable the site and restart Apache:</p>
        <div class="code-block"><pre># Enable the site
sudo a2ensite kms-monitor.conf

# Disable default site (if used)
sudo a2dissite 000-default.conf

# Check configuration for errors
sudo apachectl configtest

# Restart Apache
sudo systemctl reload apache2</pre></div>
        <div class="success">✅ After this, the site should be available at <code>http://example.com/vlmc.php</code></div>
    </div>
    
    <div class="section-card">
        <div class="section-title"><span>🔒</span> 4. Permissions Setup</div>
        <div class="code-block"><pre># Set owner (www-data is Apache user)
sudo chown -R www-data:www-data /var/www/html

# Set file permissions
sudo find /var/www/html -type f -exec chmod 644 {} \;
sudo find /var/www/html -type d -exec chmod 755 {} \;

# Special permissions for config files (read-only)
sudo chmod 640 /var/www/html/vlmcconf/vlmcconf_config.json
sudo chmod 640 /var/www/html/vlmcconf/users.json</pre></div>
        <div class="warning">⚠️ <strong>Important:</strong> Files <code>vlmcconf_config.json</code> and <code>users.json</code> contain hashed passwords. Ensure they are protected from direct access via <code>.htaccess</code>.</div>
    </div>
    
    <div class="section-card">
        <div class="section-title"><span>📝</span> 5. Log File Access Configuration</div>
        <p>The web interface needs access to the KMS server log file. There are two ways:</p>
        
        <h4>Method 1: Symbolic Link (Recommended)</h4>
        <div class="code-block"><pre># Create symbolic link to log in html folder
sudo ln -s /var/log/vlmcsd.log /var/www/html/vlmcsd.log

# Verify the link
ls -la /var/www/html/vlmcsd.log</pre></div>
        <div class="note">💡 <strong>Advantage:</strong> If the log path changes on the KMS server, you only need to update the symlink, not the web interface settings.</div>
        
        <h4>Method 2: Specify Path in Web Interface</h4>
        <p>After first login to the control panel (<code>/vlmcconf/login.php</code>):</p>
        <ol>
            <li>Go to <strong>General Settings</strong></li>
            <li>In <strong>Log file path</strong> field, enter <code>/var/log/vlmcsd.log</code></li>
            <li>Click <strong>Save Path</strong></li>
        </ol>
        
        <div class="warning">⚠️ <strong>Important:</strong> Ensure the <code>www-data</code> user has read permissions for the log file:
        <div class="code-block"><pre>sudo chmod 644 /var/log/vlmcsd.log
sudo chown www-data:www-data /var/log/vlmcsd.log</pre></div></div>
    </div>
    
    <div class="section-card">
        <div class="section-title"><span>🔐</span> 6. SSL Configuration (Let's Encrypt)</div>
        
        <h4>For Ubuntu 22.04 LTS:</h4>
        <div class="code-block"><pre># Install software-properties-common
sudo apt update
sudo apt install software-properties-common -y

# Add certbot repository
sudo add-apt-repository ppa:certbot/certbot -y
sudo apt update

# Install certbot and Apache plugin
sudo apt install certbot python3-certbot-apache -y

# Obtain certificate (replace example.com with your domain)
sudo certbot --apache -d example.com

# Test automatic renewal
sudo certbot renew --dry-run</pre></div>
        
        <h4>For Ubuntu 24.04 LTS:</h4>
        <div class="code-block"><pre># Install snapd
sudo apt update
sudo apt install snapd -y

# Install certbot via snap
sudo snap install core
sudo snap refresh core
sudo snap install --classic certbot
sudo ln -s /snap/bin/certbot /usr/bin/certbot

# Obtain certificate (replace example.com with your domain)
sudo certbot --apache -d example.com

# Test automatic renewal
sudo certbot renew --dry-run</pre></div>
        
        <div class="note">💡 Certbot automatically configures certificate renewal. Check with: <code>sudo systemctl list-timers | grep certbot</code></div>
    </div>
    
    <div class="section-card">
        <div class="section-title"><span>🔐</span> 7. First Login to Control Panel</div>
        <ol>
            <li>Open browser and go to: <code>http://example.com/vlmcconf/login.php</code> (or <code>https://...</code> after SSL setup)</li>
            <li>Enter default credentials:
                <ul>
                    <li><strong>Username:</strong> <code>root</code></li>
                    <li><strong>Password:</strong> <code>root</code></li>
                </ul>
            </li>
            <li>The system <strong>will require password change</strong> on first login.</li>
            <li>Set a new password (requirements: minimum 8 characters, uppercase/lowercase letters, digits, special characters).</li>
            <li>After password change, the control panel will open.</li>
        </ol>
        <div class="success">🎉 <strong>Done!</strong> Now you can configure groups, add devices, and manage users.</div>
    </div>
    
    <div class="section-card">
        <div class="section-title"><span>📂</span> 8. Complete File Structure</div>
        <div class="file-tree">
            <div><span class="dir">📁 /var/www/html/</span> <span class="comment">— web root directory</span></div>
            <div style="margin-left: 20px;">├── <span class="file">vlmc.php</span> <span class="comment">— main monitoring page (public)</span></div>
            <div style="margin-left: 20px;">├── <span class="file">.htaccess</span> <span class="comment">— protection for config files</span></div>
            <div style="margin-left: 20px;">├── <span class="file">index.php</span> <span class="comment">— redirects to vlmc.php</span></div>
            <div style="margin-left: 20px;">├── <span class="dir">📁 vlmcconf/</span> <span class="comment">— control panel (requires authentication)</span></div>
            <div style="margin-left: 40px;">│   ├── <span class="file">vlmcconf.php</span> <span class="comment">— main settings panel</span></div>
            <div style="margin-left: 40px;">│   ├── <span class="file">login.php</span> <span class="comment">— login page</span></div>
            <div style="margin-left: 40px;">│   ├── <span class="file">logout.php</span> <span class="comment">— logout</span></div>
            <div style="margin-left: 40px;">│   ├── <span class="file">vlmcgeoip.php</span> <span class="comment">— IP geolocation via external APIs</span></div>
            <div style="margin-left: 40px;">│   ├── <span class="file">vlmcloghandler.php</span> <span class="comment">— log processing and formatting</span></div>
            <div style="margin-left: 40px;">│   ├── <span class="file">vlmctheme.php</span> <span class="comment">— theme library</span></div>
            <div style="margin-left: 40px;">│   ├── <span class="file">flags.php</span> <span class="comment">— country name to flag code mapping</span></div>
            <div style="margin-left: 40px;">│   ├── <span class="file">vlmcconf_config.json</span> <span class="comment">— configuration (groups, devices, theme, language)</span></div>
            <div style="margin-left: 40px;">│   ├── <span class="file">users.json</span> <span class="comment">— user database (usernames, password hashes, permissions)</span></div>
            <div style="margin-left: 40px;">│   ├── <span class="dir">📁 vlmcinc/</span> <span class="comment">— helper PHP libraries</span></div>
            <div style="margin-left: 60px;">│   │   ├── <span class="file">config.php</span> <span class="comment">— common functions and utilities</span></div>
            <div style="margin-left: 60px;">│   │   ├── <span class="file">auth.php</span> — authentication functions</div>
            <div style="margin-left: 60px;">│   │   ├── <span class="file">users.php</span> — user and permission management</div>
            <div style="margin-left: 60px;">│   │   ├── <span class="file">analytics.php</span> — statistics functions (activity, charts)</div>
            <div style="margin-left: 60px;">│   │   ├── <span class="file">structure.php</span> — dynamic project structure</div>
            <div style="margin-left: 60px;">│   │   ├── <span class="file">ajax.php</span> — AJAX handlers for dynamic requests</div>
            <div style="margin-left: 40px;">│   ├── <span class="dir">📁 sections/</span> <span class="comment">— control panel pages</span></div>
            <div style="margin-left: 60px;">│   │   ├── <span class="file">general.php</span> — general settings (theme, language, log path)</div>
            <div style="margin-left: 60px;">│   │   ├── <span class="file">groups.php</span> — device group management</div>
            <div style="margin-left: 60px;">│   │   ├── <span class="file">devices.php</span> — device management</div>
            <div style="margin-left: 60px;">│   │   ├── <span class="file">security.php</span> — security, log cleanup, user management</div>
            <div style="margin-left: 60px;">│   │   ├── <span class="file">stats.php</span> — statistics and charts</div>
            <div style="margin-left: 60px;">│   │   ├── <span class="file">info.php</span> — project information and file structure</div>
            <div style="margin-left: 40px;">│   ├── <span class="dir">📁 locale/</span> <span class="comment">— language files</span></div>
            <div style="margin-left: 60px;">│   │   ├── <span class="file">ru.php</span> — Russian translations</div>
            <div style="margin-left: 60px;">│   │   ├── <span class="file">en.php</span> — English translations</div>
            <div style="margin-left: 40px;">│   ├── <span class="file">.htaccess</span> <span class="comment">— vlmcconf directory protection</span></div>
            <div style="margin-left: 20px;">├── <span class="dir">📁 pic/</span> <span class="comment">— icons and images</span></div>
        </div>
    </div>
    
    <div class="section-card">
        <div class="section-title"><span>🛠️</span> 9. Troubleshooting (Web Interface)</div>
        
        <h4>❌ Error 403 Forbidden</h4>
        <div class="code-block"><pre># Check permissions
sudo chown -R www-data:www-data /var/www/html
sudo chmod 755 /var/www/html
sudo chmod 644 /var/www/html/vlmc.php

# Check .htaccess
cat /var/www/html/.htaccess</pre></div>
        
        <h4>❌ Log file not found</h4>
        <div class="code-block"><pre># Check if log file exists
ls -la /var/log/vlmcsd.log

# If not — create it
sudo touch /var/log/vlmcsd.log
sudo chown www-data:www-data /var/log/vlmcsd.log
sudo chmod 644 /var/log/vlmcsd.log

# Create symbolic link
sudo ln -s /var/log/vlmcsd.log /var/www/html/vlmcsd.log</pre></div>
        
        <h4>❌ Settings not saving</h4>
        <div class="code-block"><pre># Check write permissions
sudo chmod 664 /var/www/html/vlmcconf/vlmcconf_config.json
sudo chown www-data:www-data /var/www/html/vlmcconf/vlmcconf_config.json</pre></div>
        
        <h4>❌ Country flags not displaying</h4>
        <div class="code-block"><pre># Check flag-icons CSS loading (open browser console F12 → Network tab)
# If flags don't load — check internet connection</pre></div>
    </div>
</div>
HTML_WEB_EN;

// ============================================
// АНГЛИЙСКАЯ ВЕРСИЯ — KMS Server (FIXED)
// ============================================
$docKmsEn = <<<'HTML_KMS_EN'
<div class="doc-content-inner">
    <div class="section-card">
        <div class="section-title"><span>📥</span> 1. Download and Prepare Files</div>
        <p>Go to the link and download the archive with binary files:</p>
        <div class="code-block"><pre>🔗 <a href="https://www.upload.ee/files/11363704/vlmcsd-1113-2020-03-28-Hotbird64.7z.html" target="_blank">https://www.upload.ee/files/11363704/vlmcsd-1113-2020-03-28-Hotbird64.7z.html</a></pre></div>
        <p>After downloading, extract the archive on your computer. Inside, find the folder <code>binaries/Linux/</code>.</p>
        <p><strong>Important:</strong> Files need to be uploaded to the server via SFTP or another method. Below are commands for uploading (run from the computer where the archive is downloaded):</p>
        <div class="code-block"><pre># Upload the archive to the server (replace user and server with your data)
scp vlmcsd-1113-2020-03-28-Hotbird64.7z user@your-server:/tmp/

# Connect via SSH and extract the archive
ssh user@your-server
cd /tmp
sudo apt update
sudo apt install p7zip-full -y
7z x vlmcsd-1113-2020-03-28-Hotbird64.7z
cd binaries/Linux/</pre></div>
        
        <div class="note">💡 <strong>Choosing the binary:</strong><br>
        • For Ubuntu 22.04/24.04 x86_64: <code>cd intel/glibc/</code><br>
        • File: <code>vlmcsd-x64-glibc</code><br>
        • <strong>Important:</strong> Note the letter <strong>d</strong> at the end — this indicates daemon mode.</div>
        
        <div class="warning">⚠️ If you have a 32-bit system, choose <code>vlmcsd-x86-glibc</code> from the <code>intel/glibc/</code> folder.</div>
    </div>
    
    <div class="section-card">
        <div class="section-title"><span>📁</span> 2. Create Directory and Copy Files</div>
        <p>Create a folder for the KMS server and copy the three required files:</p>
        <div class="code-block"><pre># Create KMS server directory
sudo mkdir -p /usr/local/vlmcsd

# Copy three required files (run from the extracted folder)
sudo cp vlmcsd.ini /usr/local/vlmcsd/
sudo cp vlmcsd.kmd /usr/local/vlmcsd/
sudo cp vlmcsd-x64-glibc /usr/local/vlmcsd/vlmcsd

# Make executable
sudo chmod +x /usr/local/vlmcsd/vlmcsd
sudo chmod 775 /usr/local/vlmcsd/vlmcsd

# Verify files are in place
ls -la /usr/local/vlmcsd/</pre></div>
        <div class="note">📌 <strong>What these files are:</strong>
        <ul>
            <li><code>vlmcsd</code> — the KMS server itself (renamed binary file)</li>
            <li><code>vlmcsd.ini</code> — configuration file (from the <code>\etc</code> folder of the archive)</li>
            <li><code>vlmcsd.kmd</code> — activation data file (created automatically, but better to copy from the archive)</li>
        </ul>
        </div>
    </div>
    
    <div class="section-card">
        <div class="section-title"><span>⚙️</span> 3. Create Configuration File</div>
        <p>Edit the configuration file (or create a new one if it doesn't exist):</p>
        <div class="code-block"><pre>sudo nano /usr/local/vlmcsd/vlmcsd.ini</pre></div>
        <p>Insert the following content:</p>
        <div class="code-block"><pre>[General]
# Listening port (standard KMS port)
Port = 1688

# Activation data storage file
DataFile = /usr/local/vlmcsd/vlmcsd.kmd

# Logging level (0-5, 3 is optimal)
LogLevel = 3

# Log file
LogFile = /var/log/vlmcsd.log

# Run as specific user (security)
RunAsUser = nobody
RunAsGroup = nogroup</pre></div>
        <div class="note">💡 All settings are well documented in the <code>vlmcsd.ini</code> file inside the archive.</div>
    </div>
    
    <div class="section-card">
        <div class="section-title"><span>🚀</span> 4. Start KMS Server</div>
        <div class="code-block"><pre># Manual start for testing
sudo /usr/local/vlmcsd/vlmcsd -i /usr/local/vlmcsd/vlmcsd.ini -l /var/log/vlmcsd.log</pre></div>
        <p>Verify the server started correctly:</p>
        <div class="code-block"><pre># Check if port 1688 is listening
sudo netstat -tlnp | grep 1688
# Should see: tcp 0 0 0.0.0.0:1688 0.0.0.0:* LISTEN

# View log
sudo tail -f /var/log/vlmcsd.log</pre></div>
        <div class="success">✅ Successful startup shows in log:<br>
        <code>Listening on [::]:1688</code><br>
        <code>Listening on 0.0.0.0:1688</code><br>
        <code>vlmcsd started successfully</code></div>
        <div class="warning">⚠️ If the server doesn't start, check file permissions and that port 1688 is not in use.</div>
    </div>
    
    <div class="section-card">
        <div class="section-title"><span>🔄</span> 5. Autostart with systemd</div>
        <p>For Ubuntu 22.04/24.04, use systemd:</p>
        <div class="code-block"><pre># Create service file
sudo nano /etc/systemd/system/vlmcsd.service</pre></div>
        <p>Insert the following content:</p>
        <div class="code-block"><pre>[Unit]
Description=vlmcsd KMS Server
After=network.target

[Service]
Type=forking
ExecStart=/usr/local/vlmcsd/vlmcsd -i /usr/local/vlmcsd/vlmcsd.ini -l /var/log/vlmcsd.log
PIDFile=/run/vlmcsd.pid
Restart=on-failure
RestartSec=5
User=nobody
Group=nogroup

[Install]
WantedBy=multi-user.target</pre></div>
        <div class="code-block"><pre># Reload systemd configuration
sudo systemctl daemon-reload

# Enable autostart
sudo systemctl enable vlmcsd

# Start the service
sudo systemctl start vlmcsd

# Check status
sudo systemctl status vlmcsd</pre></div>
        <div class="note">📌 <strong>Important:</strong> After configuring autostart, the KMS server will automatically start on every system reboot.</div>
    </div>
    
    <div class="section-card">
        <div class="section-title"><span>🔗</span> 6. Log Access for Web Interface</div>
        <p>To allow the web interface to read the KMS server log:</p>
        <div class="code-block"><pre># Set log file permissions
sudo chmod 644 /var/log/vlmcsd.log
sudo chown www-data:www-data /var/log/vlmcsd.log

# Create symbolic link in web folder
sudo ln -s /var/log/vlmcsd.log /var/www/html/vlmcsd.log

# Verify
ls -la /var/www/html/vlmcsd.log</pre></div>
        <div class="warning">⚠️ If the log file is not readable, the web interface won't display any data. Ensure the <code>www-data</code> user has access to the file.</div>
    </div>
    
    <div class="section-card">
        <div class="section-title"><span>🛠️</span> 7. KMS Server Troubleshooting</div>
        
        <h4>❌ KMS server won't start</h4>
        <div class="code-block"><pre># Check log for errors
sudo tail -50 /var/log/vlmcsd.log

# Check file permissions
ls -la /usr/local/vlmcsd/

# Check if port 1688 is already in use
sudo lsof -i :1688</pre></div>
        
        <h4>❌ Error "Address already in use"</h4>
        <div class="code-block"><pre># Find process using port 1688
sudo lsof -i :1688
# Kill the process (replace PID with actual number)
sudo kill -9 PID

# Or stop any running vlmcsd
sudo systemctl stop vlmcsd
sudo pkill vlmcsd</pre></div>
        
        <h4>❌ Clients cannot activate</h4>
        <div class="code-block"><pre># Check if port 1688 is open in firewall
sudo ufw status
# If needed — open the port
sudo ufw allow 1688/tcp

# Verify server listens on all interfaces
sudo netstat -tlnp | grep 1688
# Should be 0.0.0.0:1688, not 127.0.0.1:1688</pre></div>
    </div>
</div>
HTML_KMS_EN;

// ============================================
// ПОЛНЫЙ HTML ДЛЯ СКАЧИВАНИЯ (с переключателями) - ИСПРАВЛЕНА
// ============================================
function getFullDownloadHtml() {
    global $docWebRu, $docKmsRu, $docWebEn, $docKmsEn;
    
    // Экранируем содержимое для вставки в JavaScript
    $webRuJson = json_encode($docWebRu);
    $kmsRuJson = json_encode($docKmsRu);
    $webEnJson = json_encode($docWebEn);
    $kmsEnJson = json_encode($docKmsEn);
    
    // Формируем HTML через конкатенацию
    $html = '<!DOCTYPE html>
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
        
        .lang-tabs, .section-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        
        .lang-btn, .section-btn {
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
        
        .lang-btn.active, .section-btn.active {
            background: #3b82f6;
            color: white;
        }
        
        .lang-btn:hover:not(.active), .section-btn:hover:not(.active) {
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
        
        /* Стили для скроллбара */
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
            border: 1px solid #33485d;
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
        
        .file-tree .dir { color: #3b82f6; }
        .file-tree .file { color: #8aa0bb; }
        .file-tree .comment { color: #6b8ba4; margin-left: 10px; }
        
        .resources {
            background: #0f1a2f;
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
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
            .lang-btn, .section-btn { padding: 6px 12px; font-size: 12px; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>📦 KMS Monitor</h1>
        <div class="version">Версия 4.8.1 | Полное руководство по развёртыванию | Апрель 2026</div>
    </div>
    
    <div class="tabs-wrapper">
        <div class="lang-tabs">
            <button class="lang-btn active" data-lang="ru">🇷🇺 Русский</button>
            <button class="lang-btn" data-lang="en">🇬🇧 English</button>
        </div>
        
        <div class="section-tabs">
            <button class="section-btn active" data-section="web">🌐 Веб-оболочка</button>
            <button class="section-btn" data-section="kms">🖥️ KMS Сервер</button>
        </div>
    </div>
    
    <div id="docContent" class="doc-content"></div>
    
    <footer>
        KMS Monitor v4.8.1 — Полное руководство по развёртыванию<br>
        © 2025-2026
    </footer>
</div>

<script>
// Все содержимое для разных языков и вкладок
const contentMap = {
    ru: {
        web: ' . $webRuJson . ',
        kms: ' . $kmsRuJson . '
    },
    en: {
        web: ' . $webEnJson . ',
        kms: ' . $kmsEnJson . '
    }
};

const tabLabels = {
    ru: {
        web: "🌐 Веб-оболочка",
        kms: "🖥️ KMS Сервер"
    },
    en: {
        web: "🌐 Web Interface",
        kms: "🖥️ KMS Server"
    }
};

let currentLang = "ru";
let currentSection = "web";

function renderDocumentation() {
    const container = document.getElementById("docContent");
    if (container) {
        container.innerHTML = contentMap[currentLang][currentSection];
    }
    
    // Обновляем активные кнопки языка
    document.querySelectorAll(".lang-btn").forEach(btn => {
        btn.classList.remove("active");
        if (btn.dataset.lang === currentLang) btn.classList.add("active");
    });
    
// Обновляем текст кнопок секций в зависимости от языка
const webBtn = document.querySelector(\'.section-btn[data-section="web"]\');
const kmsBtn = document.querySelector(\'.section-btn[data-section="kms"]\');
if (webBtn) webBtn.innerHTML = tabLabels[currentLang].web;
if (kmsBtn) kmsBtn.innerHTML = tabLabels[currentLang].kms;
    
    // Обновляем активные кнопки секций
    document.querySelectorAll(".section-btn").forEach(btn => {
        btn.classList.remove("active");
        if (btn.dataset.section === currentSection) btn.classList.add("active");
    });
}

document.querySelectorAll(".lang-btn").forEach(btn => {
    btn.addEventListener("click", function() {
        currentLang = this.dataset.lang;
        renderDocumentation();
    });
});

document.querySelectorAll(".section-btn").forEach(btn => {
    btn.addEventListener("click", function() {
        currentSection = this.dataset.section;
        renderDocumentation();
    });
});

renderDocumentation();
</script>
</body>
</html>';
    
    return $html;
}

?>

<div id="section-documentation" class="settings-section <?= $activeSection === 'documentation' ? 'active' : '' ?>">
    <div class="section-title" style="display: flex; justify-content: space-between; align-items: center; flex-shrink: 0;">
        <span>📚 <?= __('doc_title') ?></span>
        <button id="downloadDocBtn" class="btn btn-primary" style="font-size: 12px; padding: 6px 12px;">📥 <?= __('doc_download') ?></button>
    </div>
    
    <div class="doc-tabs-wrapper" style="flex-shrink: 0;">
        <!-- Уровень 1: Переключение языка -->
        <div class="doc-lang-tabs">
            <button class="doc-lang-btn active" data-lang="ru">🇷🇺 Русский</button>
            <span style="color: <?= $themeCSS['border'] ?>;">|</span>
            <button class="doc-lang-btn" data-lang="en">🇬🇧 English</button>
        </div>
        
        <!-- Уровень 2: Вкладки (Веб-оболочка / KMS Сервер) -->
        <div class="doc-section-tabs">
            <button class="doc-tab-btn active" data-tab="web">🌐 <?= __('doc_web') ?></button>
            <span style="color: <?= $themeCSS['border'] ?>;">|</span>
            <button class="doc-tab-btn" data-tab="kms">🖥️ <?= __('doc_kms') ?></button>
        </div>
    </div>
    
    <!-- Контейнер для содержимого (будет прокручиваться) -->
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

/* Обертка для переключателей */
.doc-tabs-wrapper {
    flex-shrink: 0;
    margin-bottom: 15px;
}

/* Блок с переключателями */
.doc-lang-tabs, .doc-section-tabs {
    display: inline-flex;
    gap: 10px;
    background: <?= $themeCSS['input'] ?>;
    padding: 8px 12px;
    border-radius: 8px;
    margin-bottom: 10px;
}

.doc-lang-btn, .doc-tab-btn {
    background: none;
    border: none;
    cursor: pointer;
    font-weight: 600;
    padding: 4px 12px;
    border-radius: 6px;
    transition: all 0.2s;
}

.doc-lang-btn.active, .doc-tab-btn.active {
    color: <?= $themeCSS['primary'] ?> !important;
    background: <?= $themeCSS['card'] ?>;
}

.doc-lang-btn:hover, .doc-tab-btn:hover {
    opacity: 0.8;
    background: <?= $themeCSS['card'] ?>;
}

/* Стили для скроллбара внутри документации */
.doc-content::-webkit-scrollbar {
    width: 8px;
}
.doc-content::-webkit-scrollbar-track {
    background: <?= $themeCSS['bg'] ?>;
    border-radius: 4px;
}
.doc-content::-webkit-scrollbar-thumb {
    background: <?= $themeCSS['primary'] ?>;
    border-radius: 4px;
}
.doc-content::-webkit-scrollbar-thumb:hover {
    background: #2563eb;
}

/* Стили для внутреннего контента (без изменений) */
.doc-content .section-card {
    background: <?= $themeCSS['input'] ?>;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 25px;
    border: 1px solid <?= $themeCSS['border'] ?>;
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
.doc-content h3 {
    font-size: 18px;
    margin: 20px 0 12px 0;
    color: #8aa0bb;
}
.doc-content h4 {
    font-size: 16px;
    margin: 15px 0 10px 0;
    color: #b0c4de;
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
.doc-content .file-tree .dir {
    color: <?= $themeCSS['primary'] ?>;
}
.doc-content .file-tree .file {
    color: #8aa0bb;
}
.doc-content .file-tree .comment {
    color: #6b8ba4;
    margin-left: 8px;
}
.doc-content .resources {
    background: <?= $themeCSS['input'] ?>;
    padding: 16px 20px;
    border-radius: 8px;
    margin-bottom: 25px;
    border: 1px solid <?= $themeCSS['border'] ?>;
}
#section-documentation.active {
    display: flex;
    flex-direction: column;
    height: 100%;
    min-height: 0;
}


</style>

<script>
// Все содержимое для разных языков и вкладок (для веб-версии)
const contentMap = {
    ru: {
        web: <?= json_encode($docWebRu) ?>,
        kms: <?= json_encode($docKmsRu) ?>
    },
    en: {
        web: <?= json_encode($docWebEn) ?>,
        kms: <?= json_encode($docKmsEn) ?>
    }
};

let currentLang = 'ru';
let currentTab = 'web';

// Тексты для кнопок вкладок на разных языках
const tabLabels = {
    ru: {
        web: '🌐 Веб-оболочка',
        kms: '🖥️ KMS Сервер'
    },
    en: {
        web: '🌐 Web Interface',
        kms: '🖥️ KMS Server'
    }
};

function renderDocumentation() {
    const container = document.getElementById('docContent');
    if (container) {
        container.innerHTML = contentMap[currentLang][currentTab];
    }
    
    // Обновляем активные кнопки языка
    document.querySelectorAll('.doc-lang-btn').forEach(btn => {
        btn.classList.remove('active');
        if (btn.dataset.lang === currentLang) {
            btn.classList.add('active');
            btn.style.color = '<?= $themeCSS['primary'] ?>';
        } else {
            btn.style.color = '<?= $themeCSS['text'] ?>';
        }
    });
    
    // Обновляем текст кнопок вкладок в зависимости от языка
    const webBtn = document.querySelector('.doc-tab-btn[data-tab="web"]');
    const kmsBtn = document.querySelector('.doc-tab-btn[data-tab="kms"]');
    if (webBtn) {
        webBtn.innerHTML = tabLabels[currentLang].web;
    }
    if (kmsBtn) {
        kmsBtn.innerHTML = tabLabels[currentLang].kms;
    }
    
    // Обновляем активные кнопки вкладок
    document.querySelectorAll('.doc-tab-btn').forEach(btn => {
        btn.classList.remove('active');
        if (btn.dataset.tab === currentTab) {
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

// Переключение вкладок
document.querySelectorAll('.doc-tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        currentTab = this.dataset.tab;
        renderDocumentation();
    });
});

// Скачивание ПОЛНОГО документа (с переключателями)
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
    } else if (docSection) {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.attributeName === 'class' && docSection.classList.contains('active')) {
                    renderDocumentation();
                    observer.disconnect();
                }
            });
        });
        observer.observe(docSection, { attributes: true });
    }
});
</script>