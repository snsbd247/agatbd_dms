{{--
    Mushak 4.3: input-output coefficient declaration (উপকরণ-উৎপাদ সহগ ঘোষণা),
    rule 21 of the VAT and SD Rules 2016, filed for goods made in the
    factory (formula or kit). For bought or imported goods the same layout
    is printed as a plain price-setting sheet. Always in Bangla.
--}}
@extends('pdfs.layout')

@section('docTitle', ($mushak ? 'মূসক-৪.৩' : 'মূল্য নির্ধারণ শিট').' - '.$sheet->product->name)

@section('body')
@php
    $n = fn ($v, $d = 2) => number_format((float) $v, $d);
    $q = fn ($v) => rtrim(rtrim(number_format((float) $v, 4), '0'), '.') ?: '0';
    $materials = $sheet->items->where('kind', 'material')->values();
    $overheads = $sheet->items->where('kind', 'overhead')->values();
    $profit = round((float) $sheet->dealer_price - (float) $sheet->total_cost, 2);
    $value = $overheads->map(fn ($o) => [$o->description, (float) $o->amount])->push(['মুনাফা', $profit]);
    $rows = max($materials->count(), $value->count(), 1);
@endphp

<table class="plain" style="margin-bottom: 3mm;">
    <tr>
        <td style="width: 25%;"></td>
        <td style="width: 50%; text-align: center;">
            @if($mushak)
                <div>গণপ্রজাতন্ত্রী বাংলাদেশ সরকার</div>
                <div>জাতীয় রাজস্ব বোর্ড</div>
                <div style="font-size: 15px; font-weight: bold; margin-top: 1mm;">উপকরণ-উৎপাদ সহগ (Input-Output Coefficient) ঘোষণা</div>
                <div class="small">[বিধি ২১ দ্রষ্টব্য]</div>
            @else
                <div style="font-size: 15px; font-weight: bold;">মূল্য নির্ধারণ শিট (কস্ট শিট)</div>
                <div class="small">ক্রয়/আমদানি মূল্য ও খরচের ভিত্তিতে</div>
            @endif
        </td>
        <td style="width: 25%; text-align: right; vertical-align: top;">
            @if($mushak)<span style="border: 1px solid #222; padding: 1mm 3mm; font-weight: bold;">মূসক-৪.৩</span>@endif
        </td>
    </tr>
</table>

<table class="plain" style="margin-bottom: 4mm;">
    <tr>
        <td style="width: 60%;">
            প্রতিষ্ঠানের নাম: <strong>{{ $company?->name ?? config('app.name') }}</strong><br>
            ঠিকানা: {{ $company?->address ?: '—' }}<br>
            বিআইএন: <strong>{{ $bin ?: '—' }}</strong>
        </td>
        <td style="width: 40%;">
            দাখিলের তারিখ: {{ ($sheet->approved_at ?? $sheet->created_at)->format('d/m/Y') }}<br>
            ঘোষিত সহগ অনুযায়ী প্রথম সরবরাহের তারিখ: {{ ($sheet->first_supply_date ?? $sheet->effective_from)->format('d/m/Y') }}<br>
            শিট নং: {{ $sheet->number }} (ভার্সন {{ $sheet->version }})
        </td>
    </tr>
</table>

<table class="lines">
    <thead>
        <tr>
            <th rowspan="2" class="c">ক্রমিক নং</th>
            <th rowspan="2">পণ্যের এইচএস কোড / সেবা কোড</th>
            <th rowspan="2">সরবরাহতব্য পণ্যের নাম ও বর্ণনা (ব্র্যান্ড নামসহ)</th>
            <th rowspan="2" class="c">সরবরাহের একক</th>
            <th colspan="3" class="c">একক পণ্য সরবরাহে ব্যবহার্য উপকরণ/কাঁচামাল ও প্যাকিং সামগ্রী</th>
            <th colspan="2" class="c">অপচয়</th>
            <th colspan="2" class="c">মূল্য সংযোজনের বিবরণ</th>
            <th rowspan="2">মন্তব্য</th>
        </tr>
        <tr>
            <th>বিবরণ</th>
            <th class="r">অপচয়সহ পরিমাণ</th>
            <th class="r">মূল্য (টাকা)</th>
            <th class="r">পরিমাণ</th>
            <th class="r">শতকরা হার</th>
            <th>খাত</th>
            <th class="r">মূল্য (টাকা)</th>
        </tr>
        <tr>@foreach(range(1, 12) as $c)<th class="c small">({{ $c }})</th>@endforeach</tr>
    </thead>
    <tbody>
        @for($i = 0; $i < $rows; $i++)
            @php $m = $materials[$i] ?? null; $v = $value[$i] ?? null; @endphp
            <tr>
                @if($i === 0)
                    <td rowspan="{{ $rows }}" class="c">1</td>
                    <td rowspan="{{ $rows }}">{{ $sheet->product->hs_code ?: '—' }}</td>
                    <td rowspan="{{ $rows }}"><strong>{{ $sheet->product->name }}</strong>@if($sheet->product->brand)<br>({{ $sheet->product->brand->name }})@endif</td>
                    <td rowspan="{{ $rows }}" class="c">1 {{ $sheet->product->unit?->short_code }}</td>
                @endif
                <td>{{ $m?->description }}</td>
                <td class="r">@if($m){{ $q($m->quantity) }} {{ $m->product?->unit?->short_code }}@endif</td>
                <td class="r">@if($m){{ $n($m->amount) }}@endif</td>
                <td class="r">@if($m && (float) $m->wastage_percent > 0){{ $q((float) $m->quantity - (float) $m->quantity / (1 + (float) $m->wastage_percent / 100)) }}@endif</td>
                <td class="r">@if($m && (float) $m->wastage_percent > 0){{ $q($m->wastage_percent) }}%@endif</td>
                <td>{{ $v[0] ?? '' }}</td>
                <td class="r">@if($v){{ $n($v[1]) }}@endif</td>
                @if($i === 0)<td rowspan="{{ $rows }}" class="small">{{ $sheet->notes }}</td>@endif
            </tr>
        @endfor
    </tbody>
    <tfoot>
        <tr>
            <td colspan="6" class="r">মোট উপকরণ মূল্য</td>
            <td class="r">{{ $n($sheet->base_cost) }}</td>
            <td colspan="2"></td>
            <td class="r">মোট মূল্য সংযোজন</td>
            <td class="r">{{ $n((float) $sheet->overhead_cost + $profit) }}</td>
            <td></td>
        </tr>
    </tfoot>
</table>

<table class="plain" style="margin-top: 4mm;">
    <tr>
        <td style="width: 50%;">
            <table class="totals">
                <tr><td>মোট খরচ (প্রতি একক)</td><td class="r">{{ $n($sheet->total_cost) }}</td></tr>
                <tr class="grand"><td>ঘোষিত মূল্য — ডিলার মূল্য</td><td class="r">{{ $n($sheet->dealer_price) }}</td></tr>
                <tr><td>খুচরা বিক্রেতা মূল্য</td><td class="r">{{ $n($sheet->retailer_price) }}</td></tr>
                <tr><td>সর্বোচ্চ খুচরা মূল্য (MRP)</td><td class="r">{{ $n($sheet->mrp) }}</td></tr>
                @if((float) $sheet->vat_rate > 0)<tr><td colspan="2" class="small muted">মূল্যে {{ $q($sheet->vat_rate) }}% মূসক অন্তর্ভুক্ত</td></tr>@endif
            </table>
        </td>
        <td style="width: 50%; padding-left: 10mm; vertical-align: bottom;">
            প্রতিষ্ঠানের ক্ষমতাপ্রাপ্ত ব্যক্তির নাম: {{ $sheet->approver?->name ?? auth()->user()?->name }}<br>
            পদবি: {{ $signerTitle }}<br><br>
            স্বাক্ষর: ______________________<br>
            সিল:
        </td>
    </tr>
</table>
@endsection
