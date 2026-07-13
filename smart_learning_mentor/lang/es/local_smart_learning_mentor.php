<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin strings are defined here - español
 *
 * @package     local_smart_learning_mentor
 * @category    string
 * @copyright   2026 Estefania Martinez <joselyn.martinez@epn.edu.ec>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Smart Learning Mentor';

// Página de configuración.
$string['setting_webhook_url']        = 'URL del Webhook';
$string['setting_webhook_url_desc']   = 'URL del webhook de n8n (u otro servicio de IA) que recibirá el payload de datos del estudiante.';
$string['setting_webhook_token']      = 'Token de seguridad';
$string['setting_webhook_token_desc'] = 'Token opcional para autenticar la petición hacia el webhook.';

// Navegación.
$string['coursereport'] = 'Smart Learning Mentor';

// Panel del estudiante.
$string['panel_title']         = 'Smart Learning Mentor';
$string['panel_subtitle']      = 'Asistente de programación con IA';
$string['panel_welcome_title'] = '👋 Bienvenido';
$string['panel_welcome_body']  = 'Analiza tu progreso y mejora tu proceso de aprendizaje.';
$string['panel_get_help']      = 'Obtener ayuda';
$string['panel_processing']    = 'Procesando tus resultados...';
$string['panel_analysis_label'] = 'Estado del análisis';
$string['panel_errors_title']   = '⚠️ Errores frecuentes';
$string['panel_errors_desc']    = 'Cada error se puede desplegar y contiene sus conceptos y recursos relacionados.';

// Mensajes de error y éxito.
$string['error_no_submissions']  = 'No se encontraron versiones guardadas para analizar. Guarda tu código al menos una vez.';
$string['error_no_webhook']      = 'Payload generado correctamente. El webhook de IA no está configurado aún.';
$string['error_sending_data']    = 'Error al enviar los datos al servicio de IA.';
$string['success_analysis_sent'] = 'Datos enviados correctamente a la IA.';
$string['nopermission']          = 'No tienes permiso para acceder a esta sección.';


// Navegación principal del profesor.
$string['nav_general']  = 'General';
$string['nav_catalog']  = 'Temas, Conceptos y Recursos';
$string['nav_config']   = 'Configuración';

// Sub-navegación.
$string['subview_activities'] = 'Actividades';
$string['subview_concepts']   = 'Conceptos';
$string['subview_topics']     = 'Temas y Conceptos';
$string['subview_resources']  = 'Recursos';

// Columnas de la tabla de actividades.
$string['col_activity']  = 'Actividad VPL';
$string['col_students']  = 'Estudiantes con ayuda';
$string['col_errors']    = 'Errores frecuentes';
$string['col_concepts']  = 'Conceptos frecuentes';
$string['col_resources'] = 'Recursos IA frecuentes';
$string['col_examples']  = 'Ejemplos IA frecuentes';

// Mensajes generales.
$string['no_activities']         = 'No hay actividades VPL en este curso.';
$string['coming_soon']           = 'Esta sección estará disponible próximamente.';
$string['concepts_coming_soon']  = 'La vista por conceptos estará disponible próximamente.';
$string['topics_coming_soon']    = 'La gestión de temas y conceptos estará disponible próximamente.';
$string['resources_coming_soon'] = 'La gestión de recursos estará disponible próximamente.';
$string['config_coming_soon']    = 'La configuración del plugin estará disponible próximamente.';


// Configuracion - estados.
$string['config_status_configured'] = 'Configurado';
$string['config_status_default']    = 'Por defecto';

// Configuracion - textos de la interfaz.
$string['config_title']               = 'Configuracion del refuerzo y retroalimentacion';
$string['config_subtitle']            = 'Configura analisis de errores, conceptos y ejemplos IA por actividad.';
$string['config_save_btn']            = 'Guardar configuracion';
$string['config_default_info']        = 'Configuracion predeterminada: si una actividad no tiene valores configurados, el sistema usara estos valores automaticamente.';
$string['config_default_minenvios']   = 'Minimo de envios: 1';
$string['config_default_maxsolic']    = 'Maximo de solicitudes: 3';
$string['config_filters_title']       = 'Filtros de actividades';
$string['config_filters_show']        = 'Mostrar filtros';
$string['config_search_label']        = 'Buscar actividad';
$string['config_search_placeholder']  = 'Ejercicio 1, practica, arrays...';
$string['config_section_label']       = 'Seccion';
$string['config_section_all']         = 'Todas';
$string['config_reset_filters']       = 'Limpiar filtros';
$string['config_table_title']         = 'Actividades VPL';
$string['config_table_subtitle']      = 'Selecciona que funcionalidades estaran disponibles para cada actividad.';
$string['config_col_activity']        = 'Actividad';
$string['config_col_help']            = 'Habilitar ayuda';
$string['config_col_resources']       = 'Conceptos y recursos';
$string['config_col_examples']        = 'Ejemplos IA';
$string['config_col_minenvios']       = 'Min. envios';
$string['config_col_maxsolic']        = 'Max. solicitudes';
$string['config_open_vpl']            = 'Abrir VPL';
$string['config_no_vpls']             = 'No se encontraron actividades VPL en este curso.';
$string['config_saving']              = 'Guardando...';
$string['config_saved_ok']            = 'Configuracion guardada correctamente.';
$string['config_save_error']          = 'Error al guardar la configuracion.';
$string['config_help_tooltip']        = 'El estudiante puede solicitar ayuda sobre sus errores en el codigo.';
$string['config_resources_tooltip']   = 'Relaciona errores con conceptos del curso y recomienda recursos. Requiere Habilitar ayuda.';
$string['config_examples_tooltip']    = 'Permite al estudiante solicitar ejemplos de codigo generados por IA. Requiere Habilitar ayuda.';
$string['config_minenvios_tooltip']   = 'Numero minimo de guardados del codigo antes de poder solicitar ayuda.';
$string['config_maxsolic_tooltip']    = 'Numero maximo de veces que el estudiante puede solicitar ayuda.';
$string['error_invalid_config_data']  = 'Los datos de configuracion recibidos no son validos.';
$string['success_config_saved']       = 'Configuracion guardada correctamente.';
$string['error_config_save']          = 'Error al guardar la configuracion.';



// Temas y conceptos.
$string['concept_singular']              = 'concepto';
$string['concept_plural']               = 'conceptos';
$string['topics_title']                 = 'Temas y conceptos del catalogo';
$string['topics_subtitle']              = 'Define los temas y conceptos que la IA usara como referencia pedagogica.';
$string['topics_edit_mode']             = 'Modo edicion';
$string['topics_add_theme']             = 'Nuevo tema';
$string['topics_no_topics']             = 'No hay temas registrados. Agrega el primero con el boton "Nuevo tema".';
$string['topics_ai_panel_title']        = 'Sugerencias de la IA';
$string['topics_ai_panel_sub']          = 'Conceptos propuestos por la IA que aun no estan en el catalogo.';
$string['topics_ai_add_btn']            = 'Agregar al catalogo';
$string['topics_ai_empty']              = 'No hay conceptos sugeridos por la IA para este curso aun.';
$string['topics_theme_edit']            = 'Editar tema';
$string['topics_theme_delete']          = 'Eliminar tema';
$string['topics_add_concept']           = 'Agregar concepto';
$string['topics_concept_placeholder']   = 'Nombre del concepto...';
$string['topics_save_concept']          = 'Guardar';
$string['topics_concept_delete']        = 'Eliminar concepto';
$string['topics_theme_name_label']      = 'Nombre del tema';
$string['topics_theme_name_placeholder']= 'Ej: Bucles, Funciones, Punteros...';
$string['topics_save_theme']            = 'Guardar tema';
$string['topics_cancel']                = 'Cancelar';
$string['topics_modal_title']           = 'Agregar concepto de la IA al catalogo';
$string['topics_modal_theme_label']     = 'Agregar al tema';
$string['topics_modal_select_theme']    = '-- Selecciona un tema --';
$string['topics_modal_concept_label']   = 'Nombre del concepto (puedes editarlo)';
$string['topics_modal_concept_placeholder'] = 'Nombre del concepto';
$string['topics_modal_save']            = 'Agregar al catalogo';
$string['topics_modal_cancel']          = 'Cancelar';

// Mensajes de exito y error para temas.
$string['error_theme_name_empty']  = 'El nombre del tema no puede estar vacio.';
$string['error_theme_create']      = 'Error al crear el tema.';
$string['error_theme_update']      = 'Error al actualizar el tema.';
$string['error_theme_delete']      = 'Error al eliminar el tema.';
$string['success_theme_created']   = 'Tema creado correctamente.';
$string['success_theme_updated']   = 'Tema actualizado correctamente.';
$string['success_theme_deleted']   = 'Tema eliminado correctamente.';

// Mensajes de exito y error para conceptos.
$string['error_concept_name_empty'] = 'El nombre del concepto no puede estar vacio.';
$string['error_concept_create']     = 'Error al crear el concepto.';
$string['error_concept_delete']     = 'Error al eliminar el concepto.';
$string['error_no_permission']      = 'No tienes permiso para realizar esta accion.';
$string['success_concept_created']  = 'Concepto creado correctamente.';
$string['success_concept_deleted']  = 'Concepto eliminado correctamente.';
$string['success_concept_promoted'] = 'Concepto agregado al catalogo correctamente.';


// Recursos.
$string['resources_no_concepts_title'] = 'Primero agrega temas y conceptos';
$string['resources_no_concepts_body']  = 'Para poder asociar recursos del curso a conceptos, primero debes definir al menos un tema y un concepto en el catalogo pedagogico.';
$string['resources_go_to_topics']      = 'Ir a Temas y Conceptos';
$string['resources_toggle_sidebar']    = 'Mostrar/ocultar temas';
$string['resources_sidebar_title']     = 'Temas y conceptos';
$string['resources_sidebar_sub']       = 'Conceptos disponibles para asociar.';
$string['resources_main_title']        = 'Recursos del curso';
$string['resources_main_sub']          = 'Asocia cada recurso con uno o varios conceptos del catalogo.';
$string['resources_elements']          = 'elementos';
$string['resources_no_modules']        = 'No se encontraron recursos en este curso.';
$string['resources_associate_btn']     = 'Asociar conceptos';
$string['resources_modal_title']       = 'Asociar recurso a conceptos';
$string['resources_modal_subtitle']    = 'Selecciona uno o varios conceptos para el recurso elegido.';
$string['resources_selected_resource'] = 'Recurso seleccionado';
$string['resources_modal_concepts']    = 'Conceptos disponibles';
$string['resources_modal_search']      = 'Buscar concepto...';
$string['resources_modal_summary']     = 'Resumen de la asociacion';
$string['resources_modal_selected']    = 'Conceptos seleccionados';
$string['resources_modal_none']        = 'Ninguno seleccionado';
$string['resources_modal_cancel']      = 'Cancelar';
$string['resources_modal_save']        = 'Guardar asociacion';
$string['error_resource_save']         = 'Error al guardar la asociacion del recurso.';
$string['success_resource_saved']      = 'Asociacion guardada correctamente.';

// Modal nuevo tema - conceptos obligatorios.
$string['topics_modal_concepts_hint']     = 'Agrega al menos un concepto para este tema.';
$string['topics_modal_concepts_empty']    = 'Ningún concepto agregado aún.';
$string['topics_modal_concepts_required'] = 'Debes agregar al menos un concepto antes de guardar el tema.';


// Vista General - columnas y navegacion.
$string['col_activity']       = 'Actividad';
$string['col_students']       = 'Estudiantes';
$string['col_errors']         = 'Errores frecuentes';
$string['col_concepts']       = 'Conceptos';
$string['col_resources']      = 'Recursos';
$string['col_examples']       = 'Ejemplos IA';
$string['col_detail']         = 'Detalle';
$string['col_student']        = 'Estudiante';
$string['col_requests']       = '# Solicitudes';
$string['col_last_request']   = 'Última solicitud';
$string['view_detail']        = 'Ver detalle';
$string['back_to_activities'] = 'Actividades';
$string['back_to_vpl']        = 'Estudiantes';
$string['no_activities']      = 'No hay actividades VPL en este curso.';
$string['no_students']        = 'Ningún estudiante ha solicitado ayuda en esta actividad.';
$string['no_history']         = 'Este estudiante no tiene solicitudes registradas.';
$string['concepts_coming_soon'] = 'Vista de conceptos en desarrollo.';


// Vista Conceptos.
$string['col_concept']               = 'Concepto';
$string['col_occurrences']           = 'Frecuencia';
$string['concepts_no_catalog']       = 'No tienes conceptos en el catálogo.';
$string['concepts_no_catalog_body']  = 'Ve al catálogo y agrega temas con sus conceptos para ver el reporte.';
$string['concepts_go_catalog']       = 'Ir al catálogo';
$string['back_to_concepts']          = 'Conceptos';
$string['tema_label']                = 'Tema';
$string['errors_title']              = 'Errores detectados';
$string['resources_title']           = 'Recursos del profesor';
$string['recommendation']            = 'Recomendación';
$string['ia_examples']               = 'Ejemplos IA';
$string['occurrences']               = 'veces';
$string['no_errors_for_concept']     = 'No hay errores registrados para este concepto.';
$string['no_resources_for_concept']  = 'No hay recursos asociados a este concepto.';


// Panel - mensajes de bloqueo.
$string['panel_blocked_teacher']      = 'Esta actividad no tiene el asistente habilitado. Si deseas usarlo, solicítalo a tu profesor.';
$string['panel_blocked_submissions']  = 'Debes enviar al menos {$a->min} entrega(s) antes de solicitar ayuda. Llevas {$a->current}.';
$string['panel_blocked_max_requests'] = 'Has alcanzado el límite de {$a->max} solicitudes para esta actividad.';
