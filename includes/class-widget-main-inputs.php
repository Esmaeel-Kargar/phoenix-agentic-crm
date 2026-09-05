<?php
/**
 * Phoenix CRM Dashboard Widget — Main Inputs
 *
 * Consolidated data entry panel for quick input: tasks, notes,
 * and triggering data collection. Provides a single point of
 * entry for user-generated content.
 *
 * @package Phoenix_CRM
 * @since   1.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Phoenix_CRM_Widget_Main_Inputs
 */
class Phoenix_CRM_Widget_Main_Inputs {

    /**
     * Get widget ID.
     */
    public static function get_id() {
        return 'main-inputs';
    }

    /**
     * Get widget title.
     */
    public static function get_title() {
        return __( 'Main Inputs', 'phoenix-crm' );
    }

    /**
     * Render the main inputs widget.
     */
    public static function render() {
        ?>
        <div class="phoenix-widget-main-inputs">
            <!-- Quick Task Input -->
            <div class="phoenix-input-section" style="margin-bottom:16px;">
                <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px;">
                    <?php esc_html_e( 'Quick Task', 'phoenix-crm' ); ?>
                </label>
                <div class="phoenix-quick-add">
                    <input type="text" id="phoenix-quick-task-input" 
                           placeholder="<?php esc_attr_e( 'Add a task...', 'phoenix-crm' ); ?>"
                           style="flex:1;" class="regular-text" />
                    <select id="phoenix-quick-task-priority" style="width:100px;">
                        <option value="low"><?php esc_html_e( 'Low', 'phoenix-crm' ); ?></option>
                        <option value="medium" selected><?php esc_html_e( 'Medium', 'phoenix-crm' ); ?></option>
                        <option value="high"><?php esc_html_e( 'High', 'phoenix-crm' ); ?></option>
                        <option value="critical"><?php esc_html_e( 'Critical', 'phoenix-crm' ); ?></option>
                    </select>
                    <button class="button button-primary" id="phoenix-quick-task-btn">
                        <?php esc_html_e( 'Add', 'phoenix-crm' ); ?>
                    </button>
                </div>
            </div>

            <!-- Quick Note Input -->
            <div class="phoenix-input-section" style="margin-bottom:16px;">
                <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px;">
                    <?php esc_html_e( 'Quick Note', 'phoenix-crm' ); ?>
                </label>
                <div class="phoenix-quick-add" style="flex-direction:column;gap:6px;">
                    <textarea id="phoenix-quick-note-input" rows="2" 
                              placeholder="<?php esc_attr_e( 'Write a note...', 'phoenix-crm' ); ?>"
                              style="width:100%;" class="large-text"></textarea>
                    <button class="button" id="phoenix-quick-note-btn">
                        <?php esc_html_e( 'Save Note', 'phoenix-crm' ); ?>
                    </button>
                </div>
            </div>

            <!-- Quick Data Collection Trigger -->
            <div class="phoenix-input-section">
                <label style="font-weight:600;font-size:13px;display:block;margin-bottom:6px;">
                    <?php esc_html_e( 'Data Collection', 'phoenix-crm' ); ?>
                </label>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <button class="button" id="phoenix-run-collector" 
                            title="<?php esc_attr_e( 'Run internal data collector now', 'phoenix-crm' ); ?>">
                        <span class="dashicons dashicons-update" style="font-size:14px;width:14px;height:14px;margin-top:3px;"></span>
                        <?php esc_html_e( 'Collect Now', 'phoenix-crm' ); ?>
                    </button>
                    <span id="phoenix-collector-status" style="font-size:12px;color:#72777c;align-self:center;"></span>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Handle AJAX: run collector manually.
     */
    public static function handle_ajax_run_collector() {
        if ( ! check_ajax_referer( 'phoenix_crm_admin_nonce', '_wpnonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'phoenix-crm' ) ) );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'phoenix-crm' ) ) );
        }

        // Trigger internal collector.
        if ( class_exists( 'Phoenix_CRM_Collector_Internal' ) ) {
            $collector = new Phoenix_CRM_Collector_Internal();
            $collector->collect_all();
        }
        if ( class_exists( 'Phoenix_CRM_Collector_External' ) ) {
            $collector = new Phoenix_CRM_Collector_External();
            $collector->collect_all();
        }

        do_action( 'phoenix_event_logged', 0, 'user', 'collector_manual_run', 'system', 0, 'Manual data collection triggered from dashboard.' );

        wp_send_json_success( array( 'message' => __( 'Collection complete.', 'phoenix-crm' ) ) );
    }
}