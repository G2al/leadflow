<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Prova script - {{ $offer->name }}</title>
    <style>
        body { margin: 0; color: #1f2937; background: #f3f4f6; font: 16px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        .notice { padding: 12px 16px; color: #92400e; font-size: .9rem; text-align: center; background: #fef3c7; }
        .landing { max-width: 640px; margin: 24px auto; padding: 0 16px; }
        h1 { margin: 0 0 16px; font-size: 1.25rem; }
        .frame { background: #fff; border-radius: 12px; }
    </style>
</head>
<body>
    <p class="notice"><strong>Pagina di prova:</strong> ogni invio crea un ordine reale nella sezione Ordini.</p>

    <main class="landing">
        <h1>{{ $offer->name }}</h1>

        <div class="frame">
            {!! $snippet !!}
        </div>
    </main>
</body>
</html>
