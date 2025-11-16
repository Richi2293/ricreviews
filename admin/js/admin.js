/**
 * RicReviews Admin JavaScript
 *
 * @package RicReviews
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        var $apiKeyField = $('#ricreviews_api_key');
        var $placeIdField = $('#ricreviews_place_id');
        var $validationMessage = $('#ricreviews-api-key-validation');
        var validationTimeout;
        
        // Validate API key on blur
        $apiKeyField.on('blur', function() {
            var apiKey = $(this).val();
            var placeId = $placeIdField.val();
            
            if (!apiKey || !placeId) {
                return;
            }
            
            // Clear previous timeout
            if (validationTimeout) {
                clearTimeout(validationTimeout);
            }
            
            // Show loading state
            $validationMessage
                .removeClass('success error')
                .html('Validating...')
                .show();
            
            // Debounce validation
            validationTimeout = setTimeout(function() {
                validateApiKey(apiKey, placeId);
            }, 500);
        });
        
        /**
         * Validate API key via AJAX
         */
        function validateApiKey(apiKey, placeId) {
            $.ajax({
                url: ricreviewsAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ricreviews_validate_api_key',
                    api_key: apiKey,
                    place_id: placeId,
                    nonce: ricreviewsAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $validationMessage
                            .removeClass('error')
                            .addClass('success')
                            .html('✓ API key is valid');
                    } else {
                        $validationMessage
                            .removeClass('success')
                            .addClass('error')
                            .html('✗ ' + (response.data || 'Invalid API key'));
                    }
                },
                error: function() {
                    $validationMessage
                        .removeClass('success')
                        .addClass('error')
                        .html('✗ Error validating API key');
                }
            });
        }
    });
})(jQuery);

