<?php
// SQLite tabanlı kurulumsuz veritabanı yapılandırması
// - Varsayılan olarak proje içi data/ossiqntv.sqlite dosyasını kullanır
// - Eğer DB yoksa tabloları oluşturur ve örnek admin kullanıcıyı ekler
// - $db PDO nesnesi global olarak kullanılabilir

// Konfig
$db_path = getenv('DB_PATH') ?: __DIR__ . '/data/ossiqntv.sqlite';

try {
    // data dizinini oluştur (yoksa)
    $dataDir = dirname($db_path);
    if (!is_dir($dataDir)) {
        if (!mkdir($dataDir, 0755, true) && !is_dir($dataDir)) {
            throw new RuntimeException(sprintf('Klasör oluşturulamadı: %s', $dataDir));
        }
    }

    // PDO SQLite bağlantısı
    $dsn = 'sqlite:' . $db_path;
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $db = new PDO($dsn, null, null, $options);

    // SQLite performans/pragmalari
    $db->exec('PRAGMA journal_mode = WAL');
    $db->exec('PRAGMA foreign_keys = ON');
    $db->exec('PRAGMA encoding = "UTF-8"');

    // Eğer tablolar yoksa oluştur
    $createQueries = [
        "CREATE TABLE IF NOT EXISTS admin_users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE,
            password TEXT
        );",

        "CREATE TABLE IF NOT EXISTS pf_settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT DEFAULT 'OSSIQN TV',
            bio TEXT,
            bg_url TEXT,
            logo_url TEXT,
            avatar_url TEXT
        );",

        "CREATE TABLE IF NOT EXISTS pf_users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT,
            email TEXT,
            password TEXT,
            balance NUMERIC DEFAULT 0.00,
            api_key TEXT DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );",

        "CREATE TABLE IF NOT EXISTS pf_licenses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            product_name TEXT,
            license_key TEXT,
            license_type TEXT DEFAULT 'premium',
            status TEXT DEFAULT 'Aktif',
            expires_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );",

        "CREATE TABLE IF NOT EXISTS pf_promo_codes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT,
            duration_months INTEGER DEFAULT 1
        );",
    ];

    $db->beginTransaction();
    foreach ($createQueries as $q) {
        $db->exec($q);
    }

    // Örnek pf_settings satırı varsa ekle
    $stmt = $db->query("SELECT COUNT(*) as c FROM pf_settings");
    $row = $stmt->fetch();
    if (!$row || intval($row['c']) === 0) {
        $ins = $db->prepare('INSERT INTO pf_settings (name) VALUES (:name)');
        $ins->execute([':name' => 'OSSIQN TV']);
    }

    // Örnek promo kodu varsa ekle
    $stmt = $db->query("SELECT COUNT(*) as c FROM pf_promo_codes WHERE code = 'OSSIQNVIP'");
    $row = $stmt->fetch();
    if (!$row || intval($row['c']) === 0) {
        $ins = $db->prepare('INSERT INTO pf_promo_codes (code, duration_months) VALUES (:code, :months)');
        $ins->execute([':code' => 'OSSIQNVIP', ':months' => 1]);
    }

    // Admin kullanıcı yoksa ekle (varsayılan şifre: ossiqn3131- -> hash'lenmiş)
    $stmt = $db->query("SELECT COUNT(*) as c FROM admin_users WHERE username = 'admin'");
    $row = $stmt->fetch();
    if (!$row || intval($row['c']) === 0) {
        $defaultPassword = password_hash('ossiqn3131-', PASSWORD_DEFAULT);
        $ins = $db->prepare('INSERT INTO admin_users (username, password) VALUES (:username, :password)');
        $ins->execute([':username' => 'admin', ':password' => $defaultPassword]);
    }

    $db->commit();

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('SQLite setup error: ' . $e->getMessage());
    http_response_code(500);
    die('<h1>Veritabanı Hatası</h1><p>Veritabanı oluşturulurken hata oluştu. Lütfen sunucu izinlerini kontrol edin.</p>');
}

// $db global olarak kullanılabilir
