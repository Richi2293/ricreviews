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
     * Write a message to the plugin logger if available.
     *
     * @param string $message Message to log.
     * @param array  $context Optional context.
     * @return void
     */
    private function log($message, $context = array())
    {
        if (class_exists('RicReviews_Logger')) {
            RicReviews_Logger::log($message, $context);
        }
    }

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
     * @param string $language Optional language code (e.g., 'it', 'en'). If empty, uses WordPress locale
     * @return array|WP_Error Array of reviews (max 5) or WP_Error on failure
     */
    public function fetch_reviews($place_id, $api_key, $language = '')
    {
        if (empty($place_id) || empty($api_key)) {
            return new WP_Error('missing_params', __('Place ID and API key are required.', 'ricreviews'));
        }

        // Sanitize inputs
        $place_id = sanitize_text_field($place_id);
        $api_key = sanitize_text_field($api_key);
        $language = !empty($language) ? sanitize_text_field($language) : '';

        // Build request URL with place ID
        // Using GET method directly as it's more reliable and simpler
        // According to Google Places API (New) documentation, GET requests work well for reviews
        
        // Determine language code to use
        $requested_language = '';
        $requested_language_full = ''; // Full format (e.g., 'it-IT')
        $language_source = 'none'; // Track where language code comes from
        
        if (!empty($language)) {
            // Use provided language code
            $requested_language = strtolower($language);
            // Try to convert to full format if it's just a 2-letter code
            // Google Places API prefers full format (e.g., 'it-IT' instead of 'it')
            if (strlen($requested_language) === 2) {
                $requested_language_full = $requested_language . '-' . strtoupper($requested_language);
            } else {
                $requested_language_full = $requested_language;
            }
            $language_source = 'parameter';
        } else {
            // Try to add language code from WordPress locale if available
            $language_code = get_locale();
            if (!empty($language_code)) {
                // Convert WordPress locale to Google API format (e.g., 'it_IT' -> 'it-IT')
                $lang_parts = explode('_', $language_code);
                if (!empty($lang_parts[0])) {
                    $requested_language = strtolower($lang_parts[0]);
                    // Use full format if we have both parts (e.g., 'it_IT' -> 'it-IT')
                    if (isset($lang_parts[1]) && !empty($lang_parts[1])) {
                        $requested_language_full = $requested_language . '-' . strtoupper($lang_parts[1]);
                    } else {
                        $requested_language_full = $requested_language . '-' . strtoupper($requested_language);
                    }
                    $language_source = 'wordpress_locale';
                }
            }
        }

        // Build GET URL with fields and languageCode parameters
        // NOTE: Google Places API has a hard limit of 5 reviews per place
        // There are no parameters to increase this limit (no pagination, no maxResults, etc.)
        $get_url = $this->api_endpoint . $place_id . '?fields=reviews';
        
        // Add languageCode as query parameter if available
        if (!empty($requested_language_full)) {
            $get_url .= '&languageCode=' . urlencode($requested_language_full);
        }

        $this->log('Starting Google Places reviews fetch.', array(
            'place_id' => $place_id,
            'language_short' => $requested_language ?: 'not_set',
            'language_full' => $requested_language_full ?: 'not_set',
            'language_source' => $language_source,
            'request_url' => $get_url,
        ));

        // Make API request using GET method (more reliable than POST)
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

        // Check for request errors
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $this->log('Google Places API request failed.', array(
                'place_id' => $place_id,
                'error' => $error_message,
            ));
            return new WP_Error('request_error', sprintf(__('Request failed: %s', 'ricreviews'), $error_message));
        }

        // Get response code
        $response_code = wp_remote_retrieve_response_code($response);

        // Parse response body first
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        $this->log('Google Places API response received.', array(
            'place_id' => $place_id,
            'status_code' => $response_code,
            'raw_body' => $body,
        ));

        // Handle different response codes
        if ($response_code === 429) {
            $this->log('Google Places API rate limit exceeded.', array('place_id' => $place_id));
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

            $this->log('Google Places API returned 403.', array(
                'place_id' => $place_id,
                'error_message' => $error_message,
            ));
            return new WP_Error('invalid_key', $error_message);
        }

        if ($response_code === 404) {
            $error_message = __('Invalid Google Place ID.', 'ricreviews');
            if (isset($data['error'])) {
                if (isset($data['error']['message'])) {
                    $error_message = $data['error']['message'];
                }
            }

            $this->log('Google Places API returned 404.', array(
                'place_id' => $place_id,
                'request_url' => $get_url,
                'response_body' => $body,
            ));

            return new WP_Error('invalid_place', $error_message);
        }

        if ($response_code !== 200) {
            $error_message = sprintf(__('Google Places API returned error code: %d', 'ricreviews'), $response_code);

            if (isset($data['error']['message'])) {
                $error_message = $data['error']['message'];
            }

            $this->log('Google Places API returned unexpected status.', array(
                'place_id' => $place_id,
                'status_code' => $response_code,
                'response_body' => $body,
            ));

            return new WP_Error('api_error', $error_message);
        }

        // Check for JSON errors
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log('Failed to parse Google Places API response.', array(
                'place_id' => $place_id,
                'raw_body' => $body,
            ));
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

            $this->log('Google Places API returned an error payload.', array(
                'place_id' => $place_id,
                'error_code' => $error_code,
                'error_message' => $error_message,
            ));
            return new WP_Error($error_code, $error_message);
        }

        // Extract reviews (Places API New format)
        // NOTE: Google Places API returns maximum 5 reviews per place
        // This is a hard limit from Google, not a bug in this code
        if (!isset($data['reviews']) || !is_array($data['reviews'])) {
            $this->log('Google Places API response did not include reviews.', array(
                'place_id' => $place_id,
            ));
            return array();
        }

        $reviews = array();

        foreach ($data['reviews'] as $review) {
            // Safely extract authorAttribution
            $author_attribution = isset($review['authorAttribution']) && is_array($review['authorAttribution'])
                ? $review['authorAttribution']
                : array();

            // Safely extract text object (translated text)
            $text_object = isset($review['text']) && is_array($review['text'])
                ? $review['text']
                : array();

            // Safely extract originalText object (original text in original language)
            $original_text_object = isset($review['originalText']) && is_array($review['originalText'])
                ? $review['originalText']
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

            // Extract language from review if available, otherwise use requested language
            // Note: Google Places API may not return language in review object
            // We use the languageCode sent in the request as fallback
            $review_language = '';
            if (isset($text_object['languageCode'])) {
                $review_language = sanitize_text_field($text_object['languageCode']);
            } elseif (isset($review['languageCode'])) {
                $review_language = sanitize_text_field($review['languageCode']);
            } elseif (!empty($requested_language)) {
                $review_language = $requested_language;
            }

            // Extract original language from originalText object
            $original_language = '';
            if (isset($original_text_object['languageCode'])) {
                $original_language = sanitize_text_field($original_text_object['languageCode']);
            }

            // Helper function to normalize language codes for comparison
            // Converts 'it-IT', 'it', 'IT' to 'it' for comparison
            $normalize_lang = function($lang) {
                if (empty($lang)) {
                    return '';
                }
                $lang = strtolower($lang);
                // Extract base language code (e.g., 'it-IT' -> 'it', 'en-US' -> 'en')
                $parts = explode('-', $lang);
                return $parts[0];
            };

            // Determine which text to use
            // If requested language doesn't match text language, but matches original language, use original text
            $text_to_use = isset($text_object['text']) ? $text_object['text'] : '';
            $language_to_use = $review_language;
            $use_original = false;

            if (!empty($requested_language)) {
                $requested_normalized = $normalize_lang($requested_language);
                $text_normalized = $normalize_lang($review_language);
                $original_normalized = $normalize_lang($original_language);

                // If text language doesn't match requested, but original does, use original
                if ($text_normalized !== $requested_normalized && $original_normalized === $requested_normalized) {
                    if (!empty($original_text_object['text'])) {
                        $text_to_use = $original_text_object['text'];
                        $language_to_use = $original_language;
                        $use_original = true;
                    }
                }
            }

            $this->log('Resolved review language mapping.', array(
                'review_id' => substr($review_id, 0, 20) . '...',
                'requested_language' => $requested_language ?: 'not_set',
                'review_language' => $review_language ?: 'not_set',
                'original_language' => $original_language ?: 'not_set',
                'using_original' => $use_original ? 'yes' : 'no',
                'final_language' => $language_to_use ?: 'not_set',
            ));

            $reviews[] = array(
                'review_id' => $review_id,
                'author_name' => $author_name,
                'author_url' => isset($author_attribution['uri']) ? $author_attribution['uri'] : '',
                'profile_photo_url' => isset($author_attribution['photoUri']) ? $author_attribution['photoUri'] : '',
                'rating' => isset($review['rating']) ? absint($review['rating']) : 0,
                'text' => $text_to_use,
                'original_text' => isset($original_text_object['text']) ? $original_text_object['text'] : '',
                'time' => $time,
                'relative_time_description' => isset($review['relativePublishTimeDescription']) ? $review['relativePublishTimeDescription'] : '',
                'language' => $language_to_use,
                'original_language' => $original_language,
            );
        }

        $this->log('Reviews fetched successfully.', array(
            'place_id' => $place_id,
            'language_short' => $requested_language ?: 'not_set',
            'reviews_count' => count($reviews),
        ));

        return $reviews;
    }

    /**
     * Fetch reviews from multiple languages
     * Makes multiple API calls (one per language) and merges results
     * 
     * @param string $place_id Google Place ID
     * @param string $api_key  Google Places API key
     * @param array  $languages Array of language codes (e.g., ['it', 'en', 'fr'])
     * @return array|WP_Error Array of reviews (merged from all languages) or WP_Error on failure
     */
    public function fetch_reviews_multiple_languages($place_id, $api_key, $languages = array()) {
        if (empty($place_id) || empty($api_key)) {
            return new WP_Error('missing_params', __('Place ID and API key are required.', 'ricreviews'));
        }

        if (empty($languages) || !is_array($languages)) {
            return new WP_Error('invalid_languages', __('Languages must be a non-empty array.', 'ricreviews'));
        }

        $all_reviews = array();
        $review_ids_seen = array(); // To avoid duplicates

        $this->log('Starting multi-language reviews fetch.', array(
            'place_id' => $place_id,
            'languages' => $languages,
        ));

        foreach ($languages as $language) {
            $language = sanitize_text_field($language);
            if (empty($language)) {
                continue;
            }

            $this->log('Fetching reviews for specific language.', array(
                'place_id' => $place_id,
                'language' => $language,
            ));

            // Fetch reviews for this language
            $reviews = $this->fetch_reviews($place_id, $api_key, $language);

            if (is_wp_error($reviews)) {
                // Log error but continue with other languages
                $this->log('Error fetching reviews for language.', array(
                    'place_id' => $place_id,
                    'language' => $language,
                    'error' => $reviews->get_error_message(),
                ));
                continue;
            }

            // Merge reviews, avoiding duplicates based on review_id
            foreach ($reviews as $review) {
                $review_id = isset($review['review_id']) ? $review['review_id'] : '';
                
                if (empty($review_id)) {
                    // If no review_id, generate one
                    $review_id = md5($place_id . (isset($review['author_name']) ? $review['author_name'] : '') . (isset($review['time']) ? $review['time'] : ''));
                    $review['review_id'] = $review_id;
                }

                // Only add if we haven't seen this review_id before
                if (!isset($review_ids_seen[$review_id])) {
                    $all_reviews[] = $review;
                    $review_ids_seen[$review_id] = true;
                }
            }
        }

        $this->log('Completed multi-language reviews fetch.', array(
            'place_id' => $place_id,
            'languages' => $languages,
            'total_reviews' => count($all_reviews),
        ));

        return $all_reviews;
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
