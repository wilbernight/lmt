<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>صورت‌جلسه {{ $number }} — {{ $minute->subject }}</title>
    <link rel="icon" href="/brand/icon.png" type="image/png">
    @vite(['resources/css/app.css'])
    <style nonce="{{ Vite::cspNonce() }}">
        :root {
            --brand: #dd7a00;
            --brand-2: #f2a21b;
            --brand-deep: #a85a00;
            --navy: #141a33;
            --ink: #1d2232;
            --muted: #6b7183;
            --line: #e9e4dc;
            --paper: #ffffff;
            --cream: #fbf8f3;
        }

        @page { size: A4; margin: 0; }

        html, body { background: #e9e7e3 !important; color: var(--ink); }
        body { font-family: 'Vazirmatn Variable', sans-serif; -webkit-print-color-adjust: exact; print-color-adjust: exact; }

        /* ---- Screen toolbar ---- */
        .toolbar {
            position: sticky; top: 0; z-index: 10; display: flex; align-items: center; gap: 10px;
            padding: 12px 20px; background: rgba(20, 26, 51, .92); color: #fff; backdrop-filter: blur(8px);
        }
        .toolbar .title { font-size: 13px; opacity: .85; flex: 1; }
        .toolbar button, .toolbar a {
            display: inline-flex; align-items: center; gap: 8px; height: 38px; padding: 0 16px; border-radius: 10px;
            font-size: 13px; font-weight: 700; border: 1px solid rgba(255,255,255,.18); color: #fff; background: transparent; cursor: pointer; text-decoration: none;
        }
        .toolbar .primary { background: var(--brand); border-color: transparent; }

        /* ---- A4 sheet ---- */
        .sheet {
            position: relative; width: 210mm; min-height: 297mm; margin: 24px auto 48px; background: var(--paper);
            box-shadow: 0 1px 2px rgba(0,0,0,.06), 0 24px 60px -20px rgba(20,26,51,.35); overflow: hidden;
        }
        .frame { width: 100%; border-collapse: collapse; }
        .frame > thead td, .frame > tbody td, .frame > tfoot td { padding: 0; }

        .watermark {
            position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; pointer-events: none; z-index: 0;
        }
        .watermark img { width: 118mm; opacity: .035; filter: grayscale(.2); }

        /* ---- Letterhead ---- */
        .head { position: relative; padding: 0 16mm; z-index: 1; }
        .accent { display: flex; height: 5mm; margin: 0 -16mm; }
        .accent span:nth-child(1) { flex: 7; background: linear-gradient(90deg, var(--brand-2), var(--brand)); }
        .accent span:nth-child(2) { flex: 1.2; background: var(--navy); }
        .accent span:nth-child(3) { flex: .35; background: var(--brand-deep); }

        .brandrow { display: flex; align-items: center; justify-content: space-between; gap: 10mm; padding: 9mm 0 6mm; }
        .brand { display: flex; align-items: center; gap: 4.5mm; direction: ltr; }
        .brand img { width: 17mm; height: 17mm; }
        .wordmark { line-height: 1; }
        .wordmark .name { font-size: 25pt; letter-spacing: -.5px; color: var(--navy); }
        .wordmark .name .l { font-weight: 300; }
        .wordmark .name .b { font-weight: 900; }
        .wordmark .tag { margin-top: 2.4mm; font-size: 8.6pt; color: var(--muted); direction: rtl; text-align: left; letter-spacing: 0; }

        .meta {
            min-width: 58mm; border: 1px solid var(--line); border-radius: 3mm; overflow: hidden; background: var(--cream);
            box-shadow: inset 0 0 0 1px rgba(255,255,255,.6);
        }
        .meta .row { display: flex; align-items: center; justify-content: space-between; gap: 6mm; padding: 2.3mm 4mm; font-size: 9.5pt; }
        .meta .row + .row { border-top: 1px dashed var(--line); }
        .meta .k { color: var(--muted); font-weight: 600; }
        .meta .v { font-weight: 800; color: var(--ink); }
        .meta .row:first-child { background: linear-gradient(90deg, rgba(221,122,0,.10), rgba(221,122,0,0)); }
        .meta .row:first-child .v { color: var(--brand-deep); font-size: 11pt; }

        .rule { position: relative; height: 1px; background: linear-gradient(90deg, transparent, var(--line) 12%, var(--line) 88%, transparent); margin-bottom: 7mm; }
        .rule::before, .rule::after {
            content: ''; position: absolute; top: 50%; left: 50%; width: 2.2mm; height: 2.2mm; background: var(--brand);
            transform: translate(-50%, -50%) rotate(45deg);
        }
        .rule::after { width: 1.3mm; height: 1.3mm; left: calc(50% + 4mm); background: var(--navy); }

        /* ---- Stamps ---- */
        .stamps { position: absolute; top: 24mm; left: 50%; transform: translateX(-50%); display: flex; gap: 4mm; z-index: 2; }
        .stamp {
            padding: 1.6mm 5mm; border: .7mm solid currentColor; border-radius: 2mm; font-weight: 900; font-size: 11pt;
            letter-spacing: 1px; transform: rotate(-8deg); opacity: .82; background: rgba(255,255,255,.7);
        }
        .stamp.draft { color: #a85a00; }
        .stamp.final { color: #15803d; }

        /* ---- Body ---- */
        .content { position: relative; z-index: 1; padding: 0 16mm; font-size: 11.5pt; }
        .parties { display: grid; grid-template-columns: auto 1fr; gap: 2.4mm 5mm; margin-bottom: 4mm; align-items: baseline; }
        .parties .k { color: var(--muted); font-size: 10pt; font-weight: 700; }
        .parties .v { font-weight: 700; }
        .parties .v small { font-weight: 500; color: var(--muted); font-size: 9.5pt; }

        .subject {
            display: flex; gap: 4mm; align-items: baseline; padding: 3.5mm 5mm; margin-bottom: 7mm; border-radius: 2.5mm;
            background: var(--cream); border-right: 1.4mm solid var(--brand);
        }
        .subject .k { color: var(--brand-deep); font-weight: 800; font-size: 10pt; white-space: nowrap; }
        .subject .v { font-weight: 900; font-size: 12.5pt; color: var(--navy); }

        .body { line-height: 2.15; text-align: justify; white-space: normal; word-break: break-word; min-height: 40mm; }

        .section { margin-top: 8mm; page-break-inside: avoid; }
        .section h3 { display: flex; align-items: center; gap: 2.5mm; font-size: 10pt; font-weight: 800; color: var(--navy); margin-bottom: 3mm; }
        .section h3::before { content: ''; width: 2mm; height: 2mm; background: var(--brand); transform: rotate(45deg); }

        table.list { width: 100%; border-collapse: collapse; font-size: 9pt; }
        table.list th { text-align: right; font-weight: 700; color: var(--muted); background: var(--cream); padding: 2mm 3mm; border-bottom: 1px solid var(--line); }
        table.list td { padding: 2.2mm 3mm; border-bottom: 1px dashed var(--line); vertical-align: top; }
        table.list td.no { text-align: center; font-weight: 800; color: var(--brand-deep); }

        /* ---- Signatures ---- */
        .sig-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 6mm 10mm; margin-top: 4mm; }
        .sig { text-align: center; border: 1px solid var(--line); border-radius: 2.5mm; padding: 3mm 3mm 4mm; background: linear-gradient(180deg, #fff, var(--cream)); }
        .sig .who { font-weight: 900; font-size: 10pt; color: var(--navy); }
        .sig .role { font-size: 8.5pt; color: var(--muted); margin-top: .6mm; min-height: 3.6mm; }
        .sig .mark { margin-top: 9mm; font-size: 7.5pt; color: #a29b8f; border-top: 1px solid #c9c3b8; padding-top: 1.2mm; }
        .sig.approved { border-color: #86efac; }
        .sig.approved .mark { border-top-color: #86efac; color: #15803d; font-weight: 700; }

        .verify {
            display: flex; align-items: center; gap: 5mm; margin-top: 10mm; padding: 4mm; border: 1px solid var(--line); border-radius: 3mm;
            page-break-inside: avoid; background: linear-gradient(180deg, #fff, var(--cream));
        }
        .verify .qr { width: 24mm; height: 24mm; flex-shrink: 0; }
        .verify .qr svg { width: 100%; height: 100%; }
        .verify .txt { font-size: 8.5pt; color: var(--muted); line-height: 1.9; }
        .verify .code { font-size: 12pt; font-weight: 900; color: var(--navy); letter-spacing: 1px; direction: ltr; display: inline-block; }

        /* ---- Footer ---- */
        .footer-space { height: 26mm; }
        .page-footer { position: absolute; bottom: 0; left: 0; right: 0; z-index: 1; }
        .page-footer .bar { display: flex; height: 1.6mm; }
        .page-footer .bar span:nth-child(1) { flex: .35; background: var(--brand-deep); }
        .page-footer .bar span:nth-child(2) { flex: 1.2; background: var(--navy); }
        .page-footer .bar span:nth-child(3) { flex: 7; background: linear-gradient(90deg, var(--brand), var(--brand-2)); }
        .page-footer .info {
            display: flex; flex-wrap: wrap; justify-content: center; gap: 2mm 7mm; padding: 4.5mm 16mm 3mm;
            font-size: 8.6pt; color: var(--ink); background: var(--cream);
        }
        .page-footer .info span { display: inline-flex; align-items: center; gap: 1.8mm; }
        .page-footer .info svg { width: 3.4mm; height: 3.4mm; color: var(--brand); flex-shrink: 0; }
        .page-footer .ltr { direction: ltr; }
        .page-footer .printed { text-align: center; font-size: 7pt; color: #a29b8f; padding: 0 16mm 3.5mm; background: var(--cream); }

        @media print {
            .toolbar { display: none; }
            .sheet { margin: 0; box-shadow: none; width: auto; min-height: 0; overflow: visible; }
            .watermark, .page-footer { position: fixed; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <span class="title">صورت‌جلسه شماره {{ $number }} · {{ $minute->subject }}</span>
        <a href="{{ route('minutes.show', [$minute->workspace_id, $minute]) }}">بازگشت</a>
        <button type="button" class="primary" id="print">چاپ / ذخیره PDF</button>
    </div>

    <article class="sheet">
        <div class="watermark" aria-hidden="true"><img src="/brand/icon.png" alt=""></div>

        <div class="stamps">
            @if ($minute->status === 'final')
                <span class="stamp final">تأییدشده</span>
            @else
                <span class="stamp draft">پیش‌نویس</span>
            @endif
        </div>

        <table class="frame">
            <thead>
                <tr><td>
                    <header class="head">
                        <div class="accent"><span></span><span></span><span></span></div>
                        <div class="brandrow">
                            <div class="brand">
                                <img src="/brand/icon.png" alt="{{ $company['name'] }}">
                                <div class="wordmark">
                                    @php($name = $company['name'] ?: 'WilberNight')
                                    @if ($name === 'WilberNight')
                                        <div class="name"><span class="l">Wilber</span><span class="b">Night</span></div>
                                    @else
                                        <div class="name"><span class="b">{{ $name }}</span></div>
                                    @endif
                                    @if ($company['tagline'])<div class="tag">{{ $company['tagline'] }}</div>@endif
                                </div>
                            </div>
                            <div class="meta">
                                <div class="row"><span class="k">شماره جلسه</span><span class="v">{{ $number }}</span></div>
                                <div class="row"><span class="k">تاریخ</span><span class="v">{{ $dateNumeric ?? '—' }}</span></div>
                                <div class="row"><span class="k">ساعت</span><span class="v">{{ $time ?? '—' }}</span></div>
                                <div class="row"><span class="k">پیوست</span><span class="v">{{ $minute->attachments->count() ? 'دارد ('.$fa($minute->attachments->count()).' فایل)' : 'ندارد' }}</span></div>
                            </div>
                        </div>
                        <div class="rule"></div>
                    </header>
                </td></tr>
            </thead>

            <tbody>
                <tr><td>
                    <section class="content">
                        <div class="subject"><span class="k">موضوع جلسه:</span><span class="v">{{ $minute->subject }}</span></div>

                        <div class="parties">
                            <span class="k">مجری:</span>
                            <span class="v">{{ $organizer ?? '—' }}@if ($organizerTitle) <small>({{ $organizerTitle }})</small>@endif</span>
                            <span class="k">محل برگزاری:</span>
                            <span class="v">{{ $minute->location ?: '—' }}</span>
                            <span class="k">تاریخ و ساعت:</span>
                            <span class="v">{{ $date ?? '—' }}@if ($time) <small>ساعت {{ $time }}</small>@endif</span>
                        </div>

                        @if ($present->isNotEmpty())
                            <div class="section">
                                <h3>حاضرین</h3>
                                <table class="list">
                                    <thead><tr><th style="width:8mm">#</th><th>نام و نام خانوادگی</th><th>سمت</th></tr></thead>
                                    <tbody>
                                        @foreach ($present as $i => $p)
                                            <tr>
                                                <td class="no">{{ $fa($i + 1) }}</td>
                                                <td>{{ $p->user?->name ?? $p->person?->name }}@if (! $p->user_id) <small>(بدون اکانت)</small>@endif</td>
                                                <td>{{ $titles[$p->user_id] ?? '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        @if ($absent->isNotEmpty())
                            <div class="section">
                                <h3>غایبان</h3>
                                <table class="list">
                                    <thead><tr><th style="width:8mm">#</th><th>نام و نام خانوادگی</th><th>سمت</th></tr></thead>
                                    <tbody>
                                        @foreach ($absent as $i => $p)
                                            <tr>
                                                <td class="no">{{ $fa($i + 1) }}</td>
                                                <td>{{ $p->user?->name ?? $p->person?->name }}@if (! $p->user_id) <small>(بدون اکانت)</small>@endif</td>
                                                <td>{{ $titles[$p->user_id] ?? '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        <div class="section">
                            <h3>شرح مذاکرات و مصوبات</h3>
                            <div class="body">{!! nl2br(e($minute->body ?: '—'), false) !!}</div>
                        </div>

                        @if ($actions->isNotEmpty())
                            <div class="section">
                                <h3>اقدامات</h3>
                                <table class="list">
                                    <thead><tr><th style="width:8mm">#</th><th>شرح اقدام</th><th style="width:34mm">مسئول</th><th style="width:20mm">مهلت</th></tr></thead>
                                    <tbody>
                                        @foreach ($actions as $i => $a)
                                            <tr>
                                                <td class="no">{{ $fa($i + 1) }}</td>
                                                <td>{{ $a['title'] }}@if ($a['task']) <small>— به وظیفه تبدیل شد</small>@endif</td>
                                                <td>{{ $a['who'] ?? '—' }}</td>
                                                <td>{{ $a['due'] ?? '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        @if ($minute->attachments->isNotEmpty())
                            <div class="section">
                                <h3>پیوست‌ها</h3>
                                <div class="files" style="display:flex; flex-wrap:wrap; gap:2mm; font-size:9pt;">
                                    @foreach ($minute->attachments as $a)<span style="padding:1mm 3mm; border:1px solid var(--line); border-radius:1.5mm; background:var(--cream);">{{ $a->original_name }}</span>@endforeach
                                </div>
                            </div>
                        @endif

                        <div class="section">
                            <h3>امضاها</h3>
                            <div class="sig-grid">
                                @foreach ($present->filter(fn ($p) => $p->user_id || $p->person_id) as $p)
                                    @php($approved = $approvals->firstWhere('name', $p->user?->name ?? $p->person?->name))
                                    <div class="sig {{ $approved ? 'approved' : '' }}">
                                        <div class="who">{{ $p->user?->name ?? $p->person?->name }}</div>
                                        <div class="role">{{ $titles[$p->user_id] ?? 'مدعو' }}</div>
                                        <div class="mark">{{ $approved ? 'تأیید الکترونیکی · '.$approved['date'] : 'محل امضا' }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="verify">
                            <div class="qr" aria-label="کد QR صورت‌جلسه">{!! $qr !!}</div>
                            <div class="txt">
                                کد رهگیری: <span class="code">{{ $code }}</span><br>
                                این صورت‌جلسه در سامانه {{ $company['name'] }} ثبت شده و حاضرین دارای اکانت می‌توانند آن را به‌صورت الکترونیکی تأیید کنند.<br>
                                برای مشاهده نسخه اصلی، کد را با تلفن همراه اسکن کنید (نیازمند ورود به حساب).
                            </div>
                        </div>
                    </section>
                </td></tr>
            </tbody>

            <tfoot><tr><td><div class="footer-space"></div></td></tr></tfoot>
        </table>

        <footer class="page-footer">
            <div class="bar"><span></span><span></span><span></span></div>
            <div class="info">
                @if ($company['address'])
                    <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s7-6.2 7-12a7 7 0 1 0-14 0c0 5.8 7 12 7 12Z"/><circle cx="12" cy="10" r="2.5"/></svg>{{ $company['address'] }}</span>
                @endif
                @if ($company['phone'])
                    <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2Z"/></svg><span class="ltr">{{ $company['phone'] }}</span></span>
                @endif
                @if ($company['email'])
                    <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/></svg><span class="ltr">{{ $company['email'] }}</span></span>
                @endif
                @if ($company['website'])
                    <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10Z"/></svg><span class="ltr">{{ $company['website'] }}</span></span>
                @endif
            </div>
            <div class="printed">چاپ‌شده توسط {{ $printedBy }} · {{ $printedAt }}</div>
        </footer>
    </article>

    <script nonce="{{ Vite::cspNonce() }}">
        document.getElementById('print').addEventListener('click', function () { window.print(); });
        if (new URLSearchParams(location.search).has('auto')) {
            window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 350); });
        }
    </script>
</body>
</html>
