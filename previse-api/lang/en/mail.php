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

];
