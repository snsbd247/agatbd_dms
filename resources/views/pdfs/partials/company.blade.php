{{-- Company block for invoice PDFs: logo, name and contact line from Settings → Company Profile. --}}
@php $logo = $company?->logoDataUri(); @endphp
<table style="border: none; margin: 0; width: auto;">
    <tr>
        @if($logo)
            <td style="border: none; padding: 0 4mm 0 0; vertical-align: middle;">
                <img src="{{ $logo }}" alt="" style="max-height: 18mm; max-width: 40mm;">
            </td>
        @endif
        <td style="border: none; padding: 0; vertical-align: middle;">
            <h1 style="margin: 0;">{{ $company?->name ?? config('app.name', 'DMS') }}</h1>
            @php $contact = collect([$company?->address, $company?->phone, $company?->email])->filter()->implode(' · '); @endphp
            @if($contact)
                <p style="margin: 1mm 0 0; font-size: 10px; color: #555;">{{ $contact }}</p>
            @endif
        </td>
    </tr>
</table>
