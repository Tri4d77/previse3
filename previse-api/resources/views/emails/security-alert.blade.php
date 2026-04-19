<x-mail.layout
    :title="$subject"
    :heading="$heading"
    :footer-note="__('mail.security.footer_tip')"
>
    <p style="margin:0 0 16px 0;">{!! $intro !!}</p>
    @if(!empty($details))
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width:100%; margin:8px 0 16px; border:1px solid #e5e7eb; border-radius:6px; overflow:hidden;">
            <tbody>
                @foreach($details as $labelKey => $value)
                    @php
                        // A details tömb kulcsa lehet nyers azonosító ('time', 'ip', 'device'),
                        // amit a mail.security.labels.* kulcsból fordítunk a render locale-jában.
                        // Ha a kulcs már fordított string (backward-compat), azt használjuk.
                        $label = in_array($labelKey, ['time', 'ip', 'device'], true)
                            ? __('mail.security.labels.' . $labelKey)
                            : $labelKey;
                    @endphp
                    <tr style="background-color:#f9fafb;">
                        <td style="padding:8px 12px; font-size:12px; color:#6b7280; width:30%; border-bottom:1px solid #e5e7eb;">{{ $label }}</td>
                        <td style="padding:8px 12px; font-size:13px; color:#111827; border-bottom:1px solid #e5e7eb;">{{ $value }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
    <p style="margin:0;">
        @lang('mail.security.not_you_warning')
    </p>
</x-mail.layout>
