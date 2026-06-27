<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$opts        = wmsp_mo()->get_options();
$log         = wmsp_mo()->images->get_log();
$saved       = wmsp_mo()->images->total_saved();
$count       = wmsp_mo()->images->total_count();
$total_imgs  = wmsp_mo()->images->count_image_attachments();
$has_compress= wmsp_mo()->images->supports_compression();
$has_webp    = wmsp_mo()->images->supports_webp();

// ── Handle server-side bulk (no JS/AJAX needed) ───────────────────────────
$bulk_msg    = '';
$bulk_status = '';

if ( isset( $_POST['mo_action'] ) && $_POST['mo_action'] === 'bulk_run' ) {
    check_admin_referer( 'mo_bulk_server' );
    if ( current_user_can( 'manage_options' ) ) {
        if ( ! ini_get('safe_mode') ) @set_time_limit( 300 );

        $offset      = (int) ( $_POST['mo_offset'] ?? 0 );
        $batch_size  = 10; // larger batch since no AJAX overhead
        $result      = wmsp_mo()->images->bulk_process_batch( $offset, $batch_size );

        // Accumulate totals across page reloads
        $prev = get_option( 'wmsp_mo_bulk_run', [ 'comp'=>0,'webp'=>0,'skip'=>0,'bytes'=>0 ] );
        $totals = [
            'comp'  => $prev['comp']  + $result['compressed'],
            'webp'  => $prev['webp']  + $result['webp'],
            'skip'  => $prev['skip']  + $result['skipped'],
            'bytes' => $prev['bytes'] + $result['bytes_saved'],
        ];
        update_option( 'wmsp_mo_bulk_run', $totals );

        if ( $result['finished'] ) {
            delete_option( 'wmsp_mo_bulk_run' );
            $bulk_status = 'done';
            $bulk_msg    = sprintf(
                '✅ All done! Compressed %d files, generated %d WebP, saved %s total.',
                $totals['comp'], $totals['webp'], mo_fmt( $totals['bytes'] )
            );
        } else {
            $bulk_status   = 'continue';
            $next_offset   = $result['next_offset'];
            $pct           = $total_imgs > 0 ? round( $next_offset / $total_imgs * 100 ) : 0;
            $bulk_msg      = sprintf(
                'Processed %d of ~%d images (%d%%) — Compressed: %d, WebP: %d, Skipped: %d, Saved: %s',
                $next_offset, $total_imgs, $pct,
                $totals['comp'], $totals['webp'], $totals['skip'], mo_fmt( $totals['bytes'] )
            );
        }
    }
}

// Handle settings save (server-side fallback too)
if ( isset( $_POST['mo_action'] ) && $_POST['mo_action'] === 'save_settings' ) {
    check_admin_referer( 'mo_save_settings' );
    if ( current_user_can( 'manage_options' ) ) {
        wmsp_mo()->save_options( $_POST );
        $opts = wmsp_mo()->get_options(); // reload
        $bulk_msg    = '✅ Settings saved.';
        $bulk_status = 'saved';
    }
}

function mo_fmt( int $b ): string {
    if ( $b >= 1073741824 ) return round( $b / 1073741824, 2 ) . ' GB';
    if ( $b >= 1048576 )    return round( $b / 1048576, 2 ) . ' MB';
    if ( $b >= 1024 )       return round( $b / 1024, 1 ) . ' KB';
    return $b . ' B';
}

$in_progress = get_option( 'wmsp_mo_bulk_run', null );
$next_offset = isset( $_POST['mo_offset'] ) && $bulk_status === 'continue'
    ? (int) $result['next_offset']
    : ( $in_progress ? -1 : 0 );
?>
<div class="mo-wrap">

  <div class="mo-header">
    <div class="mo-logo">🌿 Media Optimizer</div>
    <div class="mo-tagline">Image &amp; Video Speed — The Herb Company</div>
  </div>

  <!-- Library status -->
  <div class="mo-lib-row">
    <span class="mo-lib-badge <?php echo $has_compress ? 'ok' : 'warn'; ?>">
      <?php echo $has_compress ? '✅ ' . ( extension_loaded('imagick') ? 'Imagick' : 'GD' ) . ' — compression ready' : '⚠️ No image library'; ?>
    </span>
    <span class="mo-lib-badge <?php echo $has_webp ? 'ok' : 'warn'; ?>">
      <?php echo $has_webp ? '✅ WebP supported' : '⚠️ WebP not available'; ?>
    </span>
  </div>

  <!-- Status message from server action -->
  <?php if ( $bulk_msg ) : ?>
  <div class="mo-alert <?php echo $bulk_status === 'done' || $bulk_status === 'saved' ? 'ok' : 'info'; ?>">
    <?php echo esc_html( $bulk_msg ); ?>
    <?php if ( $bulk_status === 'continue' ) : ?>
      — <strong>page will continue automatically…</strong>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Auto-continue form (submits itself immediately if bulk is in progress) -->
  <?php if ( $bulk_status === 'continue' ) : ?>
  <form method="post" id="mo-auto-continue">
    <?php wp_nonce_field( 'mo_bulk_server' ); ?>
    <input type="hidden" name="mo_action" value="bulk_run">
    <input type="hidden" name="mo_offset" value="<?php echo (int) $result['next_offset']; ?>">
  </form>
  <script>
    // Auto-submit to continue next batch
    document.getElementById('mo-auto-continue').submit();
  </script>
  <?php endif; ?>

  <!-- Stats -->
  <div class="mo-stats">
    <div class="mo-stat">
      <div class="mo-stat-n"><?php echo $count; ?></div>
      <div class="mo-stat-l">Files Compressed</div>
    </div>
    <div class="mo-stat green">
      <div class="mo-stat-n"><?php echo mo_fmt( $saved ); ?></div>
      <div class="mo-stat-l">Total Space Saved</div>
    </div>
    <div class="mo-stat blue">
      <div class="mo-stat-n"><?php echo $total_imgs; ?></div>
      <div class="mo-stat-l">Images in Library</div>
    </div>
    <div class="mo-stat orange">
      <div class="mo-stat-n"><?php echo $total_imgs > 0 && $count > 0 ? min(100, round($count/$total_imgs*100)).'%' : '0%'; ?></div>
      <div class="mo-stat-l">Library Optimized</div>
    </div>
  </div>

  <div class="mo-cols">

    <!-- Settings — pure HTML form, no JS needed -->
    <div class="mo-panel">
      <h2>⚙️ Settings</h2>
      <form method="post">
        <?php wp_nonce_field('mo_save_settings'); ?>
        <input type="hidden" name="mo_action" value="save_settings">

        <div class="mo-section-label">🖼️ Images</div>

        <div class="mo-row">
          <label class="mo-toggle-wrap">
            <span class="mo-row-info">
              <strong>Auto-compress on upload</strong>
              <span>Every new image is compressed the moment you upload it.</span>
            </span>
            <span class="mo-switch">
              <input type="checkbox" name="compress_on_upload" value="1" <?php checked($opts['compress_on_upload']); ?>>
              <span class="mo-slider"></span>
            </span>
          </label>
        </div>

        <div class="mo-row">
          <div class="mo-quality-label">
            <strong>Quality: <span id="mo-qval"><?php echo $opts['image_quality']; ?></span></strong>
            <span>80 is ideal for product photos — great quality, much smaller size.</span>
          </div>
          <input type="range" name="image_quality" id="mo-qslider"
                 min="40" max="95" step="5" value="<?php echo $opts['image_quality']; ?>"
                 oninput="document.getElementById('mo-qval').textContent=this.value">
          <div class="mo-qlabels"><span>Smaller</span><span>← Best: 75–85 →</span><span>Sharper</span></div>
        </div>

        <div class="mo-row">
          <label class="mo-toggle-wrap">
            <span class="mo-row-info">
              <strong>Generate WebP</strong>
              <span>Creates a .webp copy — 25–35% smaller than JPEG at same quality.</span>
            </span>
            <span class="mo-switch">
              <input type="checkbox" name="generate_webp" value="1" <?php checked($opts['generate_webp']); ?>>
              <span class="mo-slider"></span>
            </span>
          </label>
        </div>

        <div class="mo-row">
          <label class="mo-toggle-wrap">
            <span class="mo-row-info">
              <strong>Lazy load images</strong>
              <span>Images load only when scrolled into view.</span>
            </span>
            <span class="mo-switch">
              <input type="checkbox" name="image_lazy" value="1" <?php checked($opts['image_lazy']); ?>>
              <span class="mo-slider"></span>
            </span>
          </label>
        </div>

        <div class="mo-section-label">🎬 Videos</div>

        <div class="mo-row">
          <label class="mo-toggle-wrap">
            <span class="mo-row-info">
              <strong>YouTube &amp; Vimeo click-to-play</strong>
              <span>Shows thumbnail instead of iframe. Saves 400–600 KB per embed.</span>
            </span>
            <span class="mo-switch">
              <input type="checkbox" name="video_facade" value="1" <?php checked($opts['video_facade']); ?>>
              <span class="mo-slider"></span>
            </span>
          </label>
        </div>

        <div class="mo-row">
          <label class="mo-toggle-wrap">
            <span class="mo-row-info">
              <strong>Lazy load self-hosted videos</strong>
              <span>Delays video loading until scrolled into view.</span>
            </span>
            <span class="mo-switch">
              <input type="checkbox" name="video_lazy" value="1" <?php checked($opts['video_lazy']); ?>>
              <span class="mo-slider"></span>
            </span>
          </label>
        </div>

        <div class="mo-actions">
          <button type="submit" class="mo-btn primary">💾 Save Settings</button>
        </div>
      </form>
    </div>

    <!-- Bulk — pure form POST, zero JS dependency -->
    <div class="mo-panel">
      <h2>🗜️ Bulk Optimize All <?php echo $total_imgs; ?> Images</h2>

      <?php if ( ! $has_compress ) : ?>
        <div class="mo-alert warn">Image compression not available on this server.</div>
      <?php else : ?>

        <div class="mo-what-happens">
          <strong>For each image this will:</strong>
          <ul>
            <li>✅ Compress the original + all thumbnail sizes</li>
            <?php if ($has_webp): ?><li>✅ Generate WebP version</li><?php endif; ?>
            <li>✅ Skip files under 30 KB</li>
            <li>✅ Auto-rollback if anything fails</li>
          </ul>
        </div>

        <?php if ( $bulk_status !== 'continue' ) : ?>
        <form method="post">
          <?php wp_nonce_field( 'mo_bulk_server' ); ?>
          <input type="hidden" name="mo_action" value="bulk_run">
          <input type="hidden" name="mo_offset" value="0">
          <button type="submit" class="mo-btn success mo-btn-full">
            ▶ Start — Optimize All <?php echo $total_imgs; ?> Images
          </button>
        </form>
        <p class="mo-status-text" style="margin-top:8px">
          ⚠️ <strong>Keep this tab open</strong> while it runs. The page will auto-refresh through all batches.
        </p>
        <?php else : ?>
        <div class="mo-alert info">⏳ Running… processing batch automatically.</div>
        <?php endif; ?>

      <?php endif; ?>

      <div class="mo-tip" style="margin-top:16px">
        <strong>💡 New uploads</strong> are compressed automatically — only run Bulk once for existing images.
      </div>
      <div class="mo-bypass">
        <strong>🆘 Emergency:</strong> If site looks wrong:<br>
        <code><?php echo esc_html( home_url('/?mo_bypass=1') ); ?></code>
      </div>
    </div>

  </div>

  <!-- Log -->
  <?php if ( ! empty( $log ) ) :
    $recent = array_slice( array_reverse( $log ), 0, 50 ); ?>
  <div class="mo-panel mo-log-panel">
    <div class="mo-log-hdr">
      <h2>📋 Compression Log <span class="mo-log-count"><?php echo count($log); ?> entries</span></h2>
      <form method="post" style="display:inline">
        <?php wp_nonce_field('mo_clear_log_nonce'); ?>
        <input type="hidden" name="mo_action" value="clear_log">
        <button type="submit" class="mo-btn danger-sm"
                onclick="return confirm('Clear log? Images are NOT affected.')">🗑 Clear Log</button>
      </form>
    </div>
    <table class="mo-table">
      <thead>
        <tr><th>File</th><th>Before</th><th>After</th><th>Saved</th><th>Type</th><th>When</th></tr>
      </thead>
      <tbody>
        <?php foreach ( $recent as $e ) : ?>
        <tr>
          <td class="mo-f"><?php echo esc_html( $e['file'] ); ?></td>
          <td><?php echo mo_fmt( $e['before'] ); ?></td>
          <td><?php echo mo_fmt( $e['after'] ); ?></td>
          <td class="mo-sv">▼ <?php echo $e['pct']; ?>% <em><?php echo mo_fmt( $e['saved'] ); ?></em></td>
          <td><span class="mo-type"><?php echo esc_html( strtoupper( str_replace('image/', '', $e['mime'] ?? '') ) ); ?></span></td>
          <td class="mo-dt"><?php echo date( 'M j, H:i', $e['ts'] ); ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php if ( count($log) > 50 ) : ?>
      <p class="mo-log-more">Showing latest 50 of <?php echo count($log); ?> total</p>
    <?php endif; ?>
  </div>
  <?php else : ?>
  <div class="mo-panel mo-empty">📭 No compressions yet — click Start above.</div>
  <?php endif; ?>

</div>
