{{--
    Shared PDF layout. Built only with tables and simple CSS so it looks the
    same in dompdf (English) and mPDF (Bangla). Sections:
      docTitle   — big title on the right ("Delivery Invoice")
      docMeta    — lines under the title (number, date)
      content    — the document body
--}}
<!DOCTYPE html>
<html lang="{{ $bangla ? 'bn' : 'en' }}">
<head>
    <meta charset="utf-8">
    <title>@yield('docTitle')</title>
    <style>
        @page { margin: 12mm; }
        body { font-family: '{{ $pdfFont }}', sans-serif; font-size: 10.5px; color: #222; line-height: 1.45; margin: 0; }
        h1 { font-size: 17px; margin: 0; }
        table { border-collapse: collapse; width: 100%; }
        .plain td { border: none; padding: 0; vertical-align: top; }
        .head { border-bottom: 2px solid #222; padding-bottom: 3mm; margin-bottom: 4mm; }
        .doc-title { font-size: 16px; font-weight: bold; text-align: right; text-transform: uppercase; letter-spacing: .5px; }
        .doc-meta { text-align: right; font-size: 10px; color: #444; }
        .box-title { font-weight: bold; font-size: 9.5px; text-transform: uppercase; color: #555; border-bottom: 1px solid #ccc; padding-bottom: 1mm; margin-bottom: 1.5mm; }
        .lines th { background: #f1f1f1; border: 1px solid #bbb; padding: 2mm; text-align: left; font-size: 9.5px; }
        .lines td { border: 1px solid #ccc; padding: 1.8mm 2mm; vertical-align: top; }
        .lines tr.alt td { background: #fafafa; }
        .lines tfoot td { font-weight: bold; background: #f1f1f1; }
        .r { text-align: right; }
        .c { text-align: center; }
        .muted { color: #666; }
        .small { font-size: 9px; }
        .totals td { padding: 1.5mm 2mm; border-bottom: 1px solid #ddd; }
        .totals tr.grand td { font-weight: bold; font-size: 12px; border-top: 2px solid #222; border-bottom: 2px solid #222; background: #f1f1f1; }
        .badge { padding: .5mm 2mm; background: #eee; border: 1px solid #ccc; font-size: 9px; font-weight: bold; }
        .foot { margin-top: 8mm; border-top: 1px solid #ccc; padding-top: 2mm; font-size: 9px; color: #666; text-align: center; }
        .page-break { page-break-after: always; }
    </style>
</head>
<body>
@yield('body')
</body>
</html>
