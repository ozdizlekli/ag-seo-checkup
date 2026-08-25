<?php
$app = file_get_contents("frontend/js/app.js");

// 1. Add Service and FAQ generating logic
$old_generate = <<<'EOF'
    if (shipCost || shipDays) {
      schema.offers.shippingDetails = {
        "@type": "OfferShippingDetails",
        "shippingRate": {
          "@type": "MonetaryAmount",
          "value": shipCost || "0",
          "currency": currency
        },
        "deliveryTime": {
          "@type": "ShippingDeliveryTime",
          "businessDays": {
            "@type": "OpeningHoursSpecification",
            "dayOfWeek": ["Monday","Tuesday","Wednesday","Thursday","Friday"]
          }
        }
      };
      if (shipDays) {
        schema.offers.shippingDetails.deliveryTime.transitTime = {
          "@type": "QuantitativeValue",
          "maxValue": parseInt(shipDays),
          "unitCode": "d"
        };
      }
    }
    
    if (returnDays) {
      schema.offers.hasMerchantReturnPolicy = {
        "@type": "MerchantReturnPolicy",
        "merchantReturnDays": parseInt(returnDays),
        "returnPolicyCategory": "https://schema.org/MerchantReturnFiniteReturnWindow"
      };
    }
  } else if (schemaType === 'breadcrumb') {
    const b1Name = document.getElementById('t4-bc1-name').value.trim();
    const b1Url = document.getElementById('t4-bc1-url').value.trim();
    const b2Name = document.getElementById('t4-bc2-name').value.trim();
    const b2Url = document.getElementById('t4-bc2-url').value.trim();
    const b3Name = document.getElementById('t4-bc3-name').value.trim();
    const b3Url = document.getElementById('t4-bc3-url').value.trim();

    const items = [];
    if(b1Name && b1Url) items.push({ "@type": "ListItem", "position": 1, "name": b1Name, "item": b1Url });
    if(b2Name && b2Url) items.push({ "@type": "ListItem", "position": 2, "name": b2Name, "item": b2Url });
    if(b3Name && b3Url) items.push({ "@type": "ListItem", "position": 3, "name": b3Name, "item": b3Url });

    if(items.length === 0){
      showToast('En az 1 Breadcrumb adımı girmelisiniz.', 'error');
      return;
    }
    
    schema = {
      "@context": "https://schema.org/",
      "@type": "BreadcrumbList",
      "itemListElement": items
    };
  } else if (schemaType === 'category') {
    if(!name){
      showToast('Kategori adı girmelisiniz.', 'error');
      return;
    }
    schema = {
      "@context": "https://schema.org/",
      "@type": "CollectionPage",
      "name": name,
      "url": "https://www.ornekmagaza.com/kategori/" + name.toLowerCase().replace(/[^a-z0-9ığüşöç]+/gi,'-')
    };
  }

  document.getElementById('t4-schema-output').value = JSON.stringify(schema, null, 2);
  document.getElementById('t4-copy-btn').classList.remove('hidden');
});
EOF;

$new_generate = <<<'EOF'
    if (shipCost || shipDays) {
      schema.offers.shippingDetails = {
        "@type": "OfferShippingDetails",
        "shippingRate": {
          "@type": "MonetaryAmount",
          "value": shipCost || "0",
          "currency": currency
        },
        "deliveryTime": {
          "@type": "ShippingDeliveryTime",
          "businessDays": {
            "@type": "OpeningHoursSpecification",
            "dayOfWeek": ["Monday","Tuesday","Wednesday","Thursday","Friday"]
          }
        }
      };
      if (shipDays) {
        schema.offers.shippingDetails.deliveryTime.transitTime = {
          "@type": "QuantitativeValue",
          "maxValue": parseInt(shipDays),
          "unitCode": "d"
        };
      }
    }
    
    if (returnDays) {
      schema.offers.hasMerchantReturnPolicy = {
        "@type": "MerchantReturnPolicy",
        "merchantReturnDays": parseInt(returnDays),
        "returnPolicyCategory": "https://schema.org/MerchantReturnFiniteReturnWindow"
      };
    }
  } else if (schemaType === 'service') {
    schema = {
      "@context": "https://schema.org/",
      "@type": "Service",
      "serviceType": name || "Hizmet Adı",
      "provider": {
        "@type": "LocalBusiness",
        "name": "Şirket Adı"
      }
    };
  } else if (schemaType === 'faq') {
    schema = {
      "@context": "https://schema.org",
      "@type": "FAQPage",
      "mainEntity": [{
        "@type": "Question",
        "name": "Soru 1?",
        "acceptedAnswer": {
          "@type": "Answer",
          "text": "Cevap 1."
        }
      }]
    };
  } else if (schemaType === 'llmstxt') {
    const btn = document.getElementById('t4-generate-btn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner" style="margin-right:8px;"></span> Üretiliyor...';
    
    const content = document.getElementById('t1-scraped-text').value;
    if (!content.trim()) {
      showToast('Önce Sekme 1\'de bir içerik metni hazırlayın veya URL\'den veri çekin.', 'error');
      btn.disabled = false;
      btn.textContent = 'Schema Üret';
      return;
    }
    
    const prompt = `Bu sitenin içeriklerinden yola çıkarak, yapay zeka botlarının sitenin ne iş yaptığını anlaması için Markdown formatında, kısa faaliyet alanı ve iletişim bilgilerini içeren bir llms.txt içeriği oluştur.\n\nSite Metni:\n${content.substring(0, 3500)}`;
    
    callGemini(prompt).then(res => {
      document.getElementById('t4-schema-output').value = res;
      document.getElementById('t4-copy-btn').classList.remove('hidden');
    }).catch(err => {
      showToast('Hata: ' + err.message, 'error');
    }).finally(() => {
      btn.disabled = false;
      btn.textContent = 'Schema Üret';
    });
    return;
  } else if (schemaType === 'breadcrumb') {
    const b1Name = document.getElementById('t4-bc1-name').value.trim();
    const b1Url = document.getElementById('t4-bc1-url').value.trim();
    const b2Name = document.getElementById('t4-bc2-name').value.trim();
    const b2Url = document.getElementById('t4-bc2-url').value.trim();
    const b3Name = document.getElementById('t4-bc3-name').value.trim();
    const b3Url = document.getElementById('t4-bc3-url').value.trim();

    const items = [];
    if(b1Name && b1Url) items.push({ "@type": "ListItem", "position": 1, "name": b1Name, "item": b1Url });
    if(b2Name && b2Url) items.push({ "@type": "ListItem", "position": 2, "name": b2Name, "item": b2Url });
    if(b3Name && b3Url) items.push({ "@type": "ListItem", "position": 3, "name": b3Name, "item": b3Url });

    if(items.length === 0){
      showToast('En az 1 Breadcrumb adımı girmelisiniz.', 'error');
      return;
    }
    
    schema = {
      "@context": "https://schema.org/",
      "@type": "BreadcrumbList",
      "itemListElement": items
    };
  } else if (schemaType === 'category') {
    if(!name){
      showToast('Kategori adı girmelisiniz.', 'error');
      return;
    }
    schema = {
      "@context": "https://schema.org/",
      "@type": "CollectionPage",
      "name": name,
      "url": "https://www.ornekmagaza.com/kategori/" + name.toLowerCase().replace(/[^a-z0-9ığüşöç]+/gi,'-')
    };
  }

  document.getElementById('t4-schema-output').value = JSON.stringify(schema, null, 2);
  document.getElementById('t4-copy-btn').classList.remove('hidden');
});
EOF;

// Since we use async/await in llmstxt we must make the event listener async!
// Wait! I used `callGemini(prompt).then(...)` so it does not need to be an async function.

$app = str_replace($old_generate, $new_generate, $app);
file_put_contents("frontend/js/app.js", $app);
echo "Updated schema generating logic in app.js\n";
?>
