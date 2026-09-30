{{--
    Transactional email shell.

    Table-based and inline-styled on purpose: Outlook's Word rendering engine
    ignores <style> blocks, flexbox and most of CSS grid, and these messages
    are the ones a customer reads when money is involved. A broken layout on a
    "your card was declined" email costs a renewal.

    Dark mode is handled with a media query where it is honoured and degrades
    to the light palette where it is not.
--}}
<!DOCTYPE html>
<html lang="en" xmlns:v="urn:schemas-microsoft-com:vml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>{{ $subjectLine ?? config('platform.name') }}</title>
    <style>
        @media only screen and (max-width: 600px) {
            .es-container { width: 100% !important; }
            .es-pad { padding-left: 24px !important; padding-right: 24px !important; }
            .es-h1 { font-size: 22px !important; }
        }
        @media (prefers-color-scheme: dark) {
            .es-body { background: #0b0b10 !important; }
            .es-card { background: #14141b !important; border-color: #26262f !important; }
            .es-text { color: #d7d7e0 !important; }
            .es-heading { color: #ffffff !important; }
            .es-muted { color: #8b8b9a !important; }
            .es-rule { border-color: #26262f !important; }
            .es-meta { background: #1b1b24 !important; }
        }
    </style>
</head>
<body class="es-body" style="margin:0; padding:0; width:100%; background:#f1f5f9; -webkit-font-smoothing:antialiased;">

{{-- Preheader: the grey line inboxes show next to the subject. Wasting it on
     "View this email in your browser" is a wasted open. --}}
<div style="display:none; max-height:0; overflow:hidden; opacity:0; mso-hide:all;">
    {{ $preheader ?? '' }}
    &#8199;&#65279;&#847;&#8199;&#65279;&#847;&#8199;&#65279;&#847;&#8199;&#65279;&#847;&#8199;&#65279;&#847;
</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="es-body" style="background:#f1f5f9;">
    <tr>
        <td align="center" style="padding:32px 12px;">

            <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" class="es-container" style="width:600px; max-width:600px;">

                {{-- Wordmark --}}
                <tr>
                    <td align="left" style="padding:0 0 20px 4px;">
                        <span class="es-heading" style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:17px; font-weight:800; letter-spacing:-0.02em; color:#0f172a;">
                            {{ config('platform.name') }}
                        </span>
                    </td>
                </tr>

                {{-- Card --}}
                <tr>
                    <td class="es-card" style="background:#ffffff; border:1px solid #e2e8f0; border-radius:16px; overflow:hidden;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td class="es-pad" style="padding:36px 40px 40px 40px; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
                                    {{ $slot }}
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- Footer --}}
                <tr>
                    <td class="es-pad" style="padding:24px 8px 0 8px; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
                        <p class="es-muted" style="margin:0 0 6px 0; font-size:12px; line-height:19px; color:#64748b;">
                            Sent by {{ config('platform.name') }} because you have a workspace with us.
                            Questions? Reply to this email or write to
                            <a href="mailto:{{ config('platform.support_email') }}" style="color:#4f46e5; text-decoration:underline;">{{ config('platform.support_email') }}</a>.
                        </p>
                        <p class="es-muted" style="margin:0; font-size:12px; line-height:19px; color:#94a3b8;">
                            This is a service message about your account, not marketing.
                        </p>
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>

</body>
</html>
