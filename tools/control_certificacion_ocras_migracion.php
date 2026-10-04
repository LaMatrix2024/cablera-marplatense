<?php
declare(strict_types=1);

/**
 * Migración OCRAS Laboratorio <-> Plantel.
 * El modo predeterminado es --plan y no escribe. La escritura requiere
 * --apply (o --reverse) y --confirm. Cada ejecución crea un backup lógico
 * del destino antes de modificarlo y detiene la transacción ante conflictos.
 */
const OCRAS_TABLES = [
    'raw_certificacion_ocras', 'raw_bigstorm_precios', 'automatizaciones_estado',
    'responsables_nexo', 'nexo_usuarios_vinculaciones_operativas',
    'control_certificacion_tareas', 'control_certificacion_asignaciones',
    'control_certificacion_eventos', 'control_certificacion_fuente_estado',
    'control_certificacion_historial_operativo', 'control_certificacion_monitores',
    'control_certificacion_novedades', 'control_certificacion_sobrestantes',
    'control_certificacion_comunicaciones', 'control_certificacion_snapshots',
];
const FALLBACK_KEYS = [
    'raw_certificacion_ocras' => ['archivo_hash', 'hoja_origen', 'fila_origen'],
    'raw_bigstorm_precios' => ['source_hash'],
    'automatizaciones_estado' => ['nombre_automatizacion'],
    'control_certificacion_tareas' => ['sigest', 'etapa'],
    'responsables_nexo' => ['id'], 'nexo_usuarios_vinculaciones_operativas' => ['id'],
];

function loadEnv(string $path): array {
    $out = [];
    if (!is_readable($path)) return $out;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2); $value = trim($value);
        if (strlen($value) >= 2 && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) $value = substr($value, 1, -1);
        $out[trim($key)] = $value;
    }
    return $out;
}
function args(): array {
    $out = ['mode'=>'plan','confirm'=>false,'backup-dir'=>null,'cutover-utc'=>null,'source-schema'=>null,'destination-schema'=>null];
    foreach (array_slice($GLOBALS['argv'], 1) as $arg) {
        if ($arg === '--plan') $out['mode']='plan'; elseif ($arg === '--apply') $out['mode']='apply'; elseif ($arg === '--reverse') $out['mode']='reverse'; elseif ($arg === '--confirm') $out['confirm']=true;
        elseif (str_starts_with($arg,'--')) { [$key,$value]=array_pad(explode('=',substr($arg,2),2),2,null); if (array_key_exists($key,$out)) $out[$key]=$value; }
    }
    return $out;
}
function ident(string $value): string { return '`'.str_replace('`','``',$value).'`'; }
function tableRef(string $schema,string $table): string { return ident($schema).'.'.ident($table); }
function connect(array $env,string $prefix,?string $schema=null): PDO {
    $value=static function(string $name,string $default='') use($env,$prefix):string { $override=getenv($prefix.'_'.$name); return (string)($override!==false?$override:($env[$prefix.'_'.$name]??$default)); };
    $database=$schema??$value('DATABASE'); $dsn=sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',$value('HOST','127.0.0.1'),$value('PORT','3306'),$database);
    return new PDO($dsn,$value('USER'),$value('PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
}
function hasTable(PDO $pdo,string $schema,string $table):bool { $q=$pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=:s AND table_name=:t');$q->execute(['s'=>$schema,'t'=>$table]);return (int)$q->fetchColumn()===1; }
function showCreate(PDO $pdo,string $schema,string $table):string { $row=$pdo->query('SHOW CREATE TABLE '.tableRef($schema,$table))->fetch(PDO::FETCH_NUM);return (string)($row[1]??''); }
function keyColumns(PDO $pdo,string $schema,string $table):array {
    if (isset(FALLBACK_KEYS[$table])) return FALLBACK_KEYS[$table];
    $keys=[];foreach($pdo->query('SHOW KEYS FROM '.tableRef($schema,$table).' WHERE Key_name="PRIMARY"') as $row)$keys[(int)$row['Seq_in_index']]=(string)$row['Column_name'];ksort($keys);return $keys!==[]?array_values($keys):(FALLBACK_KEYS[$table]??['id']);
}
function keyOf(array $row,array $keys):string { return json_encode(array_map(static fn(string $key):string=>array_key_exists($key,$row)?(string)$row[$key]:'__MISSING__',$keys),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); }
function fingerprint(array $row):string { return hash('sha256',json_encode($row,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION)); }
function rowTimestamp(array $row):?DateTimeImmutable { foreach(['updated_at','ultima_fecha_hora_actualizacion','created_at','fecha_hora','resuelto_en'] as $field){if(empty($row[$field]))continue;try{return new DateTimeImmutable((string)$row[$field],new DateTimeZone('UTC'));}catch(Throwable){}}return null; }
function backupDestination(PDO $destination,string $schema,array $tables,string $dir):array {
    if(!is_dir($dir)&&!mkdir($dir,0700,true))throw new RuntimeException('No se pudo crear el backup fuera del repositorio.');$manifest=['created_at_utc'=>gmdate('c'),'schema'=>$schema,'tables'=>[]];
    foreach($tables as $table){if(!hasTable($destination,$schema,$table)){$manifest['tables'][$table]=['present'=>false];continue;}$create=showCreate($destination,$schema,$table);file_put_contents($dir.DIRECTORY_SEPARATOR.$table.'.create.sql',$create.";\n",LOCK_EX);$fh=fopen($dir.DIRECTORY_SEPARATOR.$table.'.jsonl','wb');foreach($destination->query('SELECT * FROM '.tableRef($schema,$table)) as $row)fwrite($fh,json_encode($row,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");fclose($fh);$manifest['tables'][$table]=['present'=>true,'rows'=>(int)$destination->query('SELECT COUNT(*) FROM '.tableRef($schema,$table))->fetchColumn(),'schema_sha256'=>hash('sha256',$create)];}
    file_put_contents($dir.DIRECTORY_SEPARATOR.'manifest.json',json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n",LOCK_EX);return $manifest;
}
function copyTable(PDO $source,PDO $destination,string $sourceSchema,string $destinationSchema,string $table,DateTimeImmutable $cutover,bool $write):array {
    if(!hasTable($source,$sourceSchema,$table))return['status'=>'source_absent','source_rows'=>0,'destination_rows'=>hasTable($destination,$destinationSchema,$table)?(int)$destination->query('SELECT COUNT(*) FROM '.tableRef($destinationSchema,$table))->fetchColumn():0,'inserted'=>0,'updated'=>0,'unchanged'=>0,'conflicts'=>[],'destination_only'=>0];
    if(!hasTable($destination,$destinationSchema,$table)){if(!$write)return['status'=>'destination_missing','source_rows'=>(int)$source->query('SELECT COUNT(*) FROM '.tableRef($sourceSchema,$table))->fetchColumn(),'destination_rows'=>0,'inserted'=>0,'updated'=>0,'unchanged'=>0,'conflicts'=>[],'destination_only'=>0];$destination->exec('CREATE TABLE '.tableRef($destinationSchema,$table).' LIKE '.tableRef($sourceSchema,$table));}
    $sourceRows=$source->query('SELECT * FROM '.tableRef($sourceSchema,$table))->fetchAll();$destinationRows=$destination->query('SELECT * FROM '.tableRef($destinationSchema,$table))->fetchAll();$keys=keyColumns($source,$sourceSchema,$table);$bySource=[];foreach($sourceRows as $row)$bySource[keyOf($row,$keys)]=$row;$byDestination=[];foreach($destinationRows as $row)$byDestination[keyOf($row,$keys)]=$row;$result=['status'=>'ok','source_rows'=>count($sourceRows),'destination_rows'=>count($destinationRows),'inserted'=>0,'updated'=>0,'unchanged'=>0,'conflicts'=>[],'destination_only'=>0,'keys'=>$keys];$columns=array_keys($sourceRows[0]??[]);if($columns===[])return$result;
    foreach($bySource as $key=>$row){$current=$byDestination[$key]??null;if($current===null){$result['inserted']++;if($write){$names=implode(',',array_map('ident',$columns));$params=implode(',',array_fill(0,count($columns),'?'));$destination->prepare('INSERT INTO '.tableRef($destinationSchema,$table).' ('.$names.') VALUES ('.$params.')')->execute(array_values($row));}continue;}if(fingerprint($row)===fingerprint($current)){$result['unchanged']++;continue;}$sourceWhen=rowTimestamp($row);$destinationWhen=rowTimestamp($current);$sourceChanged=$sourceWhen!==null&&$sourceWhen>$cutover;$destinationChanged=$destinationWhen!==null&&$destinationWhen>$cutover;if($destinationChanged&&$sourceChanged){$result['conflicts'][]=['key'=>json_decode($key,true),'reason'=>'ambos_lados_modificados_despues_del_corte'];continue;}if($destinationChanged){$result['conflicts'][]=['key'=>json_decode($key,true),'reason'=>'destino_modificado_despues_del_corte'];continue;}if($sourceChanged&&$destinationWhen===null){$result['conflicts'][]=['key'=>json_decode($key,true),'reason'=>'destino_sin_fecha_de_cambio'];continue;}if(!$sourceChanged&&$destinationWhen===null){$result['conflicts'][]=['key'=>json_decode($key,true),'reason'=>'destino_sin_fecha_de_cambio'];continue;}$result['updated']++;if($write){$set=[];$values=[];foreach($columns as $column)if(!in_array($column,$keys,true)){$set[]=ident($column).'=?';$values[]=$row[$column];}if($set!==[]){foreach($keys as $column)$values[]=$row[$column];$where=implode(' AND ',array_map(static fn(string $column):string=>ident($column).'=?',$keys));$destination->prepare('UPDATE '.tableRef($destinationSchema,$table).' SET '.implode(',',$set).' WHERE '.$where)->execute($values);}}}
    $result['destination_only']=count(array_diff_key($byDestination,$bySource));return$result;
}
function relationChecks(PDO $source, PDO $destination, string $sourceSchema, string $destinationSchema): array {
    $issues = [];
    if (!hasTable($destination, $destinationSchema, 'control_certificacion_tareas')) return $issues;
    foreach (['control_certificacion_asignaciones', 'control_certificacion_eventos', 'control_certificacion_novedades'] as $child) {
        if (!hasTable($destination, $destinationSchema, $child)) continue;
        $sql = 'SELECT COUNT(*) FROM '.tableRef($destinationSchema, $child).' c LEFT JOIN '.tableRef($destinationSchema, 'control_certificacion_tareas').' t ON t.id=c.tarea_id WHERE c.tarea_id IS NOT NULL AND t.id IS NULL';
        $orphans = (int)$destination->query($sql)->fetchColumn();
        if ($orphans > 0) $issues[] = ['type'=>'orphan_child_rows','table'=>$child,'count'=>$orphans];
    }
    if (!hasTable($source, $sourceSchema, 'control_certificacion_tareas')) return $issues;
    $sourceTasks = [];
    foreach ($source->query('SELECT id,sigest,etapa FROM '.tableRef($sourceSchema, 'control_certificacion_tareas')) as $row) $sourceTasks[(string)$row['id']] = (string)$row['sigest'].'|'.(string)$row['etapa'];
    $destinationTasks = [];
    foreach ($destination->query('SELECT id,sigest,etapa FROM '.tableRef($destinationSchema, 'control_certificacion_tareas')) as $row) $destinationTasks[(string)$row['sigest'].'|'.(string)$row['etapa']] = (string)$row['id'];
    foreach (['control_certificacion_asignaciones', 'control_certificacion_eventos', 'control_certificacion_novedades'] as $child) {
        if (!hasTable($source, $sourceSchema, $child)) continue;
        foreach ($source->query('SELECT tarea_id FROM '.tableRef($sourceSchema, $child).' WHERE tarea_id IS NOT NULL') as $row) {
            $semantic = $sourceTasks[(string)$row['tarea_id']] ?? null;
            if ($semantic !== null && isset($destinationTasks[$semantic]) && $destinationTasks[$semantic] !== (string)$row['tarea_id']) $issues[] = ['type'=>'task_id_mapping_required','table'=>$child,'source_task_id'=>(string)$row['tarea_id'],'destination_task_id'=>$destinationTasks[$semantic],'semantic_key'=>$semantic];
        }
    }
    return $issues;
}
function main():int {
    $opts=args();if(!in_array($opts['mode'],['plan','apply','reverse'],true))throw new InvalidArgumentException('Modo inválido.');if($opts['mode']!=='plan'&&!$opts['confirm'])throw new InvalidArgumentException('La escritura requiere --confirm.');if($opts['mode']!=='plan'&&!$opts['cutover-utc'])throw new InvalidArgumentException('La escritura requiere --cutover-utc.');$cutover=$opts['cutover-utc']?new DateTimeImmutable((string)$opts['cutover-utc'],new DateTimeZone('UTC')):new DateTimeImmutable('now',new DateTimeZone('UTC'));$env=loadEnv(dirname(__DIR__,2).DIRECTORY_SEPARATOR.'DATOS_LOCALES'.DIRECTORY_SEPARATOR.'plantel.env');$sourceSchema=$opts['source-schema']?:((string)($env['DB_HOSTINGER_LAB_DATABASE']??'u767019378_laboratorio'));$destinationSchema=$opts['destination-schema']?:((string)($env['DB_HOSTINGER_PLANTEL_DATABASE']??'u767019378_plantel'));$source=connect($env,'DB_HOSTINGER_LAB',$sourceSchema);$destination=connect($env,'DB_HOSTINGER_PLANTEL',$destinationSchema);if($opts['mode']==='reverse'){[$source,$destination]=[$destination,$source];[$sourceSchema,$destinationSchema]=[$destinationSchema,$sourceSchema];}$backup=null;if($opts['mode']!=='plan'){$dir=(string)($opts['backup-dir']??'');$resolved=strtolower(realpath($dir)?:$dir);if($dir===''||str_contains($resolved,strtolower(dirname(__DIR__))))throw new InvalidArgumentException('--backup-dir debe ser una ruta existente fuera del repositorio.');$backup=backupDestination($destination,$destinationSchema,OCRAS_TABLES,$dir);$destination->beginTransaction();}$report=['mode'=>$opts['mode'],'cutover_utc'=>$cutover->format('c'),'source_schema'=>$sourceSchema,'destination_schema'=>$destinationSchema,'backup'=>$backup,'tables'=>[]];
    try{foreach(OCRAS_TABLES as $table)$report['tables'][$table]=copyTable($source,$destination,$sourceSchema,$destinationSchema,$table,$cutover,$opts['mode']!=='plan');$conflicts=[];foreach($report['tables'] as $table=>$data)foreach($data['conflicts']??[] as $conflict)$conflicts[]=['table'=>$table]+$conflict;$report['relation_issues']=relationChecks($source,$destination,$sourceSchema,$destinationSchema);foreach($report['relation_issues'] as $issue)$conflicts[]=['table'=>$issue['table']??'relaciones']+$issue;$report['conflicts']=$conflicts;if($opts['mode']!=='plan'&&$conflicts!==[]){$destination->rollBack();$report['rolled_back']=true;throw new RuntimeException('Se detectaron conflictos o relaciones incompatibles; no se aplicó ninguna escritura.');}if($opts['mode']!=='plan')$destination->commit();}catch(Throwable $error){if($opts['mode']!=='plan'&&$destination->inTransaction())$destination->rollBack();$report['error']=$error->getMessage();echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";return$opts['mode']==='plan'?1:2;}echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";return 0;
}
try{exit(main());}catch(Throwable $error){fwrite(STDERR,$error->getMessage()."\n");exit(2);}
