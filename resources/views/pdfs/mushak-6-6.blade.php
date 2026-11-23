{{--
    Mushak 6.6, the certificate of VAT deducted at source (উৎসে কর কর্তন
    সনদপত্র) under rule 40(1)(চ) of the VAT and Supplementary Duty Rules
    2016, given to the supplier. Always printed in Bangla.
--}}
@extends('pdfs.layout')

@section('docTitle', 'মূসক-৬.৬ - '.$c->number)

@section('body')
<table class="plain" style="margin-bottom: 3mm;">
    <tr>
        <td style="width: 25%;"></td>
        <td style="width: 50%; text-align: center;">
            <div>গণপ্রজাতন্ত্রী বাংলাদেশ সরকার</div>
            <div>জাতীয় রাজস্ব বোর্ড</div>
            <div style="font-size: 15px; font-weight: bold; margin-top: 1mm;">উৎসে কর কর্তন সনদপত্র</div>
            <div class="small">[বিধি ৪০ এর উপ-বিধি (১) এর দফা (চ) দ্রষ্টব্য]</div>
        </td>
        <td style="width: 25%; text-align: right; vertical-align: top;">
            <span style="border: 1px solid #222; padding: 1mm 3mm; font-weight: bold;">মূসক-৬.৬</span>
        </td>
    </tr>
</table>

<table class="plain" style="margin-bottom: 4mm;">
    <tr>
        <td style="width: 60%; padding-right: 4mm;">
            উৎসে কর কর্তনকারী সত্তার নাম: <strong>{{ $company?->name ?? config('app.name') }}</strong><br>
            ঠিকানা: {{ $company?->address ?: '—' }}<br>
            বিআইএন: <strong>{{ $bin ?: '—' }}</strong>
        </td>
        <td style="width: 40%;">
            উৎসে কর কর্তন সনদপত্র নং: <strong>{{ $c->number }}</strong><br>
            জারির তারিখ: {{ $c->certificate_date->format('d/m/Y') }}
        </td>
    </tr>
</table>

<p style="margin-bottom: 3mm;">
    এই মর্মে প্রত্যয়ন করা যাচ্ছে যে, আইনের ধারা ৪৯ অনুযায়ী উৎসে কর কর্তনযোগ্য সরবরাহ হতে প্রযোজ্য মূল্য সংযোজন কর বাবদ উৎসে কর কর্তন করা হলো।
    কর্তনকৃত মূল্য সংযোজন করের অর্থ বুক ট্রান্সফার/ট্রেজারি চালান/দাখিলপত্রে বৃদ্ধিকারী সমন্বয়ের মাধ্যমে সরকারি কোষাগারে জমা করা হবে।
</p>

<table class="lines">
    <thead>
        <tr>
            <th class="c" rowspan="2">ক্রমিক নং</th>
            <th colspan="2" class="c">সরবরাহকারীর</th>
            <th colspan="2" class="c">সংশ্লিষ্ট কর চালানপত্র</th>
            <th class="r" rowspan="2">মোট সরবরাহ মূল্য<sup>১</sup> (টাকা)</th>
            <th class="r" rowspan="2">মূসকের পরিমাণ (টাকা)</th>
            <th class="r" rowspan="2">উৎসে কর্তনকৃত মূসকের পরিমাণ (টাকা)</th>
        </tr>
        <tr>
            <th>নাম</th>
            <th>বিআইএন</th>
            <th>নম্বর</th>
            <th>ইস্যুর তারিখ</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="c">১</td>
            <td>{{ $c->company?->name }}<br><span class="small muted">{{ $c->company?->address }}</span></td>
            <td>{{ $c->company?->bin ?: '—' }}</td>
            <td>{{ $c->invoice_no ?: '—' }}</td>
            <td>{{ $c->invoice_date?->format('d/m/Y') ?: '—' }}</td>
            <td class="r">{{ (float) $c->invoice_amount > 0 ? number_format($c->invoice_amount, 2) : '—' }}</td>
            <td class="r">{{ number_format($c->vat_amount, 2) }}</td>
            <td class="r"><strong>{{ number_format($c->vat_amount, 2) }}</strong></td>
        </tr>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="7" class="r">সর্বমোট</td>
            <td class="r">{{ number_format($c->vat_amount, 2) }}</td>
        </tr>
    </tfoot>
</table>

<p class="small" style="margin-top: 2mm;">কথায়: {{ \App\Services\PdfService::amountInWords((float) $c->vat_amount) }}</p>

<table class="plain" style="margin-top: 14mm;">
    <tr>
        <td style="width: 55%;"></td>
        <td style="width: 45%;">
            দায়িত্বপ্রাপ্ত কর্মকর্তার নাম: {{ $signer?->name ?? '' }}<br>
            পদবি: {{ $signerTitle }}<br><br>
            স্বাক্ষর: ______________________<br>
            সিল:
        </td>
    </tr>
</table>

<p class="small muted" style="margin-top: 6mm;"><sup>১</sup> মূসক সহ মোট মূল্য।</p>
@endsection
