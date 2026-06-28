<?php
// Hata gösterimini kapatıp JSON formatında yanıt veriyoruz
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

// ========================================================
// 1. VERİTABANI BİLGİLERİ
// ========================================================
$host = 'localhost'; 
$db   = 'tkasista_db';
$user = 'tkasista_user';
$pass = 'Tkasistan2026';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Veritabanı bağlantı hatası."]);
    exit;
}

// ========================================================
// 2. GELEN İSTEĞİ KONTROL ET
// ========================================================
$gelenVeri = file_get_contents('php://input');
$istek = json_decode($gelenVeri, true);

// Eğer 'islem' parametresi yoksa (Admin paneli sayfayı ilk açtığında listeyi ister)
if (!isset($istek['islem'])) {
    // İşlem geçmişi tablosu yoksa hata vermemesi için boş liste döndür
    $tabloKontrol = $pdo->query("SHOW TABLES LIKE 'islemler'");
    if ($tabloKontrol->rowCount() == 0) {
        echo json_encode([]);
        exit;
    }

    // Tablodaki tüm kayıtları en yeniden eskiye doğru çek
    $stmt = $pdo->query("SELECT * FROM islemler ORDER BY id DESC");
    $kayitlar = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sonuc = [];
    foreach ($kayitlar as $k) {
        $sonuc[] = [
            "id" => $k['id'],
            "islemTarihi" => $k['islem_tarihi'],
            "islemYapan" => $k['islem_yapan'],
            "mizanDonemi" => $k['mizan_donemi'],
            // Orijinal mizanı tekrar tablo formatına çeviriyoruz
            "orijinalMizan" => json_decode($k['orijinal_mizan'], true) 
        ];
    }
    echo json_encode($sonuc);
    exit;
}

$islem = $istek['islem'];

// ========================================================
// 3. TÜM KAYITLARI SİLME (TEMİZLE) İŞLEMİ
// ========================================================
if ($islem === 'temizle') {
    // Tablonun içindeki tüm verileri sıfırlar
    $pdo->exec("TRUNCATE TABLE islemler"); 
    echo json_encode(["success" => true]);
    exit;
}
?>