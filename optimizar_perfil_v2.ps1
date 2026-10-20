$ErrorActionPreference = 'Stop'
$root = 'C:\plantel\cablera-marplatense'
$target = Join-Path $root 'shared\auth\CorporateInvitationService.php'
if (!(Test-Path $target)) { throw "No se encuentra $target" }
Set-Location $root
$original = [System.IO.File]::ReadAllText($target)
$backup = "$target.respaldo-optimizacion"
if (Test-Path $backup) { throw "Ya existe respaldo: $backup. No se modifico nada." }
$startMarker = '        $repo = new CorporateAccessRepository($this->pdo);'
$endMarker = '        unset($module);'
$start = $original.IndexOf($startMarker, [StringComparison]::Ordinal)
$end = $original.IndexOf($endMarker, [StringComparison]::Ordinal)
if ($start -lt 0 -or $end -le $start -or $original.IndexOf($startMarker, $start + 1, [StringComparison]::Ordinal) -ge 0) { throw 'No coincide el bloque esperado; no se modifico nada.' }
$end += $endMarker.Length
$baseline = Join-Path $env:TEMP 'lcm_perfiles_baseline.json'
$afterFile = $null
$check = Join-Path $env:TEMP 'lcm_verificar_perfiles.php'
$phpCheck = @'
<?php
declare(strict_types=1);
require getcwd() . '/api/v1/central-auth/bootstrap.php';
if ($argc < 2) { fwrite(STDERR, "Falta archivo de salida\n"); exit(2); }
$pdo = api_database();
// Una sola cuenta activa: preferir usuario normal para probar roles y excepciones.
$id = $pdo->query("SELECT id FROM usuarios WHERE estado = 'ACTIVO' ORDER BY es_superadmin ASC, id ASC LIMIT 1")->fetchColumn();
if ($id === false) { fwrite(STDERR, "No hay usuarios activos\n"); exit(2); }
$start = microtime(true);
$profile = (new CorporateInvitationService($pdo))->authenticatedProfile((int)$id);
$elapsed = round(microtime(true) - $start, 2);
$results = [(string)$id => $profile];
file_put_contents($argv[1], json_encode($results, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
echo "Perfiles: 1 | Tiempo: {$elapsed} s | Modulos visibles: " . count($profile['modules'] ?? []) . "\n";
'@
[System.IO.File]::WriteAllText($check, $phpCheck, [System.Text.UTF8Encoding]::new($false))
try {
    Write-Host '1. Midiendo y guardando permisos actuales (puede tardar)...' -ForegroundColor Cyan
    & php $check $baseline
    if ($LASTEXITCODE -ne 0 -or !(Test-Path $baseline)) { throw 'Fallo la medicion inicial.' }
    $baselineJson = [System.IO.File]::ReadAllText($baseline)
    Write-Host '2. Creando respaldo y aplicando optimizacion...' -ForegroundColor Cyan
    [System.IO.File]::WriteAllText($backup, $original, [System.Text.UTF8Encoding]::new($false))
    $replacement = @'
        // Perfil: calcular permisos en lote, sin consultas por modulo.
        $authorizedModules = [];
        $keyOf = static fn(string $app, string $module): string => json_encode([$app, $module]);
        $roleRowsByModule = [];
        $exceptionsByModule = [];

        if (!$isSuperadmin && CorporateAccess::isActiveUser($user) && $moduleRows !== []) {
            $roleStmt = $this->pdo->prepare(
                'SELECT a.codigo AS aplicacion, m.codigo AS modulo,
                        rm.puede_ver, rm.puede_crear, rm.puede_editar,
                        rm.puede_eliminar, rm.puede_exportar, rm.puede_aprobar
                 FROM usuario_rol ur
                 INNER JOIN roles r ON r.id = ur.rol_id
                    AND r.aplicacion_id = ur.aplicacion_id AND r.activo = 1
                 INNER JOIN rol_modulo rm ON rm.rol_id = r.id
                    AND rm.aplicacion_id = r.aplicacion_id
                 INNER JOIN modulos m ON m.id = rm.modulo_id
                    AND m.aplicacion_id = ur.aplicacion_id AND m.activo = 1
                 INNER JOIN aplicaciones a ON a.id = ur.aplicacion_id AND a.activo = 1
                 INNER JOIN usuario_aplicacion ua ON ua.usuario_id = ur.usuario_id
                    AND ua.aplicacion_id = ur.aplicacion_id AND ua.activo = 1
                 WHERE ur.usuario_id = :usuario_id AND ur.activo = 1'
            );
            $roleStmt->execute(['usuario_id' => $usuarioId]);
            foreach ($roleStmt->fetchAll() as $row) {
                $key = $keyOf((string)$row['aplicacion'], (string)$row['modulo']);
                $roleRowsByModule[$key][] = $row;
            }

            $exceptionStmt = $this->pdo->prepare(
                'SELECT a.codigo AS aplicacion, m.codigo AS modulo,
                        um.puede_ver, um.puede_crear, um.puede_editar,
                        um.puede_eliminar, um.puede_exportar, um.puede_aprobar
                 FROM usuario_modulo um
                 INNER JOIN aplicaciones a ON a.id = um.aplicacion_id AND a.activo = 1
                 INNER JOIN modulos m ON m.id = um.modulo_id
                    AND m.aplicacion_id = a.id AND m.activo = 1
                 INNER JOIN usuario_aplicacion ua ON ua.usuario_id = um.usuario_id
                    AND ua.aplicacion_id = um.aplicacion_id AND ua.activo = 1
                 WHERE um.usuario_id = :usuario_id'
            );
            $exceptionStmt->execute(['usuario_id' => $usuarioId]);
            foreach ($exceptionStmt->fetchAll() as $row) {
                $key = $keyOf((string)$row['aplicacion'], (string)$row['modulo']);
                // Equivalente a LIMIT 1 en condiciones de unicidad usuario/aplicacion/modulo.
                if (!isset($exceptionsByModule[$key])) {
                    $exceptionsByModule[$key] = $row;
                }
            }
        }

        $activeApps = [];
        foreach ($appsRows as $app) {
            if ((int)($app['activo'] ?? 0) === 1) {
                $activeApps[(string)$app['codigo']] = true;
            }
        }
        foreach ($moduleRows as $module) {
            $key = $keyOf((string)$module['aplicacion'], (string)$module['codigo']);
            if (!CorporateAccess::isActiveUser($user)) {
                $permissions = CorporateAccess::emptyPermissions();
            } elseif ($isSuperadmin) {
                $permissions = CorporateAccess::fullPermissions();
            } elseif (!isset($activeApps[(string)$module['aplicacion']])) {
                $permissions = CorporateAccess::emptyPermissions();
            } else {
                $permissions = CorporateAccess::combineRolePermissions($roleRowsByModule[$key] ?? []);
                $permissions = CorporateAccess::applyUserException($permissions, $exceptionsByModule[$key] ?? null);
            }
            $module['permissions'] = $permissions;
            if ($permissions['puede_ver'] === true) {
                $authorizedModules[] = $module;
            }
        }
'@
    $updated = $original.Substring(0, $start) + $replacement + $original.Substring($end)
    [System.IO.File]::WriteAllText($target, $updated, [System.Text.UTF8Encoding]::new($false))
    Write-Host '3. Validando sintaxis...' -ForegroundColor Cyan
    & php -l $target
    if ($LASTEXITCODE -ne 0) { throw 'Error de sintaxis PHP.' }
    Write-Host '4. Comparando perfiles y permisos...' -ForegroundColor Cyan
    $afterFile = Join-Path $env:TEMP 'lcm_perfiles_after.json'
    & php $check $afterFile
    if ($LASTEXITCODE -ne 0 -or !(Test-Path $afterFile)) { throw 'Fallo el perfil optimizado.' }
    $afterJson = [System.IO.File]::ReadAllText($afterFile)
    $old = $baselineJson | ConvertFrom-Json
    $new = $afterJson | ConvertFrom-Json
    $oldCanonical = $old | ConvertTo-Json -Depth 100 -Compress
    $newCanonical = $new | ConvertTo-Json -Depth 100 -Compress
    if ($oldCanonical -cne $newCanonical) { throw 'Los perfiles no coinciden exactamente.' }
    Write-Host 'VALIDACION OK: perfiles y permisos identicos.' -ForegroundColor Green
    Write-Host 'Optimizacion local aplicada. NO se modifico GitHub ni Hostinger.' -ForegroundColor Green
    Write-Host "Respaldo: $backup"
} catch {
    if (Test-Path $backup) {
        [System.IO.File]::WriteAllText($target, $original, [System.Text.UTF8Encoding]::new($false))
        Write-Host 'Se restauro automaticamente el archivo original.' -ForegroundColor Yellow
    }
    Write-Host "ERROR: $_" -ForegroundColor Red
    exit 1
} finally {
    Remove-Item $check -ErrorAction SilentlyContinue
    Remove-Item $baseline -ErrorAction SilentlyContinue
    if ($afterFile) { Remove-Item $afterFile -ErrorAction SilentlyContinue }
}
