{{--
    One template for every lifecycle message.

    Twelve near-identical Blade files would drift within a release: one would
    lose its footer, another would keep an old brand colour, and the sixth
    would forget the plain-text alternative. The message is data
    (App\Notifications\LifecycleContent); this renders it.
--}}
<x-mail-layout :subject-line="$content->subject" :preheader="$content->preheader">

    @if ($content->eyebrow !== null)
        <p style="margin:0 0 10px 0; font-size:12px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:{{ $content->accent() }};">
            {{ $content->eyebrow }}
        </p>
    @endif

    <h1 class="es-h1 es-heading" style="margin:0 0 18px 0; font-size:25px; line-height:32px; font-weight:800; letter-spacing:-0.02em; color:#0f172a;">
        {{ $content->heading }}
    </h1>

    <p class="es-text" style="margin:0 0 18px 0; font-size:15px; line-height:25px; color:#334155;">
        Hi {{ $content->greetingName }},
    </p>

    @foreach ($content->lines as $line)
        <p class="es-text" style="margin:0 0 18px 0; font-size:15px; line-height:25px; color:#334155;">{!! $line !!}</p>
    @endforeach

    @if ($content->facts !== [])
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
               class="es-meta" style="background:#f8fafc; border-radius:12px; margin:0 0 24px 0;">
            @foreach ($content->facts as $label => $value)
                <tr>
                    <td style="padding:10px 16px; font-size:13px; line-height:20px; color:#64748b; white-space:nowrap;">{{ $label }}</td>
                    <td align="right" class="es-text" style="padding:10px 16px; font-size:13px; line-height:20px; font-weight:700; color:#0f172a;">{{ $value }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if ($content->actionUrl !== null)
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px 0;">
            <tr>
                <td align="center" bgcolor="{{ $content->accent() }}" style="border-radius:12px;">
                    <a href="{{ $content->actionUrl }}"
                       style="display:inline-block; padding:14px 28px; font-size:15px; font-weight:700; color:#ffffff; text-decoration:none; border-radius:12px;">
                        {{ $content->actionLabel }}
                    </a>
                </td>
            </tr>
        </table>

        {{-- Some clients strip buttons; nobody should ever be stuck. --}}
        <p class="es-muted" style="margin:0 0 20px 0; font-size:12px; line-height:19px; color:#94a3b8; word-break:break-all;">
            Button not working? Paste this into your browser:<br>{{ $content->actionUrl }}
        </p>
    @endif

    @foreach ($content->outro as $line)
        <p class="es-muted" style="margin:0 0 14px 0; font-size:13px; line-height:21px; color:#64748b;">{!! $line !!}</p>
    @endforeach

    <hr class="es-rule" style="border:none; border-top:1px solid #e2e8f0; margin:26px 0 18px 0;">

    <p class="es-muted" style="margin:0; font-size:13px; line-height:21px; color:#64748b;">
        — The {{ config('platform.name') }} team
    </p>

</x-mail-layout>
