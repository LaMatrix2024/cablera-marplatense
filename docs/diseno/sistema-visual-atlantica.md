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

## Estándar de exportaciones Excel

Las exportaciones `.xlsx` de cualquier página de La Cablera deben seguir este patrón. La referencia funcional y estética aprobada es la salida `obras_Palacios_Luis_20260624_131028.xlsx`, revisada el 24 de junio de 2026. El archivo de referencia sirve para definir el formato; nunca deben reutilizarse sus datos particulares.

### Reglas generales

- Generar un archivo XLSX nativo. No entregar HTML o CSV con extensión `.xlsx`.
- Exportar exactamente el conjunto de datos y filtros activos en la pantalla.
- Usar nombres de archivo descriptivos y seguros, con fecha y hora: `modulo_contexto_AAAAMMDD_HHMMSS.xlsx`.
- Mantener los valores numéricos como números, no como texto.
- Aplicar revisión ortográfica a títulos, encabezados, filtros y mensajes antes de publicar.
- No incluir columnas internas, credenciales, datos personales ocultos ni información que la página no exponga.
- Los resaltados excepcionales solo deben incorporarse cuando exista una regla semántica explícita; no se deben copiar formatos aislados sin criterio funcional.

### Cabecera del informe

La cabecera ocupa las primeras cuatro filas y se combina sobre todo el ancho útil de la tabla:

1. Marca: `LCM · La Cablera Marplatense`, fondo carbón `#111111`, texto blanco, negrita, 16 puntos.
2. Título del informe, color naranja `#FF6B35`, negrita, 13 puntos.
3. Contexto principal, por ejemplo contratista, área o entidad seleccionada, negrita, 14 puntos.
4. Resumen de filtros aplicados, texto gris `#777777`, 10 puntos.

Debe dejarse una fila de separación entre la cabecera y la tabla.

### Tabla

- Encabezados con fondo naranja `#FF6B35`, texto `#111111`, negrita y alineación centrada.
- Altura de encabezado aproximada: 24 puntos.
- Bordes finos `#E0E0E0` en todas las celdas de datos.
- Texto general en Calibri de 11 puntos, por compatibilidad con Excel y Hostinger.
- Descripciones extensas alineadas arriba, con ajuste de texto.
- Identificadores y categorías breves centrados cuando mejore la lectura.
- Valores numéricos alineados a la derecha.
- Altura normal de las filas de datos: 30 puntos; ampliar a 45 puntos o al valor necesario cuando el contenido ajustado lo requiera.
- Ocultar las líneas de cuadrícula de la hoja.
- Congelar la cabecera de la tabla: en el patrón de referencia, filas 1 a 6 y primera fila de datos en la fila 7.
- Activar autofiltro desde la fila de encabezados hasta la última fila de datos.

### Anchos de columna

Los anchos deben adaptarse al contenido, conservando estas proporciones de referencia:

- identificadores: 14 a 16;
- categorías o textos breves: 10 a 13;
- descripción principal: 50 a 60;
- cantidades: 9 a 13;
- importes: 16 a 20.

En la exportación aprobada de detalle de obras se utilizaron, aproximadamente: `A 16`, `B 12,86`, `C 58`, `D 10,43`, `E 9,14`, `F 9,71`, `G 10,14`, `H 10,29`, `I 12,86`, `J 11,14` y `K 16`.

### Totales y formatos numéricos

- Separar los totales de los datos mediante una fila en blanco.
- Usar fondo `#FFF3E8`, texto `#111111`, negrita y los mismos bordes de la tabla.
- Utilizar `SUBTOTAL(109, ...)` cuando haya autofiltro, para que el total responda a las filas visibles.
- Cantidades: `#,##0.00` cuando admitan decimales.
- Importes: `$ #,##0.00`, salvo que la moneda del módulo requiera otro símbolo explícito.
- No mostrar decimales innecesarios si el dato funcional es entero.

### Impresión

- Papel A4.
- Orientación horizontal para tablas anchas.
- Ajustar a una página de ancho y sin límite fijo de páginas de alto.
- Márgenes de referencia: izquierdo y derecho `0,3`; superior e inferior `0,5`; encabezado y pie `0,2`.

### Adaptación a otros módulos

La cantidad y el nombre de las columnas dependen de cada página. La estructura visual, la trazabilidad de filtros, el comportamiento de totales y la configuración de lectura e impresión deben mantenerse. Si un módulo necesita apartarse de este patrón, la excepción debe documentarse y contar con aprobación explícita.
