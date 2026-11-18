@php $ambang = app(\App\Services\Sibeban\HonorService::class)->ambangOranye(); @endphp
<div class="flex flex-wrap items-center gap-3 text-xs text-slate-500">
    <span class="inline-flex items-center gap-1"><span class="h-3 w-3 rounded bg-emerald-200"></span> ≤ {{ rtrim(rtrim(number_format($ambang, 1, ',', ''), '0'), ',') }}% limit</span>
    <span class="inline-flex items-center gap-1"><span class="h-3 w-3 rounded bg-orange-200"></span> {{ rtrim(rtrim(number_format($ambang, 1, ',', ''), '0'), ',') }}–100% (mendekati)</span>
    <span class="inline-flex items-center gap-1"><span class="h-3 w-3 rounded bg-red-200"></span> &gt; 100% (melebihi)</span>
    <span class="inline-flex items-center gap-1"><span class="h-3 w-3 rounded bg-slate-100 ring-1 ring-slate-200"></span> belum ada honor</span>
</div>
