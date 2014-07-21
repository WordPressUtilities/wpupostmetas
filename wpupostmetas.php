<?php
/*
Plugin Name: WPU Post Metas
Plugin URI: http://github.com/Darklg/WPUtilities
Description: Simple admin for post metas
Version: 0.9.2
Author: Darklg
Author URI: http://darklg.me/
License: MIT License
License URI: http://opensource.org/licenses/MIT
*/

class WPUPostMetas
{

    var $boxes = array();
    var $fields = array();

    /**
     * Initialize class
     */
    function __construct() {
        if (is_admin()) {
            load_plugin_textdomain('wpupostmetas', false, dirname(plugin_basename(__FILE__)) . '/lang/');
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
        }
    }

    function load_assets() {
        $screen = get_current_screen();
        if ($screen->base == 'post') {
            wp_enqueue_style('wpupostmetas_style', plugins_url('assets/style.css', __FILE__));
            wp_enqueue_script('wpupostmetas_scripts', plugins_url('/assets/global.js', __FILE__));
        }
    }

    /**
     * Adds meta boxes
     */
    function add_custom_box() {
        $this->load_fields();
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
                    ) , $type);
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
        $this->load_fields();
        $languages = $this->get_languages();

        $boxes = $this->boxes;
        $fields = $this->fields;

        $fields = $this->control_fields_datas($fields);
        $post_type = isset($_POST['post_type']) ? $_POST['post_type'] : 'post';

        // First we need to check if the current user is authorised to do this action.
        if ('page' == $post_type) {
            if (!current_user_can('edit_page', $post_id)) return;
        } else {
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
                    } else {
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
        $fields = $this->control_fields_datas($fields);
        wp_nonce_field(plugin_basename(__FILE__) , 'wputh_post_metas_noncename');
        echo '<table class="wpupostmetas-table">';
        foreach ($fields as $id => $field) {
            if ('wputh_box_' . $field['box'] == $details['id']) {

                // Multilingual field
                if (isset($field['lang']) && $field['lang'] && !empty($languages)) {
                    foreach ($languages as $idlang => $lang) {
                        $new_field = $field;
                        $new_field['name'] = '[' . $idlang . '] ' . $new_field['name'];
                        $this->field_content($post, $idlang . '___' . $id, $new_field);
                    }
                } else {
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
    function field_content($post, $id, $field) {
        $value = trim(get_post_meta($post->ID, $id, true));

        // If new post, try to load a default value
        if (isset($field['default'], $post->post_title, $post->post_content) && empty($post->post_title) && empty($post->post_content) && empty($value)) {
            $value = $field['default'];
        }

        $idname = 'id="el_id_' . $id . '" name="' . $id . '"';
        echo '<tr>';
        echo '<td valign="top" style="width: 150px;"><label for="el_id_' . $id . '">' . $field['name'] . ' :</label></td>';
        echo '<td valign="top" style="width: 450px;">';
        switch ($field['type']) {
            case 'attachment':
                $args = array(
                    'post_type' => 'attachment',
                    'posts_per_page' => - 1,
                    'post_status' => 'any',
                    'post_parent' => $post->ID
                );
                $attachments = get_posts($args);
                if ($attachments) {
                    echo '<div class="wpupostmetas-attachments__container"><span class="before"></span>';
                    echo '<div class="preview-img" id="preview-' . $id . '"></div>';
                    echo '<select ' . $idname . ' class="wpupostmetas-attachments" data-postid="' . $post->ID . '" data-postvalue="' . $value . '">';
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
                } else {
                    echo '<span>' . __('No attachments', 'wpupostmetas') . '</span>';
                }
                break;

            case 'select':
                echo '<select ' . $idname . '>';
                echo '<option value="" disabled selected style="display:none;">' . __('Select a value', 'wpupostmetas') . '</option>';
                foreach ($field['datas'] as $key => $var) {
                    echo '<option value="' . $key . '" ' . ((string)$key === (string)$value ? 'selected="selected"' : '') . '>' . $var . '</option>';
                }
                echo '</select>';
                break;

            case 'textarea':
            case 'htmlcontent':
                echo '<textarea rows="3" cols="50" ' . $idname . '>' . $value . '</textarea>';
                break;

            case 'editor':
                wp_editor($value, $id);
                break;

            case 'date':
            case 'email':
            case 'number':
            case 'url':
                echo '<input type="'.$field['type'].'" ' . $idname . ' value="' . esc_attr($value) . '" />';
                break;

            default:
                echo '<input type="text" ' . $idname . ' value="' . esc_attr($value) . '" />';
        }
        echo '</td>';
        echo '</tr>';
    }

    function list_attachments_options() {
        global $wpdb;

        // this is how you get access to the database
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
    function control_fields_datas($fields) {
        $default_field = array(
            'box' => '',
            'name' => 'Field Name',
            'type' => 'text',
            'datas' => array()
        );

        $new_fields = array();

        foreach ($fields as $id => $field) {
            $new_fields[$id] = array_merge($default_field, $field);

            // if incomplete "select" : defaults to txt
            if ($new_fields[$id]['type'] == 'select' && empty($new_fields[$id]['datas'])) {
                $new_fields[$id]['type'] = 'text';
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
            if (isset($field['box']) && $field['box'] == $box_id) {
                $boxfields[$id] = $field;
            }
        }
        return $boxfields;
    }

    /**
     * Load fields values
     */
    function load_fields() {
        if (empty($this->boxes)) {
            $this->boxes = apply_filters('wputh_post_metas_boxes', array());
        }
        if (empty($this->fields)) {
            $this->fields = apply_filters('wputh_post_metas_fields', array());
        }
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
 * Get an option value with l18n
 *
 * @param integer  $post_id
 * @param string  $name
 * @param bool  $single
 *
 * @return mixed
 */
function wputh_l18n_get_post_meta($id, $name, $single) {
    global $q_config;

    $meta = get_post_meta($id, $name, $single);

    if (isset($q_config['language'])) {
        $meta_l18n = get_post_meta($id, $q_config['language'] . '___' . $name, $single);
        if (!empty($meta_l18n)) {
            $meta = $meta_l18n;
        }
    }

    return $meta;
}