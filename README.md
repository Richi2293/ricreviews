# RicReviews

A WordPress plugin that displays Google Places reviews on your website using a simple shortcode. RicReviews fetches reviews from Google Places API (New) and displays them beautifully on your WordPress site.

## Features

- 🎯 **Easy Integration**: Display reviews anywhere on your site with a simple shortcode
- 🔄 **Automatic Updates**: Reviews are automatically fetched and updated every 24 hours via WordPress cron
- 💾 **Smart Caching**: Built-in caching system to improve performance
- 🎨 **Customizable**: Choose your primary color and theme (light/dark)
- 📊 **Flexible Display**: Control the number of reviews and sorting options
- 🌍 **Multilingual Ready**: Includes translation files for internationalization
- 🔒 **Secure**: Follows WordPress coding standards and security best practices

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Google Places API (New) key with Places API enabled
- A valid Google Place ID

## Installation

### From GitHub

1. Download or clone this repository
2. Upload the `ricreviews` folder to `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Go to **Settings > RicReviews** to configure the plugin

### Manual Installation

1. Download the latest release from the [Releases page](https://github.com/Richi2293/ricreviews/releases)
2. Extract the zip file
3. Upload the `ricreviews` folder to `/wp-content/plugins/` directory
4. Activate the plugin through the 'Plugins' menu in WordPress

## Configuration

### Step 1: Get Your Google Places API Key

1. Go to [Google Cloud Console](https://console.cloud.google.com/google/maps-apis)
2. Create a new project or select an existing one
3. Enable **Places API (New)** (⚠️ Important: The legacy Places API is no longer available for new projects)
4. Go to [Credentials](https://console.cloud.google.com/google/maps-apis/credentials) and create an API key
5. (Optional) Restrict your API key to Places API only for better security

### Step 2: Find Your Place ID

1. Go to [Google Maps](https://www.google.com/maps) and search for your business
2. Click on your business in the search results or on the map
3. In the business information panel, scroll down to find the Place ID (format: `ChIJN1t_tDeuEmsRUsoyG83frY4`)
4. Alternatively, use Google's [Place ID Finder tool](https://developers.google.com/maps/documentation/places/web-service/place-id#find-id)

### Step 3: Configure the Plugin

1. Navigate to **Settings > RicReviews** in your WordPress admin
2. Enter your Google Places API Key
3. Enter your Place ID
4. Configure display options:
   - **Number of Reviews**: Select how many reviews to display (5, 10, 15, or 20)
   - **Sort By**: Choose sorting method (Most Recent, Oldest First, or Highest Rating)
   - **Primary Color**: Pick a color for the reviews display
   - **Theme**: Choose between Light or Dark theme
5. Click **Save Settings**

The plugin will automatically fetch reviews when you save the settings.

## Usage

### Basic Shortcode

Simply add the shortcode to any page, post, or widget:

```
[ricreviews]
```

### Shortcode Attributes

You can override the default settings using shortcode attributes:

```
[ricreviews limit="5" order_by="rating"]
```

**Available attributes:**
- `limit`: Number of reviews to display (default: from settings)
- `order_by`: Sort method - `time` (most recent), `time_asc` (oldest first), or `rating` (highest rating)
- `order`: Sort direction - `ASC` or `DESC` (default: `DESC`)

### Examples

Display 5 most recent reviews:
```
[ricreviews limit="5" order_by="time"]
```

Display 10 highest rated reviews:
```
[ricreviews limit="10" order_by="rating"]
```

Display oldest reviews first:
```
[ricreviews limit="15" order_by="time_asc"]
```

## How It Works

1. **Initial Fetch**: When you save settings, the plugin fetches reviews from Google Places API
2. **Database Storage**: Reviews are stored in a custom database table for fast retrieval
3. **Caching**: Reviews are cached using WordPress transients for 24 hours
4. **Automatic Updates**: WordPress cron job fetches new reviews every 24 hours
5. **Display**: Shortcode retrieves reviews from cache/database and displays them

## Important Limitations

⚠️ **Google Places API Limitation**: Google Places API returns a **maximum of 5 reviews per place** per API call. This is a hard limit imposed by Google, not a limitation of this plugin.

**What this means:**
- Each API call returns up to 5 reviews
- The plugin performs automatic fetches every 24 hours
- Over time, you may accumulate more reviews in the database as Google updates the "5 most helpful reviews" for your place
- If you need all reviews, you must be the business owner and use Google My Business API

**For more information:**
- [Google's Review Policy Documentation](https://developers.google.com/maps/documentation/places/web-service/policies?hl=it#review-policy)

## File Structure

```
ricreviews/
├── admin/
│   ├── css/
│   │   └── admin.css
│   └── js/
│       └── admin.js
├── includes/
│   ├── class-ricreviews-admin.php      # Admin settings page
│   ├── class-ricreviews-api.php       # Google Places API integration
│   ├── class-ricreviews-cache.php     # Caching system
│   ├── class-ricreviews-cron.php      # WordPress cron handler
│   ├── class-ricreviews-database.php  # Database operations
│   └── class-ricreviews-shortcode.php # Shortcode handler
├── languages/
│   ├── ricreviews-en_US.mo
│   ├── ricreviews-en_US.po
│   ├── ricreviews-it_IT.mo
│   └── ricreviews-it_IT.po
├── public/
│   └── css/
│       └── ricreviews.css
├── ricreviews.php                     # Main plugin file
├── uninstall.php                      # Uninstall script
└── README.md                          # This file
```

## Development

### Setting Up Development Environment

1. Clone the repository:
```bash
git clone https://github.com/Richi2293/ricreviews.git
cd ricreviews
```

2. Set up a local WordPress development environment (using Local by Flywheel, XAMPP, MAMP, etc.)

3. Activate the plugin in your WordPress installation

### Code Standards

This plugin follows:
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/)
- [WordPress PHP Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/)

### Debugging

Enable WordPress debug mode in `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

The plugin will log API requests and responses to the debug log when `WP_DEBUG` is enabled.

## Contributing

We welcome contributions! This is an open-source project, and we appreciate any help you can provide.

### How to Contribute

1. **Fork the repository** on GitHub
2. **Create a feature branch** from `dev` branch:
   ```bash
   git checkout -b feature/your-feature-name
   ```
3. **Make your changes** following WordPress coding standards
4. **Test your changes** thoroughly
5. **Commit your changes** with clear commit messages:
   ```bash
   git commit -m "Add: Description of your feature"
   ```
6. **Push to your fork**:
   ```bash
   git push origin feature/your-feature-name
   ```
7. **Create a Pull Request** to the `dev` branch

### Contribution Guidelines

- Follow WordPress coding standards
- Write clear, descriptive commit messages
- Add comments in English for complex logic
- Test your changes before submitting
- Update documentation if needed
- Be respectful and constructive in discussions

### Types of Contributions

We welcome various types of contributions:

- 🐛 **Bug Reports**: Found a bug? Open an issue with detailed information
- 💡 **Feature Requests**: Have an idea? Share it in an issue
- 📝 **Documentation**: Improve documentation, fix typos, add examples
- 🎨 **UI/UX Improvements**: Enhance the user interface or user experience
- 🔧 **Code Improvements**: Optimize code, refactor, improve performance
- 🌍 **Translations**: Add or improve translations

### Reporting Issues

When reporting issues, please include:
- WordPress version
- PHP version
- Plugin version
- Steps to reproduce
- Expected behavior
- Actual behavior
- Screenshots (if applicable)
- Error messages (if any)

### Pull Request Process

1. Ensure your code follows WordPress coding standards
2. Test your changes in a local environment
3. Update documentation if necessary
4. Write clear commit messages
5. Reference any related issues in your PR description

## License

This plugin is licensed under the GPL v2 or later.

```
Copyright (C) 2024 Riccardo Lorenzi

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
```

## Support

- **GitHub Issues**: [Report bugs or request features](https://github.com/Richi2293/ricreviews/issues)
- **Author**: [Riccardo Lorenzi](https://github.com/Richi2293)


## Credits

- Built with ❤️ by [Riccardo Lorenzi](https://github.com/Richi2293)
- Uses [Google Places API (New)](https://developers.google.com/maps/documentation/places/web-service)

---

**Made for WordPress** | **Open Source** | **Community Driven**

