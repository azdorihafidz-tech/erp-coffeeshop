<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>@yield('title', 'Kopi Drip Sidikalang')</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo-icon.png') }}">

    <style>
        :root {
            --kd-black: #1A1A1A;
            --kd-green: #2D6A4F;
            --kd-cream: #F5F0E6;
            --kd-white: #FFFFFF;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 16px;
            background: var(--kd-cream);
            color: var(--kd-black);
            line-height: 1.5;
            padding-bottom: 90px; /* ruang buat footer sticky */
        }

        a { color: inherit; text-decoration: none; }

        button {
            font-family: inherit;
            font-size: 1rem;
            cursor: pointer;
            border: none;
        }

        /* ===== Header ===== */
        .kd-header {
            background: var(--kd-black);
            color: var(--kd-white);
            padding: 10px 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            position: sticky;
            top: 0;
            z-index: 20;
        }
        .kd-header img { width: 34px; height: 34px; border-radius: 8px; flex-shrink: 0; }
        .kd-header .kd-title { flex: 1; min-width: 0; }
        .kd-header .kd-title .brand { font-weight: 700; font-size: .95rem; }
        .kd-header .kd-title .outlet { font-size: .78rem; color: #cbd5c8; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .kd-header .kd-meja-badge {
            background: var(--kd-green);
            color: #fff;
            font-weight: 700;
            font-size: .85rem;
            padding: 6px 12px;
            border-radius: 20px;
            white-space: nowrap;
            flex-shrink: 0;
        }

        /* ===== Tab kategori ===== */
        .kd-tabs {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding: 10px 14px;
            background: var(--kd-white);
            position: sticky;
            top: 54px;
            z-index: 15;
            border-bottom: 1px solid #e5e0d5;
            -webkit-overflow-scrolling: touch;
        }
        .kd-tabs::-webkit-scrollbar { display: none; }
        .kd-tab-btn {
            background: var(--kd-cream);
            color: var(--kd-black);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: .85rem;
            font-weight: 600;
            white-space: nowrap;
            min-height: 44px;
            display: flex;
            align-items: center;
        }
        .kd-tab-btn.active { background: var(--kd-green); color: #fff; }

        /* ===== Grid item ===== */
        .kd-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            padding: 14px;
        }
        @media (min-width: 600px) { .kd-grid { grid-template-columns: repeat(3, 1fr); } }
        @media (min-width: 900px) { .kd-grid { grid-template-columns: repeat(4, 1fr); } }

        .kd-card {
            background: var(--kd-white);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 4px rgba(0,0,0,.08);
            display: flex;
            flex-direction: column;
        }
        .kd-card.habis { opacity: .5; }
        .kd-card .thumb {
            width: 100%;
            aspect-ratio: 1/1;
            background: var(--kd-cream);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            overflow: hidden;
        }
        .kd-card .thumb img { width: 100%; height: 100%; object-fit: cover; }
        .kd-card .body { padding: 8px 10px 10px; display: flex; flex-direction: column; gap: 4px; flex: 1; }
        .kd-card .nama { font-size: .85rem; font-weight: 600; line-height: 1.3; min-height: 2.2em; }
        .kd-card .harga { font-size: .82rem; font-weight: 700; color: var(--kd-green); }
        .kd-card .estimasi { font-size: .7rem; color: #888; }
        .kd-card .habis-label { font-size: .72rem; color: #b91c1c; font-weight: 600; }

        .kd-btn-add {
            margin-top: auto;
            background: var(--kd-green);
            color: #fff;
            border-radius: 8px;
            min-height: 36px;
            font-weight: 600;
            font-size: .82rem;
        }
        .kd-btn-add:disabled { background: #ccc; }

        .kd-qty-picker {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--kd-green);
            border-radius: 8px;
            min-height: 36px;
            color: #fff;
        }
        .kd-qty-picker button { background: transparent; color: #fff; font-size: 1.1rem; width: 36px; min-height: 36px; }
        .kd-qty-picker span { font-weight: 700; font-size: .9rem; }

        /* ===== Footer keranjang sticky ===== */
        .kd-cart-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--kd-black);
            color: #fff;
            padding: 12px 16px;
            display: none;
            align-items: center;
            justify-content: space-between;
            z-index: 30;
            box-shadow: 0 -2px 10px rgba(0,0,0,.2);
        }
        .kd-cart-bar.show { display: flex; }
        .kd-cart-bar .info { font-size: .85rem; }
        .kd-cart-bar .info .total { font-weight: 700; font-size: 1rem; color: #ffd166; }
        .kd-cart-bar button {
            background: var(--kd-green);
            color: #fff;
            padding: 10px 18px;
            border-radius: 8px;
            font-weight: 700;
            min-height: 44px;
        }

        /* ===== Modal (vanilla, no bootstrap) ===== */
        .kd-modal-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.5);
            z-index: 40;
        }
        .kd-modal-backdrop.show { display: flex; align-items: flex-end; justify-content: center; }
        .kd-modal {
            background: var(--kd-white);
            width: 100%;
            max-width: 480px;
            max-height: 85vh;
            border-radius: 16px 16px 0 0;
            overflow-y: auto;
            padding: 16px;
        }
        .kd-modal h3 { margin: 0 0 12px; font-size: 1.05rem; }
        .kd-modal .close-btn {
            float: right;
            background: transparent;
            font-size: 1.3rem;
            color: var(--kd-black);
            min-width: 36px;
            min-height: 36px;
        }
        .kd-cart-row { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid #eee; }
        .kd-cart-row .nama { flex: 1; font-size: .88rem; font-weight: 600; }
        .kd-cart-row .harga { font-size: .8rem; color: #666; }

        .kd-form-group { margin-bottom: 12px; }
        .kd-form-group label { display: block; font-size: .82rem; font-weight: 600; margin-bottom: 4px; }
        .kd-form-group input, .kd-form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: .95rem;
            min-height: 44px;
        }
        .kd-form-group textarea { min-height: 70px; }

        .kd-payment-option {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 8px;
            min-height: 44px;
        }
        .kd-payment-option.disabled { opacity: .5; }
        .kd-payment-option input { width: auto; min-height: auto; }

        .kd-btn-primary {
            width: 100%;
            background: var(--kd-green);
            color: #fff;
            padding: 12px;
            border-radius: 8px;
            font-weight: 700;
            min-height: 48px;
        }
        .kd-btn-primary:disabled { background: #ccc; }
    </style>

    @stack('styles')
</head>
<body>

    @yield('content')

    @stack('scripts')
</body>
</html>
