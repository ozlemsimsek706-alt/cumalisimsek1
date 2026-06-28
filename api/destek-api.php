<?php
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

$host = 'localhost'; 
$db   = 'tkasista_db';
$user = 'tkasista_user';
$pass = 'Tkasistan2026';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->exec("SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'");
} catch (PDOException $e) { echo json_encode(["error" => "Veritabanı hatası"]); exit; }

// TABLOLARI OLUŞTUR
$pdo->exec("CREATE TABLE IF NOT EXISTS destek_mesajlari (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gonderen VARCHAR(100),
    alici VARCHAR(100),
    mesaj TEXT,
    dosya VARCHAR(255),
    tarih VARCHAR(50),
    zaman INT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS destek_admin_durum (
    id INT PRIMARY KEY,
    son_aktif INT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Varsayılan admin durumunu ekle
$pdo->exec("INSERT IGNORE INTO destek_admin_durum (id, son_aktif) VALUES (1, 0)");

// UPLOADS KLASÖRÜNÜ KONTROL ET
if (!file_exists('../uploads')) { mkdir('../uploads', 0777, true); }

$islem = $_POST['islem'] ?? '';

// 1. ADMİN AKTİFLİK BİLDİRİMİ (HEARTBEAT)
if ($islem === 'heartbeat') {
    $zaman = time();
    $pdo->exec("UPDATE destek_admin_durum SET son_aktif = $zaman WHERE id = 1");
    echo json_encode(["success" => true]); exit;
}

// 2. KULLANICI İÇİN ADMİN ONLINE/OFFLINE KONTROLÜ
if ($islem === 'status_check') {
    $stmt = $pdo->query("SELECT son_aktif FROM destek_admin_durum WHERE id = 1");
    $son = $stmt->fetchColumn();
    // 15 saniye içinde adminden sinyal geldiyse online, gelmediyse offline
    $online = (time() - $son) < 15; 
    echo json_encode(["online" => $online]); exit;
}

// 3. MESAJ VE DOSYA GÖNDERME
if ($islem === 'send') {
    $gonderen = $_POST['gonderen'] ?? '';
    $alici = $_POST['alici'] ?? 'admin';
    $mesaj = $_POST['mesaj'] ?? '';
    $dosya_yolu = '';

    // Dosya Yüklendiyse
    if (isset($_FILES['dosya']) && $_FILES['dosya']['error'] === 0) {
        $ext = pathinfo($_FILES['dosya']['name'], PATHINFO_EXTENSION);
        $izinli = ['jpg','jpeg','png','pdf','doc','docx','xls','xlsx'];
        
        if(in_array(strtolower($ext), $izinli)) {
            $yeni_ad = time() . '_' . rand(100,999) . '.' . $ext;
            $hedef = '../uploads/' . $yeni_ad;
            if(move_uploaded_file($_FILES['dosya']['tmp_name'], $hedef)) {
                $dosya_yolu = 'uploads/' . $yeni_ad;
            }
        }
    }

    if($mesaj === '' && $dosya_yolu === '') { echo json_encode(["error" => "Boş mesaj."]); exit; }

    $zaman = time();
    date_default_timezone_set('Europe/Istanbul');
    $tarih = date("H:i");

    $stmt = $pdo->prepare("INSERT INTO destek_mesajlari (gonderen, alici, mesaj, dosya, tarih, zaman) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$gonderen, $alici, $mesaj, $dosya_yolu, $tarih, $zaman]);
    echo json_encode(["success" => true]); exit;
}

// 4. MESAJLARI ÇEK (CHAT PENCERESİ İÇİN)
if ($islem === 'get_messages') {
    $user1 = $_POST['user1'] ?? '';
    $user2 = $_POST['user2'] ?? 'admin';

    $stmt = $pdo->prepare("SELECT * FROM destek_mesajlari WHERE (gonderen = ? AND alici = ?) OR (gonderen = ? AND alici = ?) ORDER BY id ASC");
    $stmt->execute([$user1, $user2, $user2, $user1]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
}

// 5. ADMİN İÇİN DESTEK BEKLEYENLERİN LİSTESİ
if ($islem === 'get_conversations') {
    $stmt = $pdo->query("SELECT DISTINCT gonderen FROM destek_mesajlari WHERE gonderen != 'admin' ORDER BY id DESC");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
}
?>