<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Audit Trail</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1e293b; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        p { color: #64748b; margin-top: 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #cbd5e1; padding: 5px; text-align: left; vertical-align: top; }
        th { background: #f1f5f9; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
    </style>
</head>
<body>
    <h1>Audit Trail</h1>
    <p>Generated {{ now()->format('M d, Y h:i A') }}</p>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Actor</th>
                <th>Role</th>
                <th>Action</th>
                <th>Type</th>
                <th>Details</th>
                <th>Date/Time</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td>{{ $log->id }}</td>
                    <td>{{ $log->actor }}</td>
                    <td>{{ $log->role }}</td>
                    <td>{{ $log->action }}</td>
                    <td>{{ $log->type }}</td>
                    <td>{{ $log->information }}</td>
                    <td>{{ $log->datetime?->format('M d, Y h:i A') }}</td>
                </tr>
            @empty
                <tr><td colspan="7">No audit entries found</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
