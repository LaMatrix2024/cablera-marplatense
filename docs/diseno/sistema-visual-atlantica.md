# Sistema visual Atlántica

## Objetivo

Atlántica es el sistema visual compartido de La Cablera Marplatense. Busca ofrecer una interfaz técnica, clara y serena para el trabajo operativo prolongado, con alta legibilidad y una densidad adecuada para paneles, filtros y tablas.

## Alcance

Se aplica a las páginas institucionales y operativas que utilizan `shared/layout.php`.

Quedan fuera del alcance inicial:

- las PWA Mis Compras y Cambio;
- las APIs;
- herramientas independientes que no utilizan el layout LCM;
- cambios funcionales o de datos.

## Principios

1. La información tiene prioridad sobre la decoración.
2. El color principal se utiliza para navegación, selección y acciones importantes.
3. Verde, ámbar y rojo se reservan para estados semánticos.
4. Las sombras son discretas; la estructura se define principalmente con bordes y espaciado.
5. Las tablas deben mantener contraste, encabezados estables y desplazamiento horizontal controlado.
6. La interfaz debe ser utilizable desde dispositivos móviles sin perder funcionalidad.
7. Todos los textos visibles deben pasar una revisión ortográfica antes de publicarse.

## Paleta

```css
--atlantic-bg: #eef3f6;
--atlantic-surface: #ffffff;
--atlantic-surface-soft: #f5f8fa;
--atlantic-ink: #1d3040;
--atlantic-muted: #6b7e8b;
--atlantic-line: #d9e3e9;
--atlantic-primary: #326f93;
--atlantic-primary-strong: #244e68;
--atlantic-primary-soft: #e3eff5;
--atlantic-success: #39745c;
--atlantic-warning: #96723b;
--atlantic-danger: #ad5960;
```

## Tipografía

- Títulos: `Manrope`, con respaldo en `DM Sans` y Arial.
- Texto general: `DM Sans`, con respaldo en Arial.
- Los títulos usan tamaños moderados y una jerarquía clara; se evitan encabezados sobredimensionados.

## Componentes

### Barra de navegación

- Fondo azul petróleo `#244e68`.
- Logo oficial claro, sin filtros ni duplicación de activos.
- Elemento activo con fondo blanco translúcido.
- Desplazamiento horizontal controlado en dispositivos pequeños.

### Tarjetas e indicadores

- Fondo blanco y borde fino.
- Radio de 10 píxeles.
- Sombra mínima.
- Los indicadores pueden utilizar un borde superior azul; los estados críticos usan el color semántico correspondiente.

### Paneles y filtros

- Encabezado separado por un borde fino.
- Campos de 40 a 42 píxeles de altura.
- Foco visible con azul Atlántica.
- Acciones principales con fondo azul y texto blanco.

### Tablas

- Encabezados con fondo azul muy claro.
- Texto compacto y legible.
- Filas separadas por bordes suaves.
- Resaltado discreto al pasar el puntero.
- Desplazamiento horizontal dentro del contenedor, no en toda la página.

### Estados

- Correcto: verde.
- Advertencia: ámbar.
- Error: rojo apagado.
- Informativo o selección: azul Atlántica.

## Implementación

El tema central se encuentra en:

```text
assets/css/atlantica.css
```

El layout lo carga después de `brand.css`, de modo que todas las páginas compartidas reciben los tokens y componentes Atlántica. Los módulos con estilos propios deben consumir estos tokens o incluir una capa de compatibilidad específica.

## Validación obligatoria

Antes de considerar completa una migración se debe verificar:

- contraste del logo y la navegación;
- escritorio y vista responsive;
- filtros, tablas, modales y estados;
- ausencia de desplazamiento horizontal global;
- sintaxis PHP, CSS y JavaScript;
- ortografía de todos los textos visibles;
- ausencia de cambios funcionales o de consultas.
