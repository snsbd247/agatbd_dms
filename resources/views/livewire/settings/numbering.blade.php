<div>
    <p class="text-slate-500 text-sm mb-4">Document numbers for {{ $year }}. Read-only — numbers are issued automatically and should never be edited by hand to avoid duplicates.</p>

    <div class="bg-white rounded-xl shadow overflow-hidden">
        <table class="w-full text-sm rtable">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-3">{{ __('Document Type') }}</th>
                    <th class="px-4 py-3">{{ __('Prefix') }}</th>
                    <th class="px-4 py-3">{{ __('Last Number Issued') }}</th>
                    <th class="px-4 py-3">{{ __('Next Number') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @foreach($rows as $row)
                    <tr wire:key="seq-{{ $row['key'] }}">
                        <td class="px-4 py-3 font-medium" data-label="{{ __('Document Type') }}">{{ __(ucwords(str_replace('_', ' ', $row['key']))) }}</td>
                        <td class="px-4 py-3 text-slate-500" data-label="{{ __('Prefix') }}">{{ $row['prefix'] }}</td>
                        <td class="px-4 py-3" data-label="{{ __('Last Number Issued') }}">{{ $row['last_number'] }}</td>
                        <td class="px-4 py-3 text-indigo-600 font-mono text-xs" data-label="{{ __('Next Number') }}">{{ $row['next_number'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
