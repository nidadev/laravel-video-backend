<h2>Enable Google Authenticator</h2>


<p>Scan this QR code:</p>


{!! QrCode::size(250)->generate($qrCodeUrl) !!}


<p>
Or enter this secret manually:
</p>


<h3>{{ $secret }}</h3>


<form method="POST" action="{{ route('admin.2fa.enable') }}">

@csrf


<label>
Enter OTP Code:
</label>


<input 
type="text" 
name="code"
required
>


<button type="submit">
Enable 2FA
</button>


</form>