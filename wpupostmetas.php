<?php

/*
Plugin Name: WPU Post Metas
Plugin URI: http://github.com/Darklg/WPUtilities
Description: Simple admin for post metas
Version: 0.15.2
Author: Darklg
Author URI: http://darklg.me/
License: MIT License
License URI: http://opensource.org/licenses/MIT
*/

class WPUPostMetas {

    var $boxes = array();
    var $fields = array();

    /**
     * Initialize class
     */
    function __construct() {
        if (is_admin()) {
            add_action('plugins_loaded', array(&$this,
                'load_plugin_textdomain'
            ));
            add_action('add_meta_boxes', array(
                $this,
                'add_custom_box'
            ));
            add_action('save_post', array(
                $this,
                'save_postdata'
            ));
            add_action('admin_enqueue_scripts', array(&$this,
                'load_assets'
            ));
            add_action('wp_ajax_wpupostmetas_attachments', array(&$this,
                'list_attachments_options'
            ));
            add_action('init', array(&$this,
                'init'
            ));
        }
    }

    function init() {
        $this->load_fields();
        $this->set_admin_columns();
    }

    function load_plugin_textdomain() {
        load_plugin_textdomain('wpupostmetas', false, dirname(plugin_basename(__FILE__)) . '/lang/');
    }

    function load_assets() {
        $screen = get_current_screen();
        if ($screen->base == 'post') {
            wp_enqueue_style('wpupostmetas_style', plugins_url('assets/style.css', __FILE__));
            wp_enqueue_script('wpupostmetas_scripts', plugins_url('assets/global.js', __FILE__));
        }
    }

    /*
     * Admin list columns
    */

    function set_admin_columns() {

        $this->admin_columns = array();
        foreach ($this->boxes as $box) {
            if (isset($box['post_type']) && is_array($box['post_type'])) {
                foreach ($box['post_type'] as $post_type) {
                    $this->admin_columns[$post_type] = array();
                }
            }
        }
        foreach ($this->fields as $field_id => $field) {
            if ($field['admin_column'] !== true) {
                continue;
            }
            $this->admin_columns[$post_type][$field_id] = $field;
        }

        foreach ($this->admin_columns as $post_type => $values) {
            add_filter('manage_edit-' . $post_type . '_columns', array(&$this,
                'set_columns_head'
            ));
            add_action('manage_' . $post_type . '_posts_custom_column', array(&$this,
                'set_columns_content'
            ) , 10, 2);
        }
    }

    // Display columns header
    function set_columns_head($defaults) {
        global $post;
        foreach ($this->admin_columns as $post_type => $values) {
            if ($post_type == $post->post_type) {
                foreach ($values as $field_id => $field) {
                    $defaults['wpupostmetas_' . $field_id] = $field['name'];
                }
            }
        }
        return $defaults;
    }

    // Display column content
    function set_columns_content($column_name, $post_ID) {

        // Each post type
        foreach ($this->admin_columns as $post_type => $values) {
            foreach ($values as $field_id => $field) {

                // Display column value
                if ($column_name == 'wpupostmetas_' . $field_id) {
                    $value = get_post_meta($post_ID, $field_id, 1);
                    switch ($field['type']) {
                        case 'select':

                            // If valid select, display data label
                            if (isset($field['datas']) && array_key_exists($value, $field['datas'])) {
                                echo $field['datas'][$value];
                                break;
                            }
                        default:

                            // Display raw value
                            echo $value;
                    }
                }
            }
        }
    }

    /**
     * Adds meta boxes
     */
    function add_custom_box() {
        global $post;
        foreach ($this->boxes as $id => $box) {
            $box = $this->control_box_datas($box);
            $boxfields = $this->fields_from_box($id, $this->fields);
            if (!empty($boxfields)) {
                foreach ($box['post_type'] as $type) {

                    // Capability refused
                    if (!current_user_can($box['capability'])) {
                        continue;
                    }
                    if (!isset($box['context'])) {
                        $box['context'] = 'normal';
                    }
                    if (isset($box['page_template'])) {
                        if (!isset($post->ID)) {
                            continue;
                        }
                        $current_template = get_page_template_slug($post->ID);
                        if ($current_template != $box['page_template']) {
                            continue;
                        }
                    }
                    add_meta_box('wputh_box_' . $id, $box['name'], array(
                        $this,
                        'box_content'
                    ) , $type, $box['context']);
                }
            }
        }
    }

    /**
     * Saves meta box content
     *
     * @param unknown $post_id
     */
    function save_postdata($post_id) {
        $languages = $this->get_languages();

        $boxes = $this->boxes;
        $fields = $this->fields;

        $post_type = isset($_POST['post_type']) ? $_POST['post_type'] : 'post';

        // First we need to check if the current user is authorised to do this action.
        if ('page' == $post_type) {
            if (!current_user_can('edit_page', $post_id)) return;
        }
        else {
            if (!current_user_can('edit_post', $post_id)) return;
        }

        // Secondly we need to check if the user intended to change this value.
        if (!isset($_POST['wputh_post_metas_noncename']) || !wp_verify_nonce($_POST['wputh_post_metas_noncename'], plugin_basename(__FILE__))) return;

        $post_ID = $_POST['post_ID'];

        foreach ($boxes as $id => $box) {
            $box = $this->control_box_datas($box);

            // If box corresponds to this post type & current user has level to edit
            if (in_array($post_type, $box['post_type']) && current_user_can($box['capability'])) {
                $boxfields = $this->fields_from_box($id, $fields);
                foreach ($boxfields as $field_id => $field) {

                    // Multilingual field
                    if (isset($field['lang']) && $field['lang'] && !empty($languages)) {
                        foreach ($languages as $idlang => $lang) {
                            $tmp_field_id = $idlang . '___' . $field_id;
                            $field_value = $this->check_field_value($tmp_field_id, $field);
                            if ($field_value !== false) {
                                update_post_meta($post_ID, $tmp_field_id, $field_value);
                            }
                        }
                    }
                    else {
                        $field_value = $this->check_field_value($field_id, $field);
                        if ($field_value !== false) {
                            update_post_meta($post_ID, $field_id, $field_value);
                        }
                    }
                }
            }
        }
    }

    /**
     * Shows meta box fields
     *
     * @param unknown $post
     * @param unknown $details
     */
    function box_content($post, $details) {
        $languages = $this->get_languages();
        $fields = $this->fields;
        $boxid = str_replace('wputh_box_', '', $details['id']);
        $boxfields = $this->fields_from_box($boxid, $this->fields);
        wp_nonce_field(plugin_basename(__FILE__) , 'wputh_post_metas_noncename');
        echo '<table class="wpupostmetas-table">';
        foreach ($fields as $id => $field) {
            if (array_key_exists($id, $boxfields)) {

                // Multilingual field
                if (isset($field['lang']) && $field['lang'] && !empty($languages)) {
                    foreach ($languages as $idlang => $lang) {
                        $new_field = $field;
                        $new_field['name'] = '[' . $idlang . '] ' . $new_field['name'];
                        $this->field_content($post, $idlang . '___' . $id, $new_field);
                    }
                }
                else {
                    $this->field_content($post, $id, $field);
                }
            }
        }
        echo '</table>';
    }

    /**
     * Shows meta box field
     *
     * @param unknown $post
     * @param unknown $id
     * @param unknown $field
     */
    function field_content($post, $id, $field, $only_field = false, $val = false) {
        $value = '';
        $main_post_id = 0;
        if (is_object($post)) {
            $main_post_id = $post->ID;
            $value = @trim(get_post_meta($main_post_id, $id, true));

            // If new post, try to load a default value
            if (isset($field['default'], $post->post_title, $post->post_content) && empty($post->post_title) && empty($post->post_content) && empty($value)) {
                $value = $field['default'];
            }
        }
        if ($val !== false) {
            $value = $val;
        }
        $el_id = 'el_id_' . $id;
        $idname = 'name="' . $id . '"';
        if ($only_field === false) {
            $idname = 'id="' . $el_id . '" name="' . $id . '"';
            echo '<tr>';
            echo '<th valign="top"><label for="el_id_' . $id . '">' . $field['name'] . ' :</label></th>';
            echo '<td valign="top">';
        }
        $field_datas = array(
            'Yes',
            'No'
        );
        if (isset($field['datas'])) {
            $field_datas = $field['datas'];
        }

        if (!isset($field['type'])) {
            $field['type'] = '';
        }

        switch ($field['type']) {
            case 'attachment':
                $args = array(
                    'post_type' => 'attachment',
                    'posts_per_page' => - 1,
                    'post_status' => 'any',
                    'post_parent' => $main_post_id
                );
                $attachments = get_posts($args);
                if ($attachments) {
                    echo '<div class="wpupostmetas-attachments__container"><span class="before"></span>';
                    echo '<div class="preview-img" id="preview-' . $id . '"></div>';
                    echo '<select ' . $idname . ' class="wpupostmetas-attachments" data-postid="' . $main_post_id . '" data-postvalue="' . $value . '">';
                    echo '<option value="-">' . __('None', 'wpupostmetas') . '</option>';
                    foreach ($attachments as $attachment) {
                        $data_guid = '';
                        if (strpos($attachment->post_mime_type, 'image/') !== false) {
                            $data_guid = 'data-guid="' . $attachment->guid . '"';
                        }
                        echo '<option ' . $data_guid . ' value="' . $attachment->ID . '" ' . ($attachment->ID == $value ? 'selected="selected"' : '') . '>' . apply_filters('the_title', $attachment->post_title) . '</option>';
                    }
                    echo '</select>';
                    echo '</div>';
                }
                else {
                    echo '<span>' . __('No attachments', 'wpupostmetas') . '</span>';
                }
            break;
            case 'select':
                echo '<select ' . $idname . '>';
                echo '<option value="" disabled selected style="display:none;">' . __('Select a value', 'wpupostmetas') . '</option>';
                foreach ($field_datas as $key => $var) {
                    echo '<option value="' . $key . '" ' . ((string)$key === (string)$value ? 'selected="selected"' : '') . '>' . $var . '</option>';
                }
                echo '</select>';
            break;
            case 'radio':
                foreach ($field_datas as $key => $var) {
                    $item_id = 'radio_' . $id . '_' . $key;
                    echo '<input type="radio" id="' . $item_id . '" name="' . $id . '" value="' . $key . '" ' . ((string)$key === (string)$value ? 'checked="checked"' : '') . ' />';
                    echo '<label for="' . $item_id . '">' . $var . '</label>';
                }
            break;
            case 'page':
                echo wp_dropdown_pages(array(
                    'name' => $id,
                    'selected' => $value,
                    'echo' => 0,
                ));
            break;
            case 'post':
                $wpq_post_type_field = new WP_Query(array(
                    'posts_per_page' => - 1,
                    'no_found_rows' => true,
                    'update_post_term_cache' => false,
                    'update_post_meta_cache' => false,
                    'post_type' => $field['post_type']
                ));
                if ($wpq_post_type_field->have_posts()) {
                    echo '<select ' . $idname . '>';
                    echo '<option value="" disabled selected style="display:none;">' . __('Select a value', 'wpupostmetas') . '</option>';
                    echo '<option value="">' . __('None', 'wpupostmetas') . '</option>';
                    while ($wpq_post_type_field->have_posts()) {
                        $wpq_post_type_field->the_post();
                        $post_id = get_the_ID();
                        echo '<option value="' . $post_id . '" ' . ((string)$post_id === (string)$value ? 'selected="selected"' : '') . '>' . get_the_title() . '</option>';
                    }
                    echo '</select>';
                }
                wp_reset_postdata();
            break;
            case 'textarea':
            case 'htmlcontent':
                echo '<textarea rows="3" cols="50" ' . $idname . '>' . $value . '</textarea>';
            break;
            case 'editor':
                wp_editor($value, $id, array(
                    'textarea_rows' => 3
                ));
            break;
            case 'table':

                $table_columns = $field['columns'];
                $table_width = count($table_columns);
                $table_basename = $id . '__';
                $table_maxline = isset($field['table_maxline']) && is_numeric($field['table_maxline']) ? $field['table_maxline'] : 10;
                $values = json_decode($value, true);

                echo '<div class="wpupostmetas-table-post-wrap">';
                echo '<table data-table-basename="' . $table_basename . '" data-table-maxline="' . $table_maxline . '" class="wpupostmetas-table-post">';
                echo '<thead><tr>';
                foreach ($table_columns as $col) {
                    echo '<th>' . $col['name'] . '</th>';
                }
                echo '</tr></thead>';
                echo '<tfoot><tr><td colspan="99"><button type="button" class="plus">+</button></td></tr></tfoot>';
                echo '<tbody>';

                echo $this->field_content_table_line($id, $table_columns, $values);

                echo '</tbody>';
                echo '</table>';
                echo '<input type="hidden" ' . $idname . ' value="" />';
                echo '<textarea class="template">';
                echo htmlentities($this->field_content_table_line($id, $table_columns));

                echo '</textarea>';
                echo '</div>';
            break;
            case 'color':
            case 'date':
            case 'email':
            case 'number':
            case 'url':
                echo '<input type="' . $field['type'] . '" ' . $idname . ' value="' . esc_attr($value) . '" />';
            break;
            default:
                echo '<input type="text" ' . $idname . ' value="' . esc_attr($value) . '" />';
        }
        if (isset($field['help'])) {
            echo '<div class="wpupostmetas-description-help">' . $field['help'] . '</div>';
        }
        if ($only_field === false) {
            echo '</td>';
            echo '</tr>';
        }
    }

    function field_content_table_line($id, $table_columns, $values = false) {

        $return_html = '';
        $table_basename = $id . '__';
        $table_toolbox = '<td class="table-toolbox">' . '<button type="button" class="delete">&times;</button>' . '<button type="button" class="down">&darr;</button>' . '<button type="button" class="up">&uarr;</button>' . '</td>';

        if (!is_array($values)) {
            $values = array();
            foreach ($table_columns as $col_id => $col) {
                $values[0][$col_id] = '';
            }
        }
        foreach ($values as $col) {
            $return_html.= '<tr>';
            foreach ($table_columns as $col_id => $col_value) {
                $value = '';
                if (isset($col[$col_id])) {
                    $value = $col[$col_id];
                }
                $return_html.= '<td>';
                ob_start();
                $this->field_content(false, $table_basename . $col_id, $col_value, true, $value);
                $return_html.= ob_get_clean();
                $return_html.= '</td>';
            }
            $return_html.= $table_toolbox . '</tr>';
        }

        return $return_html;
    }

    function list_attachments_options() {
        global $wpdb;

        if (!isset($_POST['post_id'], $_POST['post_value']) || !is_numeric($_POST['post_id'])) {
            die();
        }
        $args = array(
            'post_type' => 'attachment',
            'posts_per_page' => - 1,
            'post_status' => 'any',
            'post_parent' => $_POST['post_id']
        );
        $attachments = get_posts($args);
        echo '<option value="-">' . __('None', 'wpupostmetas') . '</option>';
        foreach ($attachments as $attachment) {
            $data_guid = '';
            if (strpos($attachment->post_mime_type, 'image/') !== false) {
                $data_guid = 'data-guid="' . $attachment->guid . '"';
            }
            echo '<option ' . $data_guid . ' value="' . $attachment->ID . '" ' . ($attachment->ID == $_POST['post_value'] ? 'selected="selected"' : '') . '>' . apply_filters('the_title', $attachment->post_title) . '</option>';
        }

        die();
    }

    /* ----------------------------------------------------------
      Utilities
    ---------------------------------------------------------- */

    /**
     * Control fields value
     *
     * @param unknown $id
     * @param unknown $field
     * @return unknown
     */
    function check_field_value($id, $field) {

        if (!isset($_POST[$id])) {
            return false;
        }

        $return = false;
        $value = trim($_POST[$id]);
        switch ($field['type']) {
            case 'attachment':
                $return = ctype_digit($value) ? $value : false;
            break;
            case 'email':
                $return = (filter_var($value, FILTER_VALIDATE_EMAIL) !== false || empty($value)) ? $value : false;
            break;
            case 'radio':
            case 'select':
                $return = array_key_exists($value, $field['datas']) ? $value : false;
            break;
            case 'textarea':
                $return = strip_tags($value);
            break;
            case 'htmlcontent':
                $return = $value;
            break;
            case 'editor':
                $return = $value;
            break;
            case 'table':
                $return = $value;
                if (!is_array(json_decode(stripslashes($value) , true))) {
                    $return = array();
                }
            break;
            case 'post':
            case 'page':
                $return = is_numeric($value) ? $value : false;
            break;
            case 'url':
                $return = (filter_var($value, FILTER_VALIDATE_URL) !== false || empty($value)) ? $value : false;
            break;
            default:
                $return = sanitize_text_field($value);
        }

        return $return;
    }

    /**
     * Control box datas
     *
     * @param unknown $box
     * @return unknown
     */
    function control_box_datas($box) {
        $default_box = array(
            'name' => 'Box',
            'capability' => 'delete_others_posts',

            // Default level : editor
            'post_type' => array(
                'post'
            )
        );
        $new_box = array();
        if (!is_array($box)) {
            $box = array();
        }
        $new_box = array_merge($default_box, $box);
        if (!is_array($new_box['post_type'])) {
            $new_box['post_type'] = $default_box['post_type'];
        }

        return $new_box;
    }

    /**
     * Control fields datas
     *
     * @param unknown $fields
     * @return unknown
     */
    function control_fields_settings($fields) {
        $default_field = array(
            'box' => '',
            'name' => 'Field Name',
            'type' => 'text',
            'admin_column' => false,
            'datas' => array()
        );

        $new_fields = array();

        foreach ($fields as $id => $field) {
            $new_fields[$id] = array_merge($default_field, $field);

            // Default datas to 0/1
            if (empty($new_fields[$id]['datas'])) {
                $new_fields[$id]['datas'] = array(
                    0 => __('No', 'wpupostmetas') ,
                    1 => __('Yes', 'wpupostmetas')
                );
            }

            // Default post type to post
            if (empty($new_fields[$id]['post_type'])) {
                $new_fields[$id]['post_type'] = 'post';
            }
        }

        return $new_fields;
    }

    /**
     * Returns fields for a given box
     *
     * @param unknown $box_id
     * @param unknown $fields
     * @return unknown
     */
    function fields_from_box($box_id, $fields) {
        $boxfields = array();
        foreach ($fields as $id => $field) {
            if (!isset($field['box'])) {
                continue;
            }
            if (!is_array($field['box'])) {
                $field['box'] = array(
                    $field['box']
                );
            }
            if (in_array($box_id, $field['box'])) {
                $boxfields[$id] = $field;
            }
        }
        return $boxfields;
    }

    /**
     * Load fields values
     */
    function load_fields() {

        // Load items
        if (empty($this->boxes)) {
            $this->boxes = apply_filters('wputh_post_metas_boxes', array());
        }
        if (empty($this->fields)) {
            $this->fields = apply_filters('wputh_post_metas_fields', array());
        }

        // Check content
        $this->fields = $this->control_fields_settings($this->fields);
    }

    /**
     * Obtain a list of languages
     *
     * @return array
     */
    private function get_languages() {
        global $q_config;
        $languages = array();

        // Obtaining from Qtranslate
        if (isset($q_config['enabled_languages'])) {
            foreach ($q_config['enabled_languages'] as $lang) {
                if (!in_array($lang, $languages) && isset($q_config['language_name'][$lang])) {
                    $languages[$lang] = $q_config['language_name'][$lang];
                }
            }
        }
        return $languages;
    }
}

$WPUPostMetas = new WPUPostMetas();

/* ----------------------------------------------------------
  Utilities
---------------------------------------------------------- */

/**
 * Get an option value with l10n
 *
 * @param integer  $post_id
 * @param string  $name
 * @param bool  $single
 *
 * @return mixed
 */
function wputh_l10n_get_post_meta($id, $name, $single) {
    global $q_config;

    $meta = get_post_meta($id, $name, $single);

    if (isset($q_config['language'])) {
        $meta_l10n = get_post_meta($id, $q_config['language'] . '___' . $name, $single);
        if (!empty($meta_l10n)) {
            $meta = $meta_l10n;
        }
    }

    return $meta;
}
