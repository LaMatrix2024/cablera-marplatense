-- Rollback de infraestructura de identidad corporativa.
-- Ejecutar solo si la migracion no fue integrada por sistemas posteriores.

DROP TABLE IF EXISTS usuario_modulo;
DROP TABLE IF EXISTS identidad_conflictos;
DROP TABLE IF EXISTS rol_modulo;
DROP TABLE IF EXISTS usuario_rol;
DROP TABLE IF EXISTS usuario_aplicacion;
DROP TABLE IF EXISTS roles;
DROP TABLE IF EXISTS modulos;
DROP TABLE IF EXISTS aplicaciones;
DROP TABLE IF EXISTS usuarios;
