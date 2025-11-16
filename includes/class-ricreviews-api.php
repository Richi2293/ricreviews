<?php

/**
 * Google Places API integration for RicReviews
 *
 * @package RicReviews
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class RicReviews_API
 */
class RicReviews_API
{

    /**
     * Google Places API (New) endpoint
     * The new API uses :fetchPlace method
     *
     * @var string
     */
    private $api_endpoint = 'https://places.googleapis.com/v1/places/';

    /**
     * Fetch reviews from Google Places API (New)
     *
     * IMPORTANT LIMITATION: Google Places API returns a maximum of 5 reviews per place.
     * This is a hard limit imposed by Google, not a limitation of this code.
     * According to Google's documentation: "A Place object in a response can contain up to five reviews."
     * 
     * To get more reviews, you would need to:
     * - Use Google My Business API (if you own the business)
     * - Use third-party scraping services (may violate Google's Terms of Service)
     * 
     * @param string $place_id Google Place ID
     * @param string $api_key  Google Places API key
     * @return array|WP_Error Array of reviews (max 5) or WP_Error on failure
     */
    public function fetch_reviews($place_id, $api_key)
    {
        if (empty($place_id) || empty($api_key)) {
            return new WP_Error('missing_params', __('Place ID and API key are required.', 'ricreviews'));
        }

        // Sanitize inputs
        $place_id = sanitize_text_field($place_id);
        $api_key = sanitize_text_field($api_key);

        // Build request URL with place ID
        // According to Google Places API (New) documentation:
        // Option 1: GET request with fields parameter
        // Option 2: POST request with :fetchPlace method
        // We'll try POST with :fetchPlace first, then fallback to GET if needed

        // Try POST method with :fetchPlace (preferred for complex requests)
        $url = $this->api_endpoint . $place_id . ':fetchPlace';

        // Prepare request body for Places API (New)
        // readMask specifies which fields to return
        // For reviews, we need to specify the full path to review fields
        // NOTE: Google Places API has a hard limit of 5 reviews per place
        // There are no parameters to increase this limit (no pagination, no maxResults, etc.)
        $body = array(
            'readMask' => 'reviews.rating,reviews.text,reviews.authorAttribution,reviews.publishTime,reviews.relativePublishTimeDescription',
        );

        // Try to add language code if available (might help with results)
        $language_code = get_locale();
        if (!empty($language_code)) {
            // Convert WordPress locale to Google API format (e.g., 'it_IT' -> 'it')
            $lang = explode('_', $language_code);
            if (!empty($lang[0])) {
                $body['languageCode'] = strtolower($lang[0]);
            }
        }

        $body_json = wp_json_encode($body);

        // Log request details (only in debug mode)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('RicReviews API Request - URL: ' . $url);
            error_log('RicReviews API Request - Body: ' . $body_json);
            error_log('RicReviews API Request - Place ID: ' . $place_id);
        }

        // Make API request using POST method with :fetchPlace
        $response = wp_remote_post(
            $url,
            array(
                'timeout' => 15,
                'sslverify' => true,
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'X-Goog-Api-Key' => $api_key,
                ),
                'body' => $body_json,
            )
        );

        // Check response code
        $response_code = wp_remote_retrieve_response_code($response);

        // If we get 404, try GET method as fallback
        if ($response_code === 404) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('RicReviews API - POST method returned 404, trying GET method');
            }

            // Try GET method with fields parameter
            // Note: For GET requests, use 'fields' parameter, not 'readMask'
            $get_url = $this->api_endpoint . $place_id . '?fields=reviews';

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('RicReviews API - Trying GET request: ' . $get_url);
            }

            $response = wp_remote_get(
                $get_url,
                array(
                    'timeout' => 15,
                    'sslverify' => true,
                    'headers' => array(
                        'X-Goog-Api-Key' => $api_key,
                    ),
                )
            );

            $response_code = wp_remote_retrieve_response_code($response);
        }

        // Check for request errors
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('RicReviews API Request Error: ' . $error_message);
            }
            return new WP_Error('request_error', sprintf(__('Request failed: %s', 'ricreviews'), $error_message));
        }

        // Get response code
        $response_code = wp_remote_retrieve_response_code($response);

        // Parse response body first
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // Log response details (only in debug mode)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('RicReviews API Response - Code: ' . $response_code);
            error_log('RicReviews API Response - Body (raw): ' . $body);
            // Log formatted JSON for better readability
            if (!empty($data)) {
                error_log('RicReviews API Response - Body (formatted): ' . wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }
            if (isset($data['error'])) {
                error_log('RicReviews API Response - Error: ' . wp_json_encode($data['error'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }
        }

        // Handle different response codes
        if ($response_code === 429) {
            return new WP_Error('rate_limit', __('Google Places API rate limit exceeded. Please try again later.', 'ricreviews'));
        }

        if ($response_code === 403) {
            // Try to get more details from response body
            $error_message = __('Invalid Google Places API key or Places API (New) is not enabled.', 'ricreviews');
            if (isset($data['error']['message'])) {
                $error_message = $data['error']['message'];
                // Check if it's about legacy API
                if (strpos($error_message, 'legacy API') !== false || strpos($error_message, 'LegacyApiNotActivated') !== false) {
                    return new WP_Error('legacy_api', __('You need to enable Places API (New) in your Google Cloud Console. The legacy API is no longer available for new projects.', 'ricreviews'));
                }
            }

            return new WP_Error('invalid_key', $error_message);
        }

        if ($response_code === 404) {
            $error_message = __('Invalid Google Place ID.', 'ricreviews');
            $debug_info = '';

            if (isset($data['error'])) {
                if (isset($data['error']['message'])) {
                    $error_message = $data['error']['message'];
                }
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    if (isset($data['error']['status'])) {
                        $debug_info = ' (Status: ' . $data['error']['status'] . ')';
                    }
                    // Include full error details in debug mode
                    $debug_info .= ' | Full Error: ' . wp_json_encode($data['error']);
                }
            }

            // Add debug info if available
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $debug_info .= ' | Place ID used: ' . $place_id . ' | URL: ' . $url . ' | Body: ' . $body_json;
            }

            return new WP_Error('invalid_place', $error_message . $debug_info);
        }

        if ($response_code !== 200) {
            $error_message = sprintf(__('Google Places API returned error code: %d', 'ricreviews'), $response_code);
            $debug_info = '';

            if (isset($data['error'])) {
                if (isset($data['error']['message'])) {
                    $error_message = $data['error']['message'];
                }
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    $debug_info = ' | Full error: ' . wp_json_encode($data['error']);
                }
            }

            return new WP_Error('api_error', $error_message . $debug_info);
        }

        // Check for JSON errors
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_error', __('Failed to parse API response.', 'ricreviews'));
        }

        // Check for API errors (Places API New format)
        if (isset($data['error'])) {
            $error_message = isset($data['error']['message']) ? $data['error']['message'] : __('Unknown API error.', 'ricreviews');
            $error_code = isset($data['error']['code']) ? $data['error']['code'] : 'api_error';

            // Check for legacy API error message
            if (strpos($error_message, 'legacy API') !== false || strpos($error_message, 'LegacyApiNotActivated') !== false) {
                return new WP_Error('legacy_api', __('You need to enable Places API (New) in your Google Cloud Console. The legacy API is no longer available for new projects.', 'ricreviews'));
            }

            return new WP_Error($error_code, $error_message);
        }

        // Extract reviews (Places API New format)
        // NOTE: Google Places API returns maximum 5 reviews per place
        // This is a hard limit from Google, not a bug in this code
        if (!isset($data['reviews']) || !is_array($data['reviews'])) {
            return array();
        }

        $reviews = array();

        foreach ($data['reviews'] as $review) {
            // Safely extract authorAttribution
            $author_attribution = isset($review['authorAttribution']) && is_array($review['authorAttribution'])
                ? $review['authorAttribution']
                : array();

            // Safely extract text object
            $text_object = isset($review['text']) && is_array($review['text'])
                ? $review['text']
                : array();

            // Generate review_id from author name and publish time if not available
            $author_name = isset($author_attribution['displayName']) ? $author_attribution['displayName'] : '';
            $publish_time = isset($review['publishTime']) ? $review['publishTime'] : '';
            $review_id = !empty($author_name) && !empty($publish_time)
                ? md5($place_id . $author_name . $publish_time)
                : '';

            // Convert publishTime to Unix timestamp if it's in RFC3339 format
            $time = 0;
            if (isset($review['publishTime'])) {
                $time = strtotime($review['publishTime']);
                if ($time === false) {
                    $time = 0;
                }
            }

            $reviews[] = array(
                'review_id' => $review_id,
                'author_name' => $author_name,
                'author_url' => isset($author_attribution['uri']) ? $author_attribution['uri'] : '',
                'profile_photo_url' => isset($author_attribution['photoUri']) ? $author_attribution['photoUri'] : '',
                'rating' => isset($review['rating']) ? absint($review['rating']) : 0,
                'text' => isset($text_object['text']) ? $text_object['text'] : '',
                'time' => $time,
                'relative_time_description' => isset($review['relativePublishTimeDescription']) ? $review['relativePublishTimeDescription'] : '',
            );
        }

        return $reviews;
    }

    /**
     * Validate API key by making a test request
     *
     * @param string $api_key  Google Places API key
     * @param string $place_id Google Place ID (optional, for testing)
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    public function validate_api_key($api_key, $place_id = '')
    {
        if (empty($api_key)) {
            return new WP_Error('empty_key', __('API key cannot be empty.', 'ricreviews'));
        }

        // If place_id is provided, use it for validation
        if (!empty($place_id)) {
            $test_result = $this->fetch_reviews($place_id, $api_key);

            if (is_wp_error($test_result)) {
                // Check if error is specifically about invalid key
                if (
                    $test_result->get_error_code() === 'invalid_key' ||
                    $test_result->get_error_code() === 'request_denied'
                ) {
                    return $test_result;
                }
                // Other errors (like invalid place) don't mean the key is invalid
                return true;
            }

            return true;
        }

        // If no place_id, make a minimal test request
        // Note: Google Places API requires place_id, so we can't fully validate without it
        // Return true as a basic format check
        if (strlen($api_key) < 20) {
            return new WP_Error('invalid_format', __('API key format appears invalid.', 'ricreviews'));
        }

        return true;
    }
}
