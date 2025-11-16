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
class RicReviews_API {
    
    /**
     * Google Places API endpoint
     *
     * @var string
     */
    private $api_endpoint = 'https://maps.googleapis.com/maps/api/place/details/json';
    
    /**
     * Fetch reviews from Google Places API
     *
     * @param string $place_id Google Place ID
     * @param string $api_key  Google Places API key
     * @return array|WP_Error Array of reviews or WP_Error on failure
     */
    public function fetch_reviews($place_id, $api_key) {
        if (empty($place_id) || empty($api_key)) {
            return new WP_Error('missing_params', __('Place ID and API key are required.', 'ricreviews'));
        }
        
        // Build request URL
        $url = add_query_arg(
            array(
                'place_id' => sanitize_text_field($place_id),
                'fields' => 'reviews',
                'key' => sanitize_text_field($api_key),
            ),
            $this->api_endpoint
        );
        
        // Make API request
        $response = wp_remote_get(
            $url,
            array(
                'timeout' => 15,
                'sslverify' => true,
            )
        );
        
        // Check for request errors
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Get response code
        $response_code = wp_remote_retrieve_response_code($response);
        
        // Handle different response codes
        if ($response_code === 429) {
            return new WP_Error('rate_limit', __('Google Places API rate limit exceeded. Please try again later.', 'ricreviews'));
        }
        
        if ($response_code === 403) {
            return new WP_Error('invalid_key', __('Invalid Google Places API key.', 'ricreviews'));
        }
        
        if ($response_code === 404) {
            return new WP_Error('invalid_place', __('Invalid Google Place ID.', 'ricreviews'));
        }
        
        if ($response_code !== 200) {
            return new WP_Error('api_error', sprintf(__('Google Places API returned error code: %d', 'ricreviews'), $response_code));
        }
        
        // Parse response body
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // Check for JSON errors
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_error', __('Failed to parse API response.', 'ricreviews'));
        }
        
        // Check API status
        if (isset($data['status']) && $data['status'] !== 'OK') {
            $error_message = isset($data['error_message']) ? $data['error_message'] : __('Unknown API error.', 'ricreviews');
            
            if ($data['status'] === 'REQUEST_DENIED') {
                return new WP_Error('request_denied', $error_message);
            }
            
            if ($data['status'] === 'INVALID_REQUEST') {
                return new WP_Error('invalid_request', $error_message);
            }
            
            return new WP_Error('api_status_error', $error_message);
        }
        
        // Extract reviews
        if (!isset($data['result']['reviews']) || !is_array($data['result']['reviews'])) {
            return array();
        }
        
        $reviews = array();
        
        foreach ($data['result']['reviews'] as $review) {
            // Generate review_id from author_name and time if not available
            $review_id = isset($review['author_name']) && isset($review['time']) 
                ? md5($place_id . $review['author_name'] . $review['time']) 
                : '';
            
            $reviews[] = array(
                'review_id' => $review_id,
                'author_name' => isset($review['author_name']) ? $review['author_name'] : '',
                'author_url' => isset($review['author_url']) ? $review['author_url'] : '',
                'profile_photo_url' => isset($review['profile_photo_url']) ? $review['profile_photo_url'] : '',
                'rating' => isset($review['rating']) ? absint($review['rating']) : 0,
                'text' => isset($review['text']) ? $review['text'] : '',
                'time' => isset($review['time']) ? absint($review['time']) : 0,
                'relative_time_description' => isset($review['relative_time_description']) ? $review['relative_time_description'] : '',
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
    public function validate_api_key($api_key, $place_id = '') {
        if (empty($api_key)) {
            return new WP_Error('empty_key', __('API key cannot be empty.', 'ricreviews'));
        }
        
        // If place_id is provided, use it for validation
        if (!empty($place_id)) {
            $test_result = $this->fetch_reviews($place_id, $api_key);
            
            if (is_wp_error($test_result)) {
                // Check if error is specifically about invalid key
                if ($test_result->get_error_code() === 'invalid_key' || 
                    $test_result->get_error_code() === 'request_denied') {
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

