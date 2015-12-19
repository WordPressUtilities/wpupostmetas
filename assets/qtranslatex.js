jQuery(document).ready(function() {
    wpupostmetas_set_qtranslate();
});

/* ----------------------------------------------------------
  Set-up Qtranslate
---------------------------------------------------------- */

function wpupostmetas_set_qtranslate() {
    // only proceed if qTranslate is loaded
    if (!qTranslateConfig || !qTranslateConfig.qtx) {
        return;
    }

    // Display default lang
    wpupostmetas_qt_display_lang(qTranslateConfig.activeLanguage);

    // Toggle visible lang when user chose another.
    qTranslateConfig.qtx.addLanguageSwitchListener(function(lang_to) {
        wpupostmetas_qt_display_lang(lang_to);
    });
}

function wpupostmetas_qt_display_lang(lang) {
    jQuery('[data-wpupostmetaslang]').hide();
    jQuery('[data-wpupostmetaslang="' + lang + '"]').show();
}
