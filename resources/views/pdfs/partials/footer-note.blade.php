@if(filled($company?->invoice_footer))
    <p style="white-space: pre-line; margin-bottom: 2mm; color: #333;">{{ $company->invoice_footer }}</p>
@endif
