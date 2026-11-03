package com.kopidrip.kasir

import android.content.Context
import android.net.Uri

/**
 * Pengaturan per tablet (disimpan di SharedPreferences, tidak hilang saat
 * update APK). Printer disimpan berdasarkan alamat MAC Bluetooth.
 */
class AppPrefs(context: Context) {

    private val sp = context.getSharedPreferences("kopidrip_kasir", Context.MODE_PRIVATE)

    companion object {
        const val URL_GOLIVE = "https://erp.kopidripsidikalang.com"

        /** Alamat go-live di v1.0.3 (salah — 404). Dialihkan otomatis ke URL_GOLIVE. */
        private const val URL_GOLIVE_LAMA = "https://kopidripsidikalang.com/erp"
        const val URL_DEMO = "https://erpdrip.azwacore.com"
        const val HALAMAN_AWAL = "/penjualan/pos"

        const val PROTOKOL_TSPL = "tspl"
        const val PROTOKOL_ESCPOS = "escpos"
    }

    /** Alamat ERP tanpa garis miring di akhir. */
    var baseUrl: String
        get() {
            val url = (sp.getString("base_url", URL_GOLIVE) ?: URL_GOLIVE).trimEnd('/')
            return if (url == URL_GOLIVE_LAMA) URL_GOLIVE else url
        }
        set(v) = sp.edit().putString("base_url", v.trim().trimEnd('/')).apply()

    var strukMac: String?
        get() = sp.getString("struk_mac", null)
        set(v) = sp.edit().putString("struk_mac", v).apply()

    var strukNama: String?
        get() = sp.getString("struk_nama", null)
        set(v) = sp.edit().putString("struk_nama", v).apply()

    var labelMac: String?
        get() = sp.getString("label_mac", null)
        set(v) = sp.edit().putString("label_mac", v).apply()

    var labelNama: String?
        get() = sp.getString("label_nama", null)
        set(v) = sp.edit().putString("label_nama", v).apply()

    var labelProtokol: String
        get() = sp.getString("label_protokol", PROTOKOL_TSPL) ?: PROTOKOL_TSPL
        set(v) = sp.edit().putString("label_protokol", v).apply()

    var autoStruk: Boolean
        get() = sp.getBoolean("auto_struk", true)
        set(v) = sp.edit().putBoolean("auto_struk", v).apply()

    var autoLabel: Boolean
        get() = sp.getBoolean("auto_label", true)
        set(v) = sp.edit().putBoolean("auto_label", v).apply()

    /** false = aplikasi baru pertama kali dibuka → tampilkan Pengaturan Printer dulu. */
    var sudahSetup: Boolean
        get() = sp.getBoolean("sudah_setup", false)
        set(v) = sp.edit().putBoolean("sudah_setup", v).apply()

    /** Host ERP — hanya halaman dari host ini yang boleh memakai printer. */
    fun baseHost(): String? = Uri.parse(baseUrl).host

    fun urlAwal(): String = baseUrl + HALAMAN_AWAL
}
