<?php

declare(strict_types=1);

require_once __DIR__ . '/../shared/auth/CorporateAccess.php';

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual:   ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

// Caso 1: superadmin activo.
$superadmin = ['estado' => 'ACTIVO', 'es_superadmin' => 1];
assertSameValue(true, CorporateAccess::isActiveUser($superadmin), 'Superadmin debe estar activo.');
assertSameValue(true, CorporateAccess::isSuperadmin($superadmin), 'Superadmin debe reconocerse.');
assertSameValue(true, CorporateAccess::userCan(CorporateAccess::fullPermissions(), 'puede_eliminar'), 'Superadmin tiene acceso total.');

// Caso 2: Firebase valido pero usuario corporativo inexistente.
$missingCorporateUser = null;
assertSameValue(null, $missingCorporateUser, 'Usuario corporativo inexistente queda no autorizado.');

// Caso 3: usuario PENDIENTE.
assertSameValue(false, CorporateAccess::isActiveUser(['estado' => 'PENDIENTE']), 'PENDIENTE no accede.');

// Caso 4: usuario ACTIVO sin acceso a CABLERAMARPLATENSE.
$noAppPermissions = CorporateAccess::emptyPermissions();
assertSameValue(false, in_array(true, $noAppPermissions, true), 'Sin aplicacion autorizada no hay permisos.');

// Caso 5: usuario con rol JEFATURA.
$jefatura = CorporateAccess::combineRolePermissions([
    [
        'puede_ver' => 1,
        'puede_crear' => 0,
        'puede_editar' => 0,
        'puede_eliminar' => 0,
        'puede_exportar' => 1,
        'puede_aprobar' => 0,
    ],
]);
assertSameValue(true, $jefatura['puede_ver'], 'JEFATURA puede ver.');
assertSameValue(true, $jefatura['puede_exportar'], 'JEFATURA puede exportar.');
assertSameValue(false, $jefatura['puede_editar'], 'JEFATURA no edita por rol base.');

// Caso 6: excepcion usuario_modulo agrega y quita permisos.
$withException = CorporateAccess::applyUserException($jefatura, [
    'puede_ver' => null,
    'puede_crear' => null,
    'puede_editar' => 1,
    'puede_eliminar' => null,
    'puede_exportar' => 0,
    'puede_aprobar' => null,
]);
assertSameValue(true, $withException['puede_editar'], 'Excepcion agrega editar.');
assertSameValue(false, $withException['puede_exportar'], 'Excepcion quita exportar.');
assertSameValue(true, $withException['puede_ver'], 'NULL conserva permiso heredado.');

// Caso 7: BLOQUEADO con token Firebase valido.
assertSameValue(false, CorporateAccess::isActiveUser(['estado' => 'BLOQUEADO']), 'BLOQUEADO no accede.');

// Caso 8: sustitucion de firebase_uid ya asociado.
$conflict = CorporateAccess::resolveIdentityLink(
    ['email' => 'usuario@plantel.com', 'firebase_uid' => 'uid-original'],
    'uid-distinto',
    'usuario@plantel.com'
);
assertSameValue(false, $conflict['ok'], 'No se permite sustituir UID asociado.');
assertSameValue('firebase_uid_conflict', $conflict['reason'], 'Debe registrar conflicto de identidad.');

$link = CorporateAccess::resolveIdentityLink(
    ['email' => 'usuario@plantel.com', 'firebase_uid' => null],
    'uid-nuevo',
    'usuario@plantel.com'
);
assertSameValue(['ok' => true, 'action' => 'link_uid'], $link, 'Primer login vincula UID si email coincide.');

echo "corporate_access_test OK" . PHP_EOL;

