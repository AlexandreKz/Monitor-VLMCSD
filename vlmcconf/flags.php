<?php
// ============================================
// ФАЙЛ: vlmcconf/flags.php
// ВЕРСИЯ: 1.0.0
// ДАТА: 2026-03-28
// @description: Маппинг стран на коды флагов
// ============================================

/**
 * Получение кода страны для флага по названию страны
 * @param string $countryName Название страны (на английском или русском)
 * @return string Двухбуквенный код страны или пустая строка
 */
function getCountryCode($countryName) {
    static $codes = null;
    
    if ($codes === null) {
        $codes = [
            // Европа
            'Russia' => 'ru',
            'Russian Federation' => 'ru',
            'Россия' => 'ru',
            'United Kingdom' => 'gb',
            'UK' => 'gb',
            'Великобритания' => 'gb',
            'Germany' => 'de',
            'Германия' => 'de',
            'France' => 'fr',
            'Франция' => 'fr',
            'Poland' => 'pl',
            'Польша' => 'pl',
            'Ukraine' => 'ua',
            'Украина' => 'ua',
            'Netherlands' => 'nl',
            'The Netherlands' => 'nl',
            'Нидерланды' => 'nl',
            'Belgium' => 'be',
            'Бельгия' => 'be',
            'Italy' => 'it',
            'Италия' => 'it',
            'Spain' => 'es',
            'Испания' => 'es',
            'Sweden' => 'se',
            'Швеция' => 'se',
            'Finland' => 'fi',
            'Финляндия' => 'fi',
            'Norway' => 'no',
            'Норвегия' => 'no',
            'Denmark' => 'dk',
            'Дания' => 'dk',
            'Switzerland' => 'ch',
            'Швейцария' => 'ch',
            'Austria' => 'at',
            'Австрия' => 'at',
            'Czech Republic' => 'cz',
            'Чехия' => 'cz',
            'Hungary' => 'hu',
            'Венгрия' => 'hu',
            'Romania' => 'ro',
            'Румыния' => 'ro',
            'Bulgaria' => 'bg',
            'Болгария' => 'bg',
            'Greece' => 'gr',
            'Греция' => 'gr',
            'Portugal' => 'pt',
            'Португалия' => 'pt',
            'Ireland' => 'ie',
            'Ирландия' => 'ie',
            'Belarus' => 'by',
            'Беларусь' => 'by',
            'Lithuania' => 'lt',
            'Литва' => 'lt',
            'Latvia' => 'lv',
            'Латвия' => 'lv',
            'Estonia' => 'ee',
            'Эстония' => 'ee',
            'Serbia' => 'rs',
            'Сербия' => 'rs',
            'Croatia' => 'hr',
            'Хорватия' => 'hr',
            'Slovakia' => 'sk',
            'Словакия' => 'sk',
            'Slovenia' => 'si',
            'Словения' => 'si',
            
            // Северная Америка
            'United States' => 'us',
            'USA' => 'us',
            'США' => 'us',
            'Canada' => 'ca',
            'Канада' => 'ca',
            'Mexico' => 'mx',
            'Мексика' => 'mx',
            
            // Южная Америка
            'Brazil' => 'br',
            'Бразилия' => 'br',
            'Argentina' => 'ar',
            'Аргентина' => 'ar',
            'Chile' => 'cl',
            'Чили' => 'cl',
            'Peru' => 'pe',
            'Перу' => 'pe',
            'Colombia' => 'co',
            'Колумбия' => 'co',
            'Venezuela' => 've',
            'Венесуэла' => 've',
            'Ecuador' => 'ec',
            'Эквадор' => 'ec',
            'Bolivia' => 'bo',
            'Боливия' => 'bo',
            'Paraguay' => 'py',
            'Парагвай' => 'py',
            'Uruguay' => 'uy',
            'Уругвай' => 'uy',
            
            // Азия
            'China' => 'cn',
            'Китай' => 'cn',
            'Japan' => 'jp',
            'Япония' => 'jp',
            'South Korea' => 'kr',
            'Южная Корея' => 'kr',
            'India' => 'in',
            'Индия' => 'in',
            'Turkey' => 'tr',
            'Турция' => 'tr',
            'Iran' => 'ir',
            'Иран' => 'ir',
            'Israel' => 'il',
            'Израиль' => 'il',
            'Saudi Arabia' => 'sa',
            'Саудовская Аравия' => 'sa',
            'UAE' => 'ae',
            'United Arab Emirates' => 'ae',
            'ОАЭ' => 'ae',
            'Singapore' => 'sg',
            'Сингапур' => 'sg',
            'Malaysia' => 'my',
            'Малайзия' => 'my',
            'Indonesia' => 'id',
            'Индонезия' => 'id',
            'Philippines' => 'ph',
            'Филиппины' => 'ph',
            'Vietnam' => 'vn',
            'Вьетнам' => 'vn',
            'Thailand' => 'th',
            'Таиланд' => 'th',
            'Hong Kong' => 'hk',
            'Гонконг' => 'hk',
            'Kazakhstan' => 'kz',
            'Казахстан' => 'kz',
            'Uzbekistan' => 'uz',
            'Узбекистан' => 'uz',
            'Kyrgyzstan' => 'kg',
            'Кыргызстан' => 'kg',
            'Tajikistan' => 'tj',
            'Таджикистан' => 'tj',
            'Turkmenistan' => 'tm',
            'Туркменистан' => 'tm',
            'Mongolia' => 'mn',
            'Монголия' => 'mn',
            
            // Океания
            'Australia' => 'au',
            'Австралия' => 'au',
            'New Zealand' => 'nz',
            'Новая Зеландия' => 'nz',
            
            // Африка
            'South Africa' => 'za',
            'ЮАР' => 'za',
            'Egypt' => 'eg',
            'Египет' => 'eg',
            'Nigeria' => 'ng',
            'Нигерия' => 'ng',
            'Kenya' => 'ke',
            'Кения' => 'ke',
            'Morocco' => 'ma',
            'Марокко' => 'ma',
            
            // Специальные
            'Локальный' => 'local',
            'Local' => 'local',
            'Неизвестно' => 'unknown',
            'Unknown' => 'unknown',
        ];
    }
    
    // Ищем точное совпадение
    if (isset($codes[$countryName])) {
        return $codes[$countryName];
    }
    
    // Ищем частичное совпадение
    foreach ($codes as $key => $code) {
        if (stripos($countryName, $key) !== false) {
            return $code;
        }
    }
    
    return '';
}
?>