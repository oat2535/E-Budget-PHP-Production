
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <title>Login - E-Budget</title>
        <!-- Preload LCP Logo image FIRST with high fetch priority for instant paint -->
        <link rel="preload" as="image" href="{{ asset('static/budget_app/assets/img/logo.png') }}" fetchpriority="high">
        <!-- Preload branded font weights used on login page -->
        <link rel="preload" as="font" type="font/otf" href="{{ asset('static/budget_app/assets/fonts/metropolis/Metropolis-Regular.otf') }}" crossorigin>
        <link rel="preload" as="font" type="font/otf" href="{{ asset('static/budget_app/assets/fonts/metropolis/Metropolis-Bold.otf') }}" crossorigin>
        <link href="{{ asset('static/budget_app/css/styles.css') }}?v=20261120_03" rel="stylesheet" />
        <link rel="icon" type="image/jpeg" href="{{ asset('static/budget_app/assets/img/coins.jpg') }}" />
        <style>
            body {
                background-color: #f4f6f9; /* Light grey background */
                /* Subtle dot pattern background */
                background-image: radial-gradient(#d1d8e0 1.5px, transparent 1.5px);
                background-size: 24px 24px;
                margin: 0;
                padding: 0;
            }

            /* White Navbar */
            .top-navbar {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 85px; /* Taller navbar */
                background-color: white;
                z-index: 10;
                display: flex;
                align-items: center;
                padding-left: 2rem;
                box-shadow: 0 0.15rem 1.75rem 0 rgba(33, 40, 50, 0.15);
            }
            
            .sidebar-bg {
                position: fixed;
                top: 85px; /* Start below navbar */
                left: 0;
                width: 12%;
                height: calc(100vh - 85px);
                z-index: -1;
            }
            
            .login-card-wrapper {
                min-height: 100vh;
                padding-top: 85px; /* Offset for navbar */
                margin-left: 12%; /* Offset for sidebar to perfectly center in remaining space */
                width: 88%;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .login-card {
                width: 100%;
                max-width: 1000px; /* Wider card */
                border: none;
                border-radius: 0.75rem; 
                box-shadow: 0 1rem 4rem rgba(0, 0, 0, 0.15);
                z-index: 1;
            }
            
            .login-input {
                background-color: #e8f0fe !important;
                border: 2px solid transparent !important;
                border-radius: 50rem !important;
                padding: 1rem 1.5rem; /* Taller inputs */
                font-size: 1rem;
                transition: all 0.3s ease;
            }
            
            .login-input:focus {
                background-color: #ffffff !important;
                border-color: #b3d7ff !important;
                box-shadow: 0 0 0 0.25rem rgba(0, 118, 200, 0.15) !important;
                transform: translateY(-2px); /* Slight lift on focus */
            }
            
            .login-btn {
                border-radius: 50rem !important;
                padding: 0.9rem; /* Taller button */
                font-weight: 600;
                font-size: 1.1rem;
                background-color: #0076c8; /* Match standard blue */
                border: none;
                letter-spacing: 0.5px;
                transition: all 0.3s ease;
            }
            
            .login-btn:hover {
                background-color: #005a9c;
                transform: translateY(-2px); /* Lift button on hover */
                box-shadow: 0 0.5rem 1rem rgba(0, 118, 200, 0.3);
            }
            
            .login-btn:active {
                transform: translateY(0);
            }

            @media (max-width: 767.98px) {
                .sidebar-bg {
                    display: none;
                }
                .login-card-wrapper {
                    margin-left: 0 !important;
                    width: 100% !important;
                    padding-top: 100px !important;
                    padding-bottom: 2rem !important;
                }
                .login-card .border-start {
                    border-left: 0 !important;
                    border-top: 1px solid #e3e6ec !important;
                }
            }
        </style>
    </head>
    <body>
        <!-- Top Navbar -->
        <div class="top-navbar">
            <img src="{{ asset('static/budget_app/assets/img/logo.png') }}" alt="Logo" width="180" height="55" decoding="async" style="height: 55px; width: auto;">
        </div>
        
        <!-- The default SB Admin Pro gradient class for the sidebar -->
        <div class="sidebar-bg bg-primary bg-gradient-primary-to-secondary"></div>

        <div class="login-card-wrapper px-4">
            <div class="card login-card overflow-hidden">
                <div class="row g-0">
                    <!-- Left Side (Big Logo - LCP element) -->
                    <div class="col-md-6 d-flex align-items-center justify-content-center p-4 bg-white">
                        <img src="{{ asset('static/budget_app/assets/img/logo.png') }}" alt="Logo" class="img-fluid" width="450" height="137" fetchpriority="high" decoding="sync" loading="eager" style="max-width: 95%; height: auto;">
                    </div>
                    
                    <!-- Right Side (Form) -->
                    <div class="col-md-6 d-flex align-items-center justify-content-center p-5 bg-white border-start">
                        <div class="w-100" style="max-width: 360px;">
                            <h2 class="text-center mb-4 fw-bolder" style="color: #2a3547 !important; letter-spacing: 0.5px;">E-Budget</h2>
                            
                            @php
                                $warningMsg = session('warning') ?? ($warning ?? null);
                                $errorMsg = session('error') ?? ($error ?? null) ?? (isset($errors) && $errors->any() ? $errors->first() : null);
                            @endphp
                            @if(!empty($warningMsg))
                                <div class="alert alert-warning d-flex align-items-center mb-3 py-2 px-3" role="alert" style="border-radius: 12px; font-size: 13.5px; line-height: 1.5; border: 1px solid #fed7aa; background-color: #fff7ed; color: #9a3412;">
                                    <i class="fas fa-exclamation-circle me-2 fs-5 text-warning flex-shrink-0"></i>
                                    <div>{{ $warningMsg }}</div>
                                </div>
                            @elseif(!empty($errorMsg))
                                <div class="alert alert-danger d-flex align-items-center mb-3 py-2 px-3" role="alert" style="border-radius: 12px; font-size: 13.5px; line-height: 1.5; border: 1px solid #fecaca; background-color: #fef2f2; color: #991b1b;">
                                    <i class="fas fa-times-circle me-2 fs-5 text-danger flex-shrink-0"></i>
                                    <div>{{ $errorMsg }}</div>
                                </div>
                            @endif

                            <form method="POST" action="{{ route('login') }}">
                                @csrf
                                @if(request()->filled('next'))
                                    <input type="hidden" name="next" value="{{ request()->query('next') }}" />
                                @endif
                                
                                <div class="mb-3">
                                    <input class="form-control login-input" name="username" type="text" placeholder="Employee ID" value="{{ old('username') }}" required autocomplete="off" />
                                </div>
                                <div class="mb-4">
                                    <input class="form-control login-input" name="password" type="password" placeholder="••••" required />
                                </div>
                                <div class="d-grid mt-4">
                                    <button class="btn btn-primary login-btn" type="submit">Login</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @php
            $hasAlert = !empty($warningMsg) || !empty($errorMsg);
            $alertType = !empty($warningMsg) ? 'warning' : 'error';
            $alertTitle = !empty($warningMsg) ? 'แจ้งเตือน: เซสชันหมดอายุ' : 'เข้าสู่ระบบไม่สำเร็จ';
            $alertMsg = $warningMsg ?? $errorMsg ?? '';
        @endphp
        @if($hasAlert)
            <!-- SweetAlert2 loaded on-demand only when alerts are present -->
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
            <script>
                (function() {
                    function triggerAlert() {
                        if (typeof Swal === 'function' && Swal.fire) {
                            Swal.fire({
                                icon: '{{ $alertType }}',
                                title: '{{ $alertTitle }}',
                                html: {!! json_encode($alertMsg, JSON_UNESCAPED_UNICODE) !!},
                                confirmButtonColor: '#0076c8',
                                confirmButtonText: 'ตกลง',
                                width: 520
                            });
                        }
                    }
                    if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', triggerAlert);
                    } else {
                        triggerAlert();
                    }
                })();
            </script>
        @endif
    </body>
</html>
