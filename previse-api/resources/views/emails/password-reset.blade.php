<x-mail.layout
    :title="__('mail.password_reset.subject', ['app' => config('app.name')])"
    :heading="__('mail.password_reset.heading', ['name' => $userName])"
    :action-url="$resetUrl"
    :action-text="__('mail.password_reset.action')"
    :footer-note="__('mail.password_reset.expiry_note', ['minutes' => $expiresInMinutes])"
>
    <p style="margin:0 0 16px 0;">
        @lang('mail.password_reset.intro')
    </p>
    <p style="margin:0;">
        @lang('mail.password_reset.ignore_note')
    </p>
</x-mail.layout>
