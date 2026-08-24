<?php
$app = file_get_contents("frontend/js/app.js");

// 1. replace client fetch
$app = str_replace(
"const res = await fetch('api/clients.php'); const data = await res.json(); const error = data.error;",
"const res = await fetch('api/clients.php'); const { data, error } = await res.json();",
$app);

// 2. replace client insert
// Wait, client insert in api/clients.php returns {"data": [{"id":..., "name":...}]}
$app = str_replace(
"const res = await fetch('api/clients.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload)}); const data = [await res.json()]; const error = data[0].error;",
"const res = await fetch('api/clients.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload)}); const { data, error } = await res.json();",
$app);

// 3. content history select
$app = str_replace(
"const res = await fetch('api/content_history.php?client_id='+state.currentClientId); const data = await res.json(); const error = data.error;",
"const res = await fetch('api/content_history.php?client_id='+state.currentClientId); const { data, error } = await res.json();",
$app);

// 4. client_keywords select
// In api/client_keywords.php, what does it return? 
$app = str_replace(
"const res = await fetch('api/client_keywords.php?client_id='+state.currentClientId); const kwData = await res.json();",
"const res = await fetch('api/client_keywords.php?client_id='+state.currentClientId); const { data: kwData, error } = await res.json();",
$app);

// 5. score_history select
$app = str_replace(
"const res = await fetch('api/score_history.php?client_id='+state.currentClientId); const data = await res.json(); const error = data.error;",
"const res = await fetch('api/score_history.php?client_id='+state.currentClientId); const { data, error } = await res.json();",
$app);

file_put_contents("frontend/js/app.js", $app);
echo "Fixed destructuring.\n";
?>
