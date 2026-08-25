<?php
$app = file_get_contents("frontend/js/app.js");

// Replace all instances of document.getElementById('...').addEventListener
// with a safe wrapper.
// Example: document.getElementById('t6-fetch-gsc-btn').addEventListener('click', async () => {
// We can use regex to replace document.getElementById('ID').addEventListener with
// (document.getElementById('ID') ? document.getElementById('ID').addEventListener : function(){})

$app = preg_replace_callback('/document\.getElementById\(\'([a-zA-Z0-9\-_]+)\'\)\.addEventListener/i', function($matches) {
    $id = $matches[1];
    return "if(document.getElementById('$id')) document.getElementById('$id').addEventListener";
}, $app);

// Wait, if it's not the start of a statement, adding `if` might break syntax.
// e.g. `let x = document.getElementById...` (though event listener returns undefined so unlikely).
// A safer way is optional chaining if we can rewrite it, but we can't easily inject `?.` because it's not standard regex easily.
// Actually, `document.getElementById('id')?.addEventListener` is valid modern JS!
// Let's do that!

$app2 = file_get_contents("frontend/js/app.js");
$app2 = preg_replace('/document\.getElementById\(\'([a-zA-Z0-9\-_]+)\'\)\.addEventListener/', 'document.getElementById(\'$1\')?.addEventListener', $app2);

file_put_contents("frontend/js/app.js", $app2);
echo "Replaced with optional chaining.\n";
?>
