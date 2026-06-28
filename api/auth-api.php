<?php
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

try {
    $host = 'localhost'; 
    $db   = 'tkasista_db';
    $user = 'tkasista_user';
    $pass = 'Tkasistan2026';

    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->exec("SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // TABLOYU OLUŞTUR
    $pdo->exec("CREATE TABLE IF NOT EXISTS kullanicilar (
        id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(100) UNIQUE, password VARCHAR(255),
        isAdmin TINYINT(1) DEFAULT 0, ip VARCHAR(50), tarih VARCHAR(50)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // =========================================================================
    // SİHİRLİ DOKUNUŞ: ESKİ İSVEÇÇE TABLOLARI ZORLA TÜRKÇE (UTF8) FORMATINA ÇEVİRİR
    // BU SAYEDE 1267 HATASI ORTADAN KALKAR
    // =========================================================================
    try { $pdo->exec("ALTER TABLE kullanicilar CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"); } catch(Exception $e) {}

    // EKSİK SÜTUNLARI TAMAMLA
    try { $pdo->exec("ALTER TABLE kullanicilar ADD COLUMN ip VARCHAR(50)"); } catch(Exception $e) {}
    try { $pdo->exec("ALTER TABLE kullanicilar ADD COLUMN tarih VARCHAR(50)"); } catch(Exception $e) {}
    try { $pdo->exec("ALTER TABLE kullanicilar ADD COLUMN isAdmin TINYINT(1) DEFAULT 0"); } catch(Exception $e) {}
    try { $pdo->exec("ALTER TABLE kullanicilar ADD COLUMN kooperatif VARCHAR(150)"); } catch(Exception $e) {}
    try { $pdo->exec("ALTER TABLE kullanicilar ADD COLUMN is_banned TINYINT(1) DEFAULT 0"); } catch(Exception $e) {}
    try { $pdo->exec("ALTER TABLE kullanicilar ADD COLUMN kyc_status TINYINT(1) DEFAULT 0"); } catch(Exception $e) {} 
    try { $pdo->exec("ALTER TABLE kullanicilar ADD COLUMN pending_name VARCHAR(100)"); } catch(Exception $e) {}
    try { $pdo->exec("ALTER TABLE kullanicilar ADD COLUMN pending_koop VARCHAR(150)"); } catch(Exception $e) {}

    // KURUCU ADMİN HESABI YOKSA OLUŞTUR
    if ($pdo->query("SELECT COUNT(*) FROM kullanicilar")->fetchColumn() == 0) {
        $hash = password_hash("1234", PASSWORD_DEFAULT); date_default_timezone_set('Europe/Istanbul'); $tarih = date("d.m.Y H:i");
        $pdo->exec("INSERT INTO kullanicilar (username, password, isAdmin, tarih, kooperatif) VALUES ('admin', '$hash', 1, '$tarih', 'Sistem Yöneticisi')");
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $pdo->query("SELECT username, kooperatif, isAdmin, is_banned, ip, tarih, kyc_status FROM kullanicilar ORDER BY id DESC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $gelenVeri = file_get_contents('php://input'); 
        $data = json_decode($gelenVeri, true); 
        $islem = $data['islem'] ?? '';

        if ($islem === 'login') {
            $username = trim($data['kullanici'] ?? ''); $password = trim($data['sifre'] ?? '');
            $stmt = $pdo->prepare("SELECT * FROM kullanicilar WHERE username = ?"); $stmt->execute([$username]); $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user && password_verify($password, $user['password'])) {
                if ($user['is_banned'] == 1) { echo json_encode(["error" => "Hesabınız askıya alınmıştır!"]); exit; }
                $pdo->prepare("UPDATE kullanicilar SET ip = ? WHERE id = ?")->execute([$_SERVER['REMOTE_ADDR'], $user['id']]);
                echo json_encode(["success" => true, "isAdmin" => (bool)$user['isAdmin'], "kooperatif" => $user['kooperatif']]);
            } else { echo json_encode(["error" => "Kullanıcı adı veya şifre hatalı."]); } exit;
        }

        if ($islem === 'register') {
            $adSoyad = trim($data['adSoyad'] ?? ''); $koop = trim($data['kooperatif'] ?? ''); $sifre = trim($data['sifre'] ?? ''); $ip = $_SERVER['REMOTE_ADDR'];
            
            if (!$adSoyad || !$sifre || !$koop) { echo json_encode(["error" => "Lütfen tüm alanları doldurun."]); exit; }
            
            $isimKontrol = $pdo->prepare("SELECT id FROM kullanicilar WHERE username = ?"); $isimKontrol->execute([$adSoyad]);
            if($isimKontrol->fetchColumn()) { echo json_encode(["error" => "Bu isim kullanımda."]); exit; }
            
            $ipKontrol = $pdo->prepare("SELECT id FROM kullanicilar WHERE ip = ?"); $ipKontrol->execute([$ip]);
            if($ipKontrol->fetchColumn()) { echo json_encode(["error" => "Güvenlik: Bu cihazdan veya ağdan daha önce kayıt yapılmış."]); exit; }
            
            $hash = password_hash($sifre, PASSWORD_DEFAULT); date_default_timezone_set('Europe/Istanbul'); $tarih = date("d.m.Y H:i");
            $stmt = $pdo->prepare("INSERT INTO kullanicilar (username, password, kooperatif, isAdmin, ip, tarih) VALUES (?, ?, ?, 0, ?, ?)");
            
            if($stmt->execute([$adSoyad, $hash, $koop, $ip, $tarih])) {
                echo json_encode(["success" => true]); 
            } else { 
                echo json_encode(["error" => "Veritabanına kayıt sırasında hata oluştu."]); 
            } 
            exit;
        }

        if ($islem === 'kyc_check') {
            $stmt = $pdo->prepare("SELECT kyc_status FROM kullanicilar WHERE username = ?"); $stmt->execute([$data['username']]); $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user) { echo json_encode(["status" => "relogin"]); exit; } else { echo json_encode(["status" => $user['kyc_status']]); exit; }
        }

        if ($islem === 'kyc_uyari_gonder') {
            $pdo->prepare("UPDATE kullanicilar SET kyc_status = 1 WHERE username = ?")->execute([$data['username']]);
            echo json_encode(["success" => true]); exit;
        }

        if ($islem === 'kyc_form_gonder') {
            $user = $data['username']; $newName = trim($data['yeniAd']); $newKoop = trim($data['yeniKoop']);
            $stmt = $pdo->prepare("SELECT id FROM kullanicilar WHERE username = ? AND username != ?"); $stmt->execute([$newName, $user]);
            if ($stmt->fetchColumn()) { echo json_encode(["error" => "Bu isim sistemde kayıtlı. Lütfen ayırt edici bir soyad/harf ekleyin."]); exit; }
            $pdo->prepare("UPDATE kullanicilar SET kyc_status = 2, pending_name = ?, pending_koop = ? WHERE username = ?")->execute([$newName, $newKoop, $user]);
            echo json_encode(["success" => true]); exit;
        }

        if ($islem === 'get_pending_kyc') {
            $stmt = $pdo->query("SELECT username, kooperatif, pending_name, pending_koop FROM kullanicilar WHERE kyc_status = 2");
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
        }

        if ($islem === 'kyc_onayla') {
            $oldUser = $data['username'];
            $stmt = $pdo->prepare("SELECT pending_name, pending_koop FROM kullanicilar WHERE username = ?"); $stmt->execute([$oldUser]); $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if($row) {
                $newName = $row['pending_name']; $newKoop = $row['pending_koop'];
                $pdo->prepare("UPDATE kullanicilar SET username = ?, kooperatif = ?, kyc_status = 0, pending_name = NULL, pending_koop = NULL WHERE username = ?")->execute([$newName, $newKoop, $oldUser]);
                $pdo->prepare("UPDATE sohbet SET kullanici = ? WHERE kullanici = ?")->execute([$newName, $oldUser]);
                try { $pdo->prepare("UPDATE islemler SET islem_yapan = ? WHERE islem_yapan = ?")->execute([$newName, $oldUser]); } catch(Exception $e){}
                $pdo->prepare("UPDATE destek_mesajlari SET gonderen = ? WHERE gonderen = ?")->execute([$newName, $oldUser]);
                $pdo->prepare("UPDATE destek_mesajlari SET alici = ? WHERE alici = ?")->execute([$newName, $oldUser]);
            }
            echo json_encode(["success" => true]); exit;
        }

        if ($islem === 'kyc_reddet') {
            $pdo->prepare("UPDATE kullanicilar SET kyc_status = 3, pending_name = NULL, pending_koop = NULL WHERE username = ?")->execute([$data['username']]);
            echo json_encode(["success" => true]); exit;
        }

        if ($islem === 'hesap_banla') { $pdo->prepare("UPDATE kullanicilar SET is_banned = ? WHERE username = ?")->execute([$data['statu'] ? 1 : 0, $data['username'] ?? '']); echo json_encode(["success" => true]); exit; }
        if ($islem === 'hesap_sil') { $pdo->prepare("DELETE FROM kullanicilar WHERE username = ?")->execute([$data['username'] ?? '']); echo json_encode(["success" => true]); exit; }
        if ($islem === 'kullanici_guncelle') { $eski = $data['eskiKullaniciAdi'] ?? ''; $yeni = trim($data['yeniKullaniciAdi'] ?? ''); $sifre = trim($data['yeniSifre'] ?? ''); if ($sifre) { $pdo->prepare("UPDATE kullanicilar SET username = ?, password = ? WHERE username = ?")->execute([$yeni, password_hash($sifre, PASSWORD_DEFAULT), $eski]); } else { $pdo->prepare("UPDATE kullanicilar SET username = ? WHERE username = ?")->execute([$yeni, $eski]); } echo json_encode(["success" => true]); exit; }
    }
} catch (Throwable $e) {
    echo json_encode(["error" => "Arka Plan Hatası: " . $e->getMessage()]);
    exit;
}
?>