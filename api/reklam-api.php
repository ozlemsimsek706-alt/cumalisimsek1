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
// 2. REKLAM AYARLARI TABLOSUNU OLUŞTUR
// ========================================================
$pdo->exec("CREATE TABLE IF NOT EXISTS reklam_ayarlari (
    id INT AUTO_INCREMENT PRIMARY KEY,
    popup_aktif TINYINT(1) DEFAULT 0,
    popup_tur VARCHAR(50) DEFAULT 'resim',
    popup_baslik VARCHAR(255) DEFAULT '',
    popup_sure INT DEFAULT 10,
    modul_aktif TINYINT(1) DEFAULT 0,
    modul_baslik VARCHAR(255) DEFAULT '',
    modul_aciklama VARCHAR(255) DEFAULT '',
    modul_link VARCHAR(255) DEFAULT ''
)");

// Tablo boşsa, varsayılan değerleri içeren 1 satır ekle
$stmt = $pdo->query("SELECT COUNT(*) FROM reklam_ayarlari");
if ($stmt->fetchColumn() == 0) {
    $pdo->exec("INSERT INTO reklam_ayarlari (popup_aktif, popup_tur, popup_baslik, popup_sure, modul_aktif, modul_baslik, modul_aciklama, modul_link) 
                VALUES (0, 'resim', 'Günün Fırsatı', 10, 0, 'Özel Teklif', 'Fırsatı Kaçırma', '#')");
}

// ========================================================
// 3. İSTEKLERİ İŞLE (GET VE POST)
// ========================================================

// Arayüze verileri gönder (GET)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->query("SELECT * FROM reklam_ayarlari LIMIT 1");
    $ayarlar = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        "popup" => [
            "aktif" => (bool)$ayarlar['popup_aktif'],
            "tur" => $ayarlar['popup_tur'],
            "baslik" => $ayarlar['popup_baslik'],
            "sure" => (int)$ayarlar['popup_sure']
        ],
        "modul" => [
            "aktif" => (bool)$ayarlar['modul_aktif'],
            "baslik" => $ayarlar['modul_baslik'],
            "aciklama" => $ayarlar['modul_aciklama'],
            "link" => $ayarlar['modul_link']
        ]
    ]);
    exit;
}

// Yeni ayarları veritabanına kaydet (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gelenVeri = file_get_contents('php://input');
    $istek = json_decode($gelenVeri, true);

    if ($istek) {
        $p_aktif = isset($istek['popup']['aktif']) && $istek['popup']['aktif'] ? 1 : 0;
        $p_tur = $istek['popup']['tur'] ?? 'resim';
        $p_baslik = $istek['popup']['baslik'] ?? '';
        $p_sure = (int)($istek['popup']['sure'] ?? 10);

        $m_aktif = isset($istek['modul']['aktif']) && $istek['modul']['aktif'] ? 1 : 0;
        $m_baslik = $istek['modul']['baslik'] ?? '';
        $m_aciklama = $istek['modul']['aciklama'] ?? '';
        $m_link = $istek['modul']['link'] ?? '';

        // Sadece ID'si 1 olan (ilk ve tek) satırı güncelliyoruz
        $stmt = $pdo->prepare("UPDATE reklam_ayarlari SET 
            popup_aktif = ?, popup_tur = ?, popup_baslik = ?, popup_sure = ?, 
            modul_aktif = ?, modul_baslik = ?, modul_aciklama = ?, modul_link = ? 
            WHERE id = 1");
        $stmt->execute([$p_aktif, $p_tur, $p_baslik, $p_sure, $m_aktif, $m_baslik, $m_aciklama, $m_link]);
    }
    echo json_encode(["success" => true]);
    exit;
}
?>