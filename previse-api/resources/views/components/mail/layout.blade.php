@props([
    'title' => null,
    'heading' => null,
    'actionUrl' => null,
    'actionText' => null,
    'footerNote' => null,
    'locale' => null,
])
<!DOCTYPE html>
<html lang="{{ $locale ?? app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title>{{ $title ?? config('app.name') }}</title>
    <style>
        /* Reset */
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        body { margin: 0 !important; padding: 0 !important; width: 100% !important; background-color: #f3f4f6; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; }
        /* Mobile */
        @media only screen and (max-width: 600px) {
            .container { width: 100% !important; }
            .px-inner { padding-left: 24px !important; padding-right: 24px !important; }
        }
    </style>
</head>
<body style="margin:0; padding:0; background-color:#f3f4f6;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f3f4f6;">
        <tr>
            <td align="center" style="padding:32px 16px;">

                <!-- Card -->
                <table role="presentation" class="container" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px; max-width:600px; background-color:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,0.08);">

                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #134e4a 0%, #0d9488 50%, #14b8a6 100%); padding:32px 40px; text-align:center;">
                            <div style="display:inline-block; width:56px; height:56px; background-color:rgba(255,255,255,0.2); border-radius:14px; line-height:56px; text-align:center; margin-bottom:12px;">
                                <span style="font-size:28px;">🏢</span>
                            </div>
                            <h1 style="margin:0; color:#ffffff; font-size:24px; font-weight:700; letter-spacing:-0.5px;">{{ config('app.name') }}</h1>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td class="px-inner" style="padding:40px;">
                            @if(isset($heading))
                                <h2 style="margin:0 0 16px 0; color:#111827; font-size:20px; font-weight:600;">{{ $heading }}</h2>
                            @endif

                            <div style="color:#374151; font-size:15px; line-height:1.6;">
                                {!! $slot !!}
                            </div>

                            @if(isset($actionUrl) && isset($actionText))
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:32px 0;">
                                    <tr>
                                        <td style="border-radius:8px; background-color:#0d9488;">
                                            <a href="{{ $actionUrl }}"
                                               style="display:inline-block; padding:12px 28px; color:#ffffff; text-decoration:none; font-size:15px; font-weight:600; border-radius:8px;">
                                                {{ $actionText }}
                                            </a>
                                        </td>
                                    </tr>
                                </table>

                                <p style="margin:16px 0 0 0; color:#6b7280; font-size:13px; line-height:1.5;">
                                    @lang('mail.common.button_fallback')<br>
                                    <a href="{{ $actionUrl }}" style="color:#0d9488; word-break:break-all;">{{ $actionUrl }}</a>
                                </p>
                            @endif

                            @if(isset($footerNote))
                                <div style="margin-top:32px; padding:16px; background-color:#f9fafb; border-left:3px solid #14b8a6; border-radius:4px;">
                                    <p style="margin:0; color:#6b7280; font-size:13px; line-height:1.5;">{!! $footerNote !!}</p>
                                </div>
                            @endif
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding:24px 40px; background-color:#f9fafb; border-top:1px solid #e5e7eb; text-align:center;">
                            <p style="margin:0 0 8px 0; color:#6b7280; font-size:12px;">
                                @lang('mail.common.footer_line1', ['app' => config('app.name')])
                            </p>
                            <p style="margin:0; color:#9ca3af; font-size:11px;">
                                @lang('mail.common.footer_line2')
                            </p>
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>
</body>
</html>
