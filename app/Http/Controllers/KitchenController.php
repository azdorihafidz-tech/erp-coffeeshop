<?php

namespace App\Http\Controllers;

use App\Models\BillItem;
use App\Models\Cabang;
use App\Models\KitchenDisplaySetting;
use Illuminate\Http\Request;

class KitchenController extends Controller
{
    public function index(int $cabang)
    {
        abort_unless(auth()->user()->can('kitchen.display.view'), 403);

        $cabangModel = Cabang::findOrFail($cabang);
        $setting = KitchenDisplaySetting::forCabang($cabang);

        return view('kitchen.display', [
            'cabang'  => $cabangModel,
            'setting' => $setting,
        ]);
    }

    /** Polling JSON — daftar bill_items pending, grup per bill/meja. */
    public function pending(int $cabang)
    {
        abort_unless(auth()->user()->can('kitchen.display.view'), 403);

        $items = BillItem::with(['item', 'bill.meja'])
            ->whereHas('bill', fn ($q) => $q->where('cabang_id', $cabang)->whereIn('status', ['open', 'waiting_payment']))
            ->where('status_dapur', 'pending')
            ->orderBy('urutan_masuk')
            ->get()
            ->groupBy(fn ($bi) => $bi->bill->meja_id)
            ->map(function ($rows) {
                $first = $rows->first();
                return [
                    'meja_id'    => $first->bill->meja_id,
                    'meja_nama'  => $first->bill->meja->nama_meja,
                    'bill_id'    => $first->bill_id,
                    'started_at' => $first->bill->started_at->toIso8601String(),
                    'items'      => $rows->map(fn ($bi) => [
                        'id'                => $bi->id,
                        'nama'              => $bi->item->nama_item,
                        'qty'               => (float) $bi->qty,
                        'varian'            => $bi->varian,
                        'catatan'           => $bi->catatan,
                        // E6 — estimasi menit siap per item (dari master produk), null kalau belum diisi
                        'waktu_siap_menit'  => $bi->item->waktu_siap_menit,
                        'created_at'        => $bi->created_at->toIso8601String(),
                    ])->values(),
                ];
            })
            ->values();

        return response()->json(['orders' => $items]);
    }

    public function markSiap(int $item)
    {
        abort_unless(auth()->user()->can('kitchen.item.mark'), 403);

        $billItem = BillItem::findOrFail($item);
        $billItem->markSiap(auth()->id());

        return response()->json(['success' => true]);
    }

    public function markKomplain(Request $request, int $item)
    {
        abort_unless(auth()->user()->can('kitchen.item.mark'), 403);

        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        $billItem = BillItem::findOrFail($item);
        $billItem->markKomplain(auth()->id(), $data['reason']);

        return response()->json(['success' => true]);
    }
}
