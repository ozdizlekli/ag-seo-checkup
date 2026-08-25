<?php
$app = file_get_contents("frontend/js/app.js");

// 1. replace client fetch
$app = str_replace(
"    const { data, error } = await supabaseClient.from('clients').select('*').order('name', { ascending: true });",
"    const res = await fetch('api/clients.php'); const data = await res.json(); const error = data.error;",
$app);

// 2. replace client insert
$app = str_replace(
"    const { data, error } = await supabaseClient.from('clients').insert([payload]).select();",
"    const res = await fetch('api/clients.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload)}); const data = [await res.json()]; const error = data[0].error;",
$app);

// 3. update clients
$app = str_replace(
"    const { error } = await supabaseClient.from('clients').update(payload).eq('id', state.currentClientId);",
"    const res = await fetch('api/clients.php?id='+state.currentClientId, {method:'PUT', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload)}); const json = await res.json(); const error = json.error;",
$app);

// 4. delete clients
$app = str_replace(
"    const { error } = await supabaseClient.from('clients').delete().eq('id', state.currentClientId);",
"    const res = await fetch('api/clients.php?id='+state.currentClientId, {method:'DELETE'}); const json = await res.json(); const error = json.error;",
$app);

// 5. content history insert
$app = str_replace(
"    const { error } = await supabaseClient.from('content_history').insert([payload]);",
"    const res = await fetch('api/content_history.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload)}); const json = await res.json(); const error = json.error;",
$app);

// 6. content history select
$app = str_replace(
"    const { data, error } = await supabaseClient\n      .from('content_history')\n      .select('*')\n      .eq('client_id', state.currentClientId)\n      .order('created_at', { ascending: false });",
"    const res = await fetch('api/content_history.php?client_id='+state.currentClientId); const data = await res.json(); const error = data.error;",
$app);

// 7. client_keywords insert
$app = str_replace(
"    const { error } = await supabaseClient.from('client_keywords').insert(payload);",
"    const res = await fetch('api/client_keywords.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload)}); const json = await res.json(); const error = json.error;",
$app);

// 8. client_keywords select
$app = str_replace(
"       const { data: kwData } = await supabaseClient.from('client_keywords').select('id').eq('client_id', state.currentClientId).limit(1);",
"       const res = await fetch('api/client_keywords.php?client_id='+state.currentClientId); const kwData = await res.json();",
$app);

// 9. score_history insert
$app = str_replace(
"    const { error } = await supabaseClient.from('score_history').insert([payload]);",
"    const res = await fetch('api/score_history.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload)}); const json = await res.json(); const error = json.error;",
$app);

// 10. score_history select
$app = str_replace(
"    const { data, error } = await supabaseClient\n      .from('score_history')\n      .select('*')\n      .eq('client_id', state.currentClientId)\n      .order('created_at', { ascending: true })\n      .limit(30);",
"    const res = await fetch('api/score_history.php?client_id='+state.currentClientId); const data = await res.json(); const error = data.error;",
$app);

// 11. backlinks select
$app = str_replace(
"    const { data, error } = await supabaseClient\n      .from('backlinks')\n      .select('*')\n      .eq('client_id', state.currentClientId)\n      .order('date', { ascending: false });",
"    const data = []; const error = null;",
$app);

// 12. backlinks insert
$app = str_replace(
"    const { data, error } = await supabaseClient.from('backlinks').insert([newRow]).select();",
"    const data = [newRow]; const error = null;",
$app);

// 13. backlinks update
$app = preg_replace(
"/supabaseClient\.from\('backlinks'\)\n\s*\.update\(\{ quality_label: labelVal, quality_score: scoreVal \}\)\n\s*\.eq\('id', tr\.dataset\.id\)\n\s*\.then\(\(\{ error \}\) => \{\n\s*if\(error\) console\.warn\('\[Supabase\] backlink kalite bilgisi kaydedilemedi:', error\.message\);\n\s*\}\);/s",
"/* disabled update */",
$app);

// 14. backlinks delete
$app = str_replace(
"      const { error } = await supabaseClient.from('backlinks').delete().eq('id', rowId);",
"      const error = null;",
$app);

// 15. drive folder id update
$app = str_replace(
"    const { error } = await supabaseClient.from('clients').update({ drive_folder_id: clientFolderId }).eq('id', state.currentClientId);",
"    const res = await fetch('api/clients.php?id='+state.currentClientId, {method:'PUT', headers:{'Content-Type':'application/json'}, body:JSON.stringify({drive_folder_id: clientFolderId})}); const json = await res.json(); const error = json.error;",
$app);

// Disable backlink completely from app
$app = str_replace("await Promise.all([fetchContentHistory(), fetchBacklinks(), fetchScoreHistory()]);", "await Promise.all([fetchContentHistory(), fetchScoreHistory()]);", $app);

// Comment out supabase client init
$app = str_replace("const supabaseClient = window.supabase.createClient(SUPABASE_URL, SUPABASE_ANON_KEY);", "// const supabaseClient = window.supabase.createClient(SUPABASE_URL, SUPABASE_ANON_KEY);", $app);

file_put_contents("frontend/js/app.js", $app);
echo "Exact replace done.\n";
?>
