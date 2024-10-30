jQuery(document).ready(function ($) {
    // Add custom "Decorative" field to the media modal
    wp.media.view.Attachment.Details.TwoColumn.prototype.render = function () {
        wp.media.view.Attachment.Details.TwoColumn.__super__.render.apply(this, arguments);

        // Add Decorative checkbox
        var $decorative = $('<label class="setting"><span>' + imgA11yData.decorativeLabel + '</span><input type="checkbox" class="img-a11y-decorative" /></label>');
        this.$el.find('.attachment-compat .compat-field-alt_text').after($decorative);

        // Set Decorative checkbox based on model data
        var model = this.model;
        model.on('change', function () {
            $decorative.find('input').prop('checked', model.get('is_decorative') == 1);
        });

        // Save Decorative checkbox state
        wp.media.view.settings.post.updateAttachment = function (model) {
            model.set('is_decorative', $decorative.find('input').is(':checked') ? 1 : 0);
        };
    };
});
