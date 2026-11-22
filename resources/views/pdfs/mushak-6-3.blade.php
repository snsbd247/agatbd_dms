{{--
    Mushak 6.3, the VAT invoice (কর চালানপত্র) under rule 40(1)(c) and (f)
    of the VAT and Supplementary Duty Rules 2016. An official form, so it is
    always printed in Bangla. Prices include VAT; the VAT is shown as the
    part of each line that belongs to the government.
--}}
@extends('pdfs.layout')

@section('docTitle', 'মূসক-৬.৩ - '.$invoice->number)

@section('body')
@php
    $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 3), '0'), '.') ?: '0';
    $gross = (float) $invoice->subtotal;
    $factor = $gross > 0 ? 1 - (float) $invoice->discount / $gross : 1;   // the order discount, spread over the lines
    $rows = $invoice->items->map(function ($item) use ($factor) {
        $charged = round((float) $item->line_total * $factor, 2);
        $vat = (float) $item->vat_amount;
        $value = round($charged - $vat, 2);
        return [
            'item' => $item,
            'unit_price' => (float) $item->quantity > 0 ? $value / (float) $item->quantity : 0,
            'value' => $value,
            'vat' => $vat,
            'total' => $charged,
        ];
    });
    $sum = fn ($k) => $rows->sum($k);
@endphp

<table class="plain" style="margin-bottom: 3mm;">
    <tr>
        <td style="width: 25%;"></td>
        <td style="width: 50%; text-align: center;">
            <div>গণপ্রজাতন্ত্রী বাংলাদেশ সরকার</div>
            <div>জাতীয় রাজস্ব বোর্ড</div>
            <div style="font-size: 15px; font-weight: bold; margin-top: 1mm;">কর চালানপত্র</div>
            <div class="small">[বিধি ৪০ এর উপ-বিধি (১) এর দফা (গ) ও দফা (চ) দ্রষ্টব্য]</div>
        </td>
        <td style="width: 25%; text-align: right; vertical-align: top;">
            <span style="border: 1px solid #222; padding: 1mm 3mm; font-weight: bold;">মূসক-৬.৩</span>
        </td>
    </tr>
</table>

<table class="plain" style="margin-bottom: 3mm;">
    <tr>
        <td style="text-align: center;">
            নিবন্ধিত ব্যক্তির নাম: <strong>{{ $company?->name ?? config('app.name') }}</strong><br>
            নিবন্ধিত ব্যক্তির বিআইএন: <strong>{{ $bin ?: '—' }}</strong><br>
            চালানপত্র ইস্যুর ঠিকানা: {{ $company?->address ?: '—' }}
        </td>
    </tr>
</table>

<table class="plain" style="margin-bottom: 4mm;">
    <tr>
        <td style="width: 60%; padding-right: 4mm;">
            ক্রেতার নাম: <strong>{{ $invoice->retailer->name ?? '—' }}</strong>@if($invoice->retailer?->shop_name) ({{ $invoice->retailer->shop_name }})@endif<br>
            ক্রেতার বিআইএন: {{ $invoice->retailer?->bin ?: '—' }}<br>
            ক্রেতার ঠিকানা: {{ $invoice->retailer?->address ?: '—' }}<br>
            সরবরাহের গন্তব্যস্থল: {{ $invoice->retailer?->address ?: '—' }}<br>
            যানবাহনের প্রকৃতি ও নম্বর: {{ $vehicle ?: '—' }}
        </td>
        <td style="width: 40%;">
            চালানপত্র নম্বর: <strong>{{ $invoice->number }}</strong><br>
            ইস্যুর তারিখ: {{ $invoice->invoice_date->format('d/m/Y') }}<br>
            ইস্যুর সময়: {{ $invoice->created_at?->format('h:i A') }}<br>
            বিক্রয় আদেশ: {{ $invoice->salesOrder->number ?? '—' }}
        </td>
    </tr>
</table>

<table class="lines">
    <thead>
        <tr>
            <th class="c">ক্রমিক সংখ্যা</th>
            <th>পণ্য বা সেবার বর্ণনা (প্রযোজ্য ক্ষেত্রে ব্র্যান্ড নামসহ)</th>
            <th class="c">সরবরাহের একক</th>
            <th class="r">পরিমাণ</th>
            <th class="r">একক মূল্য<sup>১</sup> (টাকায়)</th>
            <th class="r">মোট মূল্য<sup>১</sup> (টাকায়)</th>
            <th class="r">সম্পূরক শুল্কের হার</th>
            <th class="r">সম্পূরক শুল্কের পরিমাণ (টাকায়)</th>
            <th class="r">মূল্য সংযোজন করের হার / সুনির্দিষ্ট কর</th>
            <th class="r">মূল্য সংযোজন কর / সুনির্দিষ্ট কর এর পরিমাণ (টাকায়)</th>
            <th class="r">সকল প্রকার শুল্ক ও করসহ মূল্য</th>
        </tr>
        <tr>
            @foreach(range(1, 11) as $n)<th class="c small">({{ $n }})</th>@endforeach
        </tr>
    </thead>
    <tbody>
        @foreach($rows as $i => $r)
            <tr>
                <td class="c">{{ $i + 1 }}</td>
                <td>{{ $r['item']->product->name ?? '—' }}@if($r['item']->product?->brand) ({{ $r['item']->product->brand->name }})@endif @if($r['item']->batch)<br><span class="small muted">ব্যাচ: {{ $r['item']->batch->batch_no }}</span>@endif</td>
                <td class="c">{{ $r['item']->product?->unit?->short_code }}</td>
                <td class="r">{{ $qty($r['item']->quantity) }}</td>
                <td class="r">{{ number_format($r['unit_price'], 2) }}</td>
                <td class="r">{{ number_format($r['value'], 2) }}</td>
                <td class="r">0%</td>
                <td class="r">0.00</td>
                <td class="r">{{ (float) $r['item']->vat_rate > 0 ? rtrim(rtrim((string) $r['item']->vat_rate, '0'), '.').'%' : 'অব্যাহতি' }}</td>
                <td class="r">{{ number_format($r['vat'], 2) }}</td>
                <td class="r"><strong>{{ number_format($r['total'], 2) }}</strong></td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="5" class="r">সর্বমোট</td>
            <td class="r">{{ number_format($sum('value'), 2) }}</td>
            <td></td>
            <td class="r">0.00</td>
            <td></td>
            <td class="r">{{ number_format($sum('vat'), 2) }}</td>
            <td class="r">{{ number_format($sum('total'), 2) }}</td>
        </tr>
    </tfoot>
</table>

<p class="small" style="margin-top: 2mm;">কথায়: {{ \App\Services\PdfService::amountInWords((float) $invoice->grand_total) }}</p>

<table class="plain" style="margin-top: 14mm;">
    <tr>
        <td style="width: 55%;"></td>
        <td style="width: 45%;">
            প্রতিষ্ঠান কর্তৃপক্ষের দায়িত্বপ্রাপ্ত ব্যক্তির নাম: {{ $signer?->name ?? '' }}<br>
            পদবি: {{ $signerTitle }}<br><br>
            স্বাক্ষর: ______________________<br>
            সিল:
        </td>
    </tr>
</table>

<p class="small muted" style="margin-top: 6mm;">
    <sup>১</sup> সকল প্রকার কর ব্যতীত মূল্য। মোট মূল্য থেকে প্রযোজ্য অংশ বিক্রয় আদেশের ছাড় বাদ দিয়ে দেখানো হয়েছে।
</p>
@endsection
