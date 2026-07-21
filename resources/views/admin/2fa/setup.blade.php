<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enable Google Authenticator</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body{
            background:#f4f6f9;
        }

        .card{
            border:none;
            border-radius:18px;
            box-shadow:0 10px 30px rgba(0,0,0,.08);
        }

        .qr-box{
            background:#fff;
            padding:20px;
            border:1px solid #e9ecef;
            border-radius:15px;
            display:inline-block;
        }

        .secret-box{
            background:#eef4ff;
            border:1px dashed #0d6efd;
            padding:15px;
            border-radius:10px;
            font-size:20px;
            font-weight:600;
            letter-spacing:2px;
            word-break:break-all;
        }

        .btn-primary{
            border-radius:10px;
            padding:10px;
        }

        input.form-control{
            height:50px;
            font-size:18px;
            text-align:center;
            letter-spacing:3px;
        }
    </style>
</head>

<body>

<div class="container py-5">

    <div class="row justify-content-center">

        <div class="col-lg-6">

            <div class="card">

                <div class="card-body p-5">

                    <div class="text-center mb-4">

                        <h2 class="fw-bold">
                            🔐 Enable Google Authenticator
                        </h2>

                        <p class="text-muted mb-0">
                            Protect your admin account with Two-Factor Authentication.
                        </p>

                    </div>

                    @if(session('error'))
                        <div class="alert alert-danger">
                            {{ session('error') }}
                        </div>
                    @endif

                    @if($errors->any())
                        <div class="alert alert-danger">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <div class="text-center mb-4">

                        <h5 class="mb-3">
                            Scan this QR Code
                        </h5>

                        <div class="qr-box">
                            {!! QrCode::size(220)->generate($qrCodeUrl) !!}
                        </div>

                    </div>

                    <hr>

                    <div class="mb-4">

                        <label class="form-label fw-semibold">
                            Or enter this secret manually
                        </label>

                        <div class="secret-box" id="secret">
                            {{ $secret }}
                        </div>

                        <button
                            type="button"
                            class="btn btn-outline-primary btn-sm mt-2"
                            onclick="copySecret()">
                            📋 Copy Secret
                        </button>

                    </div>

                    <form method="POST" action="{{ route('admin.2fa.enable') }}">

                        @csrf

                        <div class="mb-4">

                            <label class="form-label fw-semibold">
                                Enter the 6-digit code from Google Authenticator
                            </label>

                            <input
                                type="text"
                                name="code"
                                class="form-control"
                                maxlength="6"
                                placeholder="123456"
                                required>

                        </div>

                        <button
                            class="btn btn-primary w-100">
                            ✅ Enable Two-Factor Authentication
                        </button>

                    </form>

                </div>

            </div>

        </div>

    </div>

</div>

<script>
function copySecret() {
    navigator.clipboard.writeText(document.getElementById('secret').innerText);
    alert('Secret copied successfully.');
}
</script>

</body>
</html>