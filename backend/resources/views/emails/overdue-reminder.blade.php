<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; color: #333; line-height: 1.6; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: {{ $isOverdue ? '#dc2626' : '#d97706' }}; color: white; padding: 20px; border-radius: 8px 8px 0 0; }
        .content { background: #f9fafb; padding: 20px; border: 1px solid #e5e7eb; }
        .footer { text-align: center; color: #6b7280; font-size: 12px; padding: 10px; }
        .badge { display: inline-block; background: #dbeafe; color: #1d4ed8; padding: 4px 12px; border-radius: 9999px; font-weight: bold; }
        .warn { background: #fef3c7; color: #92400e; }
        .overdue { background: #fee2e2; color: #991b1b; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #fef3c7; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h2 style="margin:0">{{ $isOverdue ? 'OVERDUE: Please Return Equipment Immediately' : 'Reminder: Equipment Due Tomorrow' }}</h2>
        <p style="margin:4px 0 0">PHINMA COC Laboratory Equipment System</p>
    </div>
    <div class="content">
        <p>Dear {{ $borrowing->user->name }},</p>

        @if($isOverdue)
            <p><strong>Your borrowed equipment is overdue.</strong> Please return it to the laboratory as soon as possible to avoid additional fines.</p>
        @else
            <p>This is a reminder that your borrowed equipment is due for return <strong>tomorrow, {{ $borrowing->return_date->format('F d, Y') }}</strong>.</p>
        @endif

        <p><span class="badge {{ $isOverdue ? 'overdue' : 'warn' }}">{{ $borrowing->claim_code }}</span></p>

        <table>
            <tr><th>Subject</th><td>{{ $borrowing->subject }}</td></tr>
            <tr><th>Return Date</th><td>{{ $borrowing->return_date->format('F d, Y') }}</td></tr>
            @if($isOverdue)
            <tr><th>Days Overdue</th><td style="color:#dc2626;font-weight:bold">{{ \Carbon\Carbon::today()->diffInDays($borrowing->return_date) }} day(s)</td></tr>
            @endif
        </table>

        <p style="margin-top:16px">Please coordinate with your laboratory staff for the return process.</p>
    </div>
    <div class="footer">
        <p>PHINMA COC Laboratory Equipment Borrowing System &mdash; This is an automated message.</p>
    </div>
</div>
</body>
</html>
