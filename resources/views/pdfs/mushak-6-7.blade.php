{{--
    Mushak 6.7, the credit note (ক্রেডিট নোট) under rule 40(1)(ছ) of the VAT
    and Supplementary Duty Rules 2016, for goods a customer sent back.
    Always printed in Bangla. Prices include VAT; the VAT given back is the
    part of each line that belonged to the government.
--}}
@extends('pdfs.layout')

@section('docTitle', 'মূসক-৬.৭ - '.$receive->number)

@section('body')
@php
    $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 3), '0'), '.') ?: '0';
    $sum = fn ($k) => collect($rows)->sum($k);
@endphp

<table class="plain" style="margin-bottom: 3mm;">
    <tr>
        <td style="width: 25%;"></td>
        <td style="width: 50%; text-align: center;">
            <div>গণপ্রজাতন্ত্রী বাংলাদেশ সরকার</div>
            <div>জাতীয় রাজস্ব বোর্ড</div>
            <div style="font-size: 15px; font-weight: bold; margin-top: 1mm;">ক্রেডিট নোট</div>
            <div class="small">[বিধি ৪০ এর উপ-বিধি (১) এর দফা (ছ) দ্রষ্টব্য]</div>
        </td>
        <td style="width: 25%; text-align: right; vertical-align: top;">
            <span style="border: 1px solid #222; padding: 1mm 3mm; font-weight: bold;">মূসক-৬.৭</span>
        </td>
    </tr>
</table>

<table class="plain" style="margin-bottom: 3mm;">
    <tr>
        <td style="text-align: center;">
            নিবন্ধিত ব্যক্তির নাম: <strong>{{ $company?->name ?? config('app.name') }}</strong><br>
            নিবন্ধিত ব্যক্তির বিআইএন: <strong>{{ $bin ?: '—' }}</strong><br>
            ঠিকানা: {{ $company?->address ?: '—' }}
        </td>
    </tr>
</table>

<table class="plain" style="margin-bottom: 4mm;">
    <tr>
        <td style="width: 60%; padding-right: 4mm;">
            ক্রেতার নাম: <strong>{{ $customer?->name ?? '—' }}</strong>@if($customer?->shop_name) ({{ $customer->shop_name }})@endif<br>
            ক্রেতার বিআইএন: {{ $customer?->bin ?: '—' }}<br>
            ক্রেতার ঠিকানা: {{ $customer?->address ?: '—' }}<br>
            ফেরতের কারণ: {{ $reason ?: '—' }}
        </td>
        <td style="width: 40%;">
            ক্রেডিট নোট নম্বর: <strong>{{ $receive->number }}</strong><br>
            ইস্যুর তারিখ: {{ $receive->receive_date->format('d/m/Y') }}<br>
            ইস্যুর সময়: {{ $receive->created_at?->format('h:i A') }}<br>
            ফেরত অনুরোধ: {{ $request?->number ?? '—' }}<br>
            মূল কর চালানপত্র: {{ $invoices ?: '—' }}
        </td>
    </tr>
</table>

<table class="lines">
    <thead>
        <tr>
            <th class="c">ক্রমিক সংখ্যা</th>
            <th>ফেরত পণ্যের বর্ণনা</th>
            <th class="c">সরবরাহের একক</th>
            <th class="r">পরিমাণ</th>
            <th class="r">একক মূল্য<sup>১</sup> (টাকায়)</th>
            <th class="r">মোট মূল্য<sup>১</sup> (টাকায়)</th>
            <th class="r">সম্পূরক শুল্কের পরিমাণ (টাকায়)</th>
            <th class="r">মূল্য সংযোজন করের হার</th>
            <th class="r">হ্রাসকৃত মূল্য সংযোজন কর (টাকায়)</th>
            <th class="r">সকল প্রকার শুল্ক ও করসহ মূল্য</th>
        </tr>
        <tr>
            @foreach(range(1, 10) as $n)<th class="c small">({{ $n }})</th>@endforeach
        </tr>
    </thead>
    <tbody>
        @foreach($rows as $i => $r)
            <tr>
                <td class="c">{{ $i + 1 }}</td>
                <td>{{ $r['name'] }}</td>
                <td class="c">{{ $r['unit'] }}</td>
                <td class="r">{{ $qty($r['quantity']) }}</td>
                <td class="r">{{ number_format($r['unit_price'], 2) }}</td>
                <td class="r">{{ number_format($r['value'], 2) }}</td>
                <td class="r">0.00</td>
                <td class="r">{{ $r['rate'] > 0 ? rtrim(rtrim((string) $r['rate'], '0'), '.').'%' : 'অব্যাহতি' }}</td>
                <td class="r">{{ number_format($r['vat'], 2) }}</td>
                <td class="r"><strong>{{ number_format($r['total'], 2) }}</strong></td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="5" class="r">সর্বমোট</td>
            <td class="r">{{ number_format($sum('value'), 2) }}</td>
            <td class="r">0.00</td>
            <td></td>
            <td class="r">{{ number_format($sum('vat'), 2) }}</td>
            <td class="r">{{ number_format($sum('total'), 2) }}</td>
        </tr>
    </tfoot>
</table>

<p class="small" style="margin-top: 2mm;">কথায়: {{ \App\Services\PdfService::amountInWords((float) $sum('total')) }}</p>

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

<p class="small muted" style="margin-top: 6mm;"><sup>১</sup> সকল প্রকার কর ব্যতীত মূল্য।</p>
@endsection
