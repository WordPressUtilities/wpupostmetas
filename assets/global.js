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
    wpupostmetas_settables();
    wpupostmetas_setimages();

    if (typeof qTranslateConfig == 'object') {
        jQuery('.multilingual-wrapper .wp-editor-area.qtranxs-translatable').each(function() {
            qTranslateConfig.qtx.removeContentHook(this);
        });
    }
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
    input.val(JSON.stringify(results));
};

var wpupostmetas_settables = function() {
    var tables = jQuery('.wpupostmetas-table-post');
    tables.each(function() {
        var table = jQuery(this),
            table_maxline = parseInt(table.attr('data-table-maxline'), 10),
            tableParent = table.parent(),
            tpl = tableParent.find('.template'),
            input = tableParent.find('input[type=hidden]');

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
                table.append(jQuery(tpl.val()));
            }
        });
        // Copy last line
        tableParent.on('click', '.copy', function(e) {
            e.preventDefault();
            var nbLines = tableParent.find('tbody tr').length;
            if (nbLines < table_maxline) {
                var lastline = '<tr>' + tableParent.find('tbody tr:last-child').html() + '</tr>';
                table.append(jQuery(lastline));
            }
        });
        // Delete a line
        tableParent.on('click', '.delete', function(e) {
            e.preventDefault();
            if (confirm(wpupostmetas_tra.delete_line_txt)) {
                jQuery(this).closest('tr').remove();
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
    refresh.on('click', function(e) {
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
    jQuery('.wpupostmetas-field-image').each(function(e) {
        var $this = jQuery(this),
            mediatype = $this.attr('data-type'),
            $txtPreview = $this.find('.wpupostmetas-field-file__name'),
            $imgPreview = $this.find('img'),
            $imgRemove = $this.find('.wpupostmetas-field-image__remove a'),
            $imgButton = $this.find('button'),
            $imgField = $this.find('input[type="hidden"]');

        var wpmediaobj = {
            multiple: false,
        };
        if (mediatype == 'image') {
            wpmediaobj.library = {
                type: 'image'
            };
        }

        var frame = wp.media(wpmediaobj);

        // Open on selected image
        frame.on('open', function() {
            if (!$this.attr('data-attid')) {
                return;
            }
            attachment = wp.media.attachment($this.attr('data-attid'));
            attachment.fetch();
            frame.state().get('selection').add(attachment ? [attachment] : []);
        });

        // When an image is selected in the media frame...
        frame.on('select', function() {
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
            $imgButton.text($imgButton.attr('data-changelabel'));
        });

        /* Add an image */
        $imgButton.on('click', function(e) {
            e.preventDefault();
            frame.open();
        });
        $imgPreview.on('click', function(e) {
            e.preventDefault();
            frame.open();
        });

        /* Remove an image */
        $imgRemove.on('click', function(e) {
            e.preventDefault();
            $imgField.val('');
            $imgButton.attr('data-attid', '');
            $txtPreview.html('');
            $imgPreview.attr('src', '');
            $imgButton.text($imgButton.attr('data-addlabel'));
            $this.removeClass('wpupostmetas-field-image--hasimage');
            $this.removeClass('wpupostmetas-field-image--hasfile');
        })
    });

}
