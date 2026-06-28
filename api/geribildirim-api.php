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
// 2. GERİ BİLDİRİM TABLOSUNU OLUŞTUR (Sadece ilk çalışmada)
// ========================================================
$pdo->exec("CREATE TABLE IF NOT EXISTS geribildirimler (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tarih VARCHAR(50),
    gonderen VARCHAR(100),
    tur VARCHAR(50),
    mesaj TEXT
)");

// ========================================================
// 3. İSTEĞİ İŞLE (SADECE POST)
// ========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gelenVeri = file_get_contents('php://input');
    $data = json_decode($gelenVeri, true);

    $mesaj = isset($data['mesaj']) ? trim($data['mesaj']) : "";
    $gonderen = isset($data['kullaniciAdi']) ? trim($data['kullaniciAdi']) : "Bilinmeyen Kullanıcı";
    $tur = isset($data['tur']) ? trim($data['tur']) : "Görüş/Fikir";

    // Eğer mesaj boş gönderilmişse işlemi durdur
    if (empty($mesaj)) {
        http_response_code(400);
        echo json_encode(["error" => "Mesaj boş olamaz."]);
        exit;
    }

    // Türkiye saatine göre tarihi oluştur
    date_default_timezone_set('Europe/Istanbul');
    $tarih = date("d.m.Y H:i");

    // Mesajı veritabanına kaydet
    $stmt = $pdo->prepare("INSERT INTO geribildirimler (tarih, gonderen, tur, mesaj) VALUES (?, ?, ?, ?)");
    
    if ($stmt->execute([$tarih, $gonderen, $tur, $mesaj])) {
        echo json_encode(["success" => true, "message" => "Geri bildirim başarıyla kaydedildi."]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Sunucu hatası, mesaj kaydedilemedi."]);
    }
    exit;
} else {
    // Tarayıcıdan doğrudan girilmeye çalışılırsa (GET) engelle
    http_response_code(405);
    echo json_encode(["error" => "Sadece POST metodu kabul edilir."]);
    exit;
}
?>