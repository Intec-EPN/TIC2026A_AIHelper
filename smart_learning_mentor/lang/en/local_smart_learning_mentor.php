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
 * Plugin strings are defined here.
 *
 * @package     local_smart_learning_mentor
 * @category    string
 * @copyright   2026 Estefania Martinez <joselyn.martinez@epn.edu.ec>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Smart Learning Mentor';

// Settings page.
$string['setting_webhook_url']        = 'Webhook URL';
$string['setting_webhook_url_desc']   = 'URL of the n8n webhook (or other AI service) that will receive the student data payload.';
$string['setting_webhook_token']      = 'Security token';
$string['setting_webhook_token_desc'] = 'Optional token to authenticate requests to the webhook.';

// Navigation.
$string['coursereport'] = 'Smart Learning Mentor';

// Student panel.
$string['panel_title']        = 'Smart Learning Mentor';
$string['panel_subtitle']     = 'AI programming assistant';
$string['panel_welcome_title'] = '👋 Welcome';
$string['panel_welcome_body']  = 'Analyze your progress and improve your learning process.';
$string['panel_get_help']      = 'Get help';
$string['panel_processing']    = 'Processing your results...';
$string['panel_analysis_label'] = 'Analysis status';
$string['panel_errors_title']   = '⚠️ Frequent errors';
$string['panel_errors_desc']    = 'Each error can be expanded to see related concepts and resources.';

// Error/success messages.
$string['error_no_submissions']  = 'No saved versions found to analyze. Save your code at least once.';
$string['error_no_webhook']      = 'Payload generated correctly. The AI webhook is not configured yet.';
$string['error_sending_data']    = 'Error sending data to the AI service.';
$string['success_analysis_sent'] = 'Data sent to AI successfully.';
$string['nopermission']          = 'You do not have permission to access this section.';

// Main teacher navigation.
$string['nav_general']  = 'General';
$string['nav_catalog']  = 'Topics, Concepts and Resources';
$string['nav_config']   = 'Settings';

// Sub-navigation.
$string['subview_activities'] = 'Activities';
$string['subview_concepts']   = 'Concepts';
$string['subview_topics']     = 'Topics and Concepts';
$string['subview_resources']  = 'Resources';

// Activity table columns.
$string['col_activity']  = 'VPL Activity';
$string['col_students']  = 'Students Receiving Help';
$string['col_errors']    = 'Most Common Errors';
$string['col_concepts']  = 'Most Common Concepts';
$string['col_resources'] = 'Most Common AI Resources';
$string['col_examples']  = 'Most Common AI Examples';

// General messages.
$string['no_activities']         = 'There are no VPL activities in this course.';
$string['coming_soon']           = 'This section will be available soon.';
$string['concepts_coming_soon']  = 'The Concepts view will be available soon.';
$string['topics_coming_soon']    = 'Topic and concept management will be available soon.';
$string['resources_coming_soon'] = 'Resource management will be available soon.';
$string['config_coming_soon']    = 'Plugin settings will be available soon.';

// Configuration - states.
$string['config_status_configured'] = 'Configured';
$string['config_status_default']    = 'Default';

// Configuration - interface texts.
$string['config_title']               = 'Feedback and reinforcement configuration';
$string['config_subtitle']            = 'Configure error analysis, concepts, and AI examples per activity.';
$string['config_save_btn']            = 'Save configuration';
$string['config_default_info']        = 'Default configuration: if an activity has no settings, the system will use these values automatically.';
$string['config_default_minenvios']   = 'Minimum submissions: 1';
$string['config_default_maxsolic']    = 'Maximum requests: 3';
$string['config_filters_title']       = 'Activity filters';
$string['config_filters_show']        = 'Show filters';
$string['config_search_label']        = 'Search activity';
$string['config_search_placeholder']  = 'Exercise 1, practice, arrays...';
$string['config_section_label']       = 'Section';
$string['config_section_all']         = 'All';
$string['config_reset_filters']       = 'Clear filters';
$string['config_table_title']         = 'VPL Activities';
$string['config_table_subtitle']      = 'Select which features are available for each activity.';
$string['config_col_activity']        = 'Activity';
$string['config_col_help']            = 'Enable help';
$string['config_col_resources']       = 'Concepts and resources';
$string['config_col_examples']        = 'AI examples';
$string['config_col_minenvios']       = 'Min. submissions';
$string['config_col_maxsolic']        = 'Max. requests';
$string['config_open_vpl']            = 'Open VPL';
$string['config_no_vpls']             = 'No VPL activities were found in this course.';
$string['config_saving']              = 'Saving...';
$string['config_saved_ok']            = 'Configuration saved successfully.';
$string['config_save_error']          = 'Error saving configuration.';
$string['config_help_tooltip']        = 'The student can request help about errors in their code.';
$string['config_resources_tooltip']   = 'Links errors to course concepts and recommends resources. Requires Help enabled.';
$string['config_examples_tooltip']    = 'Allows the student to request AI-generated code examples. Requires Help enabled.';
$string['config_minenvios_tooltip']   = 'Minimum number of saved submissions before help can be requested.';
$string['config_maxsolic_tooltip']    = 'Maximum number of times the student can request help.';
$string['error_invalid_config_data']  = 'The received configuration data is not valid.';
$string['success_config_saved']       = 'Configuration saved successfully.';
$string['error_config_save']          = 'Error saving configuration.';


// Topics and concepts.
$string['concept_singular']              = 'concept';
$string['concept_plural']                = 'concepts';
$string['topics_title']                  = 'Topics and catalog concepts';
$string['topics_subtitle']               = 'Define the topics and concepts that the AI will use as pedagogical reference.';
$string['topics_edit_mode']              = 'Edit mode';
$string['topics_add_theme']              = 'New topic';
$string['topics_no_topics']              = 'No topics found. Add the first one using the "New topic" button.';
$string['topics_ai_panel_title']         = 'AI suggestions';
$string['topics_ai_panel_sub']           = 'AI-generated concepts that are not yet in the catalog.';
$string['topics_ai_add_btn']             = 'Add to catalog';
$string['topics_ai_empty']               = 'No AI-suggested concepts are available for this course yet.';
$string['topics_theme_edit']             = 'Edit topic';
$string['topics_theme_delete']           = 'Delete topic';
$string['topics_add_concept']            = 'Add concept';
$string['topics_concept_placeholder']    = 'Concept name...';
$string['topics_save_concept']           = 'Save';
$string['topics_concept_delete']         = 'Delete concept';
$string['topics_theme_name_label']       = 'Topic name';
$string['topics_theme_name_placeholder'] = 'e.g. Loops, Functions, Pointers...';
$string['topics_save_theme']             = 'Save topic';
$string['topics_cancel']                 = 'Cancel';
$string['topics_modal_title']            = 'Add AI concept to catalog';
$string['topics_modal_theme_label']      = 'Add to topic';
$string['topics_modal_select_theme']     = '-- Select a topic --';
$string['topics_modal_concept_label']    = 'Concept name (you can edit it)';
$string['topics_modal_concept_placeholder'] = 'Concept name';
$string['topics_modal_save']             = 'Add to catalog';
$string['topics_modal_cancel']           = 'Cancel';

// Success and error messages for topics.
$string['error_theme_name_empty']  = 'Topic name cannot be empty.';
$string['error_theme_create']      = 'Error creating topic.';
$string['error_theme_update']      = 'Error updating topic.';
$string['error_theme_delete']      = 'Error deleting topic.';
$string['success_theme_created']   = 'Topic created successfully.';
$string['success_theme_updated']   = 'Topic updated successfully.';
$string['success_theme_deleted']   = 'Topic deleted successfully.';

// Success and error messages for concepts.
$string['error_concept_name_empty'] = 'Concept name cannot be empty.';
$string['error_concept_create']     = 'Error creating concept.';
$string['error_concept_delete']     = 'Error deleting concept.';
$string['error_no_permission']      = 'You do not have permission to perform this action.';
$string['success_concept_created']  = 'Concept created successfully.';
$string['success_concept_deleted']  = 'Concept deleted successfully.';
$string['success_concept_promoted'] = 'Concept successfully added to the catalog.';


// Resources.
$string['resources_no_concepts_title'] = 'Add topics and concepts first';
$string['resources_no_concepts_body']  = 'To associate course resources with concepts, you must first define at least one topic and one concept in the pedagogical catalog.';
$string['resources_go_to_topics']      = 'Go to Topics and Concepts';
$string['resources_toggle_sidebar']    = 'Show/Hide topics';
$string['resources_sidebar_title']     = 'Topics and concepts';
$string['resources_sidebar_sub']       = 'Available concepts for association.';
$string['resources_main_title']        = 'Course resources';
$string['resources_main_sub']          = 'Associate each resource with one or more concepts from the catalog.';
$string['resources_elements']          = 'items';
$string['resources_no_modules']        = 'No resources were found in this course.';
$string['resources_associate_btn']     = 'Associate concepts';
$string['resources_modal_title']       = 'Associate resource with concepts';
$string['resources_modal_subtitle']    = 'Select one or more concepts for the selected resource.';
$string['resources_selected_resource'] = 'Selected resource';
$string['resources_modal_concepts']    = 'Available concepts';
$string['resources_modal_search']      = 'Search concept...';
$string['resources_modal_summary']     = 'Association summary';
$string['resources_modal_selected']    = 'Selected concepts';
$string['resources_modal_none']        = 'None selected';
$string['resources_modal_cancel']      = 'Cancel';
$string['resources_modal_save']        = 'Save association';
$string['error_resource_save']         = 'Error saving the resource association.';
$string['success_resource_saved']      = 'Resource association saved successfully.';

// New topic modal - required concepts.
$string['topics_modal_concepts_hint']     = 'Add at least one concept for this topic.';
$string['topics_modal_concepts_empty']    = 'No concepts added yet.';
$string['topics_modal_concepts_required'] = 'You must add at least one concept before saving the topic.';

// Overview - columns and navigation.
$string['col_activity']       = 'Activity';
$string['col_students']       = '# Requests';
$string['col_errors']         = 'Common errors';
$string['col_concepts']       = 'Concepts';
$string['col_resources']      = 'Resources';
$string['col_examples']       = 'AI Examples';
$string['col_detail']         = 'Details';
$string['col_student']        = 'Student';
$string['col_requests']       = '# Requests';
$string['col_last_request']   = 'Last request';
$string['view_detail']        = 'View details';
$string['back_to_activities'] = 'Activities';
$string['back_to_vpl']        = 'Students';
$string['no_activities']      = 'There are no VPL activities in this course.';
$string['no_students']        = 'No student has requested help for this activity.';
$string['no_history']         = 'This student has no recorded requests.';
$string['concepts_coming_soon'] = 'Concepts view coming soon.';

// Concepts view.
$string['col_concept']               = 'Concept';
$string['col_occurrences']           = 'Occurrences';
$string['concepts_no_catalog']       = 'There are no concepts in the catalog.';
$string['concepts_no_catalog_body']  = 'Go to the catalog and add topics with their concepts to view the report.';
$string['concepts_go_catalog']       = 'Go to Catalog';
$string['back_to_concepts']          = 'Concepts';
$string['tema_label']                = 'Topic';
$string['errors_title']              = 'Detected errors';
$string['resources_title']           = 'Instructor resources';
$string['recommendation']            = 'Recommendation';
$string['ia_examples']               = 'AI examples';
$string['occurrences']               = 'occurrences';
$string['no_errors_for_concept']     = 'No errors have been recorded for this concept.';
$string['no_resources_for_concept']  = 'No resources are associated with this concept.';

// Panel - blocking messages.
$string['panel_blocked_teacher']      = 'This activity does not have the assistant enabled. If you would like to use it, please ask your teacher to enable it.';
$string['panel_blocked_submissions']  = 'You must submit at least {$a->min} submission(s) before requesting help. You have submitted {$a->current}.';
$string['panel_blocked_max_requests'] = 'You have reached the maximum limit of {$a->max} help request(s) for this activity.';
