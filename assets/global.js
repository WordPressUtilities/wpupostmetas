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

    wpupostmetas_settables();

});

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
    var results = wpupostmetas_groupArray(table.find('input').serializeArray(), {
        basename: table_basename,
        cols: table.find('thead th').length
    });
    input.val(JSON.stringify(results));
};

var wpupostmetas_settables = function() {
    var tables = jQuery('.wpupostmetas-table-post');
    tables.each(function() {
        var table = jQuery(this),
            tableParent = table.parent(),
            tpl = tableParent.find('.template'),
            input = tableParent.find('input[type=hidden]');

        wpupostmetas_settable(table, input);
        // Save values in field
        table.on('change keydown keyup', 'input', function() {
            wpupostmetas_settable(table, input);
        });
        // Add a new line
        tableParent.on('click', '.plus', function(e) {
            e.preventDefault();
            table.append(jQuery(tpl.val()));
        });
        // Delete a line
        tableParent.on('click', '.delete', function(e) {
            e.preventDefault();
            if (confirm('delete this line ?')) {
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
    var refresh = jQuery('<span class="wpupostmetas-attachments__refresh"></span>'),
        sparent = self.parent();
    sparent.append(refresh);
    // Refresh on click
    refresh.on('click', function(e) {
        /* Disable click */
        sparent.addClass('is-disabled');
        e.preventDefault();
        jQuery.post(ajaxurl, {
            action: 'wpupostmetas_attachments',
            post_id: self.attr('data-postid'),
            post_value: self.attr('data-postvalue'),
        }, function(response) {
            self.html(response);
            /* Enable click */
            sparent.removeClass('is-disabled');
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