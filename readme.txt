=== RicReviews ===
Contributors: riccardolorenzi
Tags: reviews, google-places, testimonials, ratings, shortcode
Requires at least: 5.0
Tested up to: 6.9
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display Google Places reviews on your WordPress site using a simple shortcode. Fetches reviews from Google Places API (New).

== Description ==

RicReviews is a free and open-source WordPress plugin that displays Google Places reviews on your website using a simple shortcode. The plugin fetches reviews from Google Places API (New) and displays them beautifully on your WordPress site.

= Key Features =

* **Easy Integration**: Display reviews anywhere on your site with a simple shortcode
* **Automatic Updates**: Reviews are automatically fetched and updated via configurable WordPress cron (daily, weekly, or monthly)
* **Smart Caching**: Built-in caching system to improve performance
* **Customizable**: Choose your primary color and theme (light/dark)
* **Flexible Display**: Control the number of reviews and sorting options
* **Multilingual Support**: Fetch reviews in multiple languages and filter by language in shortcode
* **Debug Logging**: Optional debug logging for troubleshooting API issues
* **Secure**: Follows WordPress coding standards and security best practices

= How It Works =

1. **Initial Fetch**: When you save settings, the plugin fetches reviews from Google Places API
2. **Database Storage**: Reviews are stored in a custom database table for fast retrieval
3. **Caching**: Reviews are cached using WordPress transients for 24 hours
4. **Automatic Updates**: WordPress cron job automatically fetches new reviews based on your configured frequency
5. **Display**: Shortcode retrieves reviews from cache/database and displays them

= Important Note =

Google Places API returns a maximum of 5 reviews per place per API call. This is a hard limit imposed by Google, not a limitation of this plugin. Each API call returns up to 5 reviews, and the plugin performs automatic fetches based on your configured frequency (daily, weekly, or monthly). Over time, you may accumulate more reviews in the database as Google updates the "5 most helpful reviews" for your place.

If you configure multiple languages, each language requires a separate API call, potentially allowing you to collect more reviews (up to 5 per language).

For more information, see [Google's Review Policy Documentation](https://developers.google.com/maps/documentation/places/web-service/policies#review-policy).

= Service Information =

This plugin acts as an interface to Google Places API (New). By installing and configuring this plugin, you consent to the use of Google Places API service. Please review [Google Places API Terms of Service](https://developers.google.com/maps/documentation/places/web-service/policies) before use.

== Installation ==

= Step 1: Install the Plugin =

1. Upload the `ricreviews` folder to `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress

= Step 2: Get Your Google Places API Key =

1. Go to [Google Cloud Console](https://console.cloud.google.com/google/maps-apis)
2. Create a new project or select an existing one
3. Enable **Places API (New)** (Important: The legacy Places API is no longer available for new projects)
4. Go to [Credentials](https://console.cloud.google.com/google/maps-apis/credentials) and create an API key
5. (Optional) Restrict your API key to Places API only for better security

= Step 3: Find Your Place ID =

1. Go to [Google Maps](https://www.google.com/maps) and search for your business
2. Click on your business in the search results or on the map
3. In the business information panel, scroll down to find the Place ID (format: `ChIJN1t_tDeuEmsRUsoyG83frY4`)
4. Alternatively, use Google's [Place ID Finder tool](https://developers.google.com/maps/documentation/places/web-service/place-id#find-id)

= Step 4: Configure the Plugin =

1. Navigate to **Settings > RicReviews** in your WordPress admin
2. Enter your Google Places API Key
3. Enter your Place ID
4. Configure display options:
   * **Additional Languages** (Optional): Enter comma-separated language codes (e.g., "en,fr,de") to fetch reviews in multiple languages
   * **Enable Debug Logging**: Toggle debug logging for troubleshooting (requires WP_DEBUG enabled)
   * **Enable Automatic Updates**: Toggle automatic fetching of reviews
   * **Update Frequency**: Select how often to fetch reviews (Daily, Weekly, or Monthly)
   * **Number of Reviews**: Select how many reviews to display (5, 10, 15, or 20)
   * **Sort By**: Choose sorting method (Most Recent, Oldest First, or Highest Rating)
   * **Primary Color**: Pick a color for the reviews display
   * **Theme**: Choose between Light or Dark theme
5. Click **Save Settings**

The plugin will automatically fetch reviews when you save the settings.

== Frequently Asked Questions ==

= Do I need a Google Places API key? =

Yes, you need a Google Places API (New) key with Places API enabled. You can get one from [Google Cloud Console](https://console.cloud.google.com/google/maps-apis). The API key is required for the plugin to fetch reviews from Google Places API.

= How do I find my Place ID? =

You can find your Place ID using Google Maps or Google's [Place ID Finder tool](https://developers.google.com/maps/documentation/places/web-service/place-id#find-id). The Place ID is a unique identifier for your business location on Google Maps.

= How many reviews can I display? =

Google Places API returns a maximum of 5 reviews per place per API call. This is a hard limit imposed by Google, not a limitation of this plugin. Each API call returns up to 5 reviews, and the plugin performs automatic fetches based on your configured frequency. Over time, you may accumulate more reviews in the database as Google updates the "5 most helpful reviews" for your place.

= Can I fetch reviews in multiple languages? =

Yes! You can configure multiple languages in the plugin settings by entering comma-separated language codes (e.g., "en,fr,de"). The plugin will make separate API calls for each language and merge the results, avoiding duplicates. You can also filter reviews by language using the `language` attribute in the shortcode.

= How do I use the shortcode? =

Simply add `[ricreviews]` to any page, post, or widget. You can also use attributes to customize the display:

* `limit`: Number of reviews to display (default: from settings)
* `order_by`: Sort method - `time` (most recent), `time_asc` (oldest first), or `rating` (highest rating)
* `order`: Sort direction - `ASC` or `DESC` (default: `DESC`)
* `language`: Language code to filter reviews (e.g., `"it"`, `"en"`, `"fr"`). If not specified, uses WordPress locale

Example: `[ricreviews limit="5" order_by="rating" language="en"]`

= How often are reviews updated? =

Reviews are automatically fetched and updated via WordPress cron based on your configured frequency (Daily, Weekly, or Monthly). You can enable or disable automatic updates from the plugin settings. You can also manually trigger a fetch by saving your settings or using the "Fetch Reviews Now" button in the admin panel.

= Does the plugin track users? =

No, the plugin does not track users. The plugin only makes API calls to Google Places API to fetch reviews, and this requires explicit configuration by the site administrator. No user data is collected or tracked.

= What are the system requirements? =

* WordPress 5.0 or higher
* PHP 7.4 or higher
* Google Places API (New) key with Places API enabled
* A valid Google Place ID

== Screenshots ==

1. Plugin settings page with API key and Place ID configuration
2. Reviews displayed on frontend with customizable colors and themes
3. Shortcode configuration options

== Changelog ==

= 1.0.0 =
* Initial release
* Display Google Places reviews via shortcode
* Automatic review updates via WordPress cron
* Multilingual support
* Customizable colors and themes
* Built-in caching system
* Debug logging support
* Responsive design

== Upgrade Notice ==

= 1.0.0 =
Initial release of RicReviews. Install and configure your Google Places API key and Place ID to start displaying reviews on your site.
