<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WMSP_Video_Optimizer {

    public function hook_facade(): void {
        add_filter( 'the_content', [ $this, 'youtube_facade' ], 20 );
        add_filter( 'the_content', [ $this, 'vimeo_facade' ],   21 );
        add_action( 'wp_footer',   [ $this, 'output_assets' ] );
    }

    public function hook_native_lazy(): void {
        add_filter( 'the_content', [ $this, 'lazy_video_tags' ], 22 );
    }

    // ── YouTube → click-to-play thumbnail ────────────────────────────────
    public function youtube_facade( string $content ): string {
        if ( empty( $content ) ) return $content;

        $pattern = '#<iframe([^>]+?)src=(["\'])(?:https?:)?//(?:www\.)?(?:youtube(?:-nocookie)?\.com/embed/|youtu\.be/)([a-zA-Z0-9_\-]{11})[^"\']*\2[^>]*>(?:\s*</iframe>)?#i';

        $result = preg_replace_callback( $pattern, function ( $m ) {
            $vid = $m[3];
            if ( ! preg_match( '/^[a-zA-Z0-9_\-]{11}$/', $vid ) ) return $m[0];

            // YouTube gives us multiple thumbnail qualities — use maxresdefault with hqdefault fallback
            $thumb_hq  = 'https://img.youtube.com/vi/' . $vid . '/hqdefault.jpg';
            $embed_url = 'https://www.youtube-nocookie.com/embed/' . $vid . '?autoplay=1&rel=0';

            return $this->facade_markup( $vid, $thumb_hq, $embed_url, 'YouTube video' );
        }, $content );

        return $result ?? $content;
    }

    // ── Vimeo → click-to-play thumbnail ──────────────────────────────────
    public function vimeo_facade( string $content ): string {
        if ( empty( $content ) ) return $content;

        $pattern = '#<iframe([^>]+?)src=(["\'])(?:https?:)?//(?:player\.)?vimeo\.com/(?:video/)?([0-9]+)[^"\']*\2[^>]*>(?:\s*</iframe>)?#i';

        $result = preg_replace_callback( $pattern, function ( $m ) {
            $vid = $m[3];
            if ( ! ctype_digit( $vid ) ) return $m[0];

            // Cache the thumbnail URL so we don't hit Vimeo API on every page load
            $cached = get_transient( 'mo_vimeo_' . $vid );
            if ( $cached ) {
                $thumb = $cached;
            } else {
                $thumb = ''; // fallback: no thumbnail, still shows play button
                $resp  = wp_remote_get(
                    'https://vimeo.com/api/v2/video/' . $vid . '.json',
                    [ 'timeout' => 4, 'sslverify' => true ]
                );
                if ( ! is_wp_error( $resp ) && wp_remote_retrieve_response_code( $resp ) === 200 ) {
                    $data  = json_decode( wp_remote_retrieve_body( $resp ), true );
                    $thumb = $data[0]['thumbnail_large'] ?? '';
                }
                set_transient( 'mo_vimeo_' . $vid, $thumb ?: 'none', WEEK_IN_SECONDS );
            }
            if ( $thumb === 'none' ) $thumb = '';

            $embed_url = 'https://player.vimeo.com/video/' . $vid . '?autoplay=1';
            return $this->facade_markup( $vid, $thumb, $embed_url, 'Vimeo video' );
        }, $content );

        return $result ?? $content;
    }

    // ── Shared facade HTML ────────────────────────────────────────────────
    private function facade_markup( string $vid, string $thumb, string $embed_url, string $label ): string {
        $thumb_html = $thumb
            ? '<img src="' . esc_url( $thumb ) . '" alt="' . esc_attr( $label ) . '" loading="lazy" decoding="async">'
            : '<div class="mo-facade-bg"></div>';

        return '<div class="mo-facade" data-embed="' . esc_url( $embed_url ) . '" role="button" tabindex="0" aria-label="' . esc_attr( 'Play ' . $label ) . '">'
            . $thumb_html
            . '<button class="mo-play" type="button" aria-label="Play">'
            . '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 68 48" width="68" height="48" aria-hidden="true">'
            . '<rect width="68" height="48" rx="10" fill="rgba(0,0,0,.7)"/>'
            . '<polygon points="26,14 26,34 46,24" fill="#fff"/>'
            . '</svg>'
            . '</button>'
            . '</div>';
    }

    // ── Native <video> lazy load ──────────────────────────────────────────
    public function lazy_video_tags( string $content ): string {
        if ( empty( $content ) ) return $content;

        $result = preg_replace_callback( '/<video([^>]*)>/i', function ( $m ) {
            $attrs = $m[1];
            if ( strpos( $attrs, 'preload=' ) === false ) $attrs .= ' preload="none"';
            if ( strpos( $attrs, 'loading=' ) === false ) $attrs .= ' loading="lazy"';
            return '<video' . $attrs . '>';
        }, $content );

        return $result ?? $content;
    }

    // ── Inline CSS + JS (output once in footer) ───────────────────────────
    public function output_assets(): void {
        ?>
<style id="mo-facade-css">
.mo-facade{position:relative;display:block;width:100%;padding-top:56.25%;background:#111;cursor:pointer;overflow:hidden;border-radius:6px;margin:1em 0}
.mo-facade img,.mo-facade-bg{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;border-radius:6px}
.mo-facade-bg{background:linear-gradient(135deg,#1a1a2e,#16213e)}
.mo-play{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:none;border:none;cursor:pointer;padding:0;transition:transform .15s,opacity .15s;opacity:.9}
.mo-facade:hover .mo-play{transform:translate(-50%,-50%) scale(1.1);opacity:1}
.mo-facade iframe{position:absolute;inset:0;width:100%;height:100%;border:0;border-radius:6px}
</style>
<script id="mo-facade-js">
(function(){
  function load(el){
    var url=el.dataset.embed;
    if(!url)return;
    var f=document.createElement('iframe');
    f.src=url;
    f.allow='accelerometer;autoplay;clipboard-write;encrypted-media;gyroscope;picture-in-picture';
    f.allowFullscreen=true;
    while(el.firstChild)el.removeChild(el.firstChild);
    el.style.cursor='default';
    el.appendChild(f);
  }
  document.addEventListener('click',function(e){
    var el=e.target.closest('.mo-facade');
    if(el)load(el);
  });
  document.addEventListener('keydown',function(e){
    if(e.key!=='Enter'&&e.key!==' ')return;
    if(document.activeElement&&document.activeElement.classList.contains('mo-facade')){
      e.preventDefault();load(document.activeElement);
    }
  });
})();
</script>
        <?php
    }
}
