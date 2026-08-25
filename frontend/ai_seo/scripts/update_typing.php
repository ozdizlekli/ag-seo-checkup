<?php
$copilot = file_get_contents("frontend/js/copilot.js");

$old_typing = <<<'EOF'
  function addTypingIndicator() {
    removeTypingIndicator();
    const div = document.createElement('div');
    div.className = `chat-msg ai typing-indicator-wrap`;
    div.id = 'typing-indicator';
    div.innerHTML = `<div class="typing-indicator"><div class="typing-dot"></div><div class="typing-dot"></div><div class="typing-dot"></div></div>`;
    const msgContainer = document.getElementById('copilot-chat-messages-container');
    if (msgContainer) msgContainer.appendChild(div);
    copilotChat.scrollTop = copilotChat.scrollHeight;
  }

  function removeTypingIndicator() {
    const indicator = document.getElementById('typing-indicator');
    if (indicator) indicator.remove();
  }
EOF;

$new_typing = <<<'EOF'
  let typingTimer;
  function addTypingIndicator() {
    removeTypingIndicator();
    const div = document.createElement('div');
    div.className = `chat-msg ai typing-indicator-wrap`;
    div.id = 'typing-indicator';
    div.innerHTML = `<div style="display:flex; align-items:center; gap:10px;"><div class="typing-indicator" style="margin:0;"><div class="typing-dot"></div><div class="typing-dot"></div><div class="typing-dot"></div></div> <span id="typing-msg-text" style="font-size:13px; color:#64748b; font-style:italic;">Sayfa kodları taranıyor...</span></div>`;
    const msgContainer = document.getElementById('copilot-chat-messages-container');
    if (msgContainer) msgContainer.appendChild(div);
    copilotChat.scrollTop = copilotChat.scrollHeight;
    
    const msgs = ["İçerik yapay zeka için özetleniyor...", "E-E-A-T güven sinyalleri ölçülüyor...", "Rakiplerle kıyaslanıyor...", "Sonuçlar derleniyor..."];
    let mIdx = 0;
    typingTimer = setInterval(() => {
        const textEl = document.getElementById('typing-msg-text');
        if(textEl) {
            textEl.textContent = msgs[mIdx];
            mIdx = (mIdx + 1) % msgs.length;
        }
    }, 4000);
  }

  function removeTypingIndicator() {
    clearInterval(typingTimer);
    const indicator = document.getElementById('typing-indicator');
    if (indicator) indicator.remove();
  }
EOF;

$copilot = str_replace($old_typing, $new_typing, $copilot);
file_put_contents("frontend/js/copilot.js", $copilot);
echo "Updated dynamic loading in copilot.js\n";
?>
