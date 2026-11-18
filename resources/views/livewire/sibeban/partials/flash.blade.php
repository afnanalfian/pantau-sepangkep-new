@if (session('sukses'))
    <div class="mb-4 flex items-start gap-2 rounded-xl border border-bps-green-100 bg-bps-green-50 px-4 py-3 text-sm text-bps-green-700"><x-icon name="check-circle" class="mt-0.5 size-4 shrink-0" /> {{ session('sukses') }}</div>
@endif
@if (session('gagal'))
    <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ session('gagal') }}</div>
@endif
