jQuery(document).ready(function ($) {
    // Function to display tooltip
    function showTooltip(input, message, isSuccess) {
        // Remove existing tooltip
        $('.img-a11y-tooltip').remove();

        // Create tooltip
        const tooltip = $('<div class="img-a11y-tooltip"></div>')
            .text(message)
            .css({
                top: input.offset().top - input.outerHeight() - 10,
                left: input.offset().left,
            });

        // Add success or error color
        if (isSuccess) {
            tooltip.css({ backgroundColor: '#28a745' }); // Green for success
        } else {
            tooltip.css({ backgroundColor: '#dc3545' }); // Red for error
        }

        // Append and animate tooltip
        $('body').append(tooltip);
        tooltip.addClass('show');

        // Remove tooltip after 2 seconds
        setTimeout(() => {
            tooltip.removeClass('show').remove();
        }, 2000);
    }

    $('.img-a11y-alt-text').on('blur', function () {
        const input = $(this);
        const attachmentId = input.data('id');
        const altText = input.val();

        // Show loading feedback
        input.addClass('loading');

        // Make AJAX request to update alt text
        $.ajax({
            url: imgA11yAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'img_a11y_update_alt_text',
                nonce: imgA11yAjax.nonce,
                id: attachmentId,
                alt_text: altText,
            },
            success: function (response) {
                input.removeClass('loading');
                if (response.success) {
                    showTooltip(input, response.data.message, true);
                } else {
                    showTooltip(input, response.data.message, false);
                }
            },
            error: function () {
                input.removeClass('loading');
                showTooltip(input, 'An error occurred while updating the alt text.', false);
            },
        });
    });
});
