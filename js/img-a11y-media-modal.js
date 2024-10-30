jQuery(document).ready(function ($) {
    console.log("Img A11y plugin JavaScript loaded");

    // Add fields to the attachment details sidebar
    wp.media.view.Attachment.Details.prototype.render = _.wrap(wp.media.view.Attachment.Details.prototype.render, function (originalRender) {
        originalRender.apply(this, arguments);

        // Check if fields are already added to prevent duplication
        if (this.$('.mark-as-decorative').length === 0) {
            console.log("Adding custom fields to media modal");

            this.$('.settings').append(
                '<label class="setting">' +
                '<span>' + imgA11y.decorativeLabel + '</span>' +
                '<input type="checkbox" class="mark-as-decorative" />' +
                '</label>' +
                '<label class="setting">' +
                '<span>' + imgA11y.longDescLabel + '</span>' +
                '<textarea class="long-description" placeholder="' + imgA11y.longDescPlaceholder + '"></textarea>' +
                '</label>' +
                '<p class="accessibility-prompt">' + imgA11y.accessibilityTip + '</p>'
            );

            // Set initial values from model
            const decorative = this.model.get('decorative') === '1';
            this.$('.mark-as-decorative').prop('checked', decorative);
            this.$('.long-description').val(this.model.get('long_description') || '');

            // Update model on change
            this.$('.mark-as-decorative').on('change', () => {
                this.model.set('decorative', this.$('.mark-as-decorative').is(':checked') ? '1' : '');
            });
            this.$('.long-description').on('input', () => {
                this.model.set('long_description', this.$('.long-description').val());
            });
        }
    });
});
