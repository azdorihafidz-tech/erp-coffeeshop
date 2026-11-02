package com.kopidrip.kasir

import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

/** Data contoh untuk tombol "Tes Cetak" di Pengaturan Printer. */
object TesCetak {

    private fun waktu(): String = SimpleDateFormat("dd/MM/yyyy HH:mm", Locale("id", "ID")).format(Date())

    /** ESC/POS, kertas 58 mm (32 karakter). */
    fun struk(): ByteArray {
        val out = ArrayList<Byte>()
        fun raw(vararg b: Int) = b.forEach { out.add(it.toByte()) }
        fun teks(s: String) = s.toByteArray(Charsets.US_ASCII).forEach { out.add(it) }

        raw(0x1B, 0x40)            // init
        raw(0x1B, 0x61, 0x01)      // tengah
        raw(0x1B, 0x45, 0x01)      // tebal
        teks("KOPI DRIP KASIR\n")
        raw(0x1B, 0x45, 0x00)
        teks("Tes printer struk\n")
        teks(waktu() + "\n")
        raw(0x1B, 0x61, 0x00)      // kiri
        teks("--------------------------------\n")
        teks("Kalau tulisan ini rapi dalam\n")
        teks("1 baris 32 karakter, printer\n")
        teks("struk siap dipakai.\n")
        teks("--------------------------------\n\n\n\n")
        return out.toByteArray()
    }

    /** Label TSPL ukuran lebar×tinggi mm (default stiker 40×30). */
    fun labelTspl(lebarMm: Int = 40, tinggiMm: Int = 30): String =
        listOf(
            "SIZE $lebarMm mm,$tinggiMm mm",
            "GAP 2 mm,0 mm",
            "DIRECTION 1",
            "CLS",
            "TEXT 12,10,\"2\",0,1,1,\"KOPI DRIP  #TES 1/1\"",
            "BAR 12,36,${lebarMm * 8 - 24},2",
            "TEXT 12,44,\"3\",0,1,1,\"TES LABEL\"",
            "TEXT 12,76,\"2\",0,1,1,\"${waktu()}\"",
            "TEXT 12,${tinggiMm * 8 - 26},\"1\",0,1,1,\"Printer label siap\"",
            "PRINT 1,1",
            "",
        ).joinToString("\r\n")

    /** Label untuk printer ESC/POS yang diisi kertas stiker. */
    fun labelEscpos(): ByteArray {
        val teks = "KOPI DRIP  #TES 1/1\nTES LABEL\n${waktu()}\nPrinter label siap\n"
        return byteArrayOf(0x1B, 0x40) + teks.toByteArray(Charsets.US_ASCII) + byteArrayOf(0x0C)
    }
}
