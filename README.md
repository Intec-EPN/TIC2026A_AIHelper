# TIC2026A_AIHelper
Complemento local para Moodle que integra Inteligencia Artificial para brindar ayuda personalizada a estudiantes que están aprendiendo a programar en actividades del Laboratorio Virtual de Programación (VPL).

## Descripción
El complemento analiza el historial de entregas de un estudiante en una actividad VPL. Para cada entrega se considera el código fuente enviado, así como la retroalimentación proporcionada automáticamente por VPL, que incluye los resultados de compilación, los resultados de ejecución y los comentarios generados por el profesor.

A partir de este análisis, el sistema genera mediante Inteligencia Artificial una ayuda personalizada que incluye:
- Errores frecuentes detectados entre entregas.
- Temas y conceptos del catálogo pedagógico definido por el profesor.
- Recursos de estudio asociados a cada concepto.
- Ejemplos de código generados por la IA.

Además, el profesor puede configurar la ayuda proporcionada por la IA, gestionar el catálogo pedagógico del curso y revisar o editar las respuestas generadas antes de que sean utilizadas como referencia.

---

## Requisitos

- **Moodle:** 5.1 o superior
- **PHP:** 8.2.0 o superior
- **Base de datos:** MariaDB 10.11 o MySQL 8.4
- **VPL:** Versión compatible con Moodle 5.1
- **moodledata:** Acceso de lectura desde PHP

---

## Instalación

1. Descargar el archivo **smart_learning_mentor**.
2. Ingresar a **Administración del sitio -> Plugins -> Instalar complementos**.
3. Subir el archivo ZIP descargado y seguir el asistente de instalación.
4. Una vez instalado, acceder a: Administración del sitio -> Plugins - Complementos locales -> Smart Learning Mentor
5. Configurar la URL del webhook
6. Dentro de cada curso, acceder al complemento desde el menú del curso y configurar:
   - El catálogo pedagógico.
   - Las opciones de ayuda para cada actividad VPL.

---

## Roles y funcionalidades

### Estudiante

- Panel flotante disponible en las páginas de actividades VPL.
- Solicitud de ayuda personalizada basada en el historial de entregas.
- Visualización de:
  - Errores detectados.
  - Conceptos relacionados.
  - Recursos de aprendizaje.
  - Ejemplos de código generados por IA.

### Profesor

- Gestión del catálogo pedagógico (temas, conceptos y recursos).
- Configuración de la ayuda para cada actividad VPL.
- Revisión del historial de solicitudes de los estudiantes.
- Edición de las respuestas generadas por la Inteligencia Artificial.
