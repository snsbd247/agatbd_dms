{{-- Signature lines. Nested tables because mPDF does not draw borders on inline spans. --}}
<table class="plain" style="margin-top: 16mm;">
    <tr>
        @foreach($labels as $label)
            <td style="width: {{ 100 / count($labels) }}%; text-align: center;">
                <table align="center" style="width: 70%;">
                    <tr><td style="border-top: 1px solid #444; padding-top: 1mm; text-align: center; font-size: 9.5px;">{{ $label }}</td></tr>
                </table>
            </td>
        @endforeach
    </tr>
</table>
