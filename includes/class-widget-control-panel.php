<?php
/**
 * Phoenix CRM Dashboard Widget — Control Panel
 *
 * Quick-action control panel: run collectors, trigger agent workflow,
 * toggle features, regenerate API key.
 *
 * @package Phoenix_CRM
 * @since   1.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Phoenix_CRM_Widget_Control_Panel
 */
class Phoenix_CRM_Widget_Control_Panel {

    /**
     * Get widget ID.
     */
    public static function get_id() {
        return 'control-panel';
    }

    /**
     * Get widget title.
     */
    public static function get_title() {
        return __( 'Control Panel', 'phoenix-crm' );
    }

    /**
     * Render the control panel widget.
     */
    public static function render() {
        $api_key = get_option( 'phoenix_api_key', '' );
        ?>
        <div class="phoenix-widget-control">
            <!-- Data Collection Controls -->
            <div style="margin-bottom:14px;">
                <h4 style="margin:0 0 8px 0;font-size:12px;color:#72777c;text-transform:uppercase;">
                    <?php esc_html_e( 'Data Collection', 'phoenix-crm' ); ?>
                </h4>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <button class="button button-small" id="phoenix-ctrl-run-internal"
                            onclick="if(confirm('<?php esc_js_e('Run internal collector?', 'phoenix-crm'); ?>')){var d={action:'phoenix_run_collector',_ajax_nonce:'<?php echo esc_js( wp_create_nonce( 'phoenix_crm_admin_nonce' ) ); ?>'};jQuery.post(ajaxurl,d).done(function(r){alert(r.data.message)});}">
                        <?php esc_html_e( '▶ Collect Internal', 'phoenix-crm' ); ?>
                    </button>
                    <button class="button button-small" id="phoenix-ctrl-run-external"
                            onclick="if(confirm('<?php esc_js_e('Run external collector?', 'phoenix-crm'); ?>')){var d={action:'phoenix_run_collector',external:'1',_ajax_nonce:'<?php echo esc_js( wp_create_nonce( 'phoenix_crm_admin_nonce' ) ); ?>'};jQuery.post(ajaxurl,d).done(function(r){alert(r.data.message)});}">
                        <?php esc_html_e( '▶ Collect External', 'phoenix-crm' ); ?>
                    </button>
                </div>
            </div>

            <!-- System Controls -->
            <div style="margin-bottom:14px;">
                <h4 style="margin:0 0 8px 0;font-size:12px;color:#72777c;text-transform:uppercase;">
                    <?php esc_html_e( 'System', 'phoenix-crm' ); ?>
                </h4>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=phoenix-crm-settings' ) ); ?>" class="button button-small">
                        <?php esc_html_e( '⚙ Settings', 'phoenix-crm' ); ?>
                    </a>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=phoenix-crm-raw-data' ) ); ?>" class="button button-small">
                        <?php esc_html_e( '🗄 Raw Data', 'phoenix-crm' ); ?>
                    </a>
                </div>
            </div>

            <!-- API Status -->
            <div style="border-top:1px solid #f0f0f1;padding-top:10px;">
                <h4 style="margin:0 0 6px 0;font-size:12px;color:#72777c;text-transform:uppercase;">
                    <?php esc_html_e( 'API Status', 'phoenix-crm' ); ?>
                </h4>
                <div style="display:flex;align-items:center;gap:8px;font-size:12px;">
                    <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:<?php echo ! empty( $api_key ) ? '#4caf50' : '#f44336'; ?>;"></span>
                    <span><?php echo ! empty( $api_key ) ? esc_html__( 'API Key Active', 'phoenix-crm' ) : esc_html__( 'No API Key', 'phoenix-crm' ); ?></span>
                    <code style="font-size:10px;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                        <?php echo ! empty( $api_key ) ? esc_html( substr( $api_key, 0, 16 ) . '...' ) : ''; ?>
                    </code>
                </div>
            </div>
        </div>
        <?php
    }
}