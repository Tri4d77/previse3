<x-mail.layout
    :title="__('mail.email_change_confirm.subject', ['app' => config('app.name')])"
    :heading="__('mail.email_change_confirm.heading', ['name' => $userName])"
    :action-url="$confirmUrl"
    :action-text="__('mail.email_change_confirm.action')"
    :footer-note="__('mail.email_change_confirm.expiry_note', ['minutes' => $expiresInMinutes])"
>
    <p style="margin:0 0 16px 0;">
        {!! __('mail.email_change_confirm.intro', [
            'old_email' => '<code>' . e($oldEmail) . '</code>',
            'new_email' => '<code>' . e($newEmail) . '</code>',
        ]) !!}
    </p>
    <p style="margin:0;">
        @lang('mail.email_change_confirm.ignore_note')
    </p>
</x-mail.layout>
