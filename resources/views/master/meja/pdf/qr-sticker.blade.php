<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>QR Meja {{ $meja->nomor_meja }} — {{ $meja->cabang->nama_cabang }}</title>
    <style>
        @page { margin: 0; }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            margin: 0;
            padding: 14px;
            text-align: center;
            color: #1A1A1A;
        }
        .logo { width: 40px; height: 40px; margin-bottom: 4px; }
        .brand-name { font-size: 12px; font-weight: bold; letter-spacing: 1px; margin-bottom: 2px; }
        .outlet-name { font-size: 9px; color: #555; margin-bottom: 10px; }
        .qr-box { margin: 10px 0; }
        .qr-box img { width: 160px; height: 160px; }
        .meja-label { font-size: 22px; font-weight: bold; color: #2D6A4F; margin-top: 8px; }
        .scan-text { font-size: 10px; color: #444; margin-top: 4px; }
        .footer { margin-top: 14px; font-size: 7px; color: #888; border-top: 1px solid #ddd; padding-top: 6px; }
        .footer .url-fallback { word-break: break-all; }
    </style>
</head>
<body>

    @if(file_exists(public_path('images/logo.png')))
    <img src="{{ public_path('images/logo.png') }}" class="logo">
    @endif

    <div class="brand-name">KOPI DRIP</div>
    <div class="outlet-name">{{ $meja->cabang->nama_cabang }}</div>

    <div class="qr-box">
        <img src="{{ $qrImg }}" alt="QR Meja {{ $meja->nomor_meja }}">
    </div>

    <div class="meja-label">MEJA {{ $meja->nomor_meja }}</div>
    <div class="scan-text">Scan untuk pesan</div>

    <div class="footer">
        <div>{{ $meja->cabang->nama_cabang }} — {{ $meja->nama_meja }}</div>
        <div class="url-fallback">QR rusak? Buka: {{ $orderUrl }}</div>
    </div>

</body>
</html>
