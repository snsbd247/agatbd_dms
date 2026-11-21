{{-- Top of every PDF document: company block on the left, title and meta lines on the right. --}}
<table class="plain head">
    <tr>
        <td style="width: 60%;">@include('pdfs.partials.company')</td>
        <td style="width: 40%; text-align: right;">
            <div class="doc-title">{{ $title }}</div>
            @foreach(($meta ?? []) as $line)
                <div class="doc-meta">{{ $line }}</div>
            @endforeach
        </td>
    </tr>
</table>
