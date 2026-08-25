<?php
require_once __DIR__ . '/db.php';
if(!$pdo) die("No DB connection");

$tables = ['clients', 'content_history', 'client_keywords', 'score_history', 'chat_history'];
$sqlDump = "-- Müşteriler ve sohbet verileri SQL yedeği\n\n";

foreach($tables as $table) {
    $stmt = $pdo->query("SELECT * FROM $table");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if(empty($rows)) continue;
    
    foreach($rows as $row) {
        $keys = array_keys($row);
        $vals = array_values($row);
        $keysStr = implode(", ", $keys);
        
        $escapedVals = array_map(function($val) use ($pdo) {
            if($val === null) return "NULL";
            return $pdo->quote($val);
        }, $vals);
        $valsStr = implode(", ", $escapedVals);
        
        $sqlDump .= "INSERT IGNORE INTO $table ($keysStr) VALUES ($valsStr);\n";
    }
}

file_put_contents(__DIR__ . '/ag_seo_db_data.sql', $sqlDump);
echo "DB Exported to frontend/ag_seo_db_data.sql\n";
?>
