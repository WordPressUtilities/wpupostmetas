jQuery(document).ready(function() {
    var attachments_select = jQuery('.wpupostmetas-attachments');
    // Display image preview
    attachments_select.on('change', function() {
        wpupostmetas_setattachmentpreview(jQuery(this));
    });
    attachments_select.each(function() {
        var self = jQuery(this);
        wpupostmetas_setattachmentpreview(self);
        wpupostmetas_setattachmentrefresh(self);
    });

    wpupostmetas_setmultilingual();
    wpupostmetas_settabs();
    wpupostmetas_settables();
    wpupostmetas_setimages();

    if (typeof qTranslateConfig == 'object') {
        jQuery('.multilingual-wrapper .wp-editor-area.qtranxs-translatable').each(function() {
            qTranslateConfig.qtx.removeContentHook(this);
        });
    }

    jQuery('.wpupostmetas-table input[type="color"]').each(function() {
        jQuery(this).attr('type', 'text').wpColorPicker();
    });
});

/* ----------------------------------------------------------
  Multilingual
---------------------------------------------------------- */

var wpupostmetas_setmultilingual = function() {
    var display_line = function(table, i) {
        var lines = table.find('.wpupostmetas-table--multilingual > tbody > tr'),
            pagers = table.find('[data-i]');
        lines.removeClass('is-visible');
        lines.eq(i).addClass('is-visible');

        pagers.removeClass('current');
        pagers.eq(i).addClass('current');
    };
    jQuery('.multilingual-wrapper').each(function() {
        var $this = jQuery(this);
        display_line($this, 0);
        $this.on('click', '[data-i]', function(e) {
            e.preventDefault();
            display_line($this, parseInt(jQuery(this).attr('data-i'), 20));
        });
    });
};

/* ----------------------------------------------------------
  Tabs
---------------------------------------------------------- */

var wpupostmetas_settabs = function() {

    jQuery('.wpupostmetas-table').each(function() {
        set_tabs_elements(jQuery(this));
    });

    function set_tabs_elements($first_table) {
        /* Build elements */
        var $tab_fields = $first_table.find('[data-wpufieldtab]');

        if ($tab_fields.length < 1) {
            return;
        }

        /* Extract tabs */
        var tab_ids = {};
        $tab_fields.each(function() {
            if (this.getAttribute('data-wpufieldtab')) {
                tab_ids[this.getAttribute('data-wpufieldtab')] = this.getAttribute('data-wpufieldtab');
            }
        });

        /* Generate tabs */
        var $tab_wrapper = jQuery('<div class="wpupostmetas-tabs__wrapper nav-tab-small nav-tab-wrapper"></div>');
        for (var _tab in tab_ids) {
            $tab_wrapper.append(jQuery('<button class="nav-tab" data-tab="' + _tab + '">' + _tab + '</button>'));
        }
        $first_table.parent().prepend($tab_wrapper);

        /* Switch tab on click */
        $tab_wrapper.on('click', 'button', function(e) {
            e.preventDefault();
            set_tab_action(this.getAttribute('data-tab'), $tab_fields, $tab_wrapper);
        });

        /* Initial tab */
        set_tab_action(tab_ids[Object.keys(tab_ids)[0]], $tab_fields, $tab_wrapper);

    }

    function set_tab_action(tab_id, $tab_fields, $tab_wrapper) {
        /* Display fields */
        $tab_fields.addClass('is-hidden');
        $tab_fields.filter('[data-wpufieldtab="' + tab_id + '"]').removeClass('is-hidden');
        /* Change tab status */
        $tab_wrapper.find('[data-tab]').removeClass('nav-tab-active');
        $tab_wrapper.find('[data-tab="' + tab_id + '"]').addClass('nav-tab-active');
    }

};

/* ----------------------------------------------------------
  Set tables
---------------------------------------------------------- */

var wpupostmetas_groupArray = function(arr, options) {
    var newArr = [{}],
        tmpName = '',
        currentCol = 0;
    for (var i = 0, len = arr.length; i < len; i++) {
        if (i > 0 && i % options.cols == 0) {
            currentCol++;
            newArr[currentCol] = {};
        }
        tmpName = arr[i]['name'].replace(options.basename, '');
        newArr[currentCol][tmpName] = arr[i]['value'];
    }

    return newArr;
};

var wpupostmetas_settable = function(table, input) {
    var table_basename = table.attr('data-table-basename');
    var results = wpupostmetas_groupArray(table.find('[name]').serializeArray(), {
        basename: table_basename,
        cols: table.find('thead th').length
    });
    if (!input) {
        input = table.parent().find('.wpupostmetas-table-main-value');
    }
    input.val(JSON.stringify(results));
};

var wpupostmetas_settables = function() {
    var tables = jQuery('.wpupostmetas-table-post');
    tables.each(function() {
        var table = jQuery(this),
            table_maxline = parseInt(table.attr('data-table-maxline'), 10),
            tableParent = table.parent(),
            tpl = tableParent.find('.template'),
            input = tableParent.find('.wpupostmetas-table-main-value');

        wpupostmetas_settable(table, input);

        // Save values in field
        table.on('change keydown keyup', '[name]', function() {
            wpupostmetas_settable(table, input);
        });

        // Add a new line
        tableParent.on('click', '.plus', function(e) {
            e.preventDefault();
            var nbLines = tableParent.find('tbody tr').length;
            if (nbLines < table_maxline) {
                var newLine = jQuery(tpl.val());
                table.append(newLine);
                jQuery(window).trigger('wpupostmetas__action__add_line', newLine);
            }
        });

        // Copy last line
        tableParent.on('click', '.copy', function(e) {
            e.preventDefault();
            var nbLines = tableParent.find('tbody tr').length;
            if (nbLines < table_maxline) {
                var lastline = jQuery('<tr>' + tableParent.find('tbody tr:last-child').html() + '</tr>');
                table.append(lastline);
                jQuery(window).trigger('wpupostmetas__action__copy_last_line', lastline);
            }
        });

        // Delete a line
        tableParent.on('click', '.delete', function(e) {
            e.preventDefault();
            if (confirm(wpupostmetas_tra.delete_line_txt)) {
                jQuery(this).closest('tr').remove();
                jQuery(window).trigger('wpupostmetas__action__delete_line_txt');
            }
            wpupostmetas_settable(table, input);
        });

        // Move a line
        tableParent.on('click', '.down, .up', function(e) {
            e.preventDefault();
            var $this = jQuery(this),
                tr = $this.closest('tr');
            if ($this.hasClass('up')) {
                tr.insertBefore(tr.prev());
            }
            else {
                tr.insertAfter(tr.next());
            }
            jQuery(window).trigger('wpupostmetas__action__moving_line', tr);
            wpupostmetas_settable(table, input);
        });

    });

};

/* ----------------------------------------------------------
  Set refresh event
---------------------------------------------------------- */

var wpupostmetas_setattachmentrefresh = function(self) {
    var refreshClass = 'wpupostmetas-attachments__refresh',
        refresh = jQuery('<span class="' + refreshClass + '"></span>'),
        sparent = self.parent();
    sparent.append(refresh);

    // Refresh all items on click
    refresh.on('click', function() {
        jQuery('.' + refreshClass).trigger('click-refresh');
    });

    // Refresh on click
    refresh.on('click-refresh', function(e) {

        /* Disable click */
        sparent.addClass('is-disabled');
        e.preventDefault();
        jQuery.post(ajaxurl, {
            action: 'wpupostmetas_attachments',
            post_id: self.attr('data-postid'),
            post_value: self.val(),
        }, function(response) {
            self.html(response);
            /* Enable click */
            sparent.removeClass('is-disabled').attr('data-attachment-count', self.find('option').length - 1);
            /* Reload select */
            wpupostmetas_setattachmentpreview(self);
        });
    });
};

/* ----------------------------------------------------------
  Set attachment preview image
---------------------------------------------------------- */

var wpupostmetas_setattachmentpreview = function(self) {
    var selected_value = self.find(':selected'),
        guid = false,
        img = false,
        prev = false;
    if (!selected_value) {
        selected_value = self.find('[data-guid]');
    }
    guid = selected_value.attr('data-guid');
    if (guid) {
        img = '<img src="' + guid + '" alt="" />';
    }
    if (!img) {
        img = '';
    }
    prev = self.closest('td').find('.preview-img');
    if (prev) {
        prev.html(img);
    }
};

/* ----------------------------------------------------------
  Image field
---------------------------------------------------------- */

function wpupostmetas_setimages() {
    function setup_image() {
        var $this = jQuery(this);
        if ($this.attr('data-setup') == '1') {
            return;
        }
        $this.attr('data-setup', 1);

        var mediatype = $this.attr('data-type'),
            $txtPreview = $this.find('.wpupostmetas-field-file__name'),
            $imgPreview = $this.find('img'),
            $imgRemove = $this.find('.wpupostmetas-field-image__remove a'),
            $imgButton = $this.find('button'),
            $imgField = $this.find('.wpupostmetas-field-image__preview');

        var wpmediaobj = {
            multiple: false,
            state: 'insert',
            frame: 'post'
        };

        if (mediatype == 'image') {
            wpmediaobj.library = {
                type: 'image'
            };
        }

        var frame = wp.media(wpmediaobj);

        // Open on selected image
        frame.on('open', function() {
            if (!$imgButton.attr('data-attid')) {
                return;
            }
            var attachment = wp.media.attachment($imgButton.attr('data-attid'));
            attachment.fetch();
            frame.state().get('selection').add(attachment ? [attachment] : []);
        });

        // When an image is selected in the media frame...
        frame.on('insert', function() {

            /* Reset */
            reset_wrapper();

            var attachment = frame.state().get('selection').first().toJSON();
            if (attachment.type == 'image') {
                $imgPreview.attr('src', attachment.url);
                $this.addClass('wpupostmetas-field-image--hasimage');
            }
            else {
                $txtPreview.html(attachment.filename);
                $this.addClass('wpupostmetas-field-image--hasfile');
            }
            $imgButton.attr('data-attid', attachment.id);
            // Send the attachment id to our hidden input
            $imgField.val(attachment.id);
            $imgField.trigger('change');
            $imgButton.text($imgButton.attr('data-changelabel'));
        });

        /* Add an image */
        function imgOpenEvent(e) {
            e.preventDefault();
            frame.open();
        }
        $imgButton.on('click', imgOpenEvent);
        $imgPreview.on('click', imgOpenEvent);

        function reset_wrapper(){
            $imgField.val('0');
            $imgField.trigger('change');
            $imgButton.attr('data-attid', '');
            $txtPreview.html('');
            $imgPreview.attr('src', '');
            $imgButton.text($imgButton.attr('data-addlabel'));
            $this.removeClass('wpupostmetas-field-image--hasimage');
            $this.removeClass('wpupostmetas-field-image--hasfile');
        }

        /* Remove an image */
        $imgRemove.on('click', function(e) {
            e.preventDefault();
            reset_wrapper();
        });
    }

    jQuery(window).on('wpupostmetas__action__add_line wpupostmetas__action__copy_last_line wpupostmetas__action__delete_line_txt wpupostmetas__action__moving_line', function() {
        jQuery('.wpupostmetas-field-image').each(setup_image);
    });
    jQuery('.wpupostmetas-field-image').each(setup_image);

}
