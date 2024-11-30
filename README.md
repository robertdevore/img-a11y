# img-a11y

IMG A11Y is a free WordPress® plugin that enhances image accessibility by adding fields for decorative image marking and accessibility prompts to the WordPress® media editor. 

It validates images on post save to ensure all images meet accessibility requirements, promoting better compliance with web accessibility standards.

## Features

- **Decorative Image Marking**: Add a checkbox in the media editor to mark images as decorative, indicating they do not require alt text.
- **Alt Text Enforcement**: Prevent saving posts or media if images are missing alt text and are not marked as decorative.
- **Gutenberg and Classic Editor Support**: Works seamlessly with both Gutenberg and Classic WordPress® editors.
- **Admin Accessibility Overview**: Provides an admin page listing images without alt text and offers statistics on image accessibility compliance.
- **Automatic Updates**: Integrated with GitHub for automatic updates of the plugin.

## Installation

1. **Download the Plugin**: Clone or download the plugin files from the [GitHub repository](https://github.com/robertdevore/img-a11y).

2. **Upload to WordPress®**:

    - **Via Admin Dashboard**:

        1. Navigate to `Plugins` > `Add New`.
        2. Click `Upload Plugin`.
        3. Choose the `img-a11y.zip` file you downloaded.
        4. Click `Install Now`, then `Activate`.
    - **Via FTP**:

        1. Extract the `img-a11y.zip` file.
        2. Upload the extracted `img-a11y` folder to your `wp-content/plugins/` directory.
        3. Go to `Plugins` in your WordPress admin dashboard and activate **IMG A11Y**.

## Usage

### Marking Images as Decorative

1. **Edit an Image**:

    - Go to `Media` > `Library` and select an image to edit.
    - In the attachment details, you'll see a new checkbox labeled **Mark as Decorative**.
2. **Mark as Decorative**:

    - If the image is purely decorative and doesn't add informative content, check the **Mark as Decorative** box.
    - This indicates that the image does not require alt text for accessibility purposes.

### Alt Text Validation

- **On Post Save**:

    - When saving a post, the plugin checks all images in the content.
    - If any images lack alt text and are not marked as decorative, the save operation is blocked.
    - An error message is displayed:  
`Save failed: Please ensure all images in the content have alt tags or are marked as decorative for accessibility.`
- **In Media Library**:

    - When editing media, if an image lacks alt text and is not marked as decorative, saving is blocked.
    - An error message is displayed:  
`Save failed: Please provide an Alt tag for accessibility or mark the image as decorative.`

### Admin Accessibility Overview

- **Access the Overview**:

    - Navigate to `Media` > **IMG A11Y** in the WordPress admin dashboard.
- **Features of the Admin Page**:

    - **Statistics**: View counts of decorative images, non-decorative images without alt text, and non-decorative images with alt text.
    - **Filtering**: Click on the statistics boxes to filter and list specific groups of images.
    - **Image Listing**: See a table of images based on the selected filter, including thumbnails, IDs, titles, and file links.

## Contributing

Contributions are welcome! Please follow these steps:

1. **Fork the Repository**: Click on the `Fork` button in GitHub.

2. **Clone Your Fork**:
```
git clone https://github.com/your-username/img-a11y.git
```

3. **Create a Branch**:
```
git checkout -b feature/your-feature-name
```

4. **Make Changes**: Implement your feature or fix.

5. **Commit Changes**:
```
git commit -m 'Add new feature'
```

6. **Push to GitHub**:
```
git push origin feature/your-feature-name
```

7. **Create a Pull Request**: Submit your PR for review.

## License

This plugin is licensed under the GPL-2.0+ license. See the [LICENSE](LICENSE) file for details.
