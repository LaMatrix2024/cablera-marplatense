<?php
declare(strict_types=1);
if (!isset($_GET['tipo']) || $_GET['tipo'] === '') {
    $_GET['tipo'] = ['FIBRA', 'FIBRA+IPTV', 'FIBRA+IPTV_SD'];
}
if (isset($_GET['tipo'])) {
    $_GET['tipo'] = array_map('strtolower', is_array($_GET['tipo']) ? $_GET['tipo'] : [$_GET['tipo']]);
}
$chartRows = [];
$sourceUpdatedAt = 'sin dato';
try {
    $envFileValues = [];
    foreach ([__DIR__ . '/.env', 'C:\\plantel\\DATOS_LOCALES\\plantel.env'] as $envFile) {
        if (!is_readable($envFile)) continue;
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $envLine) {
            if (!str_contains($envLine, '=')) continue;
            [$envKey, $envValue] = explode('=', $envLine, 2);
            $envFileValues[trim($envKey)] = trim($envValue, " \t\"'");
        }
    }
    $env = static fn(string $key, ?string $default = null): ?string => (($value = getenv($key)) !== false && $value !== '') ? $value : ($envFileValues[$key] ?? $default);
    $chartPdo = new PDO(
        'mysql:host=' . $env('DB_HOSTINGER_LAB_HOST') . ';port=' . $env('DB_HOSTINGER_LAB_PORT', '3306') . ';dbname=' . $env('DB_HOSTINGER_LAB_DATABASE') . ';charset=utf8mb4',
        $env('DB_HOSTINGER_LAB_USER'),
        $env('DB_HOSTINGER_LAB_PASSWORD'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    $stateQuery = $chartPdo->prepare("SELECT ultima_fecha_hora_actualizacion, ultima_fecha_hora_origen FROM automatizaciones_estado WHERE nombre_automatizacion = 'dataflow_toa_instalaciones' LIMIT 1");
    $stateQuery->execute();
    $sourceState = $stateQuery->fetch() ?: [];
    $sourceUpdatedAt = (string) ($sourceState['ultima_fecha_hora_actualizacion'] ?? $sourceState['ultima_fecha_hora_origen'] ?? 'sin dato');
    $chartQuery = $chartPdo->query("SELECT periodo, JSON_UNQUOTE(JSON_EXTRACT(datos_json,'$.zona22')) AS grupo, COUNT(*) AS total
        FROM raw_dataflow_toa_instalaciones
        WHERE LOWER(TRIM(JSON_UNQUOTE(JSON_EXTRACT(datos_json,'$.tecnologia')))) IN ('fibra','fibra+iptv','fibra+iptv_sd')
          AND UPPER(JSON_UNQUOTE(JSON_EXTRACT(datos_json,'$.estado_de_la_orden'))) = 'COMPLETADO'
          AND LOWER(JSON_UNQUOTE(JSON_EXTRACT(datos_json,'$.alta'))) IN ('true','1','si','sí')
        GROUP BY periodo, grupo ORDER BY periodo DESC");
    $periodSet = [];
    foreach ($chartQuery as $row) {
        $period = (string) $row['periodo'];
        if (!preg_match('/^\\d{6}$/', $period)) continue;
        if ($period === date('Ym')) continue;
        $isNewPeriod = !isset($periodSet[$period]);
        $periodSet[$period] = true;
        if (count($periodSet) <= 12) $chartRows[] = ['periodo' => $period, 'grupo' => (string) ($row['grupo'] ?: 'Sin grupo'), 'total' => (int) $row['total']];
        if ($isNewPeriod && count($periodSet) > 12) break;
    }
    usort($chartRows, static fn(array $a, array $b): int => [$a['periodo'], $a['grupo']] <=> [$b['periodo'], $b['grupo']]);
} catch (Throwable $chartError) {
    error_log('Gráfico de proyección: ' . $chartError->getMessage());
}
$chartPayload = base64_encode((string) json_encode($chartRows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
ob_start();
require __DIR__ . '/proyeccion_opciones_fixed.php';
$html = (string) ob_get_clean();
$html = str_replace('Tipo de instalación', 'Tecnologías', $html);
$html = str_replace(['Zona 1 + 2', 'Zona 3'], ['Zona 1 + 3 - AMBA + La Plata', 'Zona 3 - PBA'], $html);
$sourceMeta = '<div class="toa-source-meta">Fuente: Dataflow TOA · Última actualización: ' . htmlspecialchars($sourceUpdatedAt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>';
$html = str_replace('</div><form class="filters">', $sourceMeta . '</div><form class="filters">', $html, $metaReplaced);
$html = str_replace(
    ['ÃƒÂ¡', 'ÃƒÂ©', 'ÃƒÂ­', 'ÃƒÂ³', 'ÃƒÂº', 'ÃƒÂ±', 'ÃƒÂ¼', 'Ã¡', 'Ã©', 'Ã­', 'Ã³', 'Ãº', 'Ã±', 'Ã¼'],
    ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'ü', 'á', 'é', 'í', 'ó', 'ú', 'ñ', 'ü'],
    $html
);
$script = <<<'HTML'
<style>
.type-options{position:relative;display:block;min-width:180px}.type-options legend{display:block}.type-options label{display:none}
.toa-picker{position:relative}.toa-picker-button{height:36px;width:100%;padding:0 10px;text-align:left;background:#fff;color:#415063;border:1px solid #c5d5de;border-radius:7px;font-weight:400;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.toa-picker-panel{position:absolute;z-index:10;top:40px;left:0;width:250px;max-height:280px;overflow:auto;padding:8px;background:#fff;border:1px solid #c5d5de;border-radius:7px;box-shadow:0 8px 20px #12344735}.toa-picker-panel[hidden]{display:none}.toa-picker-search{width:100%;height:32px;border:1px solid #c5d5de;border-radius:5px;padding:0 8px;margin-bottom:6px}.toa-picker-option{display:flex!important;align-items:center;gap:7px;padding:7px 4px;white-space:nowrap;font-size:12px}.toa-picker-option input{accent-color:#087f8c}
</style><script>(function(){const field=document.querySelector('.type-options');if(!field)return;const labels=[...field.querySelectorAll('label')];const picker=document.createElement('div');picker.className='toa-picker';const title=document.createElement('span');title.textContent='Tecnologías';title.style.cssText='display:block;color:#415063;font-size:11px;font-weight:700;margin-bottom:4px';const button=document.createElement('button');button.type='button';button.className='toa-picker-button';const panel=document.createElement('div');panel.className='toa-picker-panel';panel.hidden=true;const search=document.createElement('input');search.type='search';search.className='toa-picker-search';search.placeholder='Buscar...';panel.appendChild(search);labels.forEach(label=>{label.className='toa-picker-option';panel.appendChild(label)});picker.append(title,button,panel);field.replaceWith(picker);const update=()=>{const checked=[...panel.querySelectorAll('input[name="tipo[]"]:checked')];button.textContent=checked.length?checked.map(x=>x.parentElement.textContent.trim()).join(', '):'Seleccionar tipos';};button.onclick=()=>{panel.hidden=!panel.hidden;if(!panel.hidden)search.focus()};search.oninput=()=>{const q=search.value.toLowerCase();panel.querySelectorAll('.toa-picker-option').forEach(x=>x.hidden=!x.textContent.toLowerCase().includes(q))};panel.addEventListener('change',e=>{if(e.target.name!=='tipo[]')return;const all=panel.querySelector('input[value="TODOS"]');if(e.target.value==='TODOS'&&e.target.checked)panel.querySelectorAll('input[name="tipo[]"]:not([value="TODOS"])').forEach(x=>x.checked=false);if(e.target.value!=='TODOS'&&e.target.checked&&all)all.checked=false;update()});document.addEventListener('click',e=>{if(!picker.contains(e.target))panel.hidden=true});update()})();</script>
HTML;
$html = preg_replace('/<label>\s*Hist[^<]*<select\b[^>]*name="(?:desde|hasta)"[^>]*>.*?<\/select>\s*<\/label>/is', '', $html) ?? $html;
$html = str_replace('</body>', $script . '</body>', $html);
$html = str_replace('</body>', '<style>.toa-loading{position:fixed;inset:0;z-index:50;display:none;place-items:center;background:#102c4366}.toa-loading.visible{display:grid}.toa-loading-box{background:#fff;border-radius:10px;padding:18px 24px;box-shadow:0 10px 30px #102c4340;color:#102c43;font-weight:700}.toa-loading-box:before{content:"";display:inline-block;width:16px;height:16px;margin-right:10px;border:3px solid #c5d5de;border-top-color:#087f8c;border-radius:50%;vertical-align:-4px;animation:toa-spin .8s linear infinite}@keyframes toa-spin{to{transform:rotate(360deg)}}</style><div class="toa-loading" id="toaLoading"><div class="toa-loading-box">Consultando proyección...</div></div><script>(function(){const f=document.querySelector("form.filters");const b=f?.querySelector("button");const l=document.getElementById("toaLoading");if(f&&b&&l)f.addEventListener("submit",()=>{b.disabled=true;l.classList.add("visible")})})();</script></body>', $html);
$html = str_replace('</head>', '<style>.toa-source-meta{margin-top:7px;color:#087f8c;font-size:12px;font-weight:600}</style></head>', $html);
$chartUi = <<<'HTML'
<style>
.toa-chart-button{height:36px;width:38px;padding:0;background:#fff;color:#087f8c;border:1px solid #b8ccd5;font-size:18px;cursor:pointer}.toa-chart-modal{position:fixed;inset:0;z-index:60;display:none;place-items:center;background:#102c4366;padding:16px}.toa-chart-modal.visible{display:grid}.toa-chart-card{width:min(760px,100%);max-height:90vh;overflow:auto;background:#fff;border:1px solid #c5d5de;border-radius:12px;box-shadow:0 15px 40px #102c4355;padding:20px}.toa-chart-head{display:flex;justify-content:space-between;align-items:center;gap:12px}.toa-chart-close{background:#fff;color:#087f8c;border:1px solid #b8ccd5;width:34px;padding:0}.toa-chart-note{font-size:12px;color:#647589}.toa-chart-table{width:100%;border-collapse:collapse;margin-top:14px;font-size:12px}.toa-chart-table th,.toa-chart-table td{padding:8px;border-bottom:1px solid #d7e2e7}.toa-chart-table th{background:#e7f0f3;text-align:left}.toa-chart-table td:not(:first-child),.toa-chart-table th:not(:first-child){text-align:right}
</style>
<button type="button" class="toa-chart-button" id="toaChartOpen" title="Ver instalaciones por período y grupo" aria-label="Ver instalaciones por período y grupo">📊</button>
<div class="toa-chart-modal" id="toaChartModal" role="dialog" aria-modal="true" aria-labelledby="toaChartTitle"><div class="toa-chart-card"><div class="toa-chart-head"><div><h2 id="toaChartTitle">Instalaciones por período y grupo</h2><p class="toa-chart-note">Últimos 12 períodos · FIBRA, FIBRA+IPTV y FIBRA+IPTV_SD · instalaciones completadas</p></div><button type="button" class="toa-chart-close" id="toaChartClose" aria-label="Cerrar">×</button></div><table class="toa-chart-table"><thead><tr><th>Período</th><th>Grupo</th><th>Total</th></tr></thead><tbody id="toaChartBody"></tbody></table><button type="button" id="toaChartCloseBottom">Cerrar</button></div></div>
<script>(function(){const data=JSON.parse(atob('__PAYLOAD__'));const modal=document.getElementById('toaChartModal');const body=document.getElementById('toaChartBody');const open=document.getElementById('toaChartOpen');const close=()=>modal.classList.remove('visible');data.forEach(r=>{const tr=document.createElement('tr');tr.innerHTML='<td>'+r.periodo.slice(4)+'/'+r.periodo.slice(0,4)+'</td><td>'+r.grupo+'</td><td><b>'+new Intl.NumberFormat('es-AR').format(r.total)+'</b></td>';body.appendChild(tr)});if(!data.length){body.innerHTML='<tr><td colspan="3">No hay datos disponibles para el gráfico.</td></tr>'}open?.addEventListener('click',()=>modal.classList.add('visible'));document.getElementById('toaChartClose')?.addEventListener('click',close);document.getElementById('toaChartCloseBottom')?.addEventListener('click',close);modal?.addEventListener('click',e=>{if(e.target===modal)close()});document.addEventListener('keydown',e=>{if(e.key==='Escape')close()})})();</script>
HTML;
$chartUi = str_replace('<table class="toa-chart-table">', '<canvas id="toaChartCanvas" height="280" style="width:100%;margin-top:14px"></canvas><table class="toa-chart-table">', $chartUi);
$chartUi .= '<script>(function(){const data=JSON.parse(atob("__PAYLOAD__"));const canvas=document.getElementById("toaChartCanvas");if(!canvas||!data.length)return;const ctx=canvas.getContext("2d");const groups=[...new Set(data.map(x=>x.grupo))];const periods=[...new Set(data.map(x=>x.periodo))];const totals=periods.map(p=>groups.map(g=>(data.find(x=>x.periodo===p&&x.grupo===g)||{total:0}).total));const max=Math.max(1,...totals.flat());const w=canvas.clientWidth||680;canvas.width=w;const h=280;canvas.height=h;const pad={l:48,r:12,t:20,b:42};const pw=(w-pad.l-pad.r)/periods.length;const colors=["#087f8c","#e08b2e","#6b5bb5"];ctx.font="10px system-ui";ctx.textAlign="center";periods.forEach((p,i)=>{const vals=totals[i];const bw=Math.max(3,pw/(groups.length+1));vals.forEach((v,j)=>{const bh=(h-pad.b-pad.t)*v/max;const x=pad.l+i*pw+j*bw;const y=h-pad.b-bh;ctx.fillStyle=colors[j%colors.length];ctx.fillRect(x,y,bw-2,bh)});ctx.fillStyle="#415063";ctx.fillText(p.slice(4)+"/"+p.slice(0,4),pad.l+i*pw+pw/2,h-18)});ctx.textAlign="left";groups.forEach((g,i)=>{ctx.fillStyle=colors[i%colors.length];ctx.fillRect(pad.l+i*120,6,10,10);ctx.fillStyle="#415063";ctx.fillText(g,pad.l+i*120+14,15)})})();</script>';
$chartUi .= '<script>(function(){const data=JSON.parse(atob("__PAYLOAD__"));const c=document.getElementById("toaChartCanvas");if(!c||!data.length)return;const x=c.getContext("2d"),groups=[...new Set(data.map(r=>r.grupo))],periods=[...new Set(data.map(r=>r.periodo))],colors=["#087f8c","#e08b2e","#6b5bb5"],w=c.clientWidth||680,h=280,p={l:48,r:18,t:28,b:42},max=Math.max(1,...data.map(r=>r.total)),sx=(w-p.l-p.r)/Math.max(1,periods.length-1),sy=(h-p.t-p.b)/max;c.width=w;c.height=h;x.clearRect(0,0,w,h);x.font="10px system-ui";x.strokeStyle="#d7e2e7";x.fillStyle="#415063";x.textAlign="center";for(let i=0;i<periods.length;i++){const xx=p.l+i*sx;x.beginPath();x.moveTo(xx,p.t);x.lineTo(xx,h-p.b);x.stroke();x.fillText(periods[i].slice(4)+"/"+periods[i].slice(0,4),xx,h-18)};groups.forEach((g,gi)=>{const vals=periods.map(per=>(data.find(r=>r.periodo===per&&r.grupo===g)||{total:0}).total);x.strokeStyle=colors[gi%colors.length];x.fillStyle=colors[gi%colors.length];x.lineWidth=3;x.beginPath();vals.forEach((v,i)=>{const xx=p.l+i*sx,yy=h-p.b-v*sy;i?x.lineTo(xx,yy):x.moveTo(xx,yy)});x.stroke();vals.forEach((v,i)=>{const xx=p.l+i*sx,yy=h-p.b-v*sy;x.beginPath();x.arc(xx,yy,4,0,Math.PI*2);x.fill()});const lx=p.l+gi*150;x.fillRect(lx,6,10,10);x.fillStyle="#415063";x.fillText(g,lx+55,15);x.fillStyle=colors[gi%colors.length]})})();</script>';
$chartUi .= '<script>(function(){const data=JSON.parse(atob("__PAYLOAD__"));const c=document.getElementById("toaChartCanvas");if(!c||!data.length)return;const groups=[...new Set(data.map(r=>r.grupo))],periods=[...new Set(data.map(r=>r.periodo))],tip=document.createElement("div"),wrap=c.parentElement;wrap.style.position="relative";tip.style.cssText="position:absolute;display:none;z-index:3;background:#102c43;color:#fff;padding:7px 9px;border-radius:6px;font:12px system-ui;pointer-events:none;white-space:nowrap;box-shadow:0 4px 12px #102c4366";wrap.appendChild(tip);const p={l:48,r:18,t:28,b:42},max=Math.max(1,...data.map(r=>r.total)),sx=(c.width-p.l-p.r)/Math.max(1,periods.length-1),sy=(c.height-p.t-p.b)/max;c.addEventListener("mousemove",e=>{const r=c.getBoundingClientRect(),mx=e.clientX-r.left,my=e.clientY-r.top;const i=Math.max(0,Math.min(periods.length-1,Math.round((mx-p.l)/sx)));let hit=null,dist=Infinity;groups.forEach(g=>{const row=data.find(x=>x.periodo===periods[i]&&x.grupo===g);if(!row)return;const y=c.height-p.b-row.total*sy,d=Math.hypot(mx-(p.l+i*sx),my-y);if(d<dist){dist=d;hit=row}});if(hit&&dist<18){tip.textContent=periods[i].slice(4)+"/"+periods[i].slice(0,4)+" · "+hit.grupo+": "+new Intl.NumberFormat("es-AR").format(hit.total);tip.style.display="block";tip.style.left=Math.min(mx+12,wrap.clientWidth-tip.offsetWidth-8)+"px";tip.style.top=Math.max(4,my-tip.offsetHeight-10)+"px"}else tip.style.display="none"});c.addEventListener("mouseleave",()=>tip.style.display="none")})();</script>';
$chartUi = str_replace('__PAYLOAD__', $chartPayload, $chartUi);
$html = str_replace('</form>', '<span style="display:inline-flex;align-items:end">' . $chartUi . '</span></form>', $html);
header('Content-Type: text/html; charset=UTF-8');
echo $html;
