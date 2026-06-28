<?php
// Hata gösterimini kapatıyoruz
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

// ========================================================
// 1. VERİTABANI BAĞLANTISI (UTF8MB4 İLE GÜNCELLENDİ)
// ========================================================
$host = 'localhost'; 
$db   = 'tkasista_db';
$user = 'tkasista_user';
$pass = 'Tkasistan2026';

try {
    // charset=utf8 yerine charset=utf8mb4 kullanıyoruz (Emojiler ve Türkçe için şart)
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    // Veritabanına her işlemi bu dilde yapmasını emrediyoruz
    $pdo->exec("SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(["error" => "Veritabanı bağlantı hatası."]);
    exit;
}

// ========================================================
// 2. MODÜLLER TABLOSUNU OLUŞTUR VE DİLİNİ DÜZELT
// ========================================================
$pdo->exec("CREATE TABLE IF NOT EXISTS moduller (
    id INT AUTO_INCREMENT PRIMARY KEY,
    baslik VARCHAR(255),
    aciklama VARCHAR(255),
    link VARCHAR(255),
    icon VARCHAR(50),
    renkSinifi VARCHAR(50),
    linkMetni VARCHAR(50),
    linkRenk VARCHAR(50)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Eğer tablo daha önceden bozuk dille oluştuysa, onu zorla düzeltiyoruz
$pdo->exec("ALTER TABLE moduller CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

// Tablo boşsa, varsayılan "Mizan Mahsuplaşma" modülünü ekle
$stmt = $pdo->query("SELECT COUNT(*) FROM moduller");
if ($stmt->fetchColumn() == 0) {
    $baslik = "Mizan Mahsuplaşma";
    $aciklama = "Kooperatifler arası borç/alacak ilişkilerini PDF üzerinden otomatik hesaplayın ve EBYS şablonu oluşturun.";
    $link = "mahsuplasma.html";
    $icon = "🔄";
    $renkSinifi = "icon-blue";
    $linkMetni = "Uygulamayı Aç";
    $linkRenk = "#0ea5e9";
    
    $pdo->exec("INSERT INTO moduller (baslik, aciklama, link, icon, renkSinifi, linkMetni, linkRenk) 
                VALUES ('$baslik', '$aciklama', '$link', '$icon', '$renkSinifi', '$linkMetni', '$linkRenk')");
}

// ========================================================
// 3. İSTEKLERİ İŞLE (GET VE POST)
// ========================================================

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->query("SELECT * FROM moduller");
    $moduller = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // JSON_UNESCAPED_UNICODE ile Türkçe karakterlerin bozulmasını engelliyoruz
    echo json_encode($moduller, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gelenVeri = file_get_contents('php://input');
    $istek = json_decode($gelenVeri, true);

    if (isset($istek['aksiyon']) && $istek['aksiyon'] === 'sil') {
        $stmt = $pdo->prepare("DELETE FROM moduller WHERE id = ?");
        $stmt->execute([$istek['id']]);
        echo json_encode(["success" => true]);
    } 
    else {
        $stmt = $pdo->prepare("INSERT INTO moduller (baslik, aciklama, link, icon, renkSinifi, linkMetni, linkRenk) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $istek['baslik'] ?? '',
            $istek['aciklama'] ?? '',
            $istek['link'] ?? '',
            $istek['icon'] ?? '',
            $istek['renkSinifi'] ?? '',
            $istek['linkMetni'] ?? '',
            $istek['linkRenk'] ?? ''
        ]);
        echo json_encode(["success" => true]);
    }
    exit;
}
?>