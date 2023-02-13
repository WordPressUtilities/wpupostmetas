<?php

/*
Plugin Name: WPU Post Metas
Plugin URI: https://github.com/WordPressUtilities/wpupostmetas
Update URI: https://github.com/WordPressUtilities/wpupostmetas
Description: Simple admin for post metas
Version: 0.32.0
Author: Darklg
Author URI: http://darklg.me/
License: MIT License
License URI: http://opensource.org/licenses/MIT
*/

defined('ABSPATH') or die(':(');

class WPUPostMetas {

    public $version = '0.32.0';
    public $boxes = array();
    public $fields = array();
    public $settings_update;
    public $plugin_description;
    public $admin_columns;
    public $admin_columns_sortable;
    public $admin_columns_filterable;

    /**
     * Initialize class
     */
    public function __construct() {
        add_action('plugins_loaded', array(&$this,
            'check_update'
        ));
        if (!is_admin()) {
            return;
        }
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
        add_action('qtranslate_add_admin_footer_js', array(&$this,
            'load_assets_qtranslatex'
        ));
        add_action('wp_ajax_wpupostmetas_attachments', array(&$this,
            'list_attachments_options'
        ));
        add_action('init', array(&$this,
            'init'
        ), 50);
    }

    public function check_update() {
        include dirname(__FILE__) . '/inc/WPUBaseUpdate/WPUBaseUpdate.php';
        $this->settings_update = new \wpupostmetas\WPUBaseUpdate(
            'WordPressUtilities',
            'wpupostmetas',
            $this->version,
            array(
                'tested' => '4.9.8'
            ));
    }

    public function init() {
        $this->load_fields();
        $this->set_admin_columns();
    }

    public function load_plugin_textdomain() {
        $lang_dir = dirname(plugin_basename(__FILE__)) . '/lang/';
        if (!load_plugin_textdomain('wpupostmetas', false, $lang_dir)) {
            load_muplugin_textdomain('wpupostmetas', $lang_dir);
        }
        $this->plugin_description = __('Simple admin for post metas', 'wpupostmetas');
    }

    public function load_assets() {
        $screen = get_current_screen();
        if ($screen->base != 'post') {
            return;
        }
        wp_register_script('wpupostmetas_scripts', plugins_url('assets/global.js', __FILE__), array('wp-color-picker'), $this->version);

        // Localize the script with new data
        wp_localize_script('wpupostmetas_scripts', 'wpupostmetas_tra', array(
            'delete_line_txt' => __('Delete this line?', 'wpupostmetas')
        ));
        wp_enqueue_style('wpupostmetas_style', plugins_url('assets/style.css', __FILE__), array(), $this->version);
        wp_enqueue_script('wpupostmetas_scripts');
        wp_enqueue_style('wp-color-picker');

    }

    public function load_assets_qtranslatex() {
        $screen = get_current_screen();
        if ($screen->base != 'post') {
            return;
        }
        wp_enqueue_script('wpupostmetas_qtranslatex', plugins_url('assets/qtranslatex.js', __FILE__), array(), $this->version);
    }

    /*
     * Admin list columns
    */

    public function set_admin_columns() {

        $this->admin_columns = array();
        $this->admin_columns_sortable = array();
        $this->admin_columns_filterable = array();
        foreach ($this->boxes as $box_id => $box) {
            if (isset($box['post_type']) && is_array($box['post_type'])) {
                foreach ($box['post_type'] as $post_type) {
                    if (!isset($this->admin_columns[$post_type])) {
                        $this->admin_columns[$post_type] = array();
                    }
                    foreach ($this->fields as $field_id => $field) {

                        if ($field['admin_column'] !== true || $field['box'] != $box_id) {
                            continue;
                        }
                        // Column
                        $this->admin_columns[$post_type][$field_id] = $field;
                        // Sortable
                        if (isset($field['admin_column_sortable']) && $field['admin_column_sortable'] === true) {
                            $this->admin_columns_sortable['wpupostmetas_' . $field_id] = $field_id;
                        }
                        // Filterable
                        if (isset($field['admin_column_filterable']) && $field['admin_column_filterable'] === true) {
                            $this->admin_columns_filterable[$field_id] = $field_id;
                        }
                    }
                }
            }
        }

        foreach ($this->admin_columns as $post_type => $values) {
            if (empty($values)) {
                continue;
            }
            add_filter('manage_edit-' . $post_type . '_columns', array(&$this,
                'set_columns_head'
            ), 10, 2);
            add_action('manage_' . $post_type . '_posts_custom_column', array(&$this,
                'set_columns_content'
            ), 10, 2);
            add_filter('manage_edit-' . $post_type . '_sortable_columns', array(&$this,
                'set_columns_sortable'
            ), 10, 2);
            add_action('pre_get_posts', array(&$this,
                'set_columns_sortable_orderby'
            ));
            add_action('pre_get_posts', array(&$this,
                'set_columns_sortable_filterby'
            ));
        }
    }

    public function set_columns_sortable_orderby($query) {
        $orderby = $query->get('orderby');
        foreach ($this->admin_columns_sortable as $key => $val) {
            if ($val == $orderby) {
                $query->set('meta_key', $val);
            }
        }
    }

    public function set_columns_sortable_filterby($query) {
        if (!isset($_GET['meta_key']) || !isset($_GET['meta_value'])) {
            return;
        }
        foreach ($this->admin_columns_filterable as $key => $val) {
            if ($val == $_GET['meta_key']) {
                $query->set('meta_key', $val);
                $query->set('meta_value', $_GET['meta_value']);
            }
        }
    }

    // Sort columns
    public function set_columns_sortable($columns) {
        foreach ($this->admin_columns_sortable as $key => $val) {
            $columns[$key] = $val;
        }
        return $columns;
    }

    // Display columns header
    public function set_columns_head($defaults) {
        global $post;
        $current_post_type = get_query_var('post_type');
        foreach ($this->admin_columns as $post_type => $values) {
            if ($post_type == $current_post_type) {
                foreach ($values as $field_id => $field) {
                    $defaults['wpupostmetas_' . $field_id] = $field['name'];
                }
            }
        }
        return $defaults;
    }

    // Display column content
    public function set_columns_content($column_name, $post_ID) {
        global $post;
        $post_type = 'any';
        if (isset($post->post_type)) {
            $post_type = $post->post_type;
        }

        // Each post type
        foreach ($this->admin_columns as $post_type => $values) {
            foreach ($values as $field_id => $field) {

                // Display column value
                if ($column_name == 'wpupostmetas_' . $field_id) {
                    $display_value = '';
                    $value = get_post_meta($post_ID, $field_id, 1);
                    switch ($field['type']) {
                    case 'select':

                        // If valid select, display data label
                        if (isset($field['datas']) && array_key_exists($value, $field['datas'])) {
                            $display_value = $field['datas'][$value];
                            break;
                        }

                    case 'image':
                    case 'attachment':

                        // If valid select, display data label
                        if (is_numeric($value)) {
                            $display_value = '<img src="' . wp_get_attachment_thumb_url($value) . '" alt="" />';
                            break;
                        }
                    default:
                        $display_value = $value;
                    }

                    if (isset($field['admin_column_filterable']) && $field['admin_column_filterable'] === true) {
                        $display_value = '<a href="' . admin_url('edit.php?post_type=' . urlencode($post_type) . '&meta_key=' . urlencode($field_id) . '&meta_value=' . urlencode($value)) . '">' . $display_value . '</a>';
                    }

                    echo apply_filters('wputh_post_metas_admin_column_content_callback', $display_value, $field_id, $post_ID, $field, $value);

                }
            }
        }
    }

    /**
     * Adds meta boxes
     */
    public function add_custom_box() {
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
                    if (isset($box['post_id'])) {
                        if (!is_array($box['post_id'])) {
                            $box['post_id'] = array($box['post_id']);
                        }
                        if (!isset($post->ID) || !in_array($post->ID, $box['post_id'])) {
                            continue;
                        }
                    }
                    if (isset($box['page_template'])) {
                        if (!isset($post->ID)) {
                            continue;
                        }
                        if (!is_array($box['page_template'])) {
                            $box['page_template'] = array($box['page_template']);
                        }
                        $current_template = get_page_template_slug($post->ID);
                        if (!in_array($current_template, $box['page_template'])) {
                            continue;
                        }
                    }
                    add_meta_box('wputh_box_' . $id, $box['name'], array(
                        $this,
                        'box_content'
                    ), $type, $box['context']);
                }
            }
        }
    }

    /**
     * Saves meta box content
     *
     * @param unknown $post_id
     */
    public function save_postdata($post_id) {
        $languages = $this->get_languages();

        $boxes = $this->boxes;
        $fields = $this->fields;

        $post_type = isset($_POST['post_type']) ? $_POST['post_type'] : 'post';

        // First we need to check if the current user is authorised to do this action.
        if ('page' == $post_type) {
            if (!current_user_can('edit_page', $post_id)) {
                return;
            }

        } else {
            if (!current_user_can('edit_post', $post_id)) {
                return;
            }

        }

        // Secondly we need to check if the user intended to change this value.
        if (!isset($_POST['wputh_post_metas_noncename']) || !wp_verify_nonce($_POST['wputh_post_metas_noncename'], plugin_basename(__FILE__))) {
            return;
        }

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
    public function box_content($post, $details) {
        $languages = $this->get_languages();
        $fields = $this->fields;
        $boxid = str_replace('wputh_box_', '', $details['id']);
        $boxfields = $this->fields_from_box($boxid, $this->fields);
        wp_nonce_field(plugin_basename(__FILE__), 'wputh_post_metas_noncename');
        echo apply_filters('wpupostmetas__box_content__before_table', '', $boxid, $details, $boxfields);
        echo '<table class="wpupostmetas-table">';
        foreach ($fields as $id => $field) {
            if (array_key_exists($id, $boxfields)) {

                // Multilingual field
                if (isset($field['lang']) && $field['lang'] && !empty($languages)) {

                    if (!$this->qtranslatex) {
                        echo '<tr><td colspan="2"><div class="multilingual-wrapper">';
                        echo '<div class="multilingual-toolbox">';
                        foreach ($languages as $idlang => $lang) {
                            echo ' <span data-i="' . $id . '">' . $lang . '</span> ';
                        }
                        echo '</div>';
                        echo '<table class="wpupostmetas-table wpupostmetas-table--multilingual">';
                    }

                    foreach ($languages as $idlang => $lang) {
                        $this->field_content($post, $idlang . '___' . $id, $field, false, false, $idlang);
                    }

                    if (!$this->qtranslatex) {
                        echo '</table></div></td></tr>';
                    }
                } else {
                    $this->field_content($post, $id, $field);
                }
            }
        }
        echo '</table>';
        echo apply_filters('wpupostmetas__box_content__after_table', '', $boxid, $details, $boxfields);
    }

    /**
     * Shows meta box field
     *
     * @param unknown $post
     * @param unknown $id
     * @param unknown $field
     */
    public function field_content($post, $id, $field, $only_field = false, $val = false, $id_lang = false) {
        $value = '';
        $main_post_id = 0;
        if (is_object($post)) {
            $main_post_id = $post->ID;
            $value = @trim(get_post_meta($main_post_id, $id, true));

            // If new post, try to load a default value
            if (isset($field['default'], $post->post_title, $post->post_content) && empty($post->post_title) && empty($post->post_content) && empty($value)) {
                $value = $this->field_content_value($field['default'], $id_lang);
            }

            // Test if non single value contains an empty value
            if (isset($field['default']) && empty($value)) {
                $arr_post_meta = get_post_meta($main_post_id, $id, false);
                if (!isset($arr_post_meta[0])) {
                    $value = $this->field_content_value($field['default'], $id_lang);
                    $i = update_post_meta($main_post_id, $id, $value);
                }
            }
        }
        if ($val !== false) {
            $value = $val;
        }

        if (!isset($field['tab']) || !$field['tab']) {
            $field['tab'] = '';
        }

        $orderby = isset($field['orderby']) ? $field['orderby'] : 'name';
        $order = isset($field['order']) ? $field['order'] : 'ASC';
        $el_id = 'el_id_' . $id;
        $idname = 'name="' . $id . '"';

        if (!isset($field['required'])) {
            $field['required'] = false;
        }

        $required_attr = $field['required'] ? ' required="required" aria-required="true" ' : '';
        $required_label = $field['required'] ? ' <em>*</em>' : '';

        if (isset($field['type']) && ($field['type'] == 'separator' || $field['type'] == 'title')) {
            if ($only_field === false) {
                echo '<tr ' . ($field['tab'] ? 'data-wpufieldtab="' . $field['tab'] . '"' : '') . ' class="' . $field['type'] . '"><td colspan="2">';
            }
            if ($field['type'] == 'separator') {
                echo '<hr />';
            }
            if ($field['type'] == 'title') {
                echo '<h3>' . $field['name'] . '</h3>';
            }
            if ($only_field === false) {
                echo '</td></tr>';
            }
            return;
        }

        if ($only_field === false) {
            $idname = 'id="' . $el_id . '" name="' . $id . '"';
            echo '<tr ' . ($field['tab'] ? 'data-wpufieldtab="' . $field['tab'] . '"' : '') . '  ' . ($id_lang !== false ? 'data-wpupostmetaslang="' . $id_lang . '"' : '') . '>';
            echo '<th valign="top"><label for="el_id_' . $id . '">' . $field['name'] . $required_label . ' :</label></th>';
            echo '<td valign="top">';
            if ($id_lang !== false) {
                echo '<div class="qtranxs-translatable">';
            }
        }

        $field_datas = array(
            'Yes',
            'No'
        );
        if (isset($field['datas']) && is_array($field['datas'])) {
            $field_datas = $field['datas'];
        }

        if (!isset($field['type'])) {
            $field['type'] = '';
        }

        if (!empty($field['placeholder'])) {
            $idname .= ' placeholder="' . esc_attr($field['placeholder']) . '"';
        }

        switch ($field['type']) {
        case 'attachment':
        case 'image':

            $img_url = '';
            $file_name = '';
            if (is_numeric($value)) {
                $img_url_tmp = wp_get_attachment_image_src($value);
                if (is_array($img_url_tmp)) {
                    $img_url = $img_url_tmp[0];
                } else {
                    $file_name = basename(get_attached_file($value));
                }
            }

            $label_delete = __('Remove image', 'wpupostmetas');
            $label_choose = __('Choose an image', 'wpupostmetas');
            $label_change = __('Change image', 'wpupostmetas');
            if ($field['type'] == 'attachment') {
                $label_delete = __('Remove file', 'wpupostmetas');
                $label_choose = __('Choose a file', 'wpupostmetas');
                $label_change = __('Change file', 'wpupostmetas');
            }

            $label = (!empty($img_url) || !empty($file_name)) ? $label_change : $label_choose;

            echo '<div data-type="' . $field['type'] . '" class="wpupostmetas-field-image ' . (!empty($img_url) ? 'wpupostmetas-field-image--hasimage' : '') . ' ' . (!empty($file_name) ? 'wpupostmetas-field-image--hasfile' : '') . '">';
            echo '<div class="wpupostmetas-field-file__name">' . $file_name . '</small></div>';
            echo '<img src="' . $img_url . '"  alt="" /> ';
            echo '<button class="button primary wpupostmetas-image-link" data-attid="' . esc_attr($value) . '" data-addlabel="' . esc_attr($label_choose) . '" data-changelabel="' . esc_attr($label_change) . '" type="button">' . $label . '</button>';
            echo '<input class="wpupostmetas-field-image__preview" ' . $required_attr . ' type="hidden" ' . $idname . ' value="' . esc_attr($value) . '" />';
            echo '<div class="wpupostmetas-field-image__remove"><small><a href="#">' . $label_delete . '</a></small></div>';
            echo '</div>';

            break;
        case 'select':
            echo '<select ' . $required_attr . ' ' . $idname . '>';
            echo '<option value="" disabled selected style="display:none;">' . __('Select a value', 'wpupostmetas') . '</option>';
            foreach ($field_datas as $key => $var) {
                echo '<option value="' . $key . '" ' . ((string) $key === (string) $value ? 'selected="selected"' : '') . '>' . $var . '</option>';
            }
            echo '</select>';
            break;
        case 'radio':
            foreach ($field_datas as $key => $var) {
                $item_id = 'radio_' . $id . '_' . $key;
                echo '<input type="radio" id="' . $item_id . '" ' . $idname . ' value="' . $key . '" ' . ((string) $key === (string) $value ? 'checked="checked"' : '') . ' />';
                echo '<label for="' . $item_id . '">' . $var . '</label>';
            }
            break;
        case 'checkbox':
            echo '<label><input type="checkbox" ' . $idname . ' ' . checked($value, '1', 0) . ' value="1" /> ' . (isset($field['checkbox_label']) ? $field['checkbox_label'] : $field['name']) . $required_label . '</label>';
            echo '<input ' . $required_attr . ' type="hidden" name="' . $id . '__check" value="1" />';
            break;
        case 'page':
            wp_dropdown_pages(array(
                'name' => $id,
                'selected' => $value,
                'show_option_none' => __('None', 'wpupostmetas')
            ));
            break;
        case 'post':
            $posts = get_posts(array(
                'posts_per_page' => -1,
                'no_found_rows' => true,
                'update_post_term_cache' => false,
                'update_post_meta_cache' => false,
                'post_type' => $field['post_type'],
                'orderby' => $orderby,
                'order' => $order
            ));
            if (!empty($posts)) {
                echo '<select ' . $required_attr . ' ' . $idname . '>';
                echo '<option value="" disabled selected style="display:none;">' . __('Select a value', 'wpupostmetas') . '</option>';
                echo '<option value="">' . __('None', 'wpupostmetas') . '</option>';
                foreach ($posts as $post_item) {
                    $post_id = $post_item->ID;
                    echo '<option value="' . $post_id . '" ' . ((string) $post_id === (string) $value ? 'selected="selected"' : '') . '>' . $post_item->post_title . '</option>';
                }
                echo '</select>';
            }
            break;
        case 'textarea':
        case 'htmlcontent':
            echo '<textarea ' . $required_attr . ' rows="3" cols="50" ' . $idname . '>' . $value . '</textarea>';
            break;
        case 'editor':
            $editor_args = array(
                'textarea_rows' => 3
            );
            if (isset($field['editor_args']) && is_array($field['editor_args'])) {
                $editor_args = array_merge($editor_args, $field['editor_args']);
            }
            wp_editor($value, $id, $editor_args);
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
            foreach ($table_columns as $col_id => $col) {
                echo '<th>' . (isset($col['name']) ? $col['name'] : ucfirst($col_id)) . '</th>';
            }
            echo '</tr></thead>';
            echo '<tfoot><tr><td colspan="99">';
            echo '<button type="button" class="button-secondary plus" title="' . __('Add a new line', 'wpupostmetas') . '"><span class="dashicons dashicons-welcome-add-page"></span> ' . __('Add a new line', 'wpupostmetas') . '</button> ';
            echo '<button type="button" class="button-secondary copy" title="' . __('Copy last line', 'wpupostmetas') . '"><span class="dashicons dashicons-admin-appearance"></span> ' . __('Copy last line', 'wpupostmetas') . '</button>';
            echo '</td></tr></tfoot>';
            echo '<tbody>';

            echo $this->field_content_table_line($id, $table_columns, $values);

            echo '</tbody>';
            echo '</table>';
            echo '<input class="wpupostmetas-table-main-value" type="hidden" ' . $idname . ' value="" />';
            echo '<textarea class="template">';
            echo htmlentities($this->field_content_table_line($id, $table_columns));

            echo '</textarea>';
            echo '</div>';
            break;
        case 'color':
        case 'date':
        case 'datetime-local':
        case 'email':
        case 'number':
        case 'url':
            echo '<input ' . $required_attr . ' type="' . $field['type'] . '" ' . $idname . ' value="' . esc_attr($value) . '" />';
            break;
        default:
            echo '<input ' . $required_attr . ' type="text" ' . $idname . ' value="' . esc_attr($value) . '" />';
        }
        if (isset($field['help'])) {
            echo '<div class="wpupostmetas-description-help">' . $field['help'] . '</div>';
        }
        if ($only_field === false) {
            if ($id_lang !== false) {
                echo '</div>';
            }
            echo '</td>';
            echo '</tr>';
        }
    }

    public function field_content_value($default, $id_lang) {
        $value = $default;
        if (is_array($default) && $id_lang) {
            /* Lang default key exists */
            if (isset($default[$id_lang])) {
                return $default[$id_lang];
            }
            /* Take first available value */
            else {
                foreach ($default as $value_tmp) {
                    return $value_tmp;
                }
            }
        }

        return $value;
    }

    public function field_content_table_line($id, $table_columns, $values = false) {

        $demo_line = ($values == false);
        $return_html = '';
        $table_basename = $id . '__';
        $table_toolbox = '<td class="table-toolbox"><div>' . '<button type="button" class="delete">&times;</button>' . '<button type="button" class="down">&darr;</button>' . '<button type="button" class="up">&uarr;</button>' . '</div></td>';

        if (!is_array($values)) {
            $values = array();
            foreach ($table_columns as $col_id => $col) {
                $values[0][$col_id] = '';
            }
        }
        foreach ($values as $col) {
            $return_html_line = '<tr>';
            $has_filled_value = false;
            foreach ($table_columns as $col_id => $col_value) {
                $value = '';
                if (isset($col[$col_id])) {
                    $value = $col[$col_id];
                }
                if (!empty($value)) {
                    $has_filled_value = true;
                }
                $return_html_line .= '<td>';
                ob_start();
                $this->field_content(false, $table_basename . $col_id, $col_value, true, $value);
                $return_html_line .= ob_get_clean();
                $return_html_line .= '</td>';
            }
            $return_html_line .= $table_toolbox . '</tr>';
            if ($has_filled_value || $demo_line) {
                $return_html .= $return_html_line;
            }
        }

        return $return_html;
    }

    public function list_attachments_options() {
        global $wpdb;

        if (!isset($_POST['post_id'], $_POST['post_value']) || !is_numeric($_POST['post_id'])) {
            die();
        }
        $args = array(
            'post_type' => 'attachment',
            'posts_per_page' => -1,
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
    public function check_field_value($id, $field) {
        if (!isset($_POST[$id]) && $field['type'] != 'checkbox') {
            return false;
        }

        if ($field['type'] == 'checkbox' && isset($_POST[$id . '__check'])) {
            return isset($_POST[$id]) ? '1' : '0';
        }

        $return = false;
        $value = trim($_POST[$id]);
        switch ($field['type']) {
        case 'image':
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
            $return = stripslashes($value);
            if (!is_array(json_decode($return, true))) {
                $return = array();
            }
            break;
        case 'post':
        case 'page':
            $return = (is_numeric($value) || empty($value)) ? $value : false;
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
    public function control_box_datas($box) {
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
    public function control_fields_settings($fields) {
        $default_field = array(
            'box' => '',
            'name' => 'Field Name',
            'type' => 'text',
            'placeholder' => '',
            'admin_column' => false,
            'datas' => array()
        );

        $new_fields = array();

        foreach ($fields as $id => $field) {
            $new_fields[$id] = array_merge($default_field, $field);

            // Default datas to 0/1
            if (empty($new_fields[$id]['datas'])) {
                $new_fields[$id]['datas'] = array(
                    0 => __('No', 'wpupostmetas'),
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
    public function fields_from_box($box_id, $fields) {
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
    public function load_fields() {

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
        $this->qtranslate = false;
        $this->qtranslatex = false;
        $languages = array();

        // Obtaining from Qtranslate
        if (isset($q_config['enabled_languages'])) {
            $this->qtranslate = true;
            if (defined('QTX_VERSION')) {
                $this->qtranslatex = true;
            }
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
function wputh_l10n_get_post_meta($id, $name, $single = false, $lang = false) {
    global $q_config;

    $meta = get_post_meta($id, $name, $single);

    /* Define lang */

    if ($lang === false) {
        if (isset($q_config['language'])) {
            $lang = $q_config['language'];
        }
    }

    /* Get meta value */

    if (isset($q_config['language'])) {
        $meta_l10n = get_post_meta($id, $lang . '___' . $name, $single);
        if (!empty($meta_l10n)) {
            $meta = $meta_l10n;
        }
    }

    /* Use default language value */

    $default_language = '';
    if (isset($q_config['language'])) {
        $default_language = $q_config['enabled_languages'][0];
    }
    $default_language = apply_filters('wputh_l10n_get_post_meta__defaultlang', $default_language);

    $use_default = apply_filters('wputh_l10n_get_post_meta__usedefaultlang', true);
    if (empty($meta) && $use_default && $lang != $default_language) {
        return wputh_l10n_get_post_meta($id, $name, $single, $default_language);
    }

    return $meta;
}
