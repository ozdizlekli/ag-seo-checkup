const fs = require('fs');
let code = fs.readFileSync('frontend/ai_seo/js/copilot.js', 'utf8');

// A & B: Fix loadFromHistory block (around line 550)
const oldLoadHistoryMsg = `    const msgContainer = document.getElementById('copilot-chat-messages-container');
    if (msgContainer) msgContainer.style.display = 'block';
    if (msgContainer) { try { msgContainer.replaceChildren(); } catch(e) { msgContainer.innerHTML = ''; } }`;
const newLoadHistoryMsg = `    const msgContainer = document.getElementById('copilot-chat-messages-container');
    if (msgContainer) { 
        msgContainer.style.display = 'flex'; 
        msgContainer.style.flexDirection = 'column'; 
        msgContainer.style.flex = '1'; 
        msgContainer.style.overflowY = 'auto'; 
        msgContainer.style.paddingTop = '16px'; 
    }
    if (msgContainer) { try { msgContainer.replaceChildren(); } catch(e) { msgContainer.innerHTML = ''; } }`;
code = code.replace(oldLoadHistoryMsg, newLoadHistoryMsg);

const oldLoadHistoryCqa = `copilotInputArea.style.display = "none"; const cqa = document.getElementById("copilot-quick-actions"); if(cqa) cqa.style.display = "none";`;
const newLoadHistoryCqa = `copilotInputArea.style.display = "none"; const cqa = document.getElementById("copilot-quick-actions"); if(cqa) cqa.style.display = "flex";`;
code = code.replace(oldLoadHistoryCqa, newLoadHistoryCqa);

// Also add paddingTop: '16px' to fresh chat block if not there
const oldFreshChatMsg = `  const msgContainer = document.getElementById('copilot-chat-messages-container');
  if (msgContainer) { 
      msgContainer.style.display = 'flex'; 
      msgContainer.style.flexDirection = 'column';
      msgContainer.style.flex = '1'; 
      msgContainer.style.overflowY = 'auto';
      
  }`;
const newFreshChatMsg = `  const msgContainer = document.getElementById('copilot-chat-messages-container');
  if (msgContainer) { 
      msgContainer.style.display = 'flex'; 
      msgContainer.style.flexDirection = 'column';
      msgContainer.style.flex = '1'; 
      msgContainer.style.overflowY = 'auto';
      msgContainer.style.paddingTop = '16px';
  }`;
code = code.replace(oldFreshChatMsg, newFreshChatMsg);

fs.writeFileSync('frontend/ai_seo/js/copilot.js', code);
console.log("Patched user requested changes!");
