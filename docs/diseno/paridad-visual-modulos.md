# Paridad visual entre modulos

## Proposito

Cuando un modulo nuevo o una pagina existente debe "tomar el modelo" de otra pantalla, la consigna no es parecerse en colores sueltos. La consigna es reproducir el mismo criterio visual y estructural para que el usuario no perciba un salto al cambiar de pagina.

## Regla base

Si una pagina indica que debe copiar el modelo de otra, debe igualar al menos estos puntos:

1. Contenedor principal.
2. Padding lateral y superior.
3. Ancho util maximo.
4. Jerarquia tipografica.
5. Fondo de pagina y color de paneles.
6. Bordes, radios y sombras.
7. Estilo de encabezados de tarjetas, tablas y filtros.
8. Comportamiento responsive.

## Criterio de implementacion

- Reutilizar `shared/layout.php` y los tokens de `assets/css/atlantica.css`.
- Mantener el mismo `lcm-shell` o el mismo contenedor util que usa la pagina de referencia.
- No agregar un `padding` propio que cambie la alineacion horizontal de la vista.
- Usar la misma escala de titulos, tarjetas, paneles y tablas.
- Evitar introducir una segunda estetica dentro del mismo modulo.
- Si una pantalla tiene vista resumen y detalle, ambas deben compartir la misma grilla base.
- Si existe selector de periodo, filtros o tarjetas de KPI, deben respetar la misma densidad visual que la pagina de referencia.

## Regla de comparacion

La pagina nueva se considera alineada cuando, vista junto a la referencia:

- los bordes exteriores caen en el mismo lugar;
- los titulos empiezan en la misma linea visual;
- los paneles tienen el mismo ritmo vertical;
- la tabla o las tarjetas no cambian de ancho util percibido;
- el usuario no nota un cambio de estilo al navegar entre modulos.

## Referencia obligatoria

La referencia visual aprobada para Telefonia es `telefonia/produccion_planta`.

Cuando se indique "tomar el modelo de produccion_planta", la pagina objetivo debe copiar su criterio visual completo, no solo la paleta.

## Verificacion

Antes de cerrar una migracion visual:

- comparar captura de pantalla entre referencia y pagina objetivo;
- corregir padding lateral y ancho util si hay diferencia;
- confirmar que tipografia, tarjetas, paneles y tabla sigan el mismo sistema;
- revisar que el responsive conserve el mismo comportamiento general.

