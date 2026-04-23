<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Invitation {{ $accepted ? 'Accepted' : 'Declined' }}</title>
  <style>
    body { margin: 0; padding: 0; background: #f3f4f6; font-family: 'Segoe UI', Arial, sans-serif; color: #111; }
    .wrapper { max-width: 560px; margin: 40px auto; background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.07); }
    .header { background: #111; padding: 32px 40px; text-align: center; }
    .header .logo-text { color: #fff; font-size: 22px; font-weight: 700; letter-spacing: -0.5px; }
    .body { padding: 40px; }
    .status-icon { width: 56px; height: 56px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; }
    .body h1 { font-size: 20px; font-weight: 700; margin: 0 0 12px; text-align: center; }
    .body p { font-size: 15px; color: #374151; line-height: 1.6; margin: 0 0 16px; text-align: center; }
    .footer { padding: 24px 40px; border-top: 1px solid #f3f4f6; }
    .footer p { font-size: 13px; color: #9ca3af; margin: 0; text-align: center; }
  </style>
</head>
<body>
  <div class="wrapper">
    <div class="header">
      <div class="logo-text">storetrack</div>
    </div>
    <div class="body">
      @if($accepted)
        <h1>Invitation Accepted</h1>
        <p>
          <strong>{{ $inviteeEmail }}</strong> has accepted your invitation to join
          <strong>{{ $storeName }}</strong>. They now have access to the store.
        </p>
      @else
        <h1>Invitation Declined</h1>
        <p>
          <strong>{{ $inviteeEmail }}</strong> has declined your invitation to join
          <strong>{{ $storeName }}</strong>.
        </p>
      @endif
    </div>
    <div class="footer">
      <p>You can manage your store members in storetrack.</p>
    </div>
  </div>
</body>
</html>
