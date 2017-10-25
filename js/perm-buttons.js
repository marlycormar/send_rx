$(document).ready(function() {
    var settings = sendRx.permButtons;
    $('#repeating_forms_table_parent').after(settings.buttons);

    if (!settings.buttonsEnabled) {
        $('.send-rx-access-btn').prop('disabled', true);
        return;
    }

    var $rebuild_button = $('#send-rx-rebuild-access-btn');
    var $revoke_button = $('#send-rx-revoke-access-btn');

    // Refreshing buttons.
    $revoke_button.prop('disabled', $.isEmptyObject(settings.currMembers));
    $rebuild_button.prop('disabled', $.isEmptyObject(settings.membersToAdd) &&
        $.isEmptyObject(settings.membersToDel) &&
        $.isEmptyObject(settings.rolesToAdd) &&
        $.isEmptyObject(settings.rolesToDel)
    );

    var grantGroupAccessToStaff = function(users, group_id = '') {
        // Remove each user to DAG.
        $.each(users, function(key, value) {
            $.ajax({
                url: app_path_webroot + 'DataAccessGroups/data_access_groups_ajax.php',
                data: { pid: settings.pid, action: 'add_user', user: value, group_id: group_id },
                async: false
            });
        });
    }

    var assignRole = function(users) {
        $.each(users, function(key, value) {
            $.ajax({
                type: 'POST',
                url: app_path_webroot + 'UserRights/assign_user.php?pid=' + settings.pid,
                data: { username: key, role_id: value, notify_email_role: 0 },
                async: false
            });
        });
    }

    var reloadPage = function(msg) {
        window.location.href = window.location.href.replace('&msg=rebuild_perms', '').replace('&msg=revoke_perms', '') + '&msg=' + msg;
    }

    $rebuild_button.on('click', function() {
        // Rebuilding, delete roles
        assignRole(settings.rolesToDel);

        // Rebuilding, add/modify roles
        assignRole(settings.rolesToAdd);

        // Rebuilding, part 1: Revoke access.
        grantGroupAccessToStaff(settings.membersToDel);

        // Rebuilding, part 2: Grant access.
        grantGroupAccessToStaff(settings.membersToAdd, settings.groupId);

        // Reloading page.
        reloadPage('rebuild_perms');
    });


    $revoke_button.on('click', function() {
        // Revoke group access from users.
        grantGroupAccessToStaff(settings.currMembers);

        // Reloading page.
        reloadPage('revoke_perms');
    });
});