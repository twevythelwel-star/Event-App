@if($rows === [])
    <div class="ac-card-body ac-empty">Nothing expires in the next {{ \App\Http\Controllers\Admin\ComplianceCentreController::EXPIRY_WINDOW_DAYS }} days.</div>
@else
    <div class="ac-table-wrap"><table class="ac-table">
        <thead><tr><th>Document</th><th>For</th><th>Expires</th><th>Status</th></tr></thead>
        <tbody>@foreach($rows as $row)
            <tr>
                <td><a class="ac-link" href="{{ $row['url'] }}">{{ $row['title'] }}</a><div class="ac-hint">{{ $row['kind'] }}</div></td>
                <td>{{ $row['subject'] }}</td>
                <td>{{ $row['date']->format('j M Y') }}</td>
                <td><span class="ac-badge {{ $row['days'] < 0 ? 'danger' : ($row['days'] <= 14 ? 'warning' : 'info') }}">{{ $row['days'] < 0 ? 'Expired ' . abs($row['days']) . ' days ago' : ($row['days'] === 0 ? 'Expires today' : 'In ' . $row['days'] . ' days') }}</span></td>
            </tr>
        @endforeach</tbody>
    </table></div>
@endif
