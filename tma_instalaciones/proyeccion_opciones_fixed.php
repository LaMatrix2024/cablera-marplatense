<?php
declare(strict_types=1);
ob_start();
require __DIR__ . '/proyeccion_multi_fixed.php';
$html=(string)ob_get_clean();
$html=str_replace('</body>','<script>(function(){const s=document.querySelector("select[name=\\"tipo[]\\"]");if(!s)return;const f=document.createElement("fieldset");f.className="type-options";f.innerHTML="<legend>Tipo de instalación</legend>";Array.from(s.options).forEach(o=>{const l=document.createElement("label");l.innerHTML="<input type=\\"checkbox\\" name=\\"tipo[]\\" value=\\""+o.value+"\\" "+(o.selected?"checked":"")+">"+o.textContent;f.appendChild(l)});s.parentElement.replaceWith(f)})();</script></body>',$html);echo $html;
