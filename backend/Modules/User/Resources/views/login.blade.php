<!DOCTYPE html>
<html lang="fa">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود / ثبت نام</title>
    <link href="/css/bootstrap.rtl.min.css" rel="stylesheet">
    <style>
        body,
        html {
            height: 100%;
            margin: 0;
        }

        .video-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
        }

        .video-container video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: #0066fb26;
        }

        .auth-container {
            position: fixed;
            top: 0;
            right: 0;
            width: 30%;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            flex-wrap: nowrap;
            background-color: #f8f9fa;
            box-shadow: -5px 0px 20px 20px #f8f9fa;
            opacity: .9;
        }

        .form-toggle {
            margin-bottom: 1rem;
            text-align: center;
            position: absolute;
            bottom: 0;
            width: 100%;
        }

        .form-toggle button {
            margin: 0 0.2rem;
        }
    </style>
</head>

<body>

    <div class="video-container">
        <video autoplay muted loop>
            <source src="/src/istockphoto-1828184731-640_adpp_is.mp4" type="video/mp4">
            مرورگر شما ویدیو را پشتیبانی نمی‌کند.
        </video>
        <div class="overlay"></div>
    </div>

    <div class="auth-container" dir="rtl">
        <div class="auth-box ps-3">


            <!-- Login Form -->
            <form id="login-form" action="{{ route('dashboard.login') }}" method="POST">
                @csrf
                <div class="mb-3">
                    <label for="mobile" class="form-label">شماره موبایل</label>
                    <input type="text" class="form-control" id="mobile" name="mobile" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">رمز عبور</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="remember" name="remember">
                    <label class="form-check-label" for="remember">مرا به خاطر بسپار</label>
                </div>
                <button type="submit" class="btn btn-primary w-100">ورود</button>
            </form>

            <!-- Register Form -->
            <form id="register-form" action="{{ route('dashboard.register') }}" method="POST" style="display:none;">
                @csrf
                <div class="mb-3">
                    <label for="name" class="form-label">نام</label>
                    <input type="text" class="form-control" id="name" name="name" required>
                </div>
                <div class="mb-3">
                    <label for="mobile-register" class="form-label">شماره موبایل</label>
                    <input type="text" class="form-control" id="mobile-register" name="mobile" required>
                </div>
                <div class="mb-3">
                    <label for="password-register" class="form-label">رمز عبور</label>
                    <input type="password" class="form-control" id="password-register" name="password" required>
                </div>
                <div class="mb-3">
                    <label for="password-confirm" class="form-label">تکرار رمز عبور</label>
                    <input type="password" class="form-control" id="password-confirm" name="password_confirmation" required>
                </div>
                <button type="submit" class="btn btn-success w-100">ثبت نام</button>
            </form>

            <!-- Forgot Password Form -->
            <form id="forgot-form" action="{{ route('dashboard.forgotPassword') }}" method="POST" style="display:none;">
                @csrf
                <div class="mb-3">
                    <label for="mobile-forgot" class="form-label">شماره موبایل</label>
                    <input type="text" class="form-control" id="mobile-forgot" name="mobile" required>
                </div>
                <button type="submit" class="btn btn-warning w-100">ارسال لینک بازیابی</button>
            </form>



            <div class="form-toggle">
                <button class="btn btn-outline-primary btn-sm" onclick="showForm('login')">ورود</button>
                <button class="btn btn-outline-success btn-sm" onclick="showForm('register')">ثبت نام</button>
                <button class="btn btn-outline-warning btn-sm" onclick="showForm('forgot')">فراموشی رمز</button>
            </div>
        </div>
    </div>

    <script>
        function showForm(form) {
            document.getElementById('login-form').style.display = 'none';
            document.getElementById('register-form').style.display = 'none';
            document.getElementById('forgot-form').style.display = 'none';

            if (form === 'login') document.getElementById('login-form').style.display = 'block';
            if (form === 'register') document.getElementById('register-form').style.display = 'block';
            if (form === 'forgot') document.getElementById('forgot-form').style.display = 'block';
        }
    </script>

</body>

</html>