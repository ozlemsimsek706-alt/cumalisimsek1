<?php
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

// Özel Türkçe Karakter ve Tam Kelime Arama Motoru
if (!function_exists('tamKelimeAra')) {
    function tamKelimeAra($aranan, $metin) {
        $buyuk = ['İ', 'I', 'Ğ', 'Ü', 'Ş', 'Ö', 'Ç'];
        $kucuk = ['i', 'ı', 'ğ', 'ü', 'ş', 'ö', 'ç'];
        
        // Aranan kooperatif adını temizle (Örn: "A.Tekirçiftliği" -> " a tekirçiftliği ")
        $temizAranan = str_replace($buyuk, $kucuk, $aranan);
        $temizAranan = mb_strtolower($temizAranan, 'UTF-8');
        $temizAranan = preg_replace('/[^\p{L}0-9]/u', ' ', $temizAranan);
        $temizAranan = preg_replace('/\s+/u', ' ', $temizAranan);
        $temizAranan = " " . trim($temizAranan) . " ";
        
        // Hesap adını temizle (Örn: "2372 SAYILI SAĞKAYA KOOP." -> " 2372 sayili sağkaya koop ")
        $temizMetin = str_replace($buyuk, $kucuk, $metin);
        $temizMetin = mb_strtolower($temizMetin, 'UTF-8');
        $temizMetin = preg_replace('/[^\p{L}0-9]/u', ' ', $temizMetin);
        $temizMetin = preg_replace('/\s+/u', ' ', $temizMetin);
        $temizMetin = " " . trim($temizMetin) . " ";
        
        // Temizlenmiş hesap adı içinde, temizlenmiş kooperatif adını ara
        return mb_strpos($temizMetin, $temizAranan) !== false;
    }
}

$host = 'localhost'; 
$db   = 'tkasista_db';
$user = 'tkasista_user';
$pass = 'Tkasistan2026';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->exec("SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(["error" => "Veritabanı hatası"]);
    exit;
}

$pdo->exec("CREATE TABLE IF NOT EXISTS islemler (
    id INT AUTO_INCREMENT PRIMARY KEY,
    islem_yapan VARCHAR(50),
    mizan_donemi VARCHAR(50),
    islem_tarihi VARCHAR(50),
    orijinal_mizan LONGTEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$gelenVeri = file_get_contents('php://input');
$istek = json_decode($gelenVeri, true);

if (!$istek) { echo json_encode(["error" => "Veri alınamadı."]); exit; }

$rows = $istek['rows'] ?? [];
$rawMizan = $istek['rawMizan'] ?? [];
$date = $istek['date'] ?? date('d.m.Y');
$kullaniciAdi = $istek['kullaniciAdi'] ?? 'Bilinmiyor';

$tableData = [];
$kooperatifListesi = [
    "1010" => "Özlü", "1023" => "Kurbanlı", "1059" => "Ulaş", "1098" => "Dalakdere", "1099" => "Gözne", "1108" => "Yeşiltepe", "1110" => "Tomuk", "1112" => "Fındıkpınarı", "1113" => "Aslanköy", "1155" => "Bozyazı", "152" => "Dikilitaş", "153" => "Tekke", "1541" => "Uzuncaburç", "1658" => "Kamberhüyüğü", "1711" => "Kelahmet", "1741" => "Huzurkent", "1819" => "Taşucu", "2062" => "Dağpazarı", "2148" => "Aydıncık", "2152" => "Demirözü", "2274" => "Günyurdu", "2297" => "Haydar", "2298" => "Zeyne", "2320" => "Pirömerli", "2346" => "Kadelli", "2349" => "Yeşilovacık", "2397" => "Gülnar", "2404" => "Anamur", "2481" => "Nacarlı", "2514" => "Çeşmeli", "255" => "Tarsus", "2670" => "Sebil", "271" => "Mezitli", "2817" => "Sarıkavak", "674" => "Arpaçsakarlar", "683" => "A.Tekirçiftliği", "744" => "Erdemli", "746" => "Güzeloluk", "79" => "Evci", "849" => "Mağara", "869" => "Yenice", "904" => "Çiçekli", "921" => "Mut", "1139" => "Yumurtalık", "145" => "Kürkçüler", "1470" => "Durak", "1480" => "Pozantı", "1947" => "Hamdilli", "1949" => "Tuzla", "1953" => "Sarımazı", "1999" => "Mustafabeyli", "2001" => "Kurtkulağı", "2011" => "Salbaş", "2035" => "İmamoğlu", "2043" => "Kozan", "2069" => "Dağıstan", "2083" => "Burhanlı", "2163" => "Mercin", "2167" => "Mercimek", "2284" => "Karayusuflu", "2331" => "Tepecikören", "2362" => "Ceyhan", "2372" => "Sağkaya", "2457" => "Tufanbeyli", "2459" => "Adana", "2464" => "Yeniyayla", "2626" => "Belören", "2646" => "Kaldırım", "2668" => "Doruk", "2689" => "Kadıköy", "2691" => "Karaisalı", "2700" => "Yeşildam", "2920" => "Kırıklı", "404" => "Gerdan", "456" => "Misis", "474" => "Buruk", "77" => "Küçükdikili", "928" => "Kösreli", "930" => "Çınarlı", "931" => "Baklalı", "1328" => "Samandağ", "1353" => "Altınözü", "1864" => "Arsuz", "2053" => "Serinyol", "2403" => "Antakya", "2496" => "Reyhanlı", "2532" => "Kırıkhan", "2740" => "Hassa", "2765" => "Kumlu", "2885" => "Yayladağı", "551" => "Erzin", "654" => "Yeşilköy", "1973" => "Aşağıcıyanlı", "2023" => "Yukarıbozkuyu", "2024" => "Yalnızdut", "2071" => "Yarbaşı", "2405" => "Tacirli", "2425" => "Osmaniye", "2503" => "Aslanpınarı", "2520" => "Toprakkale", "2525" => "Cevdetiye", "2554" => "Kadirli", "2749" => "Hardallık", "2750" => "Durmuşsofular", "2794" => "Hasanbeyli", "2825" => "Sumbas", "891" => "Düziçi"
];

$aylar = ["01"=>"Ocak","02"=>"Şubat","03"=>"Mart","04"=>"Nisan","05"=>"Mayıs","06"=>"Haziran","07"=>"Temmuz","08"=>"Ağustos","09"=>"Eylül","10"=>"Ekim","11"=>"Kasım","12"=>"Aralık"];
$dateParts = explode('.', $date);
$ayIsim = (isset($dateParts[1]) && isset($aylar[$dateParts[1]])) ? $aylar[$dateParts[1]] : "";
$yil = isset($dateParts[2]) ? $dateParts[2] : "";
$ebysHeaderDate = $yil . " " . $ayIsim;

// 1. AŞAMA: Orijinal Mizandan Hesap Adlarını Çıkartmak
$hesapAdiSozlugu = [];
foreach ($rawMizan as $row) {
    if (is_array($row)) {
        $hNo = ''; $hAd = '';
        
        $rowValues = array_values($row);
        for ($i = 0; $i < count($rowValues); $i++) {
            $val = trim(str_replace(['"', "\n", "\r"], '', (string)$rowValues[$i]));
            if ($val === '') continue;

            if (preg_match('/^([1-9]\d{2}(?:\.\d+)*)\s+(.+)$/u', $val, $matches)) {
                $hNo = $matches[1]; $hAd = $matches[2]; break;
            } elseif (preg_match('/^([1-9]\d{2}(?:\.\d+)*)$/', $val)) {
                $hNo = $val;
                for ($j = $i + 1; $j < count($rowValues); $j++) {
                    $nextVal = trim(str_replace(['"', "\n", "\r"], '', (string)$rowValues[$j]));
                    if ($nextVal !== '') {
                        if (!preg_match('/^[\d\.,]+$/', $nextVal)) { $hAd = $nextVal; } else { $hAd = ""; }
                        break;
                    }
                }
                break;
            }
        }
        
        if ($hNo === '') {
            $normalizedRow = [];
            foreach ($row as $key => $value) {
                $cleanKey = mb_strtolower(trim(str_replace(['"', "\n", "\r", ' '], '', $key)), 'UTF-8');
                $normalizedRow[$cleanKey] = trim(str_replace(['"', "\n", "\r"], '', (string)$value));
            }
            if (isset($normalizedRow['hesapno'])) { 
                $hNo = $normalizedRow['hesapno']; 
                $hAd = $normalizedRow['hesapadi'] ?? ($normalizedRow['hesapismi'] ?? ''); 
            } elseif (isset($row[0]) && isset($row[1])) { 
                $hNo = trim(str_replace(['"', "\n", "\r"], '', (string)$row[0])); 
                $hAd = trim(str_replace(['"', "\n", "\r"], '', (string)$row[1])); 
            }
        }

        if ($hNo !== '') {
            $parcalar = explode('.', $hNo);
            $sonKod = ltrim(preg_replace('/[^0-9]/', '', end($parcalar)), '0'); 
            if ($sonKod !== '' && $hAd !== '') { $hesapAdiSozlugu[$sonKod] = $hAd; }
        }
    }
}

$hesaplar = [];

// 2. AŞAMA: HESAPLAMA VE GRUPLAMA
foreach ($rows as $r) {
    $tamHesapNo = $r['hesapNo'] ?? ($r['Hesap No'] ?? ($r['hesap_no'] ?? ''));
    if ($tamHesapNo !== '') {
        $parcalar = explode('.', $tamHesapNo);
        $grupKodu = ltrim(end($parcalar), '0'); 
    } else {
        $grupKodu = ltrim((string)($r['koopNo'] ?? ''), '0');
    }
    
    $hesapAdi = $r['hesapAdi'] ?? ($r['Hesap Adı'] ?? ($r['hesap_adi'] ?? ''));
    if ($hesapAdi === '' && isset($hesapAdiSozlugu[$grupKodu])) { $hesapAdi = $hesapAdiSozlugu[$grupKodu]; }
    
    if (!isset($hesaplar[$grupKodu])) { 
        $hesaplar[$grupKodu] = ['bb' => 0, 'ba' => 0, 'isim' => $hesapAdi]; 
    }
    
    $hesaplar[$grupKodu]['bb'] += (float)($r['bb'] ?? 0);
    $hesaplar[$grupKodu]['ba'] += (float)($r['ba'] ?? 0);
    
    if ($hesaplar[$grupKodu]['isim'] === '' && $hesapAdi !== '') {
        $hesaplar[$grupKodu]['isim'] = $hesapAdi;
    }
}

// 3. AŞAMA: KARAR MEKANİZMASI VE TABLO DOLDURMA
foreach ($hesaplar as $grupKodu => $v) {
    $fark = $v['bb'] - $v['ba'];
    if (abs($fark) < 0.01) continue; 
    
    $orijinalIsim = $v['isim'] !== '' ? $v['isim'] : $grupKodu;
    $yazilacakIsim = "";
    $yazilacakNo = "";
    $isimBulunduMu = false;
    
    // KURAL 1: Hesap koduyla listeden bulma
    if (isset($kooperatifListesi[$grupKodu])) {
        $yazilacakIsim = $kooperatifListesi[$grupKodu];
        $yazilacakNo = $grupKodu;
        $isimBulunduMu = true;
    } 
    
    // KURAL 2: İstediğin Kesin Arama Kuralı (Metin İçinde Geçiyorsa Direkt Kelimeyi ve Kodunu Al)
    if (!$isimBulunduMu) {
        foreach ($kooperatifListesi as $kod => $isim) {
            // Fonksiyonumuz tam kelime eşleşmesi sağlarsa
            if (tamKelimeAra($isim, $orijinalIsim)) {
                $yazilacakIsim = $isim;
                $yazilacakNo = $kod; 
                $isimBulunduMu = true;
                break;
            }
        }
    }
    
    // KURAL 3: Hiçbir türlü listedeki kelimelerden biri geçmiyorsa orjinalini yaz
    if (!$isimBulunduMu) {
        $yazilacakIsim = $orijinalIsim; 
        $yazilacakNo = ""; 
    }
    
    // Tabloya Ekle
    if ($fark > 0) {
        $tableData[] = ["k" => $yazilacakNo, "ad" => $yazilacakIsim, "borc" => number_format($fark, 2, ',', '.'), "alacak" => "", "valor" => ""];
    } else {
        $tableData[] = ["k" => $yazilacakNo, "ad" => $yazilacakIsim, "borc" => "", "alacak" => number_format(abs($fark), 2, ',', '.'), "valor" => ""];
    }
}

date_default_timezone_set('Europe/Istanbul');

// Veritabanı Kayıt İşlemi
$islenmisTabloJson = json_encode($rawMizan, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
if (!$islenmisTabloJson || $islenmisTabloJson === false) { $islenmisTabloJson = '[]'; }
$islemTarihi = date("d.m.Y H:i");

$stmt = $pdo->prepare("INSERT INTO islemler (islem_yapan, mizan_donemi, islem_tarihi, orijinal_mizan) VALUES (?, ?, ?, ?)");
$stmt->execute([$kullaniciAdi, $date, $islemTarihi, $islenmisTabloJson]);

echo json_encode([
    "tableData" => $tableData, 
    "ebysHeaderDate" => $ebysHeaderDate
]);
?>