<?php

session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode([
        'success' => false,
        'error' => 'Bu işlem için giriş yapmanız gerekiyor.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

require_once __DIR__ . '/../db.php';
header('Content-Type: application/json');

if (!$pdo) {
    echo json_encode(['error' => 'No database connection']);
    exit;
}

/**
 * Teknik SEO denetimi bir "musteri" secimine degil, dogrudan girilen bir
 * URL'ye dayandigi icin (bkz. js/technical-seo.js -> runTechnicalSeoAudit,
 * client_id yok) skor gecmisi asil olarak URL'ye gore saklanir/sorgulanir.
 * client_id opsiyoneldir (NULL olabilir) - kaydederken bir musteriye
 * etiketlemek icin kullanilir, sorgularken de (istenirse) ek bir filtre
 * olarak kullanilabilir.
 *
 * DOMAIN ESLESTIRME: URL bazli ve musteri bazli gecmis eskiden birbirinden
 * tamamen bagimsizdi - ayni site icin "URL ile Ara" ve "Musteriye Gore" iki
 * ayri, birlesmeyen liste donduruyordu. Artik her satirin normalize edilmis
 * hostname'i ('domain' kolonu) de saklaniyor; bir musterinin domain_url'i ile
 * ayni hostname'e sahip TUM kayitlar (client_id ile etiketlenmemis olsa
 * bile) o musterinin gecmisinde birlikte gosteriliyor (bkz. GET altindaki
 * (client_id = ? OR domain = ?) sorgusu).
 */
function normalizeAuditUrl(string $url): string
{
    $url = trim($url);
    $url = rtrim($url, '/');
    return $url;
}

/**
 * Bir URL'den, musteri eslestirmesinde kullanilacak "cikri" hostname'i
 * cikarir: protokol, "www." on eki, yol, sorgu string'i ve sondaki slash yok
 * sayilir. parse_url() gercek bir URL parser oldugu icin (metin icinde
 * "gecme" degil, gercek host sinirlarina gore ayiriyor) ornegin
 * "adresgezgini.example.com" ile "adresgezgini.com" YANLISLIKLA eslesmez.
 */
function normalizeDomain(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }
    $host = parse_url($url, PHP_URL_HOST);
    if (!is_string($host) || $host === '') {
        return '';
    }
    $host = strtolower(trim($host));
    if (str_starts_with($host, 'www.')) {
        $host = substr($host, 4);
    }
    return $host;
}

/**
 * Verilen hostname'e ("domain") sahip kayitli bir musteri var mi diye
 * clients tablosuna bakar - musterinin kendi domain_url'i de AYNI mantikla
 * normalize edilerek karsilastirilir (DB'de nasil yazildigina - protokollu/
 * protokolsuz, www'li/www'siz - bakilmaksizin dogru eslesmesi icin).
 * Musteri sayisi (bir ajans icin) tipik olarak kucuk oldugundan tum listeyi
 * PHP tarafinda normalize edip karsilastirmak, kirilgan bir SQL string
 * karsilastirmasindan cok daha guvenilir.
 */
function resolveClientIdByDomain(PDO $pdo, string $domain): ?int
{
    if ($domain === '') {
        return null;
    }
    $stmt = $pdo->query('SELECT id, domain_url FROM clients WHERE domain_url IS NOT NULL AND domain_url != \'\'');
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $client) {
        if (normalizeDomain((string) $client['domain_url']) === $domain) {
            return (int) $client['id'];
        }
    }
    return null;
}

$method = $_SERVER['REQUEST_METHOD'];

/**
 * TUM DENETIM GECMISI (overview) - Skor Gecmisi sekmesinin altindaki
 * acilir/kapanir "Tum Denetim Gecmisi" bolumu icin, kayitli TUM denetim
 * kayitlarini musteri/domain'e gore gruplayip (ayni domain = ayni site,
 * URL farkliliklarindan bagimsiz - bkz. normalizeDomain()) her grup icin
 * son skor + bir onceki skor (degisim icin) + son tarama tarihi + kismi/tam
 * tarama bilgisini dondurur. Sayfalama (offset/limit) ve arama (musteri adi
 * veya domain) grupLANMIS liste uzerinde yapilir - bir ajans icin toplam
 * kayit sayisi PHP tarafinda gruplamayi elle yapmaya uygun kucuklukte
 * (bkz. resolveClientIdByDomain'deki ayni felsefe).
 */
if ($method === 'GET' && isset($_GET['overview']) && $_GET['overview'] === '1') {
    $search = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
    $offset = isset($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;
    $limit = isset($_GET['limit']) ? max(1, min(50, (int) $_GET['limit'])) : 10;

    $clientsById = [];
    $clientIdByDomain = [];
    $clientStmt = $pdo->query('SELECT id, name, domain_url FROM clients');
    foreach ($clientStmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $clientsById[(int) $c['id']] = $c['name'];
        if (!empty($c['domain_url'])) {
            $d = normalizeDomain((string) $c['domain_url']);
            if ($d !== '') {
                $clientIdByDomain[$d] = (int) $c['id'];
            }
        }
    }

    $rows = $pdo->query(
        'SELECT id, url, domain, client_id, final_score, is_partial, is_full_crawl, created_at
         FROM technical_score_history ORDER BY created_at DESC'
    )->fetchAll(PDO::FETCH_ASSOC);

    // "Tum Denetim Gecmisi" basligindaki sayi, LISTEDEKI grup sayisiyla
    // (bir site/musteri = tek satir) KARISTIRILMAMALI - kullanici kac
    // "kayit" (denetim aninda kaydedilmis anlik goruntu) oldugunu merak
    // ediyor, listede kac SATIR gorundugunu degil (ayni siteye ait birden
    // fazla kayit, tek bir satirda "son skor + degisim" olarak birlesiyor -
    // bu, o siteye ait ONCEKI kayitlarin GIZLENDIGI/KAYBOLDUGU anlamina
    // gelmiyor, sadece o satirin "onceki skora gore degisim" hesabinda
    // kullanildigi anlamina geliyor). Bu yuzden ham tablo satir sayisini
    // AYRI hesaplayip donduruyoruz.
    $totalRecords = (int) $pdo->query('SELECT COUNT(*) FROM technical_score_history')->fetchColumn();

    // client_id ile etiketlenmemis ama musterinin domain_url'iyle ayni
    // hostname'e sahip kayitlar da (asagidaki normal GET'teki (client_id OR
    // domain) mantigiyla ayni prensip) o musterinin grubuna dahil edilir.
    $groups = [];
    $order = [];
    foreach ($rows as $row) {
        $effectiveClientId = $row['client_id'] !== null
            ? (int) $row['client_id']
            : ($clientIdByDomain[$row['domain']] ?? null);
        $groupKey = $effectiveClientId !== null
            ? 'client:' . $effectiveClientId
            : 'domain:' . ($row['domain'] !== null && $row['domain'] !== '' ? $row['domain'] : $row['url']);

        if (!isset($groups[$groupKey])) {
            $groups[$groupKey] = [
                'client_id' => $effectiveClientId,
                'client_name' => $effectiveClientId !== null ? ($clientsById[$effectiveClientId] ?? null) : null,
                'domain' => $row['domain'] ?: '',
                'representative_url' => $row['url'],
                'latest_score' => (int) $row['final_score'],
                'previous_score' => null,
                'latest_date' => $row['created_at'],
                'is_partial' => (bool) $row['is_partial'],
                'is_full_crawl' => (bool) $row['is_full_crawl'],
                'record_count' => 0,
            ];
            $order[] = $groupKey;
        } elseif ($groups[$groupKey]['previous_score'] === null) {
            // Satirlar zaten created_at DESC sirali geldiginden, bu grup
            // icin ikinci kez karsilasilan satir "bir onceki" skordur.
            $groups[$groupKey]['previous_score'] = (int) $row['final_score'];
        }
        $groups[$groupKey]['record_count']++;
    }

    $list = [];
    foreach ($order as $key) {
        $g = $groups[$key];
        $g['delta'] = $g['previous_score'] !== null ? ($g['latest_score'] - $g['previous_score']) : null;
        $list[] = $g;
    }

    if ($search !== '') {
        $needle = mb_strtolower($search, 'UTF-8');
        $list = array_values(array_filter($list, function ($g) use ($needle) {
            $haystack = mb_strtolower(($g['client_name'] ?? '') . ' ' . $g['domain'], 'UTF-8');
            return mb_strpos($haystack, $needle) !== false;
        }));
    }

    $total = count($list);
    $page = array_slice($list, $offset, $limit);

    echo json_encode(['data' => $page, 'total' => $total, 'total_records' => $totalRecords]);
    exit;
}

if ($method === 'GET') {
    $url = isset($_GET['url']) && $_GET['url'] !== '' ? normalizeAuditUrl((string) $_GET['url']) : null;
    $clientId = isset($_GET['client_id']) && $_GET['client_id'] !== '' ? (int) $_GET['client_id'] : null;
    $domain = isset($_GET['domain']) && $_GET['domain'] !== '' ? normalizeDomain((string) $_GET['domain']) : null;

    if ($url === null && $clientId === null && $domain === null) {
        echo json_encode(['error' => 'Missing url, client_id or domain']);
        exit;
    }

    $conditions = [];
    $params = [];

    if ($clientId !== null && $domain !== null) {
        // client_id ILE etiketlenmis kayitlar VE (etiketlenmemis olsa bile)
        // ayni domain'e ait kayitlar TEK bir listede birlesin - iki ayri
        // sorgu yerine tek OR kosulu, boylece sonuc zaten created_at'e gore
        // tek bir ORDER BY ile dogru sekilde siralanir.
        $conditions[] = '(client_id = ? OR domain = ?)';
        $params[] = $clientId;
        $params[] = $domain;
    } else {
        if ($clientId !== null) {
            $conditions[] = 'client_id = ?';
            $params[] = $clientId;
        }
        if ($domain !== null) {
            $conditions[] = 'domain = ?';
            $params[] = $domain;
        }
    }
    if ($url !== null) {
        $conditions[] = 'url = ?';
        $params[] = $url;
    }

    $sql = 'SELECT * FROM technical_score_history WHERE ' . implode(' AND ', $conditions) . ' ORDER BY created_at DESC LIMIT 30';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!is_array($data) || empty($data['url']) || !isset($data['final_score'])) {
        echo json_encode(['error' => 'Missing url or final_score']);
        exit;
    }

    $categoryScores = is_array($data['category_scores'] ?? null) ? $data['category_scores'] : [];
    $lookup = [];
    foreach ($categoryScores as $cat) {
        if (is_array($cat) && isset($cat['key'])) {
            $lookup[$cat['key']] = (int) ($cat['score'] ?? 0);
        }
    }

    $domain = normalizeDomain((string) $data['url']);
    // client_id acikca gonderilmediyse (kaydetme widget'inda musteri
    // secilmediyse) - URL'nin domain'i kayitli bir musteriyle eslesiyor mu
    // diye otomatik kontrol ediyoruz. Boylece kullanici musteriyi elle
    // secmeyi unutsa bile kayit doğru musteriye baglanmis olur.
    $clientId = !empty($data['client_id']) ? (int) $data['client_id'] : resolveClientIdByDomain($pdo, $domain);

    $stmt = $pdo->prepare(
        'INSERT INTO technical_score_history
         (url, domain, client_id, final_score, is_partial, is_full_crawl, crawlability_score, performance_score, site_structure_score, security_score, schema_score, mobile_score)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        normalizeAuditUrl((string) $data['url']),
        $domain !== '' ? $domain : null,
        $clientId,
        (int) $data['final_score'],
        !empty($data['is_partial']) ? 1 : 0,
        !empty($data['is_full_crawl']) ? 1 : 0,
        $lookup['crawlability_indexability'] ?? 0,
        $lookup['performance'] ?? 0,
        $lookup['site_structure_links'] ?? 0,
        $lookup['security_https'] ?? 0,
        $lookup['schema_structured_data'] ?? 0,
        $lookup['mobile_first'] ?? 0,
    ]);

    echo json_encode(['success' => true]);
    exit;
}

if ($method === 'DELETE') {
    $url = isset($_GET['url']) && $_GET['url'] !== '' ? normalizeAuditUrl((string) $_GET['url']) : null;

    if ($url === null) {
        echo json_encode(['error' => 'Missing url']);
        exit;
    }

    $stmt = $pdo->prepare('DELETE FROM technical_score_history WHERE url = ?');
    $stmt->execute([$url]);

    echo json_encode(['success' => true, 'deleted' => $stmt->rowCount()]);
    exit;
}

echo json_encode(['error' => 'Method not allowed']);
