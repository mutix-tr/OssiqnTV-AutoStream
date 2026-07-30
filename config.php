<?php
// SQLite tabanlı kurulumsuz veritabanı yapılandırması (proje: mutixtv)
// - Varsayılan olarak proje içi data/mutixtv.sqlite dosyasını kullanır
// - Eğer DB yoksa tabloları oluşturur ve örnek admin kullanıcıyı ekler
// - Hem eski tablo isimleri (users, promo_codes, pf_*) hem de yeni pf_* isimleri oluşturulur

// Konfig
$db_path = getenv('DB_PATH') ?: __DIR__ . '/data/mutixtv.sqlite';

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
        // admin users
        "CREATE TABLE IF NOT EXISTS admin_users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE,
            password TEXT
        );",

        // settings
        "CREATE TABLE IF NOT EXISTS pf_settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT DEFAULT 'mutixtv',
            bio TEXT,
            bg_url TEXT,
            logo_url TEXT,
            avatar_url TEXT
        );",

        // users (compatibility) and pf_users
        "CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT,
            username TEXT,
            email TEXT,
            password TEXT,
            balance NUMERIC DEFAULT 0.00,
            api_key TEXT DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
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

        // licenses
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

        // promo codes (both names for compatibility)
        "CREATE TABLE IF NOT EXISTS promo_codes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT NOT NULL UNIQUE,
            package_name TEXT NOT NULL,
            max_uses INTEGER DEFAULT 1,
            current_uses INTEGER DEFAULT 0
        );",

        "CREATE TABLE IF NOT EXISTS pf_promo_codes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT,
            duration_months INTEGER DEFAULT 1,
            package_name TEXT,
            max_uses INTEGER DEFAULT 1,
            current_uses INTEGER DEFAULT 0
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
        $ins->execute([':name' => 'mutixtv']);
    }

    // Örnek promo kodu (compatibility)
    $stmt = $db->prepare("SELECT COUNT(*) as c FROM promo_codes WHERE code = :code");
    $stmt->execute([':code' => 'MUTIXVIP']);
    $row = $stmt->fetch();
    if (!$row || intval($row['c']) === 0) {
        $ins = $db->prepare('INSERT INTO promo_codes (code, package_name, max_uses, current_uses) VALUES (:code, :pkg, :max, 0)');
        $ins->execute([':code' => 'MUTIXVIP', ':pkg' => 'VIP Promosyon', ':max' => 50]);
    }

    // Seed pf_promo_codes too
    $stmt = $db->prepare("SELECT COUNT(*) as c FROM pf_promo_codes WHERE code = :code");
    $stmt->execute([':code' => 'MUTIXVIP']);
    $row = $stmt->fetch();
    if (!$row || intval($row['c']) === 0) {
        $ins = $db->prepare('INSERT INTO pf_promo_codes (code, duration_months, package_name, max_uses, current_uses) VALUES (:code, :months, :pkg, :max, 0)');
        $ins->execute([':code' => 'MUTIXVIP', ':months' => 1, ':pkg' => 'VIP Promosyon', ':max' => 50]);
    }

    // Admin kullanıcı yoksa ekle (varsayılan şifre: ossiqn3131-)
    $stmt = $db->prepare("SELECT COUNT(*) as c FROM admin_users WHERE username = :u");
    $stmt->execute([':u' => 'admin']);
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
