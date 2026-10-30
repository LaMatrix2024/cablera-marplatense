<?php
declare(strict_types=1);
require_once __DIR__ . '/_common.php';

// Interfaz privada NEXO -> La Cablera. No habilitar sin clave de servidor.
$secret=(string)lcm_config_value('NEXO_FTTH_BRIDGE_KEY','');
$provided=(string)($_SERVER['HTTP_X_NEXO_FTTH_KEY']??'');
if(strlen($secret)<32||!hash_equals($secret,$provided)){api_json(['ok'=>false,'error'=>'No autorizado'],403);}
$pdo=api_database();
$allowed=['tipo_poli','avance_linea','hub_proy','hub_inst','cto_proy','cto_inst','observaciones','acceso','pelo','adefo','manzanas','uips','postes_reemplazo','postes_colocar','mt_cruces','mt_fibra'];
if($_SERVER['REQUEST_METHOD']==='GET'){
 $stmt=$pdo->query('SELECT `sisvadi`,'.implode(',',array_map(fn($c)=>'`'.$c.'`',$allowed)).' FROM `tma_despliegue`');
 $rows=[];foreach($stmt as $r){$rows[(string)$r['sisvadi']]=$r;}
 api_json(['ok'=>true,'rows'=>$rows,'total'=>count($rows)]);
}
if($_SERVER['REQUEST_METHOD']!=='POST')api_json(['ok'=>false,'error'=>'Método inválido'],405);
$body=api_input();$id=trim((string)($body['sigest']??''));$changes=$body['values']??null;
if(!preg_match('/^[0-9]{5,20}$/',$id)||!is_array($changes)||!$changes||array_diff(array_keys($changes),$allowed))api_json(['ok'=>false,'error'=>'Campos inválidos'],422);
$numeric=['avance_linea','hub_proy','hub_inst','cto_proy','cto_inst','mt_cruces','mt_fibra'];
foreach($changes as $k=>$v){
 if(!is_scalar($v)&&$v!==null)api_json(['ok'=>false,'error'=>'Tipo inválido'],422);
 if($v!==null&&strlen((string)$v)>2000)api_json(['ok'=>false,'error'=>'Valor excesivo'],422);
 if(in_array($k,$numeric,true)&&$v!==null&&$v!==''&&!is_numeric($v))api_json(['ok'=>false,'error'=>'Número inválido'],422);
}
$columns=array_keys($changes);$sql='UPDATE `tma_despliegue` SET '.implode(',',array_map(fn($c)=>'`'.$c.'` = ?', $columns)).' WHERE `sisvadi` = ?';
$stmt=$pdo->prepare($sql);$stmt->execute([...array_values(array_map(fn($v)=>$v===''?null:$v,$changes)),$id]);
$check=$pdo->prepare('SELECT `sisvadi` FROM `tma_despliegue` WHERE `sisvadi`=?');$check->execute([$id]);
if(!$check->fetch())api_json(['ok'=>false,'error'=>'SIGEST no cargado en tma_despliegue'],404);
api_json(['ok'=>true,'sigest'=>$id,'values'=>$changes]);
