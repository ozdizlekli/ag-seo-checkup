<?php
$file = __DIR__ . '/js/app.js';
$content = file_get_contents($file);

// 1. fetchClients
$content = preg_replace("/const \{ data, error \} = await supabaseClient\.from\('clients'\)\.select\('\*'\)\.order\('name', \{ ascending: true \}\);/i", "const res = await fetch('api/clients.php'); if(!res.ok) throw new Error('API error'); const { data } = await res.json();", $content);

// 2. Add client
$content = preg_replace("/const \{ data, error \} = await supabaseClient\.from\('clients'\)\.insert\(\[payload\]\)\.select\(\);/i", "const res = await fetch('api/clients.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload) }); const { data, error } = await res.json(); if(error) throw new Error(error);", $content);

// 3. Edit client
$content = preg_replace("/const \{ error \} = await supabaseClient\.from\('clients'\)\.update\(\{ name: newName \}\)\.eq\('id', state\.currentClientId\);/i", "const res = await fetch('api/clients.php?id=' + state.currentClientId, { method: 'PUT', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ name: newName }) }); const { error } = await res.json(); if(error) throw new Error(error);", $content);

// 4. Update client (domain url)
$content = preg_replace("/const \{ error \} = await supabaseClient\.from\('clients'\)\.update\(payload\)\.eq\('id', state\.currentClientId\);/i", "const res = await fetch('api/clients.php?id=' + state.currentClientId, { method: 'PUT', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload) }); const { error } = await res.json(); if(error) throw new Error(error);", $content);

// 5. Delete client
$content = preg_replace("/const \{ error \} = await supabaseClient\.from\('clients'\)\.delete\(\)\.eq\('id', state\.currentClientId\);/i", "const res = await fetch('api/clients.php?id=' + state.currentClientId, { method: 'DELETE' }); const { error } = await res.json(); if(error) throw new Error(error);", $content);

// 6. content_history insert
$content = preg_replace("/const \{ error \} = await supabaseClient\.from\('content_history'\)\.insert\(\[payload\]\);/i", "const res = await fetch('api/content_history.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload) }); const { error } = await res.json(); if(error) throw new Error(error);", $content);

// 7. content_history fetch
$content = preg_replace("/const \{ data, error \} = await supabaseClient\s*\.from\('content_history'\)\s*\.select\('\*'\)\s*\.eq\('client_id', state\.currentClientId\)\s*\.order\('created_at', \{ ascending: false \}\);/is", "const res = await fetch('api/content_history.php?client_id=' + state.currentClientId); const { data, error } = await res.json(); if(error) throw new Error(error);", $content);

// 8. client_keywords insert
$content = preg_replace("/const \{ error \} = await supabaseClient\.from\('client_keywords'\)\.insert\(payload\);/i", "const res = await fetch('api/client_keywords.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload) }); const { error } = await res.json(); if(error) throw new Error(error);", $content);

// 9. client_keywords select limit 1
$content = preg_replace("/const \{ data: kwData \} = await supabaseClient\.from\('client_keywords'\)\.select\('id'\)\.eq\('client_id', state\.currentClientId\)\.limit\(1\);/i", "const resKW = await fetch('api/client_keywords.php?client_id=' + state.currentClientId); const kwJson = await resKW.json(); const kwData = kwJson.data && kwJson.data.length > 0 ? [{ id: kwJson.data[0].id }] : [];", $content);

// 10. backlinks fetch
$content = preg_replace("/const \{ data, error \} = await supabaseClient\s*\.from\('backlinks'\)\s*\.select\('\*'\)\s*\.eq\('client_id', state\.currentClientId\)\s*\.order\('id', \{ ascending: true \}\);/is", "const res = await fetch('api/backlinks.php?client_id=' + state.currentClientId); const { data, error } = await res.json(); if(error) throw new Error(error);", $content);

// 11. backlinks insert
$content = preg_replace("/const \{ data, error \} = await supabaseClient\.from\('backlinks'\)\.insert\(\[newRow\]\)\.select\(\);/i", "const res = await fetch('api/backlinks.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify([newRow]) }); const { data, error } = await res.json(); if(error) throw new Error(error);", $content);

// 12. backlinks delete
$content = preg_replace("/const \{ error \} = await supabaseClient\.from\('backlinks'\)\.delete\(\)\.eq\('id', rowId\);/i", "const res = await fetch('api/backlinks.php?id=' + rowId, { method: 'DELETE' }); const { error } = await res.json(); if(error) throw new Error(error);", $content);

// 13. score_history fetch
$content = preg_replace("/const \{ data, error \} = await supabaseClient\s*\.from\('score_history'\)\s*\.select\('\*'\)\s*\.eq\('client_id', state\.currentClientId\)\s*\.order\('created_at', \{ ascending: true \}\);/is", "const res = await fetch('api/score_history.php?client_id=' + state.currentClientId); const { data, error } = await res.json(); if(error) throw new Error(error);", $content);

// 14. score_history insert
$content = preg_replace("/const \{ error \} = await supabaseClient\.from\('score_history'\)\.insert\(\[payload\]\);/i", "const res = await fetch('api/score_history.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify([payload]) }); const { error } = await res.json(); if(error) throw new Error(error);", $content);

// 15. drive folder ID update
$content = preg_replace("/const \{ error \} = await supabaseClient\.from\('clients'\)\.update\(\{ drive_folder_id: clientFolderId \}\)\.eq\('id', state\.currentClientId\);/i", "const res = await fetch('api/clients.php?id=' + state.currentClientId, { method: 'PUT', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ drive_folder_id: clientFolderId }) }); const { error } = await res.json(); if(error) throw new Error(error);", $content);

// 16. Update backlink row (quality check)
$content = preg_replace("/supabaseClient\.from\('backlinks'\)\.update\(\{ quality: data\.candidates\[0\]\.content\.parts\[0\]\.text \}\)\.eq\('id', rowId\)\.then\(\(\{ error \}\) => \{/i", "fetch('api/backlinks.php?id=' + rowId, { method: 'PUT', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ quality: data.candidates[0].content.parts[0].text }) }).then(res => res.json()).then(({ error }) => {", $content);

// 17. Fix Sitemap bug - double check strict equality
$content = preg_replace("/c\.id === id/i", "c.id == id", $content);

file_put_contents($file, $content);
echo "app.js refactored.\n";
?>
