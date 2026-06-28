<?php
// Hata gösterimini kapatıyoruz
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

// ========================================================
// 1. VERİTABANI BAĞLANTISI
// ========================================================
$host = 'localhost'; 
$db   = 'tkasista_db';
$user = 'tkasista_user';
$pass = 'Tkasistan2026';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(["error" => "Veritabanı bağlantı hatası."]);
    exit;
}

// ========================================================
// 2. AYARLAR TABLOSUNU OLUŞTUR (Sadece ilk çalışmada)
// ========================================================
$pdo->exec("CREATE TABLE IF NOT EXISTS ayarlar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pdfSakla TINYINT(1) DEFAULT 0,
    tabloKaydet TINYINT(1) DEFAULT 1
)");

// Tablo boşsa varsayılan ayarları (Kayıt Açık) ekle
$stmt = $pdo->query("SELECT COUNT(*) FROM ayarlar");
if ($stmt->fetchColumn() == 0) {
    $pdo->exec("INSERT INTO ayarlar (pdfSakla, tabloKaydet) VALUES (0, 1)");
}

// ========================================================
// 3. İSTEKLERİ İŞLE (GET VE POST)
// ========================================================
$gelenVeri = file_get_contents('php://input');
$istek = json_decode($gelenVeri, true);

// Ekrana ayarları gönder (GET)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->query("SELECT pdfSakla, tabloKaydet FROM ayarlar LIMIT 1");
    $ayarlar = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        "pdfSakla" => (bool)$ayarlar['pdfSakla'],
        "tabloKaydet" => (bool)$ayarlar['tabloKaydet']
    ]);
    exit;
}

// Yeni ayarları veritabanına kaydet (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $istek) {
    if (isset($istek['pdfSakla'])) {
        $val = $istek['pdfSakla'] ? 1 : 0;
        $pdo->exec("UPDATE ayarlar SET pdfSakla = $val");
    }
    
    if (isset($istek['tabloKaydet'])) {
        $val = $istek['tabloKaydet'] ? 1 : 0;
        $pdo->exec("UPDATE ayarlar SET tabloKaydet = $val");
    }
    
    echo json_encode(["success" => true]);
    exit;
}
?>