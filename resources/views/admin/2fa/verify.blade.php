<!DOCTYPE html>
<html>
<head>
    <title>Verify 2FA</title>
</head>

<body>

<h2>Enter Authentication Code</h2>

<form method="POST" action="{{ route('admin.2fa.verify.post') }}">

    @csrf

    <input type="text" 
           name="code" 
           placeholder="6 digit code"
           required>

    <button type="submit">
        Verify
    </button>

</form>


@if($errors->any())
    <p style="color:red">
        {{ $errors->first() }}
    </p>
@endif


</body>
</html>