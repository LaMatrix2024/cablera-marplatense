# Proyección de altas — fuente TOA

Estado: preparación local de Etapa A. No desplegado ni conectado a NEXO.

`_source.php` contiene las consultas parametrizadas previstas para leer
`bajada_toa` y `bajada_toa_importaciones` desde la base principal Plantel.

Antes de activarlo se debe:

1. aprobar y aplicar la migración `20261012_001_bajada_toa_up.sql`;
2. ejecutar y conciliar una carga inicial autorizada;
3. definir el endpoint autenticado que envolverá estas consultas;
4. comparar resultados contra la fuente vigente con el mismo período y filtros;
5. cambiar el consumidor NEXO y conservar un rollback hacia la fuente anterior.

La vista no debe consultar `bajada_toa_staging`.
