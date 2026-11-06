<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Richiesta di ricontatto</title>
    <style nonce="{{ request()->attributes->get('csp_nonce') }}">
        *, *::before, *::after { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; background: transparent; }
        body { color: #1f2937; font: 16px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        .card { max-width: 480px; margin: 0 auto; padding: 20px 16px; }
        h1 { margin: 0 0 4px; font-size: 1.25rem; line-height: 1.3; }
        .intro { margin: 0 0 16px; color: #4b5563; font-size: .9rem; }
        .field { margin-bottom: 14px; }
        label { display: block; margin-bottom: 4px; font-size: .9rem; font-weight: 600; }
        .optional { color: #6b7280; font-weight: 400; }
        input, textarea { display: block; width: 100%; padding: 10px 12px; color: inherit; font: inherit; background: #fff; border: 1px solid #d1d5db; border-radius: 8px; }
        input:focus, textarea:focus { outline: 2px solid #2563eb; outline-offset: 1px; border-color: #2563eb; }
        textarea { min-height: 72px; resize: vertical; }
        .invalid { border-color: #dc2626; }
        .error { margin: 4px 0 0; color: #b91c1c; font-size: .85rem; }
        .trap { position: absolute; left: -10000px; width: 1px; height: 1px; overflow: hidden; }
        button { width: 100%; padding: 12px 16px; color: #fff; font: inherit; font-weight: 600; background: #2563eb; border: 0; border-radius: 8px; cursor: pointer; }
        button:hover { background: #1d4ed8; }
        button:disabled { background: #93c5fd; cursor: not-allowed; }
        .privacy { margin: 12px 0 0; color: #6b7280; font-size: .8rem; }
        .privacy a { color: inherit; }
        .notice { margin: 0; padding: 14px 16px; border-radius: 8px; font-size: .95rem; }
        .notice--success { color: #065f46; background: #ecfdf5; }
        .notice--error { margin-bottom: 14px; color: #991b1b; background: #fef2f2; }
        .notice--muted { color: #4b5563; background: #f3f4f6; }
        [hidden] { display: none !important; }
    </style>
</head>
<body>
    @yield('content')

    <script nonce="{{ request()->attributes->get('csp_nonce') }}">
        (function () {
            // The payload is only a height, so it is safe for any parent; the loader
            // on the landing page verifies the origin of what it receives.
            function reportHeight() {
                window.parent.postMessage({ type: 'leadflow:resize', height: document.documentElement.offsetHeight }, '*');
            }

            window.leadflowReportHeight = reportHeight;
            window.addEventListener('load', reportHeight);
            window.addEventListener('resize', reportHeight);

            if (window.ResizeObserver) {
                new ResizeObserver(reportHeight).observe(document.body);
            }

            reportHeight();
        })();
    </script>
    @stack('scripts')
</body>
</html>
