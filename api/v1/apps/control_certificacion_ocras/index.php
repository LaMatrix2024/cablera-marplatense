<?php
declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/api/v1/_common.php';
require_once dirname(__DIR__, 4) . '/shared/auth/LocalAuthSession.php';
require_once dirname(__DIR__, 4) . '/shared/auth/HostingerTokenService.php';
require_once dirname(__DIR__, 4) . '/shared/auth/CorporateAccessRepository.php';

/* Compatibilidad con el _common.php que todavía está instalado en Hostinger.
 * La versión remota expone la conexión como $pdo_lacablera y aún no incluye
 * los helpers del proxy local. En el entorno local estas funciones ya existen
 * y no se redeclaran. */
if (!function_exists('api_database')) {
    function api_database(): PDO
    {
        global $pdo_lacablera;
        if ($pdo_lacablera instanceof PDO) return $pdo_lacablera;
        throw new RuntimeException('Conexión central no disponible.');
    }
}
if (!function_exists('lcm_auth_local_proxy_request')) {
    function lcm_auth_local_proxy_request(): bool { return false; }
}

const CC_REMOTE_URL = 'https://lacablera.com/api/v1/apps/control_certificacion_ocras/index.php';

function cc_response(array $data, int $status = 200): never
{
    if (isset($data['management'], $data['data']) && is_array($data['management']) && is_array($data['data'])) {
        $responseProfile = (array)($GLOBALS['profile'] ?? []);
        $canTransfer = !cc_is_all_role($responseProfile) && (($data['data']['puede_transferir'] ?? false) === true);
        $data['management']['can_transfer'] = $canTransfer;
        if ($canTransfer && !isset($data['management']['users'])) $data['management']['users'] = (array)(cc_rows($responseProfile)['responsables'] ?? []);
    }
    api_json($status >= 400 ? ['ok' => false, 'error' => $data['error'] ?? ['code' => 'error', 'message' => 'No se pudo completar la operación.']] : ['ok' => true, 'data' => $data], $status);
}

function cc_fail(string $code, string $message, int $status): never { api_json(['ok' => false, 'error' => ['code' => $code, 'message' => $message]], $status); }

function cc_local(): bool { return lcm_auth_local_proxy_request(); }

function cc_profile_access(array $profile): array
{
    foreach ((array)($profile['modules'] ?? []) as $module) {
        if (($module['codigo'] ?? '') === 'control_certificacion_ocras') {
            return ['permissions' => (array)($module['permissions'] ?? []), 'enabled' => ($module['estado'] ?? true) !== false];
        }
    }
    return ['permissions' => [], 'enabled' => false];
}

function cc_proxy(): never
{
    $auth = new LocalAuthSession(); $snapshot = $auth->snapshot();
    if (!is_array($snapshot)) cc_fail('unauthenticated', 'La sesión no está iniciada.', 401);
    $token = (string)($snapshot['token'] ?? '');
    if (!preg_match('/^[a-f0-9]{64}$/i', $token)) cc_fail('unauthenticated', 'La sesión no está iniciada.', 401);
    $query = [];
    foreach (['accion','sigest','sigest_exact','etapa','tarea_id','novedad_id','ids'] as $key) if (isset($_GET[$key])) $query[$key] = (string)$_GET[$key];
    $url = CC_REMOTE_URL . ($query ? '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '');
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $headers = ['Accept: application/json', 'Authorization: Bearer ' . $token, 'X-Requested-With: XMLHttpRequest']; $body = null;
    if ($method === 'POST') {
        $allowed = ['csrf','tareas','usuario_destino_id','motivo','operacion','sigest','etapa','tipo','texto','novedad_id','fecha_gestion','tarea_id','keys'];
        $form = []; foreach ($allowed as $key) if (array_key_exists($key, $_POST)) $form[$key] = (string)$_POST[$key];
        $body = http_build_query($form, '', '&', PHP_QUERY_RFC3986);
        $headers[] = 'Content-Type: application/x-www-form-urlencoded; charset=UTF-8';
        $csrf = (string)($form['csrf'] ?? '');
        if (!$auth->csrfValid($csrf)) cc_fail('csrf_invalid', 'La sesión de seguridad venció. Recargá la aplicación e intentá nuevamente.', 419);
        $headers[] = 'X-LCM-Proxy-CSRF: ' . hash_hmac('sha256', $csrf, $token);
        $headers[] = 'X-LCM-Proxy-CSRF-Token: ' . $csrf;
    }
    $curl = curl_init($url); if ($curl === false) cc_fail('proxy_unavailable', 'No se pudo contactar la API OCRAS.', 503);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_CUSTOMREQUEST=>$method, CURLOPT_HTTPHEADER=>$headers, CURLOPT_POSTFIELDS=>$body, CURLOPT_CONNECTTIMEOUT=>3, CURLOPT_TIMEOUT=>15, CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2]);
    $response = curl_exec($curl); $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE); $type = (string)curl_getinfo($curl, CURLINFO_CONTENT_TYPE); $error = curl_error($curl); curl_close($curl);
    if ($response === false || $status < 100) { error_log('control_certificacion_ocras proxy: ' . $error); cc_fail('remote_unavailable', 'La API OCRAS no está disponible.', 503); }
    http_response_code($status); header('Cache-Control: no-store'); header('Content-Type: ' . ($type !== '' ? $type : 'application/json; charset=UTF-8')); echo $response; exit;
}

if (cc_local()) {
    if (($_GET['accion'] ?? '') !== '' || ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') cc_proxy();
    cc_fail('not_found', 'Ruta no encontrada.', 404);
}

/* Ingesta técnica de Matrix. Se autentica con un secreto de integración del
 * servidor, nunca con una identidad de usuario ni con un valor del navegador. */
if (in_array((string)($_GET['accion'] ?? ''), ['ingest_raw', 'reconcile', 'automation_state', 'price_check', 'price_ingest', 'report_rows'], true) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $configured = (string)lcm_config_value('INTEGRATION_API_TOKEN', '');
    $provided = (string)($_SERVER['HTTP_X_LCM_INTEGRATION_TOKEN'] ?? '');
    if ($configured === '' || $provided === '' || !hash_equals($configured, $provided)) cc_fail('unauthenticated', 'Integración no autorizada.', 401);
    try {
        if ((string)($_GET['accion'] ?? '') === 'reconcile') {
            $pdo = api_database(); $latest = (string)$pdo->query('SELECT MAX(fecha_importacion) FROM raw_certificacion_ocras')->fetchColumn();
            if ($latest === '') api_json(['ok'=>true,'data'=>['latest'=>null,'tasks_created'=>0]], 200);
            $map = ['OBRAS AL 100'=>'cierre','PLANOS PEND'=>'planos','PLANOS CARG'=>'aprobacion','PLANOS REC'=>'rechazados']; $created = 0; $updated = 0;
            $select = $pdo->prepare("SELECT TRIM(csisvadi) sigest,grafo,estado,CASE WHEN estado='OBRAS AL 100' THEN dfecha_avance_100 ELSE final END fecha_base FROM raw_certificacion_ocras WHERE fecha_importacion=:f AND ctipo='OCRA' AND estado IN ('OBRAS AL 100','PLANOS PEND','PLANOS CARG','PLANOS REC')"); $select->execute(['f'=>$latest]);
            $insert = $pdo->prepare("INSERT INTO control_certificacion_tareas(sigest,etapa,grafo,estado_fuente,fecha_base,responsable_fuente,estado_operativo,created_at,updated_at) SELECT :s1,:e,:g,:st,:b,contrata_us,'PENDIENTE',UTC_TIMESTAMP(),UTC_TIMESTAMP() FROM raw_certificacion_ocras WHERE fecha_importacion=:f AND TRIM(csisvadi)=:s2 AND ctipo='OCRA' ORDER BY id DESC LIMIT 1 ON DUPLICATE KEY UPDATE grafo=VALUES(grafo),estado_fuente=VALUES(estado_fuente),fecha_base=VALUES(fecha_base),updated_at=VALUES(updated_at)");
            foreach ($select as $row) { $stage = $map[(string)$row['estado']] ?? ''; if ($stage === '') continue; $insert->execute(['s1'=>$row['sigest'],'s2'=>$row['sigest'],'e'=>$stage,'g'=>$row['grafo'],'st'=>$row['estado'],'b'=>$row['fecha_base'],'f'=>$latest]); $insert->rowCount() > 0 ? $created++ : $updated++; }
            api_json(['ok'=>true,'data'=>['latest'=>$latest,'tasks_created'=>$created,'tasks_updated'=>$updated]], 200);
        }
        if ((string)($_GET['accion'] ?? '') === 'automation_state') {
            $input = api_input(); $name = trim((string)($input['nombre_automatizacion'] ?? 'bajadas_certificacion_ocras')); $result = trim((string)($input['resultado_actualizacion'] ?? 'OK'));
            if ($name === '' || !in_array($result, ['OK','ERROR','pendiente'], true)) cc_fail('invalid_state','Estado de automatización inválido.',400);
            $pdo = api_database();
            $pdo->exec("CREATE TABLE IF NOT EXISTS automatizaciones_estado (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,nombre_automatizacion VARCHAR(100) NOT NULL,nombre_tabla VARCHAR(100) NOT NULL,sistema_origen VARCHAR(100) NOT NULL,resultado_actualizacion VARCHAR(30) NOT NULL,ultima_fecha_hora_actualizacion DATETIME NULL,registros_leidos INT UNSIGNED NOT NULL DEFAULT 0,registros_insertados INT UNSIGNED NOT NULL DEFAULT 0,registros_actualizados INT UNSIGNED NOT NULL DEFAULT 0,registros_omitidos INT UNSIGNED NOT NULL DEFAULT 0,ultimo_error TEXT NULL,activo TINYINT(1) NOT NULL DEFAULT 1,metadata_json JSON NULL,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,PRIMARY KEY(id),UNIQUE KEY uq_automatizaciones_nombre(nombre_automatizacion)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $stmt = $pdo->prepare("INSERT INTO automatizaciones_estado (nombre_automatizacion,nombre_tabla,sistema_origen,resultado_actualizacion,ultima_fecha_hora_actualizacion,registros_leidos,registros_insertados,registros_actualizados,registros_omitidos,ultimo_error,activo,metadata_json,created_at,updated_at) VALUES (:n,'raw_certificacion_ocras','CERTIFICACION_OCRAS',:r,UTC_TIMESTAMP(),:l,:i,:u,:o,:e,1,:m,UTC_TIMESTAMP(),UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE resultado_actualizacion=VALUES(resultado_actualizacion),ultima_fecha_hora_actualizacion=VALUES(ultima_fecha_hora_actualizacion),registros_leidos=VALUES(registros_leidos),registros_insertados=VALUES(registros_insertados),registros_actualizados=VALUES(registros_actualizados),registros_omitidos=VALUES(registros_omitidos),ultimo_error=VALUES(ultimo_error),metadata_json=VALUES(metadata_json),updated_at=VALUES(updated_at)");
            $stmt->execute(['n'=>$name,'r'=>$result,'l'=>(int)($input['registros_leidos']??0),'i'=>(int)($input['registros_insertados']??0),'u'=>(int)($input['registros_actualizados']??0),'o'=>(int)($input['registros_omitidos']??0),'e'=>($input['ultimo_error']??null),'m'=>json_encode((array)($input['metadata']??[]),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
            api_json(['ok'=>true,'data'=>['updated'=>true]],200);
        }
        if ((string)($_GET['accion'] ?? '') === 'price_check') {
            $input = api_input(); $latest = (int)($input['latest_price_code'] ?? 0); if ($latest <= 0) cc_fail('invalid_price','Código de precio inválido.',400);
            $pdo = api_database(); $exists = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='raw_bigstorm_precios'")->fetchColumn(); if ($exists !== 1) cc_fail('price_table_unavailable','No está disponible la tabla de precios en Plantel.',503);
            $columns = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='raw_bigstorm_precios'")->fetchAll(PDO::FETCH_COLUMN); $condition = in_array('vigente',$columns,true) ? 'vigente=1' : (in_array('estado',$columns,true) ? 'estado="VIGENTE"' : '1=1');
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM raw_bigstorm_precios WHERE ccod_pl_cont_prec=:code AND '.$condition); $stmt->execute(['code'=>$latest]); if ((int)$stmt->fetchColumn() < 1) cc_fail('price_not_ready','La lista de precios vigente todavía no está en Plantel.',409); api_json(['ok'=>true,'data'=>['latest_price_code'=>$latest,'ready'=>true]],200);
        }
        if ((string)($_GET['accion'] ?? '') === 'price_ingest') {
            $input = api_input();
            $rows = (array)($input['rows'] ?? []);
            if (count($rows) < 1 || count($rows) > 1000) cc_fail('invalid_batch', 'El lote de precios debe contener entre 1 y 1000 filas.', 400);
            $pdo = api_database();
            $exists = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='raw_bigstorm_precios'")->fetchColumn();
            if ($exists !== 1) cc_fail('price_table_unavailable', 'No está disponible la tabla de precios en Plantel.', 503);
            $allowed = ['batch_id','source_row_number','source_system','source_view','ccod_pl_im_hs','ccod_pl_cont','ccod_pl_zona','nvalor','cid_pl_im_hs_item','ccod_pl_cont_prec','raw_payload_json','source_hash','imported_at'];
            $written = 0;
            foreach ($rows as $item) {
                if (!is_array($item) || trim((string)($item['source_hash'] ?? '')) === '') cc_fail('invalid_row', 'Fila de precio inválida.', 400);
                $row = [];
                foreach ($allowed as $column) if (array_key_exists($column, $item)) $row[$column] = $item[$column];
                $columns = array_keys($row); $names = implode(',', array_map(static fn(string $c): string => '`'.$c.'`', $columns)); $params = implode(',', array_fill(0, count($columns), '?'));
                $existing = $pdo->prepare('SELECT id FROM raw_bigstorm_precios WHERE source_hash=:hash LIMIT 1'); $existing->execute(['hash'=>$row['source_hash']]); $existingId = $existing->fetchColumn();
                if ($existingId !== false) {
                    $updates = implode(',', array_map(static fn(string $c): string => '`'.$c.'`=?', array_diff($columns, ['source_hash'])));
                    $values = array_values(array_filter($row, static fn($key): bool => $key !== 'source_hash', ARRAY_FILTER_USE_KEY)); $values[] = $existingId;
                    $pdo->prepare("UPDATE raw_bigstorm_precios SET $updates WHERE id=?")->execute($values);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO raw_bigstorm_precios ($names) VALUES ($params)"); $stmt->execute(array_values($row));
                }
                $written++;
            }
            api_json(['ok'=>true,'data'=>['received'=>count($rows),'written'=>$written]], 200);
        }
        if ((string)($_GET['accion'] ?? '') === 'report_rows') {
            $input = api_input(); $report = trim((string)($input['report'] ?? '')); $latestDate = '';
            $pdo = api_database(); $rows = [];
            if ($report === 'obras_al100') {
                $region = trim((string)($input['region'] ?? 'BS AIRES SUR')); $estado = trim((string)($input['estado'] ?? 'OBRAS AL 100'));
                $stmt = $pdo->prepare("SELECT TRIM(r.contrata_us) RESPONSABLE,TRIM(r.csisvadi) SISVADI,TRIM(r.central) CENTRAL,TRIM(r.titulo) TITULO,r.dfecha_avance_100,TRIM(r.estado) ESTADO,TRIM(r.region) REGION FROM raw_certificacion_ocras r JOIN (SELECT TRIM(csisvadi) k,MAX(fecha_importacion) f FROM raw_certificacion_ocras WHERE TRIM(csisvadi)<>'' GROUP BY TRIM(csisvadi)) u ON u.k=TRIM(r.csisvadi) AND u.f=r.fecha_importacion WHERE UPPER(TRIM(r.estado))=UPPER(:estado) AND UPPER(TRIM(r.region))=UPPER(:region) ORDER BY TRIM(r.csisvadi)");
                $stmt->execute(['estado'=>$estado,'region'=>$region]); $rows = $stmt->fetchAll();
            } elseif ($report === 'planos_pendientes') {
                $region = trim((string)($input['region'] ?? 'BS AIRES SUR')); $estado = trim((string)($input['estado'] ?? 'PLANOS PEND'));
                $stmt = $pdo->prepare("SELECT TRIM(csisvadi) SISVADI,TRIM(central) CENTRAL,TRIM(titulo) TITULO,TRIM(estado) ESTADO,TRIM(contrata_us) RESPONSABLE,TRIM(dias_pend) ANTIGUEDAD,grafo,final,dfecha_avance_100,region FROM raw_certificacion_ocras WHERE UPPER(TRIM(estado))=UPPER(:estado) AND UPPER(TRIM(region))=UPPER(:region) ORDER BY TRIM(csisvadi)"); $stmt->execute(['estado'=>$estado,'region'=>$region]); $rows = $stmt->fetchAll();
            } elseif ($report === 'entregables_facturadas') {
                $stmt = $pdo->query("SELECT id,csisvadi,region,cresponsable,ccodcgotra,const_f_ini,final,fecha_facturada,valorprod_plantel,cestado_montaldi FROM raw_certificacion_ocras WHERE UPPER(TRIM(COALESCE(cestado_montaldi,'')))='FACTURADA'"); $rows = $stmt->fetchAll();
            } elseif ($report === 'entregables_duplicadas') {
                $stmt = $pdo->query("SELECT r.id,r.grafo,r.csisvadi,r.final,r.fecha_facturada,r.valorprod_plantel,r.cestado_montaldi,r.fecha_importacion FROM raw_certificacion_ocras r INNER JOIN (SELECT TRIM(CAST(csisvadi AS CHAR)) k FROM raw_certificacion_ocras WHERE csisvadi IS NOT NULL AND TRIM(CAST(csisvadi AS CHAR))<>'' GROUP BY TRIM(CAST(csisvadi AS CHAR)) HAVING COUNT(*)>1) d ON TRIM(CAST(r.csisvadi AS CHAR))=d.k ORDER BY d.k,r.id"); $rows = $stmt->fetchAll();
            } elseif ($report === 'pendiente_facturacion') {
                $from = trim((string)($input['periodo_desde'] ?? '')); $to = trim((string)($input['periodo_hasta'] ?? ''));
                $p = $pdo->prepare("SELECT id,periodo,c_titulo,d_fecha,fecha_costo_certif,c_responsable,c_sucursal,c_sucursal_nombre,c_cod_pl_cont,c_nom_pl_cont,estado,n_valor_tasa_total FROM raw_produccion_planta WHERE periodo BETWEEN :f AND :t AND UPPER(TRIM(c_cod_pl_tare_tipo))='OCRAS' ORDER BY periodo,id"); $p->execute(['f'=>$from,'t'=>$to]); $production = $p->fetchAll();
                $latest = $pdo->query("SELECT MAX(fecha_importacion) FROM raw_certificacion_ocras")->fetchColumn();
                $c = $pdo->prepare("SELECT id,csisvadi,estado,ent_cert,fecha_certent,fecha_facturada,total_hitos,ndefinitivoimporte,valorprod_plantel,archivo_hash,fila_origen FROM raw_certificacion_ocras WHERE fecha_importacion=:f AND csisvadi IS NOT NULL AND TRIM(csisvadi)<>''"); $c->execute(['f'=>$latest]);
                $rows = ['produccion'=>$production,'certificacion'=>$c->fetchAll(),'latest_import'=>$latest];
            } elseif ($report === 'produccion_diaria_ocras') {
                $date = trim((string)($input['fecha'] ?? '')); $field = trim((string)($input['campo'] ?? 'ingresos')); $dateColumn = $field === 'finalizados' ? 'final' : 'ingreso_obra'; $l = $field === 'finalizados' ? 'l_prod' : 'ing_l'; $n = $field === 'finalizados' ? 'n_prod' : 'ing_n'; $lc = $field === 'finalizados' ? 'lc_prod' : 'ing_lc'; $lz = $field === 'finalizados' ? 'lz_prod' : 'ing_lz'; $cash = $field === 'finalizados' ? 'valorprod_plantel' : 'ing_cash';
                if ($date === '') { $latestQ = $pdo->query("SELECT MAX(STR_TO_DATE(ingreso_obra,'%d/%m/%Y')) latest_ing,MAX(STR_TO_DATE(final,'%d/%m/%Y')) latest_fin FROM raw_certificacion_ocras WHERE ingreso_obra REGEXP '^[0-9]{2}/[0-9]{2}/[0-9]{4}$' OR final REGEXP '^[0-9]{2}/[0-9]{2}/[0-9]{4}$'")->fetch(); $date = (string)($field === 'finalizados' ? ($latestQ['latest_fin'] ?? '') : ($latestQ['latest_ing'] ?? '')); } $latestDate = $date;
                $q = $pdo->prepare("SELECT COALESCE(NULLIF(TRIM(central),''),'SIN DISTRITO') distrito,COUNT(*) cantidad,SUM(CAST(COALESCE(NULLIF(`$l`,''),'0') AS DECIMAL(18,4))) hb_l,SUM(CAST(COALESCE(NULLIF(`$n`,''),'0') AS DECIMAL(18,4))) hb_n,SUM(CAST(COALESCE(NULLIF(`$lc`,''),'0') AS DECIMAL(18,4))) hb_lc,SUM(CAST(COALESCE(NULLIF(`$lz`,''),'0') AS DECIMAL(18,4))) hb_lz,SUM(CAST(COALESCE(NULLIF(`$cash`,''),'0') AS DECIMAL(18,4))) cash FROM raw_certificacion_ocras WHERE UPPER(TRIM(ctipo))='OCRA' AND UPPER(TRIM(cresponsable))='BS AS' AND `$dateColumn` REGEXP '^[0-9]{2}/[0-9]{2}/[0-9]{4}$' AND STR_TO_DATE(`$dateColumn`,'%d/%m/%Y')=:d GROUP BY COALESCE(NULLIF(TRIM(central),''),'SIN DISTRITO') ORDER BY cantidad DESC,distrito ASC"); $q->execute(['d'=>$date]); $rows = $q->fetchAll();
            } else cc_fail('invalid_report','Informe OCRAS no habilitado.',400);
            api_json(['ok'=>true,'data'=>['report'=>$report,'rows'=>$rows,'latest_date'=>$latestDate]],200);
        }
        $input = api_input(); $items = (array)($input['rows'] ?? []);
        if (count($items) < 1 || count($items) > 1000) cc_fail('invalid_batch', 'El lote debe contener entre 1 y 1000 filas.', 400);
        foreach ($items as $item) {
            if (!is_array($item) || trim((string)($item['archivo_hash'] ?? '')) === '' || trim((string)($item['hoja_origen'] ?? '')) === '') {
                cc_fail('invalid_row', 'Fila de ingesta inválida.', 400);
            }
        }
        $pdo = api_database();
        $allowed = ['archivo_origen','archivo_hash','fecha_importacion','origen','periodo','hoja_origen','fila_origen','hash_fila','payload_json','created_at','tipo_proyecto','csisvadi','grafo','titulo','central','ingreso_obra','final','ent_cert','region','estado','cobservacion','contrata_us','dias_pend','hito1','hito2','hito3','hito4','hito80','ndefinitivoimporte','valor_mo','primer_cert','total_hitos','periodohito4','periodohito80','nvaloradjudicacion','cnomcgotra','ing_l','ing_n','ing_lc','ing_lz','l_prod','n_prod','lc_prod','lz_prod','q_prod','l_cert','n_cert','lc_cert','lz_cert','q_cert','valorprod','valorprod_plantel','ccodcgotra','const_f_ini','const_avan_total','cestado_montaldi','cresponsable','ing_cash','csucursal','sisvadi_central_titulo','czona','obras_al_100','ctipo','fecha_obras','fecha_certent','fecha_facturada','const_f_costo_certif_x','dfecha_avance_100'];
        $availableColumns = array_map('strval', $pdo->query("SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='raw_certificacion_ocras'")->fetchAll(PDO::FETCH_COLUMN));
        $allowed = array_values(array_intersect($allowed, $availableColumns));
        $written = 0; $updated = 0;
        $pdo->beginTransaction();
        foreach ($items as $item) {
            if (!is_array($item) || trim((string)($item['archivo_hash'] ?? '')) === '' || trim((string)($item['hoja_origen'] ?? '')) === '') cc_fail('invalid_row', 'Fila de ingesta inválida.', 400);
            $row = [];
            foreach ($allowed as $column) if (array_key_exists($column, $item)) $row[$column] = is_array($item[$column]) ? json_encode($item[$column], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : $item[$column];
            if (in_array('payload_json', $allowed, true)) $row['payload_json'] = json_encode((array)($item['payload'] ?? $item), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            if (in_array('created_at', $allowed, true)) $row['created_at'] = $row['created_at'] ?? gmdate('Y-m-d H:i:s');
            $columns = array_keys($row); $names = implode(',', array_map(static fn(string $c): string => '`'.$c.'`', $columns)); $params = implode(',', array_fill(0, count($columns), '?'));
            $identity = $pdo->prepare('SELECT id FROM raw_certificacion_ocras WHERE archivo_hash=:hash AND hoja_origen=:sheet AND fila_origen=:row LIMIT 1');
            $identity->execute(['hash'=>$row['archivo_hash'],'sheet'=>$row['hoja_origen'],'row'=>(int)$row['fila_origen']]);
            $existingId = $identity->fetchColumn();
            if ($existingId !== false) {
                $updates = implode(',', array_map(static fn(string $c): string => '`'.$c.'`=?', array_diff($columns, ['archivo_hash','hoja_origen','fila_origen'])));
                if ($updates !== '') {
                    $values = array_values(array_filter($row, static fn($key): bool => !in_array($key, ['archivo_hash','hoja_origen','fila_origen'], true), ARRAY_FILTER_USE_KEY));
                    $values[] = $existingId;
                    $pdo->prepare("UPDATE raw_certificacion_ocras SET $updates WHERE id=?")->execute($values);
                }
                $updated++;
            } else {
                $sql = "INSERT INTO raw_certificacion_ocras ($names) VALUES ($params)";
                $pdo->prepare($sql)->execute(array_values($row)); $written++;
            }
        }
        $pdo->commit();
        api_json(['ok'=>true,'data'=>['received'=>count($items),'written'=>$written,'updated'=>$updated]], 200);
    } catch (Throwable $error) { if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack(); error_log('control_certificacion_ocras ingest_raw: '.$error->getMessage()); cc_fail('ingest_failed','No se pudo importar el lote OCRAS.',503); }
}

$authorization = api_authorization_header() ?? '';
$token = '';
try {
    $centralPdo = api_database();
    if (preg_match('/^Bearer\s+([a-f0-9]{64})$/i', $authorization, $matches)) {
        $token = strtolower($matches[1]);
        $profile = (array)(new CentralHostingerTokenService($centralPdo))->session($token)['profile'];
    } else {
        $configured = (string)lcm_config_value('INTEGRATION_API_TOKEN', '');
        $provided = (string)($_SERVER['HTTP_X_LCM_INTEGRATION_TOKEN'] ?? '');
        $legacyId = trim((string)($_SERVER['HTTP_X_LCM_NEXO_USER'] ?? ''));
        if ($configured === '' || $provided === '' || !hash_equals($configured, $provided) || $legacyId === '') cc_fail('unauthenticated', 'La sesión no está iniciada.', 401);
        $map = require dirname(__DIR__, 4) . '/config/control_certificacion_identity_map.php';
        $email = strtolower(trim((string)($map[$legacyId] ?? '')));
        if ($email === '') cc_fail('unauthenticated', 'La identidad operativa no está vinculada.', 401);
        $stmt = $centralPdo->prepare('SELECT * FROM usuarios WHERE LOWER(email)=:email LIMIT 1'); $stmt->execute(['email'=>$email]); $user = $stmt->fetch();
        if (!is_array($user) || strtoupper((string)($user['estado'] ?? '')) !== 'ACTIVO') cc_fail('forbidden', 'Usuario inactivo.', 403);
        $accessRepo = new CorporateAccessRepository($centralPdo); $permission = $accessRepo->effectivePermissions((int)$user['id'], 'CABLERAMARPLATENSE', 'control_certificacion_ocras');
        if (!$permission['authorized']) cc_fail('module_forbidden', 'No tenés permiso para acceder a Control de certificación OCRAS.', 403);
        $roleStmt = $centralPdo->prepare('SELECT r.codigo,r.nombre FROM usuario_rol ur INNER JOIN roles r ON r.id=ur.rol_id WHERE ur.usuario_id=:id AND ur.activo=1 AND r.activo=1'); $roleStmt->execute(['id'=>(int)$user['id']]);
        $profile = ['user'=>$user,'roles'=>$roleStmt->fetchAll(),'modules'=>[['codigo'=>'control_certificacion_ocras','estado'=>true,'permissions'=>$permission['permissions']]]];
    }
} catch (HttpError $error) { cc_fail($error->errorCode(), $error->getMessage(), $error->status()); }
  catch (Throwable $error) { error_log('control_certificacion_ocras auth: ' . $error->getMessage()); cc_fail('service_unavailable', 'No se pudo validar la sesión.', 503); }
$access = cc_profile_access($profile);
if (!$access['enabled'] || ($access['permissions']['puede_ver'] ?? false) !== true) cc_fail('module_forbidden', 'No tenés permiso para acceder a Control de certificación OCRAS.', 403);

function cc_db(): PDO { static $pdo; if (!$pdo instanceof PDO) $pdo = api_database(); return $pdo; }
function cc_identity_map(): array { static $map; return $map ??= require dirname(__DIR__, 4) . '/config/control_certificacion_identity_map.php'; }
function cc_legacy_for_email(string $email): string { foreach (cc_identity_map() as $legacy => $mapped) if (strcasecmp($mapped, trim($email)) === 0) return $legacy; return ''; }
function cc_email_for_legacy(string $legacy): string { return (string)(cc_identity_map()[trim($legacy)] ?? ''); }
function cc_roles(array $profile): array { return array_map(static fn($role) => strtolower(trim((string)(is_array($role) ? ($role['codigo'] ?? $role['nombre'] ?? '') : $role))), (array)($profile['roles'] ?? [])); }
function cc_is_all_role(array $profile): bool { $user=(array)($profile['user'] ?? []); $roles=cc_roles($profile); return (int)($user['es_superadmin'] ?? 0)===1 || in_array('superadmin',$roles,true) || in_array('administrador',$roles,true) || in_array('coordinador',$roles,true); }
function cc_can_all(array $profile): bool { $all=cc_is_all_role($profile); if(!$all && ($_SERVER['REQUEST_METHOD']??'GET')==='POST' && (string)($_POST['operacion']??'')==='transferir') return true; return $all; }
function cc_current_email(array $profile): string { return strtolower(trim((string)(($profile['user']['email'] ?? '')))); }
function cc_authorized_task(array $profile, string $sigest, string $stage): bool
{
    $legacy = cc_legacy_for_email(cc_current_email($profile));
    $stmt = cc_db()->prepare("SELECT TRIM(r.csisvadi) AS sigest, COALESCE(a.responsable_usuario_id, rn.usuario_nexo_id, '') AS responsable
        FROM raw_certificacion_ocras r
        LEFT JOIN control_certificacion_tareas t ON t.sigest=TRIM(r.csisvadi) AND t.etapa=:stage
        LEFT JOIN (SELECT a1.tarea_id,a1.responsable_usuario_id FROM control_certificacion_asignaciones a1
          JOIN (SELECT tarea_id,MAX(id) id FROM control_certificacion_asignaciones GROUP BY tarea_id) x
          ON x.tarea_id=a1.tarea_id AND x.id=a1.id) a ON a.tarea_id=t.id
        LEFT JOIN responsables_nexo rn ON rn.sigest=TRIM(r.csisvadi) AND rn.activo=1
        WHERE TRIM(r.csisvadi)=:sigest AND r.estado IN ('OBRAS AL 100','PLANOS PEND','PLANOS CARG','PLANOS REC')
        ORDER BY r.fecha_importacion DESC LIMIT 1");
    $stmt->execute(['stage' => $stage, 'sigest' => $sigest]);
    $row = $stmt->fetch();
    if (!is_array($row) || trim((string)($row['sigest'] ?? '')) === '') return false;
    $responsable = trim((string)($row['responsable'] ?? ''));
    $transfer=(($_SERVER['REQUEST_METHOD']??'GET')==='POST' && (string)($_POST['operacion']??'')==='transferir');
    if ($transfer) return $legacy !== '' && $responsable === $legacy;
    if ($responsable === '' && cc_can_all($profile)) return true;
    return cc_can_all($profile) || ($legacy !== '' && $responsable === $legacy);
}
function cc_csrf_remote(string $token): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') return;
    $provided=(string)($_SERVER['HTTP_X_LCM_PROXY_CSRF'] ?? ''); $csrf=(string)($_SERVER['HTTP_X_LCM_PROXY_CSRF_TOKEN'] ?? '');
    if ($provided === '' || $csrf === '' || !hash_equals(hash_hmac('sha256',$csrf,$token),$provided)) cc_fail('csrf_invalid','La sesión de seguridad venció. Recargá la aplicación e intentá nuevamente.',419);
}
function cc_stage(string $state): string { return ['OBRAS AL 100'=>'cierre','PLANOS PEND'=>'planos','PLANOS CARG'=>'aprobacion','PLANOS REC'=>'rechazados'][$state] ?? ''; }
function cc_days(?string $value): ?int { if (!$value) return null; try { $date=new DateTimeImmutable(substr($value,0,19)); $tz=new DateTimeZone('America/Argentina/Buenos_Aires'); $base=new DateTimeImmutable($date->format('Y-m-d'),$tz); return max(0,(int)$base->diff(new DateTimeImmutable('today',$tz))->format('%r%a')); } catch(Throwable) { return null; } }
function cc_money(mixed $value): float { $v=trim((string)$value); if($v==='')return 0.0; $v=preg_replace('/[^0-9,.-]/','',$v)??''; if(str_contains($v,',')&&str_contains($v,'.')){$v=str_replace('.','',$v);$v=str_replace(',','.',$v);} elseif(str_contains($v,','))$v=str_replace(',','.',$v); return is_numeric($v)?(float)$v:0.0; }
function cc_semaphore(?int $days, string $stage): array { if($days===null)return ['label'=>'Sin fecha','class'=>'unknown']; $critical=$stage==='aprobacion'?$days>3:$days>10; return $critical?['label'=>'Crítico','class'=>'red']:(($stage==='aprobacion'?$days<=3:$days<=7)?['label'=>'En término','class'=>'green']:['label'=>'Atención','class'=>'yellow']); }
function cc_rows(array $profile): array
{
    $pdo=cc_db(); $latest=(string)$pdo->query('SELECT MAX(fecha_importacion) FROM raw_certificacion_ocras')->fetchColumn();
    if($latest==='')return ['rows'=>[],'latest'=>''];
    $sql="SELECT r.csisvadi,r.grafo,r.titulo,r.central,r.region,r.cnomcgotra,r.cresponsable,r.contrata_us,r.dfecha_avance_100,r.final,r.valorprod_plantel,r.estado,r.fecha_importacion,t.id tarea_id,a.responsable_usuario_id, rn.usuario_nexo_id rn_usuario_id,rn.usuario_nexo_nombre_snapshot rn_nombre,ev.tipo_evento gestion_evento,ev.created_at gestion_fecha
          FROM raw_certificacion_ocras r LEFT JOIN control_certificacion_tareas t ON t.sigest=TRIM(r.csisvadi) AND t.etapa=CASE r.estado WHEN 'OBRAS AL 100' THEN 'cierre' WHEN 'PLANOS PEND' THEN 'planos' WHEN 'PLANOS CARG' THEN 'aprobacion' WHEN 'PLANOS REC' THEN 'rechazados' ELSE '' END
          LEFT JOIN (SELECT a1.tarea_id,a1.responsable_usuario_id FROM control_certificacion_asignaciones a1 JOIN (SELECT tarea_id,MAX(id) id FROM control_certificacion_asignaciones GROUP BY tarea_id) x ON x.tarea_id=a1.tarea_id AND x.id=a1.id) a ON a.tarea_id=t.id
          LEFT JOIN responsables_nexo rn ON rn.sigest=TRIM(r.csisvadi) AND rn.activo=1
          LEFT JOIN (SELECT e1.sigest,e1.etapa,e1.tipo_evento,e1.created_at FROM control_certificacion_eventos e1 JOIN (SELECT sigest,etapa,MAX(id) id FROM control_certificacion_eventos GROUP BY sigest,etapa) e2 ON e2.sigest=e1.sigest AND e2.etapa=e1.etapa AND e2.id=e1.id) ev ON ev.sigest=TRIM(r.csisvadi) AND ev.etapa=CASE r.estado WHEN 'OBRAS AL 100' THEN 'cierre' WHEN 'PLANOS PEND' THEN 'planos' WHEN 'PLANOS CARG' THEN 'aprobacion' WHEN 'PLANOS REC' THEN 'rechazados' ELSE '' END
          WHERE r.ctipo='OCRA' AND r.estado IN ('OBRAS AL 100','PLANOS PEND','PLANOS CARG','PLANOS REC') AND r.fecha_importacion=:fi ORDER BY r.csisvadi";
    $st=$pdo->prepare($sql);$st->execute(['fi'=>$latest]);$rows=[];$canAll=cc_can_all($profile);$email=cc_current_email($profile);$legacy=cc_legacy_for_email($email);
    foreach($st as $r){$stage=cc_stage((string)$r['estado']);$effective=trim((string)($r['responsable_usuario_id']?:$r['rn_usuario_id']?:''));$effectiveEmail=cc_email_for_legacy($effective);if(!$canAll&&$effectiveEmail!==$email)continue;$base=$stage==='cierre'?$r['dfecha_avance_100']:$r['final'];$days=cc_days($base);$visible=$effectiveEmail?:((string)$r['rn_nombre']?:((string)$r['contrata_us']));$rows[]=['sigest'=>trim((string)$r['csisvadi']),'stage'=>$stage,'etapa'=>$stage,'grafo'=>(string)$r['grafo'],'titulo'=>(string)$r['titulo'],'central'=>(string)$r['central'],'region'=>(string)$r['region'],'cnomcgotra'=>(string)$r['cnomcgotra'],'contrata_us'=>(string)$r['contrata_us'],'cresponsable'=>(string)$r['cresponsable'],'estado'=>(string)$r['estado'],'base'=>$base,'days'=>$days,'importe'=>cc_money($r['valorprod_plantel']),'sem'=>cc_semaphore($days,$stage),'task_id'=>$r['tarea_id']?(int)$r['tarea_id']:null,'responsable_usuario_id'=>$effective?:null,'responsable_efectivo'=>$visible,'responsable_visible'=>$visible,'responsable_sigest'=>(string)$r['contrata_us'],'responsable_obra_efectivo'=>$visible,'responsable_obra_origen'=>$effective?'NEXO':'SIGEST','asignado_a'=>$visible,'asignado_a_origen'=>$effective?'TAREA':'SIGEST','puede_gestionar'=>cc_can_all($profile),'puede_asignar'=>cc_can_all($profile),'puede_transferir'=>!cc_can_all($profile)&&$effectiveEmail===$email,'gestionada'=>(string)$r['gestion_evento']==='GESTIONADA','gestion_fecha'=>$r['gestion_fecha']];}
    $realAll=cc_is_all_role($profile); $actorLegacy=cc_legacy_for_email($email);
    foreach($rows as &$row){$canTransfer=!$realAll && $actorLegacy!=='' && (string)($row['responsable_usuario_id']??'')===$actorLegacy; $row['puede_transferir']=$canTransfer; $row['can_transfer_task']=$canTransfer; $row['can_assign_task']=$realAll;} unset($row);
    $responsables=[];
    foreach ($pdo->query("SELECT id,email,nombre,apellido FROM usuarios WHERE estado='ACTIVO' ORDER BY nombre,apellido,email") as $user) {
        $display=trim((string)$user['nombre'].' '.(string)$user['apellido']) ?: (string)$user['email'];
        $responsables[]=['id'=>(int)$user['id'],'email'=>(string)$user['email'],'nombre'=>$display];
    }
    $seen = array_fill_keys(array_map(static fn(array $item): string => strtolower((string)$item['nombre']), $responsables), true);
    $legacyStmt = $pdo->query("SELECT DISTINCT usuario_nexo_id,usuario_nexo_nombre_snapshot FROM responsables_nexo WHERE activo=1 AND TRIM(usuario_nexo_nombre_snapshot)<>'' ORDER BY usuario_nexo_nombre_snapshot");
    foreach ($legacyStmt as $legacyRow) {
        $name = trim((string)$legacyRow['usuario_nexo_nombre_snapshot']);
        if ($name !== '' && !isset($seen[strtolower($name)])) {
            $responsables[] = ['id'=>(string)$legacyRow['usuario_nexo_id'], 'email'=>cc_email_for_legacy((string)$legacyRow['usuario_nexo_id']), 'nombre'=>$name, 'legacy_only'=>true];
            $seen[strtolower($name)] = true;
        }
    }
    return ['rows'=>$rows,'latest'=>$latest,'actor_id'=>(string)($profile['user']['id']??''),'actor_email'=>$email,'legacy_id'=>$legacy,'can_view_all'=>$canAll,'responsables'=>$responsables];
}
function cc_task(PDO $pdo,string $sigest,string $stage): ?array { $q=$pdo->prepare('SELECT id,sigest,etapa,estado_operativo FROM control_certificacion_tareas WHERE sigest=:sigest AND etapa=:etapa LIMIT 1');$q->execute(['sigest'=>$sigest,'etapa'=>$stage]);return $q->fetch()?:null; }
function cc_xlsx_col(int $index): string { $letters=''; while($index>0){$mod=($index-1)%26;$letters=chr(65+$mod).$letters;$index=intdiv($index-$mod,26);} return $letters; }
function cc_xlsx_cell(string $ref, mixed $value, bool $number=false): string { if($number && is_numeric($value)) return '<c r="'.$ref.'"><v>'.(string)(float)$value.'</v></c>'; return '<c r="'.$ref.'" t="inlineStr"><is><t>'.htmlspecialchars((string)($value??''),ENT_XML1|ENT_COMPAT,'UTF-8').'</t></is></c>'; }
function cc_xlsx_download(array $rows): never
{
    if (!class_exists('ZipArchive')) cc_fail('xlsx_unavailable','La exportación XLSX no está disponible en este servidor.',503);
    $headers=['SIGEST','ETAPA','TÍTULO','CENTRAL','ESTADO','RESPONSABLE','ANTIGÜEDAD','IMPORTE']; $xml=[]; $xml[]='<row r="1">'.implode('',array_map(static fn($h,$i)=>cc_xlsx_cell(cc_xlsx_col($i+1).'1',$h),$headers,array_keys($headers))).'</row>';
    foreach($rows as $index=>$row){$values=[$row['sigest'],$row['stage'],$row['titulo'],$row['central'],$row['estado'],$row['responsable_efectivo'],$row['days']===null?'':$row['days'],$row['importe']];$cells=[];foreach($values as $i=>$value)$cells[]=cc_xlsx_cell(cc_xlsx_col($i+1).($index+2),$value,$i===6||$i===7);$xml[]='<row r="'.($index+2).'">'.implode('',$cells).'</row>';}
    $last=cc_xlsx_col(count($headers)); $sheet='<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><dimension ref="A1:'.$last.max(1,count($rows)+1).'"/><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" state="frozen"/></sheetView></sheetViews><sheetData>'.implode('',$xml).'</sheetData><autoFilter ref="A1:'.$last.max(1,count($rows)+1).'"/></worksheet>'; $types='<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>'; $rels='<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>'; $wb='<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="OCRAS" sheetId="1" r:id="rId1"/></sheets></workbook>'; $wbr='<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>'; $tmp=tempnam(sys_get_temp_dir(),'ocras_');$zip=new ZipArchive();$zip->open($tmp,ZipArchive::CREATE|ZipArchive::OVERWRITE);$zip->addFromString('[Content_Types].xml',$types);$zip->addFromString('_rels/.rels',$rels);$zip->addFromString('xl/workbook.xml',$wb);$zip->addFromString('xl/_rels/workbook.xml.rels',$wbr);$zip->addFromString('xl/worksheets/sheet1.xml',$sheet);$zip->close();$data=file_get_contents($tmp);@unlink($tmp);header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');header('Content-Disposition: attachment; filename="control_certificacion_ocras.xlsx"');header('Cache-Control: no-store');echo $data;exit;
}

$action=trim((string)($_GET['accion']??''));$profile=(array)$profile;
if($action==='datos' && ($_SERVER['REQUEST_METHOD']??'GET')==='GET'){try{cc_response(cc_rows($profile));}catch(Throwable $e){error_log('control_certificacion_ocras datos: '.$e->getMessage());cc_fail('data_unavailable','No se pudo cargar el universo de certificación.',503);}}
if($action==='detalle' && ($_SERVER['REQUEST_METHOD']??'GET')==='GET'){ $sigest=trim((string)($_GET['sigest']??''));$stage=trim((string)($_GET['etapa']??''));if($sigest===''||$stage==='')cc_fail('invalid_request','Solicitud de detalle no válida.',400);$data=cc_rows($profile)['rows'];foreach($data as $row)if($row['sigest']===$sigest&&$row['stage']===$stage)cc_response(['data'=>$row,'management'=>['can_manage'=>cc_can_all($profile)]]);cc_fail('not_found','No se encontró una tarea autorizada.',404); }
if($action==='historial' && ($_SERVER['REQUEST_METHOD']??'GET')==='GET'){ $sigest=trim((string)($_GET['sigest']??''));$stage=trim((string)($_GET['etapa']??''));if($sigest===''||$stage===''||!cc_authorized_task($profile,$sigest,$stage))cc_fail('forbidden','No tenés permiso sobre esta tarea.',403);$stmt=cc_db()->prepare('SELECT tipo_evento,datos_json,created_at FROM control_certificacion_eventos WHERE sigest=:s AND etapa=:e ORDER BY id DESC LIMIT 100');$stmt->execute(['s'=>$sigest,'e'=>$stage]);$items=[];foreach($stmt as $item)$items[]=['tipo_evento'=>$item['tipo_evento'],'datos'=>json_decode((string)$item['datos_json'],true)?:[],'created_at'=>$item['created_at']];cc_response(['sigest'=>$sigest,'etapa'=>$stage,'items'=>$items]); }
if($action==='exportar' && ($_SERVER['REQUEST_METHOD']??'GET')==='POST'){cc_csrf_remote($token);$all=cc_rows($profile)['rows'];$requested=json_decode((string)($_POST['tareas']??'[]'),true);if(is_array($requested)&&$requested!==[]){$keys=[];foreach($requested as $item)$keys[trim((string)($item['sigest']??'')).'|'.trim((string)($item['etapa']??''))]=true;$all=array_values(array_filter($all,static fn(array $row): bool=>isset($keys[$row['sigest'].'|'.$row['stage']])));}cc_xlsx_download($all);}
if($action==='asignar' && ($_SERVER['REQUEST_METHOD']??'GET')==='POST'){cc_csrf_remote($token);if(!cc_can_all($profile))cc_fail('forbidden','No tenés permiso para asignar tareas.',403);$tasks=json_decode((string)($_POST['tareas']??'[]'),true);$dest=trim((string)($_POST['usuario_destino_id']??''));if(!is_array($tasks)||$tasks===[])cc_fail('invalid_request','Faltan tareas o responsable.',400);$operation=trim((string)($_POST['operacion']??'asignar'));if($operation!=='devolver'&&$dest==='')cc_fail('invalid_request','Faltan tareas o responsable.',400);$pdo=cc_db();$pdo->beginTransaction();$now=(new DateTimeImmutable('now',new DateTimeZone('UTC')))->format('Y-m-d H:i:s');$updates=[];try{$legacy=cc_legacy_for_email($dest);if($operation==='devolver'){$legacy='';}elseif($legacy===''){$q=$pdo->prepare('SELECT email FROM usuarios WHERE id=:id AND estado="ACTIVO" LIMIT 1');$q->execute(['id'=>(int)$dest]);$legacy=cc_legacy_for_email((string)$q->fetchColumn());}foreach($tasks as $task){$s=trim((string)($task['sigest']??''));$stage=trim((string)($task['etapa']??''));if($s===''||$stage===''||!cc_authorized_task($profile,$s,$stage))cc_fail('forbidden','No tenés permiso sobre una o más tareas seleccionadas.',403);if($operation==='devolver'){$raw=$pdo->prepare('SELECT contrata_us FROM raw_certificacion_ocras WHERE TRIM(csisvadi)=:s ORDER BY fecha_importacion DESC LIMIT 1');$raw->execute(['s'=>$s]);$legacy=cc_legacy_for_email((string)$raw->fetchColumn());if($legacy==='')cc_fail('invalid_destination','La devolución no tiene responsable de origen vinculado.',422);}if($legacy==='')cc_fail('invalid_destination','El responsable no está vinculado a la identidad central.',422);$t=cc_task($pdo,$s,$stage);if(!$t){$ins=$pdo->prepare("INSERT INTO control_certificacion_tareas(sigest,etapa,estado_fuente,estado_operativo,created_at,updated_at) VALUES(:s,:e,'PENDIENTE','PENDIENTE',:n1,:n2)");$ins->execute(['s'=>$s,'e'=>$stage,'n1'=>$now,'n2'=>$now]);$t=cc_task($pdo,$s,$stage);}if(!$t)continue;$pdo->prepare('INSERT INTO control_certificacion_asignaciones(tarea_id,tipo,responsable_usuario_id,usuario_origen_id,usuario_destino_id,motivo,created_at) VALUES(?,"REASIGNACION",?,?,?,?,?)')->execute([$t['id'],$legacy,(string)($profile['user']['id']??''),$legacy,trim((string)($_POST['motivo']??'')),$now]);$updates[]=['sigest'=>$s,'stage'=>$stage,'responsable_usuario_id'=>$legacy,'responsable_efectivo'=>cc_email_for_legacy($legacy)];}$pdo->commit();cc_response(['message'=>'Asignación completada.','tareas'=>$updates]);}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('control_certificacion_ocras asignar: '.$e->getMessage());cc_fail('write_failed','No se pudo completar la asignación.',500);}}
if(in_array($action,['gestionar','desgestionar'],true)&&($_SERVER['REQUEST_METHOD']??'GET')==='POST'){cc_csrf_remote($token);$s=trim((string)($_POST['sigest']??''));$stage=trim((string)($_POST['etapa']??''));if($s===''||$stage==='')cc_fail('invalid_request','Faltan datos de la tarea.',400);if(!cc_authorized_task($profile,$s,$stage))cc_fail('forbidden','No tenés permiso sobre esta tarea.',403);$pdo=cc_db();$t=cc_task($pdo,$s,$stage);if(!$t){$now=gmdate('Y-m-d H:i:s');$pdo->prepare("INSERT INTO control_certificacion_tareas(sigest,etapa,estado_fuente,estado_operativo,created_at,updated_at) VALUES(:s,:e,'PENDIENTE','PENDIENTE',:n1,:n2)")->execute(['s'=>$s,'e'=>$stage,'n1'=>$now,'n2'=>$now]);$t=cc_task($pdo,$s,$stage);}if(!$t)cc_fail('not_found','No se encontró la tarea.',404);$now=gmdate('Y-m-d H:i:s');$type=$action==='gestionar'?'GESTIONADA':'GESTION_DESMARCADA';$pdo->prepare('INSERT INTO control_certificacion_eventos(tarea_id,sigest,etapa,tipo_evento,usuario_id,registrado_por_usuario_id,registrado_por_nombre_snapshot,created_at) VALUES(:t,:s,:e,:ty,:u1,:u2,:n,:c)')->execute(['t'=>$t['id'],'s'=>$s,'e'=>$stage,'ty'=>$type,'u1'=>(string)($profile['user']['id']??''),'u2'=>(string)($profile['user']['id']??''),'n'=>trim((string)($profile['user']['nombre']??'')),'c'=>$now]);cc_response(['message'=>$action==='gestionar'?'Gestión registrada.':'Gestión desmarcada.','tarea'=>['sigest'=>$s,'stage'=>$stage,'gestionada'=>$action==='gestionar']]);}
if($action==='novedades'&&($_SERVER['REQUEST_METHOD']??'GET')==='GET'){ $s=trim((string)($_GET['sigest']??''));$stage=trim((string)($_GET['etapa']??''));if($s===''||$stage===''||!cc_authorized_task($profile,$s,$stage))cc_fail('forbidden','No tenés permiso sobre esta tarea.',403);$pdo=cc_db();$q=$pdo->prepare('SELECT id,tarea_id,sigest,etapa,usuario_id,usuario_nombre_snapshot,tipo,texto,estado,resuelto_por,resuelto_por_nombre,resuelto_en,created_at FROM control_certificacion_novedades WHERE sigest=:s AND etapa=:e ORDER BY id ASC');$q->execute(['s'=>$s,'e'=>$stage]);cc_response(['task'=>['sigest'=>$s,'etapa'=>$stage],'novedades'=>$q->fetchAll()]);}
if($action==='novedad_guardar'&&($_SERVER['REQUEST_METHOD']??'GET')==='POST'){cc_csrf_remote($token);$s=trim((string)($_POST['sigest']??''));$stage=trim((string)($_POST['etapa']??''));$text=trim((string)($_POST['texto']??''));if($s===''||$stage===''||$text==='')cc_fail('invalid_request','El comentario no puede estar vacío.',400);if(!cc_authorized_task($profile,$s,$stage))cc_fail('forbidden','No tenés permiso sobre esta tarea.',403);$pdo=cc_db();$t=cc_task($pdo,$s,$stage);if(!$t)cc_fail('not_found','No se encontró la tarea.',404);$now=gmdate('Y-m-d H:i:s');$pdo->prepare('INSERT INTO control_certificacion_novedades(tarea_id,sigest,etapa,usuario_id,usuario_nombre_snapshot,tipo,texto,estado,created_at) VALUES(:t,:s,:e,:u,:n,:ty,:x,"PENDIENTE",:c)')->execute(['t'=>$t['id'],'s'=>$s,'e'=>$stage,'u'=>(string)($profile['user']['id']??''),'n'=>trim((string)($profile['user']['nombre']??'')),'ty'=>trim((string)($_POST['tipo']??'INFORMATIVA')),'x'=>$text,'c'=>$now]);cc_response(['message'=>'Novedad guardada.']);}
if($action!=='')cc_fail('not_found','Acción no encontrada.',404);
cc_fail('method_not_allowed','Método no permitido.',405);
