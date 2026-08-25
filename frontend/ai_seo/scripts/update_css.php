<?php
$css = file_get_contents("frontend/css/copilot.css");

// Add CSS for UX features
$ux_css = <<<'EOF'

/* 1. Tooltip (Microlearning) */
.has-tooltip {
  position: relative;
  cursor: help;
  border-bottom: 1px dashed #cbd5e1;
}
.has-tooltip:hover::after {
  content: attr(data-tooltip);
  position: absolute;
  bottom: 120%;
  left: 50%;
  transform: translateX(-50%);
  background: #1e293b;
  color: #fff;
  padding: 6px 10px;
  border-radius: 6px;
  font-size: 12px;
  width: max-content;
  max-width: 200px;
  white-space: pre-wrap;
  z-index: 100;
  box-shadow: 0 4px 6px rgba(0,0,0,0.1);
  text-align: center;
  pointer-events: none;
}
.has-tooltip:hover::before {
  content: "";
  position: absolute;
  bottom: 110%;
  left: 50%;
  transform: translateX(-50%);
  border: 5px solid transparent;
  border-top-color: #1e293b;
  z-index: 100;
}

/* 3. Progressive Disclosure (Accordions) */
details.seo-details {
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  margin-top: 10px;
  background: #f8fafc;
}
details.seo-details summary {
  padding: 10px 14px;
  font-weight: 600;
  cursor: pointer;
  outline: none;
  color: #3b82f6;
  list-style: none;
  display: flex;
  align-items: center;
  gap: 8px;
}
details.seo-details summary::-webkit-details-marker {
  display: none;
}
details.seo-details summary::before {
  content: "▶";
  font-size: 10px;
  transition: transform 0.2s;
}
details.seo-details[open] summary::before {
  transform: rotate(90deg);
}
details.seo-details .details-content {
  padding: 0 14px 14px 14px;
  font-size: 13px;
  color: #475569;
  border-top: 1px solid #e2e8f0;
  margin-top: 6px;
  padding-top: 10px;
}

/* 4. Empty States */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: 40px 20px;
  color: #64748b;
  height: 100%;
}
.empty-state svg {
  color: #cbd5e1;
  margin-bottom: 16px;
}
.empty-state p {
  max-width: 300px;
  margin-bottom: 16px;
  line-height: 1.5;
}

/* 5. Quick Actions */
.quick-actions {
  display: flex;
  gap: 8px;
  padding: 10px 20px;
  overflow-x: auto;
  border-top: 1px solid #e2e8f0;
  background: #f8fafc;
}
.quick-action-btn {
  white-space: nowrap;
  background: #fff;
  border: 1px solid #e2e8f0;
  padding: 6px 12px;
  border-radius: 16px;
  font-size: 12px;
  color: #3b82f6;
  cursor: pointer;
  transition: all 0.2s;
  box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}
.quick-action-btn:hover {
  background: #eff6ff;
  border-color: #bfdbfe;
}
EOF;

file_put_contents("frontend/css/copilot.css", $css . "\n" . $ux_css);
echo "Added UX CSS\n";
?>
