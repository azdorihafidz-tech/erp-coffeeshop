#!/bin/bash
# Audit 2 Jalur B - Smoke test otomatis semua route utama via curl
# Login programmatic (admin@kopidrip.com) lalu GET tiap route, catat HTTP status

BASE_URL="http://127.0.0.1:8002"
COOKIE_JAR="/tmp/audit_cookies.txt"
rm -f "$COOKIE_JAR"

# Ambil CSRF token dari halaman login
LOGIN_PAGE=$(curl -s -c "$COOKIE_JAR" "$BASE_URL/login")
CSRF_TOKEN=$(echo "$LOGIN_PAGE" | grep -o 'name="_token" value="[^"]*"' | head -1 | sed -E 's/.*value="([^"]*)"/\1/')

if [ -z "$CSRF_TOKEN" ]; then
  echo "GAGAL: CSRF token tidak ditemukan di halaman login."
  exit 1
fi

# Login POST
LOGIN_RESPONSE=$(curl -s -b "$COOKIE_JAR" -c "$COOKIE_JAR" -o /dev/null -w "%{http_code}" \
  -X POST "$BASE_URL/login" \
  --data-urlencode "_token=$CSRF_TOKEN" \
  --data-urlencode "email=admin@kopidrip.com" \
  --data-urlencode "password=password")

echo "Login POST status: $LOGIN_RESPONSE"

# Verifikasi login berhasil (akses /dashboard harus 200, bukan redirect ke /login)
DASH_CHECK=$(curl -s -b "$COOKIE_JAR" -o /dev/null -w "%{http_code}" "$BASE_URL/dashboard")
echo "Cek /dashboard setelah login: HTTP $DASH_CHECK"
echo "---"

ROUTES=(
"/stok"
"/stok/adjustment"
"/stock-transfer"
"/stock-transfer/create"
"/stock-request"
"/stock-request/create"
"/pembelian"
"/pembelian/create"
"/purchase-order"
"/antrian/produksi"
"/supplier"
"/aset"
"/karyawan"
"/karyawan/create"
"/absensi"
"/absensi-saya"
"/penggajian"
"/cuti"
"/evaluation"
"/shift"
"/hari-libur"
"/face-registration"
"/pengaturan/penggajian"
"/laporan/penjualan"
"/laporan/stok"
"/laporan/aset"
"/laporan/setoran-harian"
"/laporan/keuangan/laba-rugi"
"/laporan/keuangan/arus-kas"
"/laporan/hr/absensi"
"/laporan/hr/penggajian"
"/laporan/hr/evaluasi"
"/laporan/cabang"
"/setoran"
"/transfer-dana"
"/keuangan/laporan"
)

DIMSUM_PATTERN="dimsum|dmentai|gyoza|mentai|berkahmulyo"

echo "ROUTE|STATUS|DIMSUM_HIT"
for r in "${ROUTES[@]}"; do
  BODY_FILE=$(mktemp)
  status=$(curl -s -b "$COOKIE_JAR" -o "$BODY_FILE" -w "%{http_code}" "$BASE_URL$r")
  hit=$(grep -oiE "$DIMSUM_PATTERN" "$BODY_FILE" | sort -u | tr '\n' ',' | sed 's/,$//')
  if [ -z "$hit" ]; then hit="-"; fi
  echo "$r|$status|$hit"
  rm -f "$BODY_FILE"
done
