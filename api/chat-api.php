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
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(["error" => "Veritabanı hatası"]); exit;
}

// 1. GEREKLİ TABLOLARI OLUŞTUR
$pdo->exec("CREATE TABLE IF NOT EXISTS sohbet (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kullanici VARCHAR(100),
    mesaj TEXT,
    tarih VARCHAR(50),
    is_admin TINYINT(1) DEFAULT 0,
    is_anonim TINYINT(1) DEFAULT 0,
    timestamp INT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS sohbet_ayarlar (
    ayar_adi VARCHAR(50) PRIMARY KEY,
    ayar_degeri VARCHAR(50)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS sohbet_yasaklar (
    kullanici VARCHAR(100) PRIMARY KEY
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Varsayılan ayarları yükle (Sohbet Açık, Limit 3 Saniyede 1 Mesaj)
$pdo->exec("INSERT IGNORE INTO sohbet_ayarlar (ayar_adi, ayar_degeri) VALUES ('chat_aktif', '1'), ('limit_saniye', '3')");

function getAyar($pdo, $adi) {
    $stmt = $pdo->prepare("SELECT ayar_degeri FROM sohbet_ayarlar WHERE ayar_adi = ?");
    $stmt->execute([$adi]);
    return $stmt->fetchColumn();
}

// 2. İSTEKLERİ İŞLE
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $isteyenKullanici = $_GET['user'] ?? '';
    $isteyenAdminMi = ($_GET['isAdmin'] ?? 'false') === 'true';
    
    $chatAktif = getAyar($pdo, 'chat_aktif') === '1';
    $limitSaniye = getAyar($pdo, 'limit_saniye');

    // Kullanıcı engelli mi kontrol et
    $engelliMi = false;
    if ($isteyenKullanici) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sohbet_yasaklar WHERE kullanici = ?");
        $stmt->execute([$isteyenKullanici]);
        $engelliMi = $stmt->fetchColumn() > 0;
    }

    $stmt = $pdo->query("SELECT * FROM sohbet ORDER BY id ASC");
    $mesajlar = [];
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $gosterilecekIsim = $row['kullanici'];
        
        // Anonimlik Mantığı
        if ($row['is_anonim']) {
            if ($isteyenAdminMi) {
                // Adminler gerçek ismi soluk bir şekilde görür
                $gosterilecekIsim = "👻 Gizli (" . $row['kullanici'] . ")";
            } else {
                // Normal kullanıcılar sadece Gizli Kullanıcı görür
                if ($row['kullanici'] === $isteyenKullanici) {
                    $gosterilecekIsim = "👻 Gizli (Sen)";
                } else {
                    $gosterilecekIsim = "👻 Gizli Kullanıcı";
                }
            }
        }

        $mesajlar[] = [
            "id" => $row['id'],
            "kullanici" => $row['kullanici'],
            "gosterilecekIsim" => $gosterilecekIsim,
            "mesaj" => $row['mesaj'],
            "tarih" => $row['tarih'],
            "isAdmin" => (bool)$row['is_admin'],
            "isAnonim" => (bool)$row['is_anonim']
        ];
    }
    
    echo json_encode([
        "mesajlar" => $mesajlar, 
        "chatAktif" => $chatAktif, 
        "limitSaniye" => $limitSaniye,
        "engelliMi" => $engelliMi
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $aksiyon = $data['aksiyon'] ?? 'gonder';
    $isteyenAdminMi = ($data['isAdmin'] ?? false) === true || ($data['isAdmin'] ?? "0") === "1" || ($data['isAdmin'] ?? false) === "true";

    // A. YENİ MESAJ GÖNDERME
    if ($aksiyon === 'gonder') {
        $kullanici = trim($data['kullanici'] ?? 'Anonim');
        $mesaj = trim($data['mesaj'] ?? '');
        $isAnonim = ($data['isAnonim'] ?? false) ? 1 : 0;
        $suan = time();
        date_default_timezone_set('Europe/Istanbul');
        $tarih = date("H:i");

        if (!$mesaj) { echo json_encode(["error" => "Boş mesaj."]); exit; }

        // 1. Engel Kontrolü
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sohbet_yasaklar WHERE kullanici = ?");
        $stmt->execute([$kullanici]);
        if ($stmt->fetchColumn() > 0) { echo json_encode(["error" => "Sohbetten engellendiniz!"]); exit; }

        // 2. Sohbet Açık mı Kontrolü (Adminler kapalıyken de yazabilir)
        if (!$isteyenAdminMi && getAyar($pdo, 'chat_aktif') !== '1') {
            echo json_encode(["error" => "Sohbet şu an yönetici tarafından kapalı."]); exit;
        }

        // 3. Hız Sınırı (Spam Koruması) - Adminler limite takılmaz
        if (!$isteyenAdminMi) {
            $limit = (int)getAyar($pdo, 'limit_saniye');
            $stmt = $pdo->prepare("SELECT timestamp FROM sohbet WHERE kullanici = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$kullanici]);
            $sonMesajZamani = $stmt->fetchColumn();
            
            if ($sonMesajZamani && ($suan - $sonMesajZamani) < $limit) {
                echo json_encode(["error" => "Çok hızlı yazıyorsunuz! Lütfen $limit saniye bekleyin."]); exit;
            }
        }

        $stmt = $pdo->prepare("INSERT INTO sohbet (kullanici, mesaj, tarih, is_admin, is_anonim, timestamp) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$kullanici, $mesaj, $tarih, $isteyenAdminMi ? 1 : 0, $isAnonim, $suan]);
        echo json_encode(["success" => true]); exit;
    }

    // ==========================================
    // BUNDAN SONRAKİ İŞLEMLER SADECE ADMİNLER İÇİN
    // ==========================================
    if (!$isteyenAdminMi) { echo json_encode(["error" => "Yetkisiz işlem."]); exit; }

    if ($aksiyon === 'temizle') {
        $pdo->exec("TRUNCATE TABLE sohbet");
        echo json_encode(["success" => true]); exit;
    }

    if ($aksiyon === 'durum_degistir') {
        $yeniDurum = $data['durum'] ? '1' : '0';
        $pdo->prepare("UPDATE sohbet_ayarlar SET ayar_degeri = ? WHERE ayar_adi = 'chat_aktif'")->execute([$yeniDurum]);
        echo json_encode(["success" => true]); exit;
    }

    if ($aksiyon === 'limit_belirle') {
        $yeniLimit = (int)($data['limit'] ?? 3);
        $pdo->prepare("UPDATE sohbet_ayarlar SET ayar_degeri = ? WHERE ayar_adi = 'limit_saniye'")->execute([$yeniLimit]);
        echo json_encode(["success" => true]); exit;
    }

    if ($aksiyon === 'mesaj_sil') {
        $msgId = (int)$data['mesajId'];
        $pdo->prepare("DELETE FROM sohbet WHERE id = ?")->execute([$msgId]);
        echo json_encode(["success" => true]); exit;
    }

    if ($aksiyon === 'kullanici_engelle') {
        $hedef = $data['hedefKullanici'];
        $pdo->prepare("INSERT IGNORE INTO sohbet_yasaklar (kullanici) VALUES (?)")->execute([$hedef]);
        echo json_encode(["success" => true]); exit;
    }

    if ($aksiyon === 'engelleri_kaldir') {
        $pdo->exec("TRUNCATE TABLE sohbet_yasaklar");
        echo json_encode(["success" => true]); exit;
    }
}
?>