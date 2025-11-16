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
                dataType: 'json',
                data: {
                    action: 'ricreviews_validate_api_key',
                    api_key: apiKey,
                    place_id: placeId,
                    nonce: ricreviewsAdmin.nonce
                },
                success: function(response) {
                    // Handle WordPress JSON response format
                    if (response && typeof response === 'object') {
                        if (response.success === true || response.success === 1) {
                            var message = 'API key is valid';
                            if (response.data) {
                                if (typeof response.data === 'string') {
                                    message = response.data;
                                } else if (response.data.message) {
                                    message = response.data.message;
                                }
                            }
                            $validationMessage
                                .removeClass('error')
                                .addClass('success')
                                .html('✓ ' + message);
                        } else {
                            var errorMessage = 'Invalid API key';
                            if (response.data) {
                                if (typeof response.data === 'string') {
                                    errorMessage = response.data;
                                } else if (response.data.message) {
                                    errorMessage = response.data.message;
                                } else if (response.data.error) {
                                    errorMessage = response.data.error;
                                }
                            }
                            $validationMessage
                                .removeClass('success')
                                .addClass('error')
                                .html('✗ ' + errorMessage);
                        }
                    } else {
                        // Fallback for unexpected response format
                        $validationMessage
                            .removeClass('success')
                            .addClass('error')
                            .html('✗ Unexpected response format');
                    }
                },
                error: function(xhr, status, error) {
                    var errorMessage = 'Error validating API key';
                    if (xhr.responseJSON && xhr.responseJSON.data) {
                        if (typeof xhr.responseJSON.data === 'string') {
                            errorMessage = xhr.responseJSON.data;
                        } else if (xhr.responseJSON.data.message) {
                            errorMessage = xhr.responseJSON.data.message;
                        }
                    }
                    $validationMessage
                        .removeClass('success')
                        .addClass('error')
                        .html('✗ ' + errorMessage);
                }
            });
        }
        
        // Handle manual fetch reviews button
        $('#ricreviews-fetch-now').on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $errorDiv = $('#ricreviews-fetch-error');
            var originalText = $button.text();
            
            // Hide previous errors
            $errorDiv.hide().empty();
            
            $button.prop('disabled', true).text('Fetching...');
            
            $.ajax({
                url: ricreviewsAdmin.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'ricreviews_fetch_reviews',
                    nonce: ricreviewsAdmin.nonce
                },
                success: function(response) {
                    if (response && response.success) {
                        // Show success message briefly, then reload
                        $errorDiv
                            .removeClass('error')
                            .addClass('success')
                            .html('✓ ' + (response.data.message || 'Reviews fetched successfully!'))
                            .show();
                        
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        var errorMessage = 'Error fetching reviews';
                        if (response && response.data) {
                            if (typeof response.data === 'string') {
                                errorMessage = response.data;
                            } else if (response.data.message) {
                                errorMessage = response.data.message;
                            }
                        }
                        
                        $errorDiv
                            .removeClass('success')
                            .addClass('error')
                            .html('✗ ' + errorMessage)
                            .show();
                        
                        // Show debug info if available
                        var $debugDiv = $('#ricreviews-debug-info');
                        var $debugContent = $('#ricreviews-debug-content');
                        if (response && response.data && response.data.code) {
                            var debugInfo = 'Error Code: ' + response.data.code + '\n';
                            debugInfo += 'Error Message: ' + errorMessage + '\n';
                            if (response.data.place_id) {
                                debugInfo += 'Place ID: ' + response.data.place_id + '\n';
                            }
                            $debugContent.text(debugInfo);
                            $debugDiv.show();
                        }
                        
                        $button.prop('disabled', false).text(originalText);
                    }
                },
                error: function(xhr, status, error) {
                    var errorMessage = 'Error fetching reviews';
                    if (xhr.responseJSON && xhr.responseJSON.data) {
                        if (typeof xhr.responseJSON.data === 'string') {
                            errorMessage = xhr.responseJSON.data;
                        } else if (xhr.responseJSON.data.message) {
                            errorMessage = xhr.responseJSON.data.message;
                        }
                    } else if (xhr.responseText) {
                        try {
                            var parsed = JSON.parse(xhr.responseText);
                            if (parsed.data && parsed.data.message) {
                                errorMessage = parsed.data.message;
                            }
                        } catch(e) {
                            // Keep default error message
                        }
                    }
                    
                    $errorDiv
                        .removeClass('success')
                        .addClass('error')
                        .html('✗ ' + errorMessage)
                        .show();
                    
                    // Show debug info for network errors
                    var $debugDiv = $('#ricreviews-debug-info');
                    var $debugContent = $('#ricreviews-debug-content');
                    var debugInfo = 'Network Error\n';
                    debugInfo += 'Status: ' + status + '\n';
                    debugInfo += 'Error: ' + error + '\n';
                    if (xhr.responseText) {
                        debugInfo += 'Response: ' + xhr.responseText.substring(0, 500) + '\n';
                    }
                    $debugContent.text(debugInfo);
                    $debugDiv.show();
                    
                    $button.prop('disabled', false).text(originalText);
                }
            });
        });
    });
})(jQuery);

