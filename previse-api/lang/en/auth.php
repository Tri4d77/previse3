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
];
