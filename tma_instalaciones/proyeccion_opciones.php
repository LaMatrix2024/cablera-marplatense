<?php
declare(strict_types=1);
ob_start();
require __DIR__ . '/proyeccion_multi.php';
$html = (string) ob_get_clean();
$html = str_replace('</body>', '<script>(function(){const s=document.querySelector("select[name=\\"tipo[]\\"]");if(s){const box=document.createElement("fieldset");box.className="type-options";box.innerHTML="<legend>Tipo de instalación</legend>";Array.from(s.options).forEach(o=>{const l=document.createElement("label");l.innerHTML="<input type=\\"checkbox\\" name=\\"tipo[]\\" value=\\""+o.value+"\\" "+(o.selected?"checked":"")+">"+o.textContent;l.style.cssText="display:flex;gap:4px;align-items:center";box.appendChild(l)});s.parentElement.replaceWith(box)}const r={"ProyecciÃ³n":"Proyección","EstimaciÃ³n":"Estimación","histÃ³rica":"histórica","PerÃ­odo":"Período","instalaciÃ³n":"instalación","sÃ¡bado":"sábado","dÃ­a":"día","proyecciÃ³n":"proyección","perÃ­odo":"período","dÃ­as":"días"};const w=document.createTreeWalker(document.body,NodeFilter.SHOW_TEXT);const a=[];while(w.nextNode())a.push(w.currentNode);a.forEach(n=>{let t=n.nodeValue;Object.keys(r).forEach(k=>t=t.split(k).join(r[k]));n.nodeValue=t})})();</script></body>', $html);
header('Content-Type: text/html; charset=UTF-8');
echo $html;
