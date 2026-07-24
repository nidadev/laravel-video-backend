<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Two-Factor Authentication</title>

    <style>
        *{
            margin:0;
            padding:0;
            box-sizing:border-box;
            font-family:Arial, Helvetica, sans-serif;
        }

        body{
            background:#f4f6f9;
            display:flex;
            justify-content:center;
            align-items:center;
            min-height:100vh;
        }

        .card{
            background:#fff;
            width:400px;
            max-width:90%;
            padding:35px;
            border-radius:12px;
            box-shadow:0 10px 30px rgba(0,0,0,.08);
        }

        h2{
            text-align:center;
            margin-bottom:10px;
            color:#222;
        }

        p.subtitle{
            text-align:center;
            color:#666;
            font-size:14px;
            margin-bottom:25px;
        }

        input{
            width:100%;
            padding:14px;
            border:1px solid #ddd;
            border-radius:8px;
            font-size:16px;
            text-align:center;
            letter-spacing:3px;
            margin-bottom:20px;
        }

        input:focus{
            outline:none;
            border-color:#0d6efd;
            box-shadow:0 0 0 3px rgba(13,110,253,.15);
        }

        button{
            width:100%;
            padding:14px;
            border:none;
            border-radius:8px;
            background:#0d6efd;
            color:#fff;
            font-size:16px;
            cursor:pointer;
            transition:.2s;
        }

        button:hover{
            background:#0b5ed7;
        }

        .error{
            margin-top:15px;
            background:#fdeaea;
            color:#c62828;
            padding:12px;
            border-radius:8px;
            text-align:center;
        }
    </style>
</head>

<body>

<div class="card">

    <h2>Two-Factor Authentication</h2>

    <p class="subtitle">
        Enter the 6-digit code from your Google Authenticator app.
    </p>

    <form method="POST" action="{{ route('admin.2fa.verify.post') }}">

        @csrf

        <input
            type="text"
            name="code"
            placeholder="000000"
            maxlength="6"
            autocomplete="one-time-code"
            required>

        <button type="submit">
            Verify
        </button>

    </form>

    @if($errors->any())
        <div class="error">
            {{ $errors->first() }}
        </div>
    @endif

</div>

</body>
</html>
