=== Media Optimizer — Image & Video Speed ===
Contributors: theherbcompany
Tags: image compression, webp, lazy load, video optimization, speed, performance, optimize images
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically compress images, generate WebP, lazy load images & videos, and replace YouTube/Vimeo embeds with lightweight thumbnails.

== Description ==

**Media Optimizer** is a lightweight, self-contained WordPress plugin that speeds up your website by optimizing images and videos — with no external service or API dependency.

= Image Optimization =
* Auto-compress JPEG, PNG, GIF on upload
* Strips EXIF/metadata (hidden data that adds file size)
* Generates WebP versions — 25–35% smaller than JPEG at same quality
* Compresses every thumbnail size WordPress creates, not just the original
* Bulk optimize your entire existing Media Library in one click
* Backup + rollback safety — original is never lost if something goes wrong
* Skips files already under 10 KB

= Video Optimization =
* YouTube & Vimeo click-to-play facade — shows a thumbnail instead of loading the full video player. Saves 400–600 KB per embed on initial page load
* Uses privacy-friendly youtube-nocookie.com
* Lazy loads self-hosted <video> elements — delays loading until scrolled into view

= Performance =
* Native lazy loading on all images (loading="lazy" — no JavaScript required)
* No external APIs or services — everything runs on your server
* Emergency bypass: visit /?mo_bypass=1 to instantly disable the plugin for your browser

= Built for =
* Product-heavy WooCommerce stores
* Photography portfolios
* Any WordPress site with large image uploads (2–5 MB photos)

== Installation ==

1. Go to **Plugins → Add New → Upload Plugin**
2. Upload the `wmsp-media-optimizer.zip` file
3. Click **Activate Plugin**
4. Go to **Media Optimizer** in your WordPress sidebar
5. Set your preferred quality (80 is recommended for product photos)
6. Click **Start — Bulk Optimize** to compress your existing image library
7. Done — new uploads are compressed automatically from now on

== Frequently Asked Questions ==

= Does this require any external service or API key? =
No. Everything runs on your server using PHP's built-in GD or Imagick library. No sign-up, no API key, no monthly fee.

= Which image formats are supported? =
JPEG, PNG, and GIF. WebP generation is supported for all three.

= What quality setting should I use? =
80 is the recommended default for product photos — visually identical to the original but significantly smaller. Go lower (70–75) if file size is more important, higher (85–90) if you need pixel-perfect quality.

= Will this break my images? =
No. Every compression is done to a temp file first. The original is only replaced if the compressed version is genuinely smaller. If anything goes wrong, the original is preserved automatically.

= What if something goes wrong on my site? =
Visit yoursite.com/?mo_bypass=1 — this instantly disables the plugin for your browser session without touching anything else.

= Does it work on WordPress.com? =
Partially. WebP generation and new upload compression work on WordPress.com Commerce plan. Bulk compression of existing images may not work due to WordPress.com's file write restrictions. All features work on self-hosted WordPress.

= Does it conflict with other plugins? =
It is designed to be compatible with WooCommerce, Elementor, Divi, and other major plugins. It does not touch page caching, HTML, CSS, or JavaScript — only images and video embeds.

= Will it compress images that are already optimized? =
It compresses to a temp file and compares sizes. If the result is not smaller than the original, it skips that file. So already-optimized images are safe.

== Screenshots ==

1. Main dashboard showing compression stats and settings
2. Bulk optimizer running with live progress
3. Compression log showing before/after file sizes
4. Video facade replacing a YouTube embed with a thumbnail

== Changelog ==

= 1.4.0 =
* Fix: Compress to temp file first — never write directly to original
* Fix: Handle WordPress.com file write restrictions with multiple fallback methods
* Reduced minimum file size threshold from 30 KB to 10 KB

= 1.3.0 =
* Fix: Replaced AJAX bulk with server-side auto-continuing form (no JavaScript dependency)
* Bulk now processes 10 images per batch and auto-continues until complete
* Settings form also works without JavaScript

= 1.2.0 =
* Fix: Better AJAX error handling with automatic retry (up to 5 failures)
* Added manual bulk fallback via URL redirect
* Increased per-batch timeout to 60 seconds

= 1.1.0 =
* Bulk optimizer now processes every thumbnail size (not just the original file)
* WebP generation included in bulk run
* Live progress counters during bulk (compressed / WebP / skipped / bytes saved)

= 1.0.0 =
* Initial release
* Auto-compress images on upload
* WebP generation
* YouTube & Vimeo click-to-play facades
* Native image lazy loading
* Self-hosted video lazy loading

== Upgrade Notice ==

= 1.4.0 =
Fixes bulk compression on WordPress.com and servers with restricted file write permissions. Recommended update for all users.

== Privacy Policy ==

This plugin does not collect, store, or transmit any personal data. All image processing happens locally on your server. No data is sent to external services.

Video facades load YouTube thumbnails from img.youtube.com and Vimeo thumbnails from vimeo.com's API when a page containing a video embed is first visited. This is a one-time request per video, cached for 7 days.
