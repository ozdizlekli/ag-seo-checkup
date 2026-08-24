<?php
$app = file_get_contents("frontend/js/app.js");

$old_change = <<<'EOF'
document.getElementById('t4-schema-type').addEventListener('change', function(){
  const val = this.value;
  document.getElementById('t4-product-fields').classList.toggle('hidden', val !== 'product');
  document.getElementById('t4-local-fields').classList.toggle('hidden', val !== 'local');
  document.getElementById('t4-breadcrumb-fields').classList.toggle('hidden', val !== 'breadcrumb');
  document.getElementById('t4-category-fields').classList.toggle('hidden', val !== 'category');
  
  if (val === 'local') {
    document.getElementById('t4-name-label').textContent = 'İşletme Adı';
    document.getElementById('t4-name').placeholder = 'örn. Ajans Adı Dijital Pazarlama';
    document.getElementById('t4-name').parentElement.classList.remove('hidden');
  } else if (val === 'product') {
    document.getElementById('t4-name-label').textContent = 'Ürün Adı';
    document.getElementById('t4-name').placeholder = 'örn. Nike Koşu Ayakkabısı';
    document.getElementById('t4-name').parentElement.classList.remove('hidden');
  } else if (val === 'category') {
    document.getElementById('t4-name-label').textContent = 'Kategori Adı';
    document.getElementById('t4-name').placeholder = 'örn. Kadın Çantaları';
    document.getElementById('t4-name').parentElement.classList.remove('hidden');
  } else if (val === 'breadcrumb') {
    document.getElementById('t4-name').parentElement.classList.add('hidden');
  }
});
EOF;

$new_change = <<<'EOF'
document.getElementById('t4-schema-type').addEventListener('change', function(){
  const val = this.value;
  document.getElementById('t4-product-fields').classList.toggle('hidden', val !== 'product');
  document.getElementById('t4-local-fields').classList.toggle('hidden', val !== 'local');
  document.getElementById('t4-breadcrumb-fields').classList.toggle('hidden', val !== 'breadcrumb');
  document.getElementById('t4-category-fields').classList.toggle('hidden', val !== 'category');
  
  if (val === 'local') {
    document.getElementById('t4-name-label').textContent = 'İşletme Adı';
    document.getElementById('t4-name').placeholder = 'örn. Ajans Adı Dijital Pazarlama';
    document.getElementById('t4-name').parentElement.classList.remove('hidden');
  } else if (val === 'product') {
    document.getElementById('t4-name-label').textContent = 'Ürün Adı';
    document.getElementById('t4-name').placeholder = 'örn. Nike Koşu Ayakkabısı';
    document.getElementById('t4-name').parentElement.classList.remove('hidden');
  } else if (val === 'category') {
    document.getElementById('t4-name-label').textContent = 'Kategori Adı';
    document.getElementById('t4-name').placeholder = 'örn. Kadın Çantaları';
    document.getElementById('t4-name').parentElement.classList.remove('hidden');
  } else if (val === 'service') {
    document.getElementById('t4-name-label').textContent = 'Hizmet Adı';
    document.getElementById('t4-name').placeholder = 'örn. SEO Danışmanlığı';
    document.getElementById('t4-name').parentElement.classList.remove('hidden');
  } else if (val === 'breadcrumb' || val === 'faq' || val === 'llmstxt') {
    document.getElementById('t4-name').parentElement.classList.add('hidden');
  }
});
EOF;

$app = str_replace($old_change, $new_change, $app);
file_put_contents("frontend/js/app.js", $app);
echo "Updated schema UI logic\n";
?>
