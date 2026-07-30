<?php
// Güvenli veritabanı yapılandırması
// - Ortam değişkenlerinden (env) değer alır, yoksa güvenli varsayılanları kullanır
// - PDO seçenekleri: ERRMODE_EXCEPTION, FETCH_ASSOC, EMULATE_PREPARES=false
// - UTF8MB4 karakter seti kullanılır
// - Hata mesajları ayrıntılı olarak kullanıcıya gösterilmez; detaylar error_log() ile kaydedilir

$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'ossiqntv';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS') ?: '';

try {
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        // INIT_COMMAND ensures proper collation/charset for older MySQL drivers
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
    ];

    // Geçici bağlantı: veritabanını oluşturma yetkisi varsa DB'yi oluştur
    $temp_dsn = "mysql:host={$host};charset=utf8mb4";
    $temp_db = new PDO($temp_dsn, $username, $password, $options);

    // Güvenlik: DB adı içindeki backtick'leri temizle
    $safe_dbname = str_replace('`', '', $dbname);
    $temp_db->exec("CREATE DATABASE IF NOT EXISTS `{$safe_dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    // Asıl uygulama bağlantısı
    $dsn = "mysql:host={$host};dbname={$safe_dbname};charset=utf8mb4";
    $db = new PDO($dsn, $username, $password, $options);

} catch (PDOException $e) {
    // Hataları logla; kullanıcıya detaylı hata gösterme
    error_log('Database connection error: ' . $e->getMessage());
    http_response_code(500);
    die('<h1>Veritabanı Hatası</h1><p>Veritabanına bağlanılamadı. Lütfen daha sonra tekrar deneyin.</p>');
}

// $db değişkeni artık uygulama genelinde kullanılabilir
