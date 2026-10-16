{{-- View standalone (TIDAK extend layouts.app) karena setelah migrate:fresh,
     tabel sessions di-drop, cache di-clear — kalau pakai layout biasa yang
     query DB untuk sidebar/notifikasi dkk, kemungkinan error. Standalone
     page = aman. --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Hasil Reset Production</title>
    <style>
        body { font-family: ui-monospace, Consolas, Monaco, monospace; max-width:900px; margin:20px auto; padding:20px; background:#1a1a1a; color:#0f0; }
        h1 { color:#fff; }
        .box { background:#000; padding:20px; border-radius:8px; }
        .line { padding:4px 0; }
        .ok { color:#0f0; }
        .err { color:#f66; }
        .info { color:#6cf; }
        .ts { color:#888; margin-right:8px; }
        a.btn-login { display:inline-block; margin-top:20px; background:#2D6A4F; color:#fff; padding:12px 24px; text-decoration:none; border-radius:6px; font-weight:bold; }
        a.btn-login:hover { background:#1B4332; }
        .note { background:#2a2a00; border-left:4px solid #ffc107; padding:12px; margin:15px 0; color:#ffd700; border-radius:4px; }
    </style>
</head>
<body>
    <h1>🔥 Hasil Reset Production</h1>
    <div class="box">
        @foreach($logs as $l)
            <div class="line {{ $l['type'] }}">
                <span class="ts">[{{ $l['ts'] }}]</span>
                @if($l['msg'] === '')
                    &nbsp;
                @else
                    {{ $l['msg'] }}
                @endif
            </div>
        @endforeach
    </div>

    <div class="note">
        <strong>CATATAN:</strong> Session login lama sudah invalid (tabel sessions di-recreate).
        Silakan logout + login ulang dengan akun default <code>admin@kopidrip.com</code> / <code>password</code>.
    </div>

    <a href="{{ url('/login') }}" class="btn-login">→ Ke Halaman Login</a>
</body>
</html>
