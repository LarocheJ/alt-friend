# Alt Friend

A WordPress plugin that leverages OpenAI's GPT Vision API to automatically generate alt text for images in your media library.

## Description

Alt Friend helps improve website accessibility and SEO by automatically creating descriptive alt text for images. Using OpenAI's powerful Vision API, the plugin analyzes images and generates contextually appropriate alternative text descriptions.

## Features

- **Automatic Alt Text Generation**: Generate alt text for images using AI-powered vision analysis
- **Bulk Processing**: Process multiple images at once through a convenient bulk generation tool
- **Media Library Integration**: Seamlessly integrates with WordPress media library
- **Custom Keywords**: Option to include custom keywords in generated alt text for better SEO
- **Settings Page**: Easy-to-use admin interface for configuration

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- OpenAI API key with access to GPT Vision API

## Installation

1. Download the plugin files
2. Upload the `alt-friend` folder to the `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Go to Settings > Alt Friend to configure the plugin
5. Enter your OpenAI API key in the settings

## Configuration

1. Navigate to **Settings > Alt Friend** in your WordPress admin panel
2. Enter your OpenAI API key
3. Configure any additional settings as needed
4. Save your changes

## Usage

### Single Image
- Upload an image to your media library
- The plugin will automatically generate alt text using the Vision API

### Bulk Generation
1. Go to **Settings > Alt Friend**
2. Scroll to the "Bulk Alt Text Generation" section
3. Click the button to process all images without alt text
4. The plugin will process images and update their alt text

## File Structure

```
alt-friend/
├── admin/
│   └── settings-page.php       # Admin settings interface
├── css/
│   └── main.css                # Plugin styles
├── includes/
│   ├── ajax-handler.php        # AJAX request handler
│   ├── enqueue-scripts.php     # Script and style enqueuing
│   └── functions.php           # Core plugin functions
├── js/
│   ├── alt-text-generator.js   # Alt text generation logic
│   ├── bulk-processor.js       # Bulk processing functionality
│   ├── keywords-toggle.js      # Keywords feature toggle
│   ├── main.js                 # Main JavaScript
│   └── utils.js                # Utility functions
├── alt-friend.php              # Main plugin file
└── package.json                # Node dependencies
```

## Development

### Dependencies
- OpenAI Node.js SDK (^6.0.1)

### Setup for Development
```bash
cd wp-content/plugins/alt-friend
npm install
```

## Version

Current version: 0.1.1

## Author

Jimmy Laroche

## License

Standard WordPress plugin license

## Support

For issues, questions, or contributions, please contact the plugin author or refer to the plugin's repository.

## Changelog

### 0.1.1
- Current stable release
- OpenAI Vision API integration
- Bulk processing functionality
- Admin settings page

## Privacy & Data

This plugin sends image data to OpenAI's API for processing. Please review OpenAI's privacy policy and ensure compliance with your local data protection regulations.
