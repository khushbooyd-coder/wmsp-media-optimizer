<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WMSP_Image_Compressor {

    private array $supported_mime = [ 'image/jpeg', 'image/jpg', 'image/png', 'image/gif' ];

    // ── Hook into upload pipeline ─────────────────────────────────────────
    public function hook_upload(): void {
        add_filter( 'wp_handle_upload',               [ $this, 'on_upload' ] );
        add_filter( 'wp_generate_attachment_metadata', [ $this, 'on_sizes_generated' ], 10, 2 );
    }

    public function hook_lazy(): void {
        add_filter( 'the_content',         [ $this, 'add_lazy' ] );
        add_filter( 'post_thumbnail_html', [ $this, 'add_lazy' ] );
        add_filter( 'widget_text',         [ $this, 'add_lazy' ] );
    }

    // ── On upload: compress full-size original ────────────────────────────
    public function on_upload( array $file ): array {
        if ( ! isset( $file['file'], $file['type'] ) ) return $file;
        if ( ! in_array( $file['type'], $this->supported_mime, true ) ) return $file;
        $this->compress_file( $file['file'], $file['type'] );
        return $file; // always return unchanged
    }

    // ── After sizes generated: compress every thumbnail too ───────────────
    public function on_sizes_generated( array $meta, int $id ): array {
        $opts = wmsp_mo()->get_options();

        $upload = wp_upload_dir();
        $base   = trailingslashit( $upload['basedir'] );
        $dir    = trailingslashit( dirname( $base . ( $meta['file'] ?? '' ) ) );
        $mime   = get_post_mime_type( $id ) ?: 'image/jpeg';

        // Original full-size
        $original_path = $base . ( $meta['file'] ?? '' );

        // All sizes (thumbnails etc)
        $all_files = [];
        if ( file_exists( $original_path ) ) $all_files[] = [ $original_path, $mime ];
        foreach ( $meta['sizes'] ?? [] as $size_data ) {
            $path = $dir . $size_data['file'];
            if ( file_exists( $path ) ) {
                $all_files[] = [ $path, $size_data['mime-type'] ?? $mime ];
            }
        }

        foreach ( $all_files as [ $path, $file_mime ] ) {
            if ( $opts['compress_on_upload'] ) {
                $this->compress_file( $path, $file_mime );
            }
            if ( $opts['generate_webp'] ) {
                $this->generate_webp( $path );
            }
        }

        return $meta; // never modify
    }

    // ════════════════════════════════════════════════════════════════════════
    // COMPRESS — with backup & rollback
    // ════════════════════════════════════════════════════════════════════════
    public function compress_file( string $path, string $mime ): bool {
        if ( ! file_exists( $path ) || ! is_readable( $path ) ) return false;
        if ( ! in_array( $mime, $this->supported_mime, true ) ) return false;

        $opts    = wmsp_mo()->get_options();
        $quality = (int) $opts['image_quality'];
        $before  = filesize( $path );

        if ( $before < 10240 ) return false;

        // Compress to TEMP file first — never touch original directly
        $tmp = $path . '.mo_tmp';
        $ok  = false;

        try {
            if ( extension_loaded( 'imagick' ) ) {
                $ok = $this->compress_imagick_to_tmp( $path, $tmp, $mime, $quality );
            } elseif ( extension_loaded( 'gd' ) ) {
                $ok = $this->compress_gd_to_tmp( $path, $tmp, $mime, $quality );
            }
        } catch ( Throwable $e ) {
            error_log( '[MediaOpt] Compress error: ' . $e->getMessage() );
            $ok = false;
        }

        if ( ! $ok || ! file_exists( $tmp ) || filesize( $tmp ) === 0 ) {
            @unlink( $tmp );
            return false;
        }

        $after = filesize( $tmp );

        // Only replace if smaller
        if ( $after >= $before ) {
            @unlink( $tmp );
            return false; // already optimised
        }

        // Replace original — try multiple methods for WP.com compatibility
        $replaced = false;
        if ( is_writable( $path ) ) {
            $replaced = (bool) @rename( $tmp, $path );
            if ( ! $replaced ) { $replaced = @copy( $tmp, $path ); @unlink( $tmp ); }
        } elseif ( is_writable( dirname( $path ) ) ) {
            @unlink( $path );
            $replaced = (bool) @rename( $tmp, $path );
        } else {
            $replaced = @copy( $tmp, $path );
            @unlink( $tmp );
        }

        if ( ! $replaced || ! file_exists( $path ) || filesize( $path ) === 0 ) {
            error_log( '[MediaOpt] Write failed: ' . basename( $path ) );
            return false;
        }

        $this->log( basename( $path ), $before, $after, $mime );
        return true;
    }

    private function compress_imagick_to_tmp( string $src_path, string $tmp, string $mime, int $quality ): bool {
        $img = new Imagick( $src_path );
        $img->stripImage();
        $img->setImageCompressionQuality( $quality );
        if ( in_array( $mime, [ 'image/jpeg', 'image/jpg' ], true ) ) {
            $img->setImageFormat( 'jpeg' );
            $img->setSamplingFactors( [ '2x2', '1x1', '1x1' ] );
            $img->setInterlaceScheme( Imagick::INTERLACE_JPEG );
        }
        $result = $img->writeImage( $tmp );
        $img->clear(); $img->destroy();
        return (bool) $result;
    }

    private function compress_gd_to_tmp( string $src_path, string $tmp, string $mime, int $quality ): bool {
        switch ( $mime ) {
            case 'image/jpeg': case 'image/jpg':
                $src = @imagecreatefromjpeg( $src_path );
                if ( ! $src ) return false;
                $ok = imagejpeg( $src, $tmp, $quality );
                imagedestroy( $src );
                return (bool) $ok;
            case 'image/png':
                $src = @imagecreatefrompng( $src_path );
                if ( ! $src ) return false;
                imagesavealpha( $src, true );
                $comp = (int) round( 9 - ( ( $quality - 40 ) / 55 * 9 ) );
                $ok   = imagepng( $src, $tmp, max( 0, min( 9, $comp ) ) );
                imagedestroy( $src );
                return (bool) $ok;
        }
        return false;
    }

    // ════════════════════════════════════════════════════════════════════════
    // WEBP GENERATION
    // ════════════════════════════════════════════════════════════════════════
    public function generate_webp( string $path, bool $force = false ): bool {
        if ( ! file_exists( $path ) ) return false;

        $webp = $this->webp_path( $path );
        if ( file_exists( $webp ) && ! $force ) return true; // already exists

        $mime    = @mime_content_type( $path );
        $quality = (int) wmsp_mo()->get_options()['image_quality'];

        // Allowed source types for WebP conversion
        if ( ! in_array( $mime, [ 'image/jpeg', 'image/jpg', 'image/png', 'image/gif' ], true ) ) return false;

        try {
            // Try Imagick first
            if ( extension_loaded( 'imagick' ) && in_array( 'WEBP', Imagick::queryFormats(), true ) ) {
                $img = new Imagick( $path );
                $img->setImageFormat( 'webp' );
                $img->setImageCompressionQuality( $quality );
                $img->stripImage();
                $ok = $img->writeImage( $webp );
                $img->clear(); $img->destroy();
                return $ok && file_exists( $webp ) && filesize( $webp ) > 0;
            }

            // GD fallback
            if ( extension_loaded( 'gd' ) && function_exists( 'imagewebp' ) ) {
                $src = match ( $mime ) {
                    'image/jpeg', 'image/jpg' => @imagecreatefromjpeg( $path ),
                    'image/png'               => @imagecreatefrompng( $path ),
                    'image/gif'               => @imagecreatefromgif( $path ),
                    default                   => false,
                };
                if ( ! $src ) return false;
                if ( $mime === 'image/png' ) imagesavealpha( $src, true );
                $ok = @imagewebp( $src, $webp, $quality );
                imagedestroy( $src );
                return $ok && file_exists( $webp ) && filesize( $webp ) > 0;
            }
        } catch ( Throwable $e ) {
            error_log( '[MediaOpt] WebP error: ' . $e->getMessage() );
            if ( file_exists( $webp ) ) @unlink( $webp );
        }
        return false;
    }

    public function webp_path( string $original_path ): string {
        // Store .webp right next to the original: image.jpg → image.jpg.webp
        return $original_path . '.webp';
    }

    public function webp_url( string $original_url ): string {
        return $original_url . '.webp';
    }

    // ════════════════════════════════════════════════════════════════════════
    // BULK — processes ALL attachments: original + every thumbnail + webp
    // ════════════════════════════════════════════════════════════════════════
    public function bulk_process_batch( int $offset = 0, int $per_batch = 3 ): array {
        $opts = wmsp_mo()->get_options();

        // Get a batch of image attachments
        $ids = get_posts([
            'post_type'      => 'attachment',
            'post_mime_type' => [ 'image/jpeg', 'image/png', 'image/gif' ],
            'posts_per_page' => $per_batch,
            'offset'         => $offset,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'orderby'        => 'ID',
            'order'          => 'ASC',
        ]);

        $compressed = 0;
        $webp_done  = 0;
        $skipped    = 0;
        $bytes_saved= 0;

        foreach ( $ids as $id ) {
            $result = $this->process_attachment( (int) $id, $opts );
            $compressed  += $result['compressed'];
            $webp_done   += $result['webp'];
            $skipped     += $result['skipped'];
            $bytes_saved += $result['saved'];
        }

        return [
            'compressed'  => $compressed,
            'webp'        => $webp_done,
            'skipped'     => $skipped,
            'bytes_saved' => $bytes_saved,
            'next_offset' => $offset + $per_batch,
            'finished'    => count( $ids ) < $per_batch,
        ];
    }

    /**
     * Process one attachment: compress original + all sizes + generate WebP for each
     */
    public function process_attachment( int $id, array $opts = [] ): array {
        if ( empty( $opts ) ) $opts = wmsp_mo()->get_options();

        $result = [ 'compressed' => 0, 'webp' => 0, 'skipped' => 0, 'saved' => 0 ];

        $meta   = wp_get_attachment_metadata( $id );
        $mime   = get_post_mime_type( $id );
        $upload = wp_upload_dir();
        $base   = trailingslashit( $upload['basedir'] );

        if ( ! $meta || ! $mime || ! in_array( $mime, $this->supported_mime, true ) ) {
            $result['skipped']++;
            return $result;
        }

        // Build list of ALL files for this attachment (original + every size)
        $files = [];
        if ( ! empty( $meta['file'] ) ) {
            $files[] = $base . $meta['file'];
        }
        if ( ! empty( $meta['sizes'] ) ) {
            $dir = trailingslashit( dirname( $base . $meta['file'] ) );
            foreach ( $meta['sizes'] as $size_data ) {
                $files[] = $dir . $size_data['file'];
            }
        }

        foreach ( $files as $path ) {
            if ( ! file_exists( $path ) ) continue;

            // 1. Compress
            if ( $opts['compress_on_upload'] ) {
                $before = filesize( $path );
                $ok     = $this->compress_file( $path, $mime );
                if ( $ok ) {
                    $result['compressed']++;
                    $result['saved'] += $before - filesize( $path );
                } else {
                    $result['skipped']++;
                }
            }

            // 2. Generate WebP
            if ( $opts['generate_webp'] ) {
                $ok = $this->generate_webp( $path );
                if ( $ok ) $result['webp']++;
            }
        }

        return $result;
    }

    // ── Lazy load ─────────────────────────────────────────────────────────
    public function add_lazy( string $content ): string {
        if ( empty( $content ) || is_feed() || is_admin() ) return $content;
        $result = preg_replace_callback( '/<img([^>]+?)>/i', function ( $m ) {
            $tag = $m[0];
            if ( strpos( $tag, 'loading=' ) !== false ) return $tag;
            if ( strpos( $tag, 'src="data:' ) !== false || strpos( $tag, "src='data:" ) !== false ) return $tag;
            if ( strpos( $tag, 'no-lazy' ) !== false ) return $tag;
            return str_replace( '<img', '<img loading="lazy" decoding="async"', $tag );
        }, $content );
        return $result ?? $content;
    }

    // ── Helpers & logging ─────────────────────────────────────────────────
    private function log( string $file, int $before, int $after, string $mime, bool $skipped = false ): void {
        $log   = get_option( 'wmsp_mo_image_log', [] );
        $log[] = [
            'file'    => $file,
            'before'  => $before,
            'after'   => $after,
            'saved'   => $before - $after,
            'pct'     => round( ( $before - $after ) / max( 1, $before ) * 100, 1 ),
            'mime'    => $mime,
            'skipped' => $skipped,
            'ts'      => time(),
        ];
        update_option( 'wmsp_mo_image_log', array_slice( $log, -500 ) );
    }

    public function get_log(): array   { return get_option( 'wmsp_mo_image_log', [] ); }
    public function total_saved(): int { return (int) array_sum( array_column( $this->get_log(), 'saved' ) ); }
    public function total_count(): int { return count( $this->get_log() ); }

    public function supports_compression(): bool {
        return extension_loaded( 'imagick' ) || extension_loaded( 'gd' );
    }

    public function supports_webp(): bool {
        return ( extension_loaded( 'imagick' ) && in_array( 'WEBP', Imagick::queryFormats(), true ) )
            || ( extension_loaded( 'gd' ) && function_exists( 'imagewebp' ) );
    }

    public function count_total_attachments(): int {
        $counts = wp_count_posts( 'attachment' );
        return (int) ( $counts->inherit ?? 0 );
    }

    public function count_image_attachments(): int {
        global $wpdb;
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts}
             WHERE post_type = 'attachment'
             AND post_mime_type IN ('image/jpeg','image/png','image/gif','image/jpg')"
        );
    }
}
