jQuery(document).ready(function ($) {
    // Decorative checkbox field HTML
    const decorativeFieldHtml = `<label class="setting">
        <span>${imgA11yData.decorativeLabel}</span>
        <input type="checkbox" class="img-a11y-decorative" />
    </label>`;

    wp.media.view.Attachment.Details.TwoColumn.prototype.render = function () {
        wp.media.view.Attachment.Details.TwoColumn.__super__.render.apply(this, arguments);

        // Add decorative checkbox after alt text field
        const $decorativeField = $(decorativeFieldHtml);
        this.$el.find('.attachment-compat .compat-field-alt_text').after($decorativeField);

        // Set decorative checkbox based on model data
        const model = this.model;
        const isDecorative = model.get('meta')?.is_decorative || false;
        $decorativeField.find('input').prop('checked', !!isDecorative);

        // Update model on checkbox toggle
        $decorativeField.find('input').on('change', function () {
            const checked = $(this).is(':checked');
            model.set('meta', { ...model.get('meta'), is_decorative: checked ? 1 : 0 });
        });
    };

    // Extend Gutenberg's media insertion to include 'data-is-decorative'
    const originalMediaEmbed = wp.media.editor.insert;
    wp.media.editor.insert = function (html) {
        const $tempDiv = $('<div>').html(html);
        const isDecorative = wp.media.frame.state().get('selection').first().get('meta')?.is_decorative;

        // If marked as decorative, add the data attribute
        if (isDecorative) {
            $tempDiv.find('img').attr('data-is-decorative', '1');
            html = $tempDiv.html();
        }

        // Call the original insert function with the modified HTML
        originalMediaEmbed(html);
    };
});
