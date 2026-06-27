# 🌿 Media Optimizer — Image & Video Speed

WordPress plugin for **The Herb Company** that compresses images, generates WebP versions, and lazy-loads videos.

## What it does

### Images
- Auto-compresses JPEG, PNG, GIF on upload (Imagick or GD)
- Strips EXIF metadata (hidden data that adds file size)
- Generates `.webp` versions — 25–35% smaller than JPEG at same quality
- Processes every thumbnail size WordPress creates, not just the original
- Bulk optimize all existing images in the Media Library
- Backup + rollback safety — originals are restored if anything goes wrong
- Skips files under 30 KB (not worth processing)

### Videos
- YouTube/Vimeo click-to-play facade — shows thumbnail, loads iframe only on click (~500 KB saved per embed)
- Uses `youtube-nocookie.com` for privacy
- Lazy loads self-hosted `<video>` elements

### Safety
- Emergency bypass: visit `/?mo_bypass=1` to disable the plugin for your browser session
- No page cache (safe for WordPress.com Commerce plan)
- No HTML minification (avoids breaking page builders)
- Each compression backed up and rolled back on failure

## Installation

1. Download the `.zip` from [Releases](../../releases)
2. Go to **Plugins → Add New → Upload Plugin**
3. Upload and activate
4. Go to **Media Optimizer** in the sidebar
5. Set quality to **80** for product photos
6. Run **Bulk Optimize** once to compress existing images

## Requirements

- WordPress 5.8+
- PHP 7.4+
- GD or Imagick extension (GD is available on WordPress.com Commerce plan)

## Development

```bash
git clone https://github.com/YOUR_USERNAME/wmsp-media-optimizer.git
cd wmsp-media-optimizer
```

To build a release zip:
```bash
zip -r wmsp-media-optimizer.zip . --exclude ".git/*" --exclude "*.zip" --exclude ".DS_Store"
```

## File structure

```
wmsp-media-optimizer/
├── wmsp-media-optimizer.php      # Main plugin file, bootstrap
├── includes/
│   ├── class-image-compressor.php  # Compression, WebP, lazy load, bulk
│   └── class-video-optimizer.php   # YouTube/Vimeo facades, native video lazy
├── admin/
│   ├── class-admin.php             # Admin menu, AJAX handlers
│   └── views/
│       └── main.php                # Dashboard UI
└── assets/
    ├── css/admin.css
    └── js/admin.js
```

## Changelog

### v1.1.0
- Bulk optimizer now processes every thumbnail size (not just the original)
- WebP generation included in bulk run
- Live progress counters during bulk (compressed / WebP / skipped / bytes saved)
- Retry logic on network failure during bulk
- Reduced batch size to 3 to avoid timeouts on WordPress.com

### v1.0.0
- Initial release
