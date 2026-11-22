<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>E-Budget Diagnostics | PHP & Laravel System Info</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('static/budget_app/img/favicon.png') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            background-color: #f4f6f9;
            font-family: 'Metropolis', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: #2c3e50;
        }
        .diag-card {
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
            margin-bottom: 24px;
            overflow: hidden;
        }
        .diag-header {
            background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
            color: #ffffff;
            padding: 24px 30px;
            border-radius: 12px 12px 0 0;
        }
        .card-inner-header {
            background-color: #f8fafc;
            border-bottom: 1px solid #edf2f7;
            padding: 14px 20px;
            font-weight: 600;
            font-size: 0.95rem;
        }
        .badge-version {
            font-size: 1.1rem;
            padding: 8px 14px;
            border-radius: 8px;
            font-weight: 700;
        }
        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 6px;
        }
        .status-dot.active {
            background-color: #10b981;
            box-shadow: 0 0 8px #10b981;
        }
        .status-dot.inactive {
            background-color: #ef4444;
            box-shadow: 0 0 8px #ef4444;
        }
        .prop-label {
            color: #64748b;
            font-size: 0.88rem;
        }
        .prop-val {
            font-weight: 600;
            font-size: 0.95rem;
        }
    </style>
</head>
<body class="py-4">
<div class="container" style="max-width: 900px;">
    <!-- Main Header Card -->
    <div class="diag-card mb-4">
        <div class="diag-header d-flex flex-wrap justify-content-between align-items-center">
            <div>
                <h3 class="mb-1 fw-bold"><i class="fa-solid fa-stethoscope me-2"></i>E-Budget System Diagnostics</h3>
                <p class="mb-0 text-white-50 small">การตรวจสอบสถานะและเวอร์ชันระบบแบบ Real-time</p>
            </div>
            <div class="mt-2 mt-md-0 d-flex gap-2">
                <a href="{{ url('/dianosis?format=json') }}" target="_blank" class="btn btn-sm btn-light fw-semibold text-primary">
                    <i class="fa-solid fa-code me-1"></i> JSON API
                </a>
                <a href="{{ url('/') }}" class="btn btn-sm btn-outline-light">
                    <i class="fa-solid fa-arrow-left me-1"></i> เข้าสู่ระบบ
                </a>
            </div>
        </div>

        <div class="p-4">
            <div class="row g-3">
                <!-- PHP Version Box -->
                <div class="col-md-6">
                    <div class="p-3 rounded-3 border bg-light d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center">
                            <i class="fa-brands fa-php fa-2x text-primary me-3"></i>
                            <div>
                                <div class="prop-label">PHP Version</div>
                                <div class="fs-5 fw-bold text-dark">{{ $data['system']['php_version'] }}</div>
                            </div>
                        </div>
                        <span class="badge bg-primary badge-version">PHP 8.3</span>
                    </div>
                </div>

                <!-- Laravel Version Box -->
                <div class="col-md-6">
                    <div class="p-3 rounded-3 border bg-light d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center">
                            <i class="fa-brands fa-laravel fa-2x text-danger me-3"></i>
                            <div>
                                <div class="prop-label">Laravel Framework</div>
                                <div class="fs-5 fw-bold text-dark">{{ $data['system']['laravel_version'] }}</div>
                            </div>
                        </div>
                        <span class="badge bg-danger badge-version">Laravel 13</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <!-- System Environment & Performance -->
        <div class="col-md-6">
            <div class="diag-card h-100">
                <div class="card-inner-header d-flex align-items-center">
                    <i class="fa-solid fa-server text-secondary me-2"></i> สภาพแวดล้อมและประสิทธิภาพ (Runtime)
                </div>
                <div class="p-3">
                    <table class="table table-sm table-borderless mb-0">
                        <tbody>
                            <tr>
                                <td class="prop-label">Environment:</td>
                                <td class="text-end prop-val">
                                    <span class="badge {{ $data['system']['environment'] === 'production' ? 'bg-success' : 'bg-warning text-dark' }}">
                                        {{ strtoupper($data['system']['environment']) }}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td class="prop-label">Server SAPI / Engine:</td>
                                <td class="text-end prop-val font-monospace">{{ $data['system']['sapi'] }}</td>
                            </tr>
                            <tr>
                                <td class="prop-label">OPcache Status:</td>
                                <td class="text-end prop-val">
                                    @if($data['performance']['opcache_enabled'])
                                        <span class="text-success"><i class="fa-solid fa-circle-check"></i> Enabled (Active)</span>
                                    @else
                                        <span class="text-muted"><i class="fa-solid fa-circle-xmark"></i> Disabled</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td class="prop-label">Memory Usage:</td>
                                <td class="text-end prop-val">{{ $data['performance']['memory_usage_mb'] }} MB (Peak: {{ $data['performance']['memory_peak_mb'] }} MB)</td>
                            </tr>
                            <tr>
                                <td class="prop-label">Execution Time:</td>
                                <td class="text-end prop-val text-primary">{{ $data['performance']['execution_time_ms'] }} ms</td>
                            </tr>
                            <tr>
                                <td class="prop-label">Timezone:</td>
                                <td class="text-end prop-val">{{ $data['system']['timezone'] }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Database Connectivity -->
        <div class="col-md-6">
            <div class="diag-card h-100">
                <div class="card-inner-header d-flex align-items-center">
                    <i class="fa-solid fa-database text-secondary me-2"></i> การเชื่อมต่อฐานข้อมูล (PostgreSQL)
                </div>
                <div class="p-3">
                    <!-- Primary DB -->
                    <div class="p-3 mb-2 rounded border bg-light">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-bold">1. Primary Database (`pgsql`)</span>
                            @if($data['databases']['primary_db']['status'] === 'connected')
                                <span class="badge bg-success-subtle text-success border border-success-subtle">
                                    <span class="status-dot active"></span> Connected
                                </span>
                            @else
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                    <span class="status-dot inactive"></span> Disconnected
                                </span>
                            @endif
                        </div>
                        <div class="small text-muted">
                            DB: <span class="text-dark fw-semibold">{{ $data['databases']['primary_db']['database'] }}</span> | 
                            Schema: <span class="text-dark fw-semibold">{{ $data['databases']['primary_db']['schema'] }}</span>
                        </div>
                    </div>

                    <!-- iMedX DB -->
                    <div class="p-3 rounded border bg-light">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-bold">2. iMedX User DB (`pgsql_imedx`)</span>
                            @if($data['databases']['imedx_db']['status'] === 'connected')
                                <span class="badge bg-success-subtle text-success border border-success-subtle">
                                    <span class="status-dot active"></span> Connected
                                </span>
                            @else
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                    <span class="status-dot inactive"></span> Disconnected
                                </span>
                            @endif
                        </div>
                        <div class="small text-muted">
                            DB: <span class="text-dark fw-semibold">{{ $data['databases']['imedx_db']['database'] }}</span> | 
                            Schema: <span class="text-dark fw-semibold">{{ $data['databases']['imedx_db']['schema'] }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- PHP Extensions Check -->
        <div class="col-12">
            <div class="diag-card mb-0">
                <div class="card-inner-header d-flex align-items-center">
                    <i class="fa-solid fa-puzzle-piece text-secondary me-2"></i> ส่วนขยายหลักที่จำเป็น (Required PHP Extensions)
                </div>
                <div class="p-3">
                    <div class="row g-2">
                        @foreach($data['extensions'] as $ext => $loaded)
                            <div class="col-6 col-sm-4 col-md-2">
                                <div class="p-2 border rounded text-center {{ $loaded ? 'bg-success-subtle border-success-subtle' : 'bg-danger-subtle border-danger-subtle' }}">
                                    <div class="small fw-bold {{ $loaded ? 'text-success' : 'text-danger' }}">
                                        <i class="fa-solid {{ $loaded ? 'fa-check-circle' : 'fa-times-circle' }} me-1"></i>
                                        {{ $ext }}
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer Note -->
    <div class="text-center text-muted small mt-4">
        E-Budget Financial Planning & Management System &bull; Powered by PHP 8.3 & Laravel 13 &bull; Generated: {{ $data['timestamp'] }}
    </div>
</div>
</body>
</html>
