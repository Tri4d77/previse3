<?php

return [

    'common' => [
        'button_fallback' => 'If the button does not work, copy and paste this link into your browser:',
        'footer_line1' => ':app — facility management platform',
        'footer_line2' => 'This is an automated message, please do not reply.',
    ],

    'invitation' => [
        'subject' => 'Invitation to :app — :organization',
        'heading' => 'Hi :name,',
        'intro_new_user' => ':inviter has invited you to join :organization with the role of :role.',
        'intro_existing_user' => ':inviter has invited you to join :organization with the role of :role.',
        'explain_new_user' => 'To accept the invitation, click the button below and set your password. After that you can log in.',
        'explain_existing_user' => 'Since you already have a Previse account, just confirm the invitation with the button below. You will then be able to switch to this organization from the organization switcher in the header.',
        'action_new_user' => 'Accept invitation',
        'action_existing_user' => 'Confirm membership',
        'expiry_note' => 'This invitation expires in <strong>:days days</strong>. If you are not the intended recipient, please ignore this message.',
    ],

    'password_reset' => [
        'subject' => 'Password reset — :app',
        'heading' => 'Hi :name,',
        'intro' => 'We received a password reset request for your account. Click the button below to set a new password.',
        'ignore_note' => 'If you did not request a password reset, you can safely ignore this email — your password will remain unchanged.',
        'action' => 'Set new password',
        'expiry_note' => 'This reset link is valid for <strong>:minutes minutes</strong>. After that you will need to request a new one.',
    ],

    // M6 - Email change
    'email_change_confirm' => [
        'subject' => 'Confirm email change — :app',
        'heading' => 'Hi :name,',
        'intro' => 'You requested an email address change: :old_email → :new_email. Click the button below from this new email address to confirm.',
        'action' => 'Confirm new email',
        'expiry_note' => 'This confirmation link is valid for <strong>:minutes minutes</strong>. After that you will need to restart the process.',
        'ignore_note' => 'If you did not request this change, ignore this message — your account email will remain unchanged.',
    ],
    'email_change_notice' => [
        'subject' => 'Security notice: email change requested — :app',
        'heading' => 'Hi :name,',
        'intro' => 'An email address change was initiated on your account. New address: :new_email. Time: :time. IP: :ip.',
        'not_you_warning' => 'If YOU did NOT initiate this change, log in immediately and change your password, or request a password reset.',
        'security_tip' => 'Security tip: enable two-factor authentication under Profile → Security.',
    ],

    // M6 - Security notifications
    'security' => [
        'footer_tip' => 'Security tip: if anything looks suspicious, log in, revoke all sessions under Profile → Security, and change your password.',
        'not_you_warning' => 'If YOU are NOT responsible for this event, act immediately: change your password, revoke all sessions, and enable 2FA (if not yet enabled).',

        'password_changed' => [
            'subject' => 'Password changed — :app',
            'heading' => 'Password changed',
            'intro' => 'The password for your account has been changed.',
        ],
        'two_factor_enabled' => [
            'subject' => '2FA enabled — :app',
            'heading' => 'Two-factor authentication enabled',
            'intro' => 'Your account now requires a 2FA code at login.',
        ],
        'two_factor_disabled' => [
            'subject' => '2FA disabled — :app',
            'heading' => 'Two-factor authentication disabled',
            'intro' => 'Two-factor authentication has been turned off on your account.',
        ],
        'new_device_login' => [
            'subject' => 'New sign-in — :app',
            'heading' => 'Sign-in from a new device or location',
            'intro' => 'Your account was signed in from a new device or IP address.',
        ],
        'email_changed' => [
            'subject' => 'Email address changed — :app',
            'heading' => 'Email address changed',
            'intro' => 'Your account email address has been successfully changed.',
        ],
        'account_deletion_scheduled' => [
            'subject' => 'Account deletion scheduled — :app',
            'heading' => 'Your account will be deleted in 30 days',
            'intro' => 'You requested the deletion of your Previse account. The account will be permanently removed <strong>in 30 days</strong>. During this time login is disabled for you, but if you change your mind, you can sign in and cancel the process with the "Cancel deletion" button.',
        ],
        'account_deletion_cancelled' => [
            'subject' => 'Account deletion cancelled — :app',
            'heading' => 'Account deletion cancelled',
            'intro' => 'You cancelled the deletion of your account. Your account is back to normal usage.',
        ],
        'admin_left_organization' => [
            'subject' => 'Organization admin has left — :app',
            'heading' => 'Admin has left the organization',
            'intro' => 'The last admin (:admin_name) of :organization has left or deleted their account. Admin tasks are now handled by the Platform super-admin — please contact them for management needs.',
        ],

        'labels' => [
            'time' => 'Time',
            'ip' => 'IP address',
            'device' => 'Device',
        ],
    ],

];
