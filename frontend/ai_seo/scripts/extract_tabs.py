import re

with open('frontend/index.php', 'r', encoding='utf-8') as f:
    content = f.read()

# Tab 3 extraction
tab3_match = re.search(r'(<section class="tab-panel" id="tab-3">.*?</section><!-- YENİ 4\. SEKME)', content, re.DOTALL)
if tab3_match:
    tab3_content = tab3_match.group(1).replace('<!-- YENİ 4. SEKME', '')
    with open('frontend/ai_seo/views/tab_ai_seo.php', 'w', encoding='utf-8') as f:
        f.write(tab3_content)
    
    # Replace in content
    content = content.replace(tab3_match.group(1), "<?php include 'ai_seo/views/tab_ai_seo.php'; ?>\n<!-- YENİ 4. SEKME")

# Tab 4 extraction
tab4_match = re.search(r'(<section class="tab-panel" id="tab-4">.*?</section>)\s*(?=</div>\s*</main>)', content, re.DOTALL)
if tab4_match:
    tab4_content = tab4_match.group(1)
    with open('frontend/ai_seo/views/tab_todos.php', 'w', encoding='utf-8') as f:
        f.write(tab4_content)
    
    # Replace in content
    content = content.replace(tab4_match.group(1), "<?php include 'ai_seo/views/tab_todos.php'; ?>")

with open('frontend/index.php', 'w', encoding='utf-8') as f:
    f.write(content)

print("Extraction complete")
