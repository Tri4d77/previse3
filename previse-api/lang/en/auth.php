<?php

return [
    'failed' => 'Invalid email or password.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',
    'inactive' => 'Your account is inactive. Please contact the super-administrator.',
    'unverified' => 'Your account has not been activated yet. Please accept the invitation via the link sent to your email.',
    'no_active_membership' => 'You have no active organization membership. Please contact your organization administrator.',
    'invalid_membership' => 'Invalid or inactive membership.',
    'logged_out' => 'Successfully logged out.',
    'logged_out_all' => 'Successfully logged out from all devices.',
    'reset_link_sent' => 'If the provided email is registered, we have sent a password reset link.',
    'password_reset_success' => 'Password has been successfully changed.',
    'invitation_invalid' => 'Invalid or already used invitation link.',
    'invitation_expired' => 'The invitation link has expired. Please request a new invitation from your administrator.',
    'invitation_accepted' => 'Membership successfully activated. You can now log in.',
    'email_required' => 'Email address is required.',
    'email_invalid' => 'Please provide a valid email address.',
    'password_required' => 'Password is required.',
    'unauthenticated' => 'Authentication required.',
    'forbidden' => 'You do not have permission to perform this action.',

    // Password & session management (M4)
    'password_same_as_old' => 'The new password must be different from the current one.',
    'password_changed' => 'Password has been successfully changed.',
    'cannot_revoke_current_session' => 'You cannot revoke your current session here. Use the Logout button.',
    'session_not_found' => 'Session not found.',
    'session_revoked' => 'Session successfully revoked.',
    'other_sessions_revoked' => 'All other devices have been logged out.',

    // Two-factor authentication (M5)
    '2fa_already_enabled' => 'Two-factor authentication is already enabled. Disable it first to reconfigure.',
    '2fa_not_enabled' => 'Two-factor authentication is not enabled.',
    '2fa_setup_not_started' => 'No 2FA setup in progress. Start the enable flow first.',
    '2fa_invalid_code' => 'Invalid verification code.',
    '2fa_code_required' => 'Please provide either the 6-digit code or a recovery code.',
    '2fa_enabled' => 'Two-factor authentication successfully enabled. Save the recovery codes in a safe place.',
    '2fa_disabled' => 'Two-factor authentication has been disabled.',
    '2fa_recovery_codes_regenerated' => 'New recovery codes generated. The old ones can no longer be used.',

    // Email change (M6)
    'email_same_as_current' => 'The new email must be different from the current one.',
    'email_already_taken' => 'This email address is already taken.',
    'email_change_requested' => 'A confirmation email has been sent to the new address. Click the link to apply the change.',
    'email_change_confirmed' => 'Email address successfully changed.',
    'email_change_cancelled' => 'Email change request cancelled.',
    'email_change_invalid' => 'Invalid or already used confirmation link.',
    'email_change_expired' => 'The confirmation link has expired. Please restart the change process.',
    'email_change_nothing_pending' => 'No email change in progress.',

    // M7 - Leave organization + account deletion
    'membership_not_found' => 'Membership not found.',
    'left_organization' => 'Successfully left the organization.',
    'cannot_leave_last_membership' => 'This is your only active membership. If you really want to leave, delete your account under Profile → Security.',
    'cannot_leave_last_super_admin' => 'You are the only super-admin. Promote another super-admin before leaving the Platform organization.',
    'cannot_delete_last_super_admin' => 'You are the only super-admin. Create another super-admin before deleting your account.',
    'account_deletion_scheduled' => 'Account scheduled for deletion. Within 30 days you can sign in with the same email and cancel the process, otherwise it will be permanently removed.',
    'account_deletion_cancelled' => 'Account deletion cancelled.',
    'account_already_scheduled_for_deletion' => 'The account is already scheduled for deletion.',
    'account_not_scheduled_for_deletion' => 'The account is not scheduled for deletion.',
];
