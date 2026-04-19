<x-mail.layout
    :title="__('mail.email_change_notice.subject', ['app' => config('app.name')])"
    :heading="__('mail.email_change_notice.heading', ['name' => $userName])"
    :footer-note="__('mail.email_change_notice.security_tip')"
>
    <p style="margin:0 0 16px 0;">
        {!! __('mail.email_change_notice.intro', [
            'new_email' => '<code>' . e($newEmail) . '</code>',
            'time' => e($requestedAt),
            'ip' => e($ipAddress ?? '—'),
        ]) !!}
    </p>
    <p style="margin:0 0 16px 0;">
        @lang('mail.email_change_notice.not_you_warning')
    </p>
</x-mail.layout>
