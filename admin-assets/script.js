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