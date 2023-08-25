/**
 * Scrips to run ajax and some events for transfer helper.
 * 
 * @package ea_transfer_helper
 */
jQuery(document).ready(function ($) {
    $('#eath-process-check').on('click', function () {
        var activityId = $('#eath-activity-option').val();

        if (activityId === '') {
            console.error('Invalid activity');
            return;
        }

        var requestData = {
            action: 'eath_check_activity_status',
            activity_id: activityId,
            security: eath_object.nonce
        };

        $.ajax({
            type: 'POST',
            url: eath_object.ajax_url,
            data: requestData,
            success: function (response) {
                if (response.success) {
                    var activity = response.data;
                    var actionHtml = '';
                    if (activity.status === 'completed' && (activity.activity_type === 'export' || activity.activity_type === 'backup')) {
                        var response = '';
                        if( activity.activity_type === 'export' ) {
                            response= JSON.parse(activity.meta.ea_transfer_exported_response);
                        } else if( activity.activity_type === 'backup' ) {
                            response= JSON.parse(activity.meta.ea_transfer_backup_response);
                        }
                        actionHtml = `<tr><td>Action</td><td><a href="${response.filename}" target="_blank" download>Download</a></td></tr>`;
                    } else if ( activity.status !== 'completed' && (activity.activity_type === 'convert' || activity.activity_type === 'restore')) {
                        actionHtml = `<tr><td>Action</td><td><button data-id="${activity.ID}" id="eath-rerun-activity-button" class="button button-primary">Resume</button></td></tr>`;
                    }

                    var tableHtml =
                        `<div id="eath-activity-status-response" style="margin-top: 15px;">
                            <table class="widefat striped">
                                <tbody>
                                    <tr><td>ID</td><td>${activity.ID}</td></tr>
                                    <tr><td>Activity</td><td>${activity.activity_type}</td></tr>
                                    <tr><td>Created By</td><td>${activity.user.display_name}</td></tr>
                                    <tr><td>Create On</td><td>${activity.created_on}</td></tr>
                                    <tr><td>Status</td><td>${activity.status}</td></tr>
                                    ${actionHtml}
                                </tbody>
                            </table>
                            <textarea class="hidden">${JSON.stringify(activity)}</textarea>
                        </div>`;
                } else {
                    var tableHtml = `<div id="eath-activity-status-response" style="margin-top: 15px;">Checking Failed...</div>`;
                }

                $('#eath-activity-status-response').remove();
                $('#eath-process-check').parents('.eath-input-group').append(tableHtml);
            }
        });
    });

    $(document).on('click', '#eath-rerun-activity-button', function (event) {
        var activityId = $(this).data('id');

        if ( activityId ) {
            jQuery( this ).text( 'Running...' ).prop('disabled', true);
            rerunActivity(activityId, event);
        }
    });

    // Function to rerun activity using AJAX
    function rerunActivity(activityId, event ) {

        var requestData = {
            action: 'eath_rerun_activity',
            activity_id: activityId,
            security: eath_object.nonce
        };

        $.ajax({
            type: 'POST',
            url: eath_object.ajax_url,
            data: requestData,
            success: function (response) {
                $('#eath-activity-option').val(activityId);
                $('#eath-process-check').trigger( 'click' );
            }
        });
    }

    // Save the activity settings with ajax.
    $('#eath-save-activity-settings-button').on('click', function () {
        var disableAutoBackup = $('#ea_disable_auto_backup').prop('checked') ? 1 : 0;

        var requestData = {
            action: 'eath_save_activity_settings',
            disable_auto_backup: disableAutoBackup,
            security: eath_object.nonce
        };

        $.ajax({
            type: 'POST',
            url: eath_object.ajax_url,
            data: requestData,
            success: function (response) {
                location.reload();
            },
            error: function (xhr, status, error) {
                console.error('AJAX Error:', status, error);
            }
        });
    });
    

});
