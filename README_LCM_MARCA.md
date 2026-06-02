# LCM - La Cablera Marplatense | Assets de marca

Archivos incluidos:

```text
assets/
├── brand/
│   ├── lcm-logo-horizontal.svg
│   ├── lcm-logo-nav.svg
│   ├── lcm-logo-compact.svg
│   ├── lcm-icon.svg
│   └── favicon.svg
│
└── css/
    └── brand.css

shared/
└── brand.php
```

## Cómo insertar en una página PHP

En el `<head>`:

```html
<link rel="icon" href="/assets/brand/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="/assets/css/brand.css">
```

En el PHP:

```php
<?php require_once __DIR__ . '/../../shared/brand.php'; ?>
```

Luego en el navbar:

```php
<a href="/menu.php" class="lcm-brand">
    <?= lcm_logo('nav') ?>
</a>
```

## Variantes disponibles

```php
<?= lcm_logo('horizontal') ?>
<?= lcm_logo('nav') ?>
<?= lcm_logo('compact') ?>
<?= lcm_logo('icon') ?>
```

## Nota

Los SVG usan la tipografía `Syne` y `DM Sans` mediante CSS.
No se incluyen archivos de fuentes dentro del paquete.
