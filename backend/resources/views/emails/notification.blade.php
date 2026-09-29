<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 520px; margin: 40px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .header { background: linear-gradient(135deg, #0a3d1f, #1e8449); padding: 28px 24px; text-align: center; }
        .header h1 { color: #ffffff; margin: 0; font-size: 18px; font-weight: bold; }
        .header p { color: #a8e6cf; margin: 4px 0 0; font-size: 12px; }
        .body { padding: 32px 28px; }
        .greeting { font-size: 15px; color: #333; margin-bottom: 16px; }
        .message-box { background: #f0faf4; border-left: 4px solid #1e8449; border-radius: 6px; padding: 16px 20px; font-size: 14px; color: #2d6a4f; line-height: 1.6; margin-bottom: 24px; }
        .note { font-size: 12px; color: #999; margin-top: 8px; }
        .footer { background: #f9f9f9; padding: 16px 24px; text-align: center; font-size: 11px; color: #aaa; border-top: 1px solid #eee; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Science Laboratory Borrowing System</h1>
            <p>Cagayan de Oro College — PHINMA Education Network</p>
        </div>
        <div class="body">
            <p class="greeting">Hello, <strong>{{ $user->name }}</strong>!</p>
            <div class="message-box">
                {{ $body }}
            </div>
            <p class="note">This is an automated notification from the Science Laboratory Borrowing System. Please do not reply to this email.</p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Cagayan de Oro College — PHINMA Education Network
        </div>
    </div>
</body>
</html>
