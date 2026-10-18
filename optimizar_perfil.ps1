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
$check = Join-Path $env:TEMP 'lcm_verificar_perfiles.php'
$phpCheck = @'
<?php
declare(strict_types=1);
require getcwd() . '/api/v1/central-auth/bootstrap.php';
$pdo = api_database();
$ids = $pdo->query("SELECT id FROM usuarios WHERE estado = 'ACTIVO' ORDER BY es_superadmin ASC, id ASC LIMIT 3")->fetchAll(PDO::FETCH_COLUMN);
$super = $pdo->query("SELECT id FROM usuarios WHERE estado = 'ACTIVO' AND es_superadmin = 1 ORDER BY id LIMIT 1")->fetchColumn();
if ($super !== false && !in_array($super, $ids)) $ids[] = $super;
if (!$ids) { fwrite(STDERR, "Sin usuarios activos para comparar.\n"); exit(2); }
$service = new CorporateInvitationService($pdo);
$results = [];
$start = microtime(true);
foreach ($ids as $id) {
    $profile = $service->authenticatedProfile((int)$id);
    $results[(string)$id] = [
        'user' => $profile['user'],
        'applications' => $profile['applications'],
        'roles' => $profile['roles'],
        'modules' => $profile['modules'],
    ];
}
fwrite(STDERR, 'Perfiles: ' . count($results) . ' | Tiempo total: ' . round(microtime(true)-$start,2) . " s\n");
echo json_encode($results, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
'@
[System.IO.File]::WriteAllText($check, $phpCheck, [System.Text.UTF8Encoding]::new($false))
try {
    Write-Host '1. Midiendo y guardando permisos actuales (puede tardar)...' -ForegroundColor Cyan
    $before = & php $check 2>&1
    if ($LASTEXITCODE -ne 0) { throw "Fallo la medicion inicial: $before" }
    $baselineJson = ($before | Where-Object { $_ -is [string] -and $_.StartsWith('{') } | Select-Object -Last 1)
    if (!$baselineJson) { throw "No se pudo capturar la referencia inicial: $before" }
    [System.IO.File]::WriteAllText($baseline, $baselineJson, [System.Text.UTF8Encoding]::new($false))
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
    $after = & php $check 2>&1
    if ($LASTEXITCODE -ne 0) { throw "Fallo el perfil optimizado: $after" }
    $afterJson = ($after | Where-Object { $_ -is [string] -and $_.StartsWith('{') } | Select-Object -Last 1)
    if (!$afterJson) { throw "No se pudo capturar el perfil nuevo: $after" }
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
}
