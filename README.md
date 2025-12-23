\# moodle-local\_padimport



Plugin \*\*local\*\* para Moodle que importa un PAD en formato \*\*Excel\*\* y construye la estructura del curso a partir de reglas de lectura del documento (REA, actividades y descripciones).



\## ¿Qué hace?

\- Lee un archivo Excel del PAD.

\- Detecta el REA actual según filas que contienen \*\*"Rea Específico"\*\* y a la derecha un número con formato \*\*`1:`\*\*, \*\*`2:`\*\*, \*\*`3:`\*\*.

\- Crea \*\*tareas (assignments)\*\* a partir de:

&nbsp; - Título: el texto después de \*\*`Actividad:`\*\*

&nbsp; - Contenido/Descripción: el texto en la celda a la derecha de \*\*`Descripción`\*\* (maneja celdas combinadas buscando la primera celda no vacía hacia la derecha).

\- Inserta un \*\*banner HTML\*\* al inicio de cada REA (si existe la tabla “REA ESPECÍFICOS” con columnas “Consecutivo” y “Nombre”).



\## Requisitos

\- Moodle instalado (plugin tipo `local`).

\- El servidor debe contar con las dependencias estándar de Moodle (el plugin usa el autoload de `vendor/` del propio Moodle).

\- Moodle debe tener instalado PhpSpreadsheet vía Composer (en el vendor/ del Moodle raíz).

\- Para que PhpSpreadsheet funcione bien suelen ser necesarias:

php-zip (muy importante)

php-xml

php-mbstring

Si falta zip, muchas veces falla la lectura de Excel.

\## Instalación

1\. Clona o copia este repositorio en:<moodle\_root>/local/padimport

2\. En Moodle: \*\*Administración del sitio → Notificaciones\*\* (para que detecte e instale el plugin).

3\. Luego: \*\*Administración del sitio → Desarrollo → Purgar todas las cachés\*\*.



\## Configuración del curso plantilla (IMPORTANTE)

El plugin crea contenido en secciones/tabs por nombre. Asegúrate de que tu \*\*curso plantilla\*\* tenga creadas las secciones necesarias, por ejemplo:

\- `CADI`

\- `REA 1`, `REA 2`, `REA 3` (según aplique)

\- (Opcional) `Etapa 1`, `Etapa 2`, etc. si tu PAD usa “Etapa X”.



> Si tu PAD solo trae `REA 1` y `REA 2`, el plugin solo generará esas secciones si detecta `1:` y `2:`.



\## Formato esperado del Excel (reglas)

\### 1) Cambio de REA

Cuando el Excel tenga una fila con \*\*"Rea Específico"\*\* y en una celda cercana a la derecha exista:

\- `1:` → REA 1

\- `2:` → REA 2

\- `3:` → REA 3



Las actividades que aparecen después se asocian a ese REA hasta que aparezca el siguiente “Rea Específico …”.



\### 2) Actividades → tareas

Una tarea se crea cuando el plugin detecta:

\- Una celda que empieza por \*\*`Actividad:`\*\*

\- Y posteriormente una fila que contenga \*\*`Descripción`\*\*, tomando el texto de la celda a la derecha como contenido de la tarea.



\*\*Ejemplo (conceptual):\*\*

\- `Actividad: Observatorio Macroeconómico ...`  → título de la tarea

\- `Descripción` | `Texto largo...` → descripción de la tarea



\## Uso

1\. Crea un curso nuevo basado en tu \*\*curso plantilla\*\*.

2\. Abre el plugin (interfaz de carga del PAD).

3\. Sube el Excel del PAD.

4\. Ejecuta la importación.

5\. Verifica que las tareas hayan quedado en `REA 1`, `REA 2`, `REA 3` según el `1:/2:/3:` del documento.



\## Notas / Limitaciones

\- La fecha de entrega (`daysdue`) está configurada por defecto (actualmente 14 días).

\- El comportamiento depende del formato del Excel (etiquetas como “Rea Específico”, “Actividad:” y “Descripción”).



\## Contribuciones

\- Issues y PRs son bienvenidos.

\- Antes de reportar errores, adjunta un ejemplo del Excel (o capturas) con la parte donde aparecen “Rea Específico”, “Actividad:” y “Descripción”.







