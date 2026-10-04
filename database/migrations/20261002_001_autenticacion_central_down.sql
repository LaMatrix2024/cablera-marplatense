-- Reversión únicamente si no existen sesiones activas dependientes de estas tablas.
DROP TABLE IF EXISTS auth_rate_limits;
DROP TABLE IF EXISTS auth_sesiones;
ALTER TABLE usuarios
    DROP COLUMN sesion_version,
    DROP COLUMN password_hash;
