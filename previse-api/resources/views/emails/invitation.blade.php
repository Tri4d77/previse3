<x-mail.layout
    :title="__('mail.invitation.subject', ['app' => config('app.name')])"
    :heading="__('mail.invitation.heading', ['name' => $userName])"
    :action-url="$invitationUrl"
    :action-text="$isNewUser ? __('mail.invitation.action_new_user') : __('mail.invitation.action_existing_user')"
    :footer-note="__('mail.invitation.expiry_note', ['days' => $expiresInDays])"
>
    @if($isNewUser)
        <p style="margin:0 0 16px 0;">
            {!! __('mail.invitation.intro_new_user', [
                'inviter' => e($inviterName),
                'organization' => '<strong>' . e($organizationName) . '</strong>',
                'role' => '<strong>' . e($roleName) . '</strong>',
            ]) !!}
        </p>
        <p style="margin:0;">
            @lang('mail.invitation.explain_new_user')
        </p>
    @else
        <p style="margin:0 0 16px 0;">
            {!! __('mail.invitation.intro_existing_user', [
                'inviter' => e($inviterName),
                'organization' => '<strong>' . e($organizationName) . '</strong>',
                'role' => '<strong>' . e($roleName) . '</strong>',
            ]) !!}
        </p>
        <p style="margin:0;">
            @lang('mail.invitation.explain_existing_user')
        </p>
    @endif
</x-mail.layout>
