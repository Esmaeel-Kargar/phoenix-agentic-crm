<?php
/**
 * Phoenix CRM Dashboard Widget — Notes
 *
 * Displays a quick-add note form and the five most recent notes
 * from the phoenix_notes table. Supports inline AJAX submission
 * without a full page reload.
 *
 * @package    Phoenix_CRM
 * @subpackage Widgets
 * @since      1.1.0
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Phoenix_CRM_Widget_Notes
 *
 * Renders a Notes widget for the CRM dashboard v2.
 *
 * @since 1.1.0
 */
class Phoenix_CRM_Widget_Notes {

    /**
     * Return the unique widget ID.
     *
     * @since 1.1.0
     * @return string
     */
    public static function get_id() {
        return 'notes';
    }

    /**
     * Return the display title for the widget.
     *
     * @since 1.1.0
     * @return string
     */
    public static function get_title() {
        return __( 'Notes', 'phoenix-crm' );
    }

    /**
     * Render the widget HTML.
     *
     * Outputs a quick-add textarea with an "Add Note" button that
     * submits via AJAX, followed by a list of the 5 most recent notes.
     * Each note shows truncated content (80 chars) with a "time ago"
     * label and an expand-toggle link.
     *
     * @since 1.1.0
     * @return void
     */
    public static function render() {
        $notes = self::get_notes();
        ?>
        <div class="phoenix-card phoenix-widget-notes">
            <h3><?php echo esc_html( self::get_title() ); ?></h3>

            <!-- Quick-add form -->
            <div class="phoenix-notes-add" style="margin-bottom:14px;">
                <textarea id="phoenix-note-content" rows="3" style="width:100%;" placeholder="<?php esc_attr_e( 'Type a quick note...', 'phoenix-crm' ); ?>"></textarea>
                <button id="phoenix-add-note-btn" class="button button-primary" style="margin-top:6px;">
                    <?php esc_html_e( 'Add Note', 'phoenix-crm' ); ?>
                </button>
                <span class="phoenix-spinner" id="phoenix-note-spinner" style="display:none;"></span>
            </div>

            <!-- Notes list -->
            <div id="phoenix-notes-list">
                <?php if ( empty( $notes ) ) : ?>
                    <p><em><?php esc_html_e( 'No notes yet.', 'phoenix-crm' ); ?></em></p>
                <?php else : ?>
                    <?php foreach ( $notes as $note ) : ?>
                        <div class="phoenix-note-item" style="padding:8px 0;border-bottom:1px solid #f0f0f1;">
                            <div class="phoenix-note-content">
                                <span class="phoenix-note-text"><?php echo esc_html( mb_substr( $note->content, 0, 80 ) ); ?></span>
                                <?php if ( mb_strlen( $note->content ) > 80 ) : ?>
                                    <span class="phoenix-note-full" style="display:none;"><?php echo esc_html( mb_substr( $note->content, 80 ) ); ?></span>
                                    <a href="#" class="phoenix-note-toggle" style="font-size:12px;"><?php esc_html_e( 'show more', 'phoenix-crm' ); ?></a>
                                <?php endif; ?>
                            </div>
                            <div class="phoenix-card-meta" style="font-size:11px;color:#787c82;margin-top:2px;">
                                <?php
                                $time = strtotime( $note->created_at );
                                echo esc_html(
                                    sprintf(
                                        /* translators: %s: human-readable time difference */
                                        __( '%s ago', 'phoenix-crm' ),
                                        human_time_diff( $time, current_time( 'timestamp' ) )
                                    )
                                );
                                ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <?php
            // Inline JS for AJAX note submission and expand toggle.
            $ajax_nonce = wp_create_nonce( 'phoenix_ajax_nonce' );
            $inline_js  = <<<JS
(function($) {
    'use strict';

    // Expand / collapse toggle.
    $(document).on('click', '.phoenix-note-toggle', function(e) {
        e.preventDefault();
        var \$link = $(this);
        var \$full = \$link.closest('.phoenix-note-content').find('.phoenix-note-full');
        if (\$full.is(':visible')) {
            \$full.hide();
            \$link.text('show more');
        } else {
            \$full.show();
            \$link.text('show less');
        }
    });

    // AJAX add note.
    $('#phoenix-add-note-btn').on('click', function(e) {
        e.preventDefault();
        var \$btn   = $(this);
        var \$ta    = $('#phoenix-note-content');
        var \$spinner = $('#phoenix-note-spinner');
        var content = \$ta.val().trim();

        if (!content) {
            alert('Please enter a note.');
            return;
        }

        \$btn.prop('disabled', true).addClass('disabled');
        \$spinner.show();

        $.post(window.phoenix_crm_admin.ajax_url, {
            _ajax_nonce: '{$ajax_nonce}',
            action: 'phoenix_add_note',
            content: content
        }, function(resp) {
            if (resp.success) {
                // Prepend the new note to the list.
                var \$list = $('#phoenix-notes-list');
                var \$empty = \$list.find('p em');
                if (\$empty.length) {
                    \$empty.closest('p').remove();
                }

                var html = '<div class="phoenix-note-item" style="padding:8px 0;border-bottom:1px solid #f0f0f1;">';
                html += '<div class="phoenix-note-content">';
                html += '<span class="phoenix-note-text">' + $('<span>').text(content).html().substring(0, 80) + '</span>';
                if (content.length > 80) {
                    html += '<span class="phoenix-note-full" style="display:none;">' + $('<span>').text(content.substring(80)).html() + '</span>';
                    html += '<a href="#" class="phoenix-note-toggle" style="font-size:12px;">show more</a>';
                }
                html += '</div>';
                html += '<div class="phoenix-card-meta" style="font-size:11px;color:#787c82;margin-top:2px;">just now</div>';
                html += '</div>';

                \$list.prepend(html);

                // Keep only the first 5 notes in the DOM.
                \$list.find('.phoenix-note-item').slice(5).remove();
                \$ta.val('');
                if (typeof phoenixToast === 'function') {
                    phoenixToast('Note added.', 'success');
                }
            } else {
                if (typeof phoenixToast === 'function') {
                    phoenixToast(resp.data.message || 'Error adding note.', 'error');
                } else {
                    alert(resp.data.message || 'Error adding note.');
                }
            }
        }).fail(function() {
            if (typeof phoenixToast === 'function') {
                phoenixToast('Server error.', 'error');
            } else {
                alert('Server error.');
            }
        }).always(function() {
            \$btn.prop('disabled', false).removeClass('disabled');
            \$spinner.hide();
        });
    });
})(jQuery);
JS;
            wp_add_inline_script( 'jquery', $inline_js );
            ?>
        </div>
        <?php
    }

    /**
     * Retrieve the most recent notes from the database.
     *
     * @since 1.1.0
     *
     * @param int $limit Maximum number of notes to return (default 5).
     * @return array Array of note objects with id, content, source, created_at.
     */
    public static function get_notes( $limit = 5 ) {
        global $wpdb;

        $table = $wpdb->prefix . 'phoenix_notes';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, content, source, created_at
                 FROM {$table}
                 ORDER BY created_at DESC
                 LIMIT %d",
                (int) $limit
            )
        );

        return is_array( $results ) ? $results : array();
    }

    /**
     * AJAX handler — add a new note.
     *
     * Expects: content (text), _ajax_nonce.
     * Returns JSON.
     *
     * @since 1.1.0
     * @return void
     */
    public static function handle_ajax_add_note() {
        // Verify nonce.
        if ( ! isset( $_REQUEST['_ajax_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_ajax_nonce'] ) ), 'phoenix_ajax_nonce' ) ) {
            wp_send_json_error( array(
                'message' => __( 'Security check failed.', 'phoenix-crm' ),
            ) );
        }

        // Verify capability.
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array(
                'message' => __( 'You do not have permission to perform this action.', 'phoenix-crm' ),
            ) );
        }

        $content = isset( $_REQUEST['content'] ) ? wp_kses_post( wp_unslash( $_REQUEST['content'] ) ) : '';

        if ( empty( $content ) ) {
            wp_send_json_error( array(
                'message' => __( 'Note content cannot be empty.', 'phoenix-crm' ),
            ) );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'phoenix_notes';

        $inserted = $wpdb->insert(
            $table,
            array(
                'content'    => $content,
                'source'     => 'dashboard',
                'created_at' => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%s' )
        );

        if ( false === $inserted ) {
            wp_send_json_error( array(
                'message' => __( 'Failed to save note.', 'phoenix-crm' ),
            ) );
        }

        $note_id = $wpdb->insert_id;

        // Log the event.
        do_action(
            'phoenix_event_logged',
            0,
            'user',
            'note_added',
            'note',
            $note_id,
            sprintf(
                /* translators: %s: truncated note preview */
                __( 'Dashboard note added — %s', 'phoenix-crm' ),
                mb_substr( $content, 0, 40 )
            )
        );

        wp_send_json_success( array(
            'message'   => __( 'Note added successfully.', 'phoenix-crm' ),
            'note_id'   => $note_id,
        ) );
    }
}