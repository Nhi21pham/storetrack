<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Store Invitation</title>
  <style>
    body { margin: 0; padding: 0; background: #f3f4f6; font-family: 'Segoe UI', Arial, sans-serif; color: #111; }
    .wrapper { max-width: 560px; margin: 40px auto; background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.07); }
    .header { background: #111; padding: 32px 40px; text-align: center; }
    .header .logo-text { color: #fff; font-size: 22px; font-weight: 700; letter-spacing: -0.5px; }
    .body { padding: 40px; }
    .body h1 { font-size: 22px; font-weight: 700; margin: 0 0 12px; }
    .body p { font-size: 15px; color: #374151; line-height: 1.6; margin: 0 0 16px; }
    .role-badge { display: inline-block; background: #f3f4f6; color: #374151; font-size: 13px; font-weight: 600; padding: 4px 12px; border-radius: 6px; text-transform: capitalize; }
    .btn-wrap { text-align: center; margin: 32px 0; }
    .btn { display: inline-block; background: #111; color: #fff; padding: 14px 36px; border-radius: 10px; font-size: 15px; font-weight: 600; text-decoration: none; }
    .footer { padding: 24px 40px; border-top: 1px solid #f3f4f6; }
    .footer p { font-size: 13px; color: #9ca3af; margin: 0; line-height: 1.6; }
    .footer a { color: #6b7280; word-break: break-all; }
  </style>
</head>
<body>
  <div class="wrapper">
    <div class="header">
      <div class="logo-text">storetrack</div>
    </div>
    <div class="body">
      <h1>You've been invited!</h1>
      <p>
        <strong>{{ $inviterName }}</strong> has invited <strong>{{ $inviteeEmail }}</strong> to join
        <strong>{{ $storeName }}</strong> as a
        <span class="role-badge">{{ ucfirst($role) }}</span>.
      </p>
      <p>Click the button below to accept or decline this invitation. The invitation expires in 7 days.</p>
      <div class="btn-wrap">
        <a class="btn" href="{{ config('app.frontend_url') }}/invite/{{ $token }}">View Invitation</a>
      </div>
    </div>
    <div class="footer">
      <p>If the button doesn't work, copy and paste this link into your browser:</p>
      <p><a href="{{ config('app.frontend_url') }}/invite/{{ $token }}">{{ config('app.frontend_url') }}/invite/{{ $token }}</a></p>
      <p style="margin-top:12px;">If you didn't expect this invitation, you can safely ignore this email.</p>
    </div>
  </div>
</body>
</html>
