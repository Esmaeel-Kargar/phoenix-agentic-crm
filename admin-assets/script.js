/**
 * Phoenix CRM — Admin UI Scripts
 *
 * Handles AJAX actions: approve/reject proposals, regenerate API key,
 * sync/delete raw data, auto-refresh dashboard, loading spinners, toasts.
 *
 * Requires: jQuery, wp_localize_script (phoenix_crm_admin)
 */
(function ($) {

    'use strict';

    /* ---- Toast notifications ---- */
    function phoenixToast(message, type) {
        var toast = $('#phoenix-toast');
        if (!toast.length) {
            $('body').append('<div id="phoenix-toast" class="phoenix-toast"></div>');
            toast = $('#phoenix-toast');
        }
        toast
            .removeClass('phoenix-toast-success phoenix-toast-error phoenix-toast-info')
            .addClass('phoenix-toast-' + (type || 'info'))
            .text(message)
            .fadeIn(200)
            .delay(4000)
            .fadeOut(400);
    }

    /* ---- Loading spinner overlay ---- */
    function phoenixShowSpinner($el) {
        if ($el.length) {
            $el.append('<span class="phoenix-spinner"></span>');
        }
    }
    function phoenixRemoveSpinner($el) {
        $el.find('.phoenix-spinner').remove();
    }

    /* ---- Disable/enable button during request ---- */
    function phoenixLoading($btn, loading) {
        if (loading) {
            $btn.prop('disabled', true).addClass('disabled');
            phoenixShowSpinner($btn);
        } else {
            $btn.prop('disabled', false).removeClass('disabled');
            phoenixRemoveSpinner($btn);
        }
    }

    /* ---- Approve a proposal ---- */
    window.approve_proposal = function (proposalId) {
        if (!confirm('Approve this proposal?')) return;
        var $btn = $('[data-approve="' + proposalId + '"]');
        phoenixLoading($btn, true);

        $.post(window.phoenix_crm_admin.ajax_url, {
            _ajax_nonce: window.phoenix_crm_admin.nonce,
            action: 'phoenix_approve_proposal',
            proposal_id: proposalId
        }, function (resp) {
            if (resp.success) {
                phoenixToast('Proposal approved.', 'success');
                $('#proposal-row-' + proposalId)
                    .find('.phoenix-status-pending')
                    .removeClass('phoenix-status-pending')
                    .addClass('phoenix-status-approved')
                    .text('approved');
            } else {
                phoenixToast(resp.data || 'Error approving proposal.', 'error');
            }
        }).fail(function () {
            phoenixToast('Server error.', 'error');
        }).always(function () {
            phoenixLoading($btn, false);
        });
    };

    /* ---- Reject a proposal with reason ---- */
    window.reject_proposal = function (proposalId) {
        var reason = prompt('Reason for rejection:');
        if (reason === null) return;

        var $btn = $('[data-reject="' + proposalId + '"]');
        phoenixLoading($btn, true);

        $.post(window.phoenix_crm_admin.ajax_url, {
            _ajax_nonce: window.phoenix_crm_admin.nonce,
            action: 'phoenix_reject_proposal',
            proposal_id: proposalId,
            reason: reason
        }, function (resp) {
            if (resp.success) {
                phoenixToast('Proposal rejected.', 'info');
                $('#proposal-row-' + proposalId)
                    .find('.phoenix-status-pending')
                    .removeClass('phoenix-status-pending')
                    .addClass('phoenix-status-rejected')
                    .text('rejected');
            } else {
                phoenixToast(resp.data || 'Error rejecting proposal.', 'error');
            }
        }).fail(function () {
            phoenixToast('Server error.', 'error');
        }).always(function () {
            phoenixLoading($btn, false);
        });
    };

    /* ---- Regenerate API key ---- */
    window.regenerate_api_key = function () {
        if (!confirm('Regenerate API key? The current key will stop working immediately.')) return;

        var $btn = $('#phoenix-regenerate-key');
        phoenixLoading($btn, true);

        $.post(window.phoenix_crm_admin.ajax_url, {
            _ajax_nonce: window.phoenix_crm_admin.nonce,
            action: 'phoenix_regenerate_api_key'
        }, function (resp) {
            if (resp.success && resp.data.key) {
                $('#phoenix-api-key-display').text(resp.data.key);
                phoenixToast('API key regenerated.', 'success');
            } else {
                phoenixToast(resp.data || 'Error regenerating key.', 'error');
            }
        }).fail(function () {
            phoenixToast('Server error.', 'error');
        }).always(function () {
            phoenixLoading($btn, false);
        });
    };

    /* ---- Sync all raw data ---- */
    window.sync_all_raw_data = function () {
        if (!confirm('Sync all raw data from external sources? This may take a moment.')) return;

        var $btn = $('#phoenix-sync-raw');
        phoenixLoading($btn, true);

        $.post(window.phoenix_crm_admin.ajax_url, {
            _ajax_nonce: window.phoenix_crm_admin.nonce,
            action: 'phoenix_sync_raw_data'
        }, function (resp) {
            if (resp.success) {
                phoenixToast('Sync completed: ' + (resp.data.count || 0) + ' records.', 'success');
            } else {
                phoenixToast(resp.data || 'Error during sync.', 'error');
            }
        }).fail(function () {
            phoenixToast('Server error.', 'error');
        }).always(function () {
            phoenixLoading($btn, false);
        });
    };

    /* ---- Delete all raw data ---- */
    window.delete_all_raw_data = function () {
        if (!confirm('Delete ALL raw data? This cannot be undone.')) return  ;
        if (!confirm('Are you sure? All raw data will be permanently removed.')) return;

        var $btn = $('#phoenix-delete-raw');
        phoenixLoading($btn, true);

        $.post(window.phoenix_crm_admin.ajax_url, {
            _ajax_nonce: window.phoenix_crm_admin.nonce,
            action: 'phoenix_delete_raw_data'
        }, function (resp) {
            if (resp.success) {
                phoenixToast('All raw data deleted.', 'info');
                $('#phoenix-raw-count').text('0');
            } else {
                phoenixToast(resp.data || 'Error deleting data.', 'error');
            }
        }).fail(function () {
            phoenixToast('Server error.', 'error');
        }).always(function () {
            phoenixLoading($btn, false);
        });
    };

    /* ---- Auto-refresh dashboard every 60s ---- */
    $(document).ready(function () {
        if ($('#phoenix-dashboard-widget').length) {
            setInterval(function () {
                $.get(window.location.href, function (html) {
                    var $new = $(html).find('#phoenix-dashboard-widget');
                    if ($new.length) {
                        $('#phoenix-dashboard-widget').replaceWith($new);
                        phoenixToast('Dashboard refreshed.', 'info');
                    }
                });
            }, 60000);
        }
    });

})(jQuery);

/* ========================================================================
 * Phoenix CRM — Dashboard v2 Widget Scripts (appended v1.1.0)
 * ====================================================================== */

    /* ---- Widget collapse/expand toggle ---- */
    $(document).on('click', '.phoenix-widget-toggle', function () {
        var $widget = $(this).closest('.phoenix-widget');
        $widget.toggleClass('collapsed');
        var $icon = $(this);
        if ($widget.hasClass('collapsed')) {
            $icon.text('\u25B6');   /* ▶ */
        } else {
            $icon.text('\u25BC');   /* ▼ */
        }
    });

    /* ---- Widget drag-to-reorder (jQuery UI Sortable) ---- */
    function phoenixInitSortable() {
        if (!$.fn.sortable) return;
        $('.phoenix-dashboard-grid').sortable({
            handle:       '.phoenix-widget-header',
            placeholder:  'phoenix-widget-placeholder',
            forcePlaceholderSize: true,
            update:       function () {
                var order = [];
                $(this).find('.phoenix-widget').each(function () {
                    var id = $(this).data('widget-id');
                    if (id) order.push(id);
                });
                if (order.length === 0) return;
                $.post(ajaxurl, {
                    _ajax_nonce: window.phoenix_crm_admin.nonce,
                    action:      'phoenix_save_widget_order',
                    widget_ids:  order
                }, function (resp) {
                    if (!resp.success) {
                        phoenixToast(resp.data || 'Failed to save widget order.', 'error');
                    }
                }).fail(function () {
                    phoenixToast('Server error saving widget order.', 'error');
                });
            }
        });
    }

    /* ---- Task checkbox toggle ---- */
    $(document).on('change', '.phoenix-task-checkbox', function () {
        var $cb    = $(this);
        var taskId = $cb.data('task-id');
        var done   = $cb.is(':checked') ? 1 : 0;

        $.post(ajaxurl, {
            _ajax_nonce: window.phoenix_crm_admin.nonce,
            action:      'phoenix_task_toggle',
            task_id:     taskId,
            done:        done
        }, function (resp) {
            if (resp.success) {
                var $title = $cb.closest('.phoenix-task-item').find('.phoenix-task-title');
                $title.toggleClass('done', !!done);
                $cb.prop('checked', !!done);
            } else {
                phoenixToast(resp.data || 'Error toggling task.', 'error');
                $cb.prop('checked', !done);
            }
        }).fail(function () {
            phoenixToast('Server error toggling task.', 'error');
            $cb.prop('checked', !done);
        });
    });

    /* ---- Quick-add task form ---- */
    $(document).on('submit', '.phoenix-quick-add-task-form', function (e) {
        e.preventDefault();
        var $form  = $(this);
        var $input = $form.find('input[name="task_title"]');
        var title  = $input.val().trim();
        if (!title) return;

        var $btn = $form.find('button[type="submit"]');
        phoenixLoading($btn, true);

        $.post(ajaxurl, {
            _ajax_nonce: window.phoenix_crm_admin.nonce,
            action:      'phoenix_add_task',
            task_title:  title
        }, function (resp) {
            if (resp.success) {
                $input.val('');
                phoenixToast('Task added.', 'success');
                location.reload();
            } else {
                phoenixToast(resp.data || 'Error adding task.', 'error');
            }
        }).fail(function () {
            phoenixToast('Server error adding task.', 'error');
        }).always(function () {
            phoenixLoading($btn, false);
        });
    });

    /* ---- Quick-add note form ---- */
    $(document).on('submit', '.phoenix-quick-add-note-form', function (e) {
        e.preventDefault();
        var $form  = $(this);
        var $input = $form.find('input[name="note_text"]');
        var text   = $input.val().trim();
        if (!text) return;

        var $btn = $form.find('button[type="submit"]');
        phoenixLoading($btn, true);

        $.post(ajaxurl, {
            _ajax_nonce: window.phoenix_crm_admin.nonce,
            action:      'phoenix_add_note',
            note_text:   text
        }, function (resp) {
            if (resp.success) {
                $input.val('');
                phoenixToast('Note added.', 'success');
                location.reload();
            } else {
                phoenixToast(resp.data || 'Error adding note.', 'error');
            }
        }).fail(function () {
            phoenixToast('Server error adding note.', 'error');
        }).always(function () {
            phoenixLoading($btn, false);
        });
    });

    /* ---- Task filter tabs ---- */
    $(document).on('click', '.phoenix-filter-tab', function () {
        var $tab   = $(this);
        var filter = $tab.data('filter');

        $tab.closest('.phoenix-filter-tabs').find('.phoenix-filter-tab').removeClass('active');
        $tab.addClass('active');

        var $list = $tab.closest('.phoenix-widget').find('.phoenix-task-list');
        if (!$list.length) return;

        $list.find('.phoenix-task-item').each(function () {
            var $item = $(this);
            switch (filter) {
                case 'all':
                    $item.show();
                    break;
                case 'active':
                    $item.find('.phoenix-task-checkbox').is(':checked')
                        ? $item.hide()
                        : $item.show();
                    break;
                case 'completed':
                    $item.find('.phoenix-task-checkbox').is(':checked')
                        ? $item.show()
                        : $item.hide();
                    break;
                default:
                    $item.show();
            }
        });
    });

    /* ---- Calendar month navigation ---- */
    $(document).on('click', '.phoenix-calendar-prev', function () {
        var $cal  = $(this).closest('.phoenix-widget');
        var month = parseInt($cal.data('cal-month'), 10);
        var year  = parseInt($cal.data('cal-year'), 10);
        month--;
        if (month < 1) { month = 12; year--; }
        phoenixLoadCalendar($cal, month, year);
    });
    $(document).on('click', '.phoenix-calendar-next', function () {
        var $cal  = $(this).closest('.phoenix-widget');
        var month = parseInt($cal.data('cal-month'), 10);
        var year  = parseInt($cal.data('cal-year'), 10);
        month++;
        if (month > 12) { month = 1; year++; }
        phoenixLoadCalendar($cal, month, year);
    });

    function phoenixLoadCalendar($widget, month, year) {
        $.post(ajaxurl, {
            _ajax_nonce: window.phoenix_crm_admin.nonce,
            action:      'phoenix_get_calendar',
            month:       month,
            year:        year
        }, function (resp) {
            if (resp.success && resp.data.html) {
                $widget.find('.phoenix-widget-body').html(resp.data.html);
                $widget.data('cal-month', month);
                $widget.data('cal-year', year);
            } else {
                phoenixToast(resp.data || 'Error loading calendar.', 'error');
            }
        }).fail(function () {
            phoenixToast('Server error loading calendar.', 'error');
        });
    }

    /* ---- Init sortable on DOM ready ---- */
    $(document).ready(function () {
        try {
            phoenixInitSortable();
        } catch (e) {
            // jQuery UI Sortable not available — drag-to-reorder disabled
        }
    });

})(jQuery);
