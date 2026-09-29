<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; color: #333; line-height: 1.6; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #4f46e5; color: white; padding: 20px; border-radius: 8px 8px 0 0; }
        .content { background: #f9fafb; padding: 20px; border: 1px solid #e5e7eb; }
        .footer { text-align: center; color: #6b7280; font-size: 12px; padding: 10px; }
        .badge { display: inline-block; background: #dbeafe; color: #1d4ed8; padding: 4px 12px; border-radius: 9999px; font-weight: bold; }
        .approved { background: #d1fae5; color: #065f46; }
        .rejected { background: #fee2e2; color: #991b1b; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #e0e7ff; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h2 style="margin:0">Borrowing Request {{ $statusLabel }}</h2>
        <p style="margin:4px 0 0">PHINMA COC Laboratory Equipment System</p>
    </div>
    <div class="content">
        <p>Dear {{ $borrowing->user->name }},</p>
        <p>{{ $statusMessage }}</p>

        <p>
            <span class="badge">{{ $borrowing->claim_code }}</span>
            &nbsp;
            <span class="badge {{ str_contains(strtolower($statusLabel), 'approved') ? 'approved' : 'rejected' }}">
                {{ $statusLabel }}
            </span>
        </p>

        <table>
            <tr><th>Subject</th><td>{{ $borrowing->subject }}</td></tr>
            <tr><th>Purpose</th><td>{{ $borrowing->purpose }}</td></tr>
            <tr><th>Borrow Date</th><td>{{ $borrowing->borrow_date->format('F d, Y') }}</td></tr>
            <tr><th>Return Date</th><td>{{ $borrowing->return_date->format('F d, Y') }}</td></tr>
        </table>

        <p style="margin-top:16px">Log in to the system to view details.</p>
    </div>
    <div class="footer">
        <p>PHINMA COC Laboratory Equipment Borrowing System &mdash; This is an automated message.</p>
    </div>
</div>
</body>
</html>
