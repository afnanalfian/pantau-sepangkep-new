@php $tot = $this->totals; @endphp
<div>
    <x-page-header title="Laporan kinerja" subtitle="Rekap capaian poin bulanan sebagai dasar komponen kinerja (70%) tunjangan kinerja.">
        <x-slot:actions>
            <input type="month" wire:model.live="month" class="input w-auto py-2">
            <a href="{{ route('reports.export', ['bulan' => $month]) }}" class="btn btn-green"><x-icon name="download" class="size-4" /> Unduh Excel</a>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="card p-4"><p class="text-xs text-navy-500">Tugas selesai</p><p class="mt-1 text-3xl font-extrabold">{{ $tot['done'] }}</p><p class="text-xs text-navy-500">dari {{ $tot['created'] }} tugas dibuat bulan ini</p></div>
        <div class="card p-4"><p class="text-xs text-navy-500">Total poin terverifikasi</p><p class="mt-1 text-3xl font-extrabold">{{ poin($tot['points']) }}</p></div>
        <div class="card p-4"><p class="text-xs text-navy-500">Tepat waktu</p><p class="mt-1 text-3xl font-extrabold {{ ($tot['on_time_pct'] ?? 100) >= 90 ? 'text-bps-green-700' : 'text-bps-orange-700' }}">{{ $tot['on_time_pct'] !== null ? $tot['on_time_pct'].'%' : '–' }}</p><p class="text-xs text-navy-500">target ≥ 90%</p></div>
        <div class="card p-4"><p class="text-xs text-navy-500">Disparitas beban</p><p class="mt-1 text-3xl font-extrabold {{ ($tot['disparity'] ?? 0) <= 15 ? 'text-bps-green-700' : 'text-bps-orange-700' }}">{{ $tot['disparity'] !== null ? $tot['disparity'].'%' : '–' }}</p><p class="text-xs text-navy-500">target < 15% (makin kecil makin merata)</p></div>
    </div>

    <div class="grid gap-6 xl:grid-cols-[1fr_22rem] [&>*]:min-w-0">
        <section class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[820px] text-sm">
                    <thead class="table-head"><tr>
                        <th class="px-5 py-3">#</th><th class="px-3 py-3">Pegawai</th><th class="px-3 py-3 text-right">Target</th><th class="px-3 py-3 text-right">Terverifikasi</th>
                        <th class="px-3 py-3 text-right">Capaian</th><th class="px-3 py-3 text-right">Komponen kinerja</th><th class="px-3 py-3 text-right">Selesai</th><th class="px-5 py-3"></th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($this->summary as $i => $s)
                            <tr wire:key="rp-{{ $s['user']->id }}">
                                <td class="px-5 py-2.5 text-navy-500">{{ $i + 1 }}</td>
                                <td class="px-3 py-2.5"><p class="font-medium">{{ $s['user']->display_name }}</p><p class="text-xs text-navy-500">{{ $s['user']->nip ?: '@'.$s['user']->username }}</p></td>
                                <td class="px-3 py-2.5 text-right">{{ poin($s['target']) }}</td>
                                <td class="px-3 py-2.5 text-right font-semibold">{{ poin($s['verified']) }}</td>
                                <td class="px-3 py-2.5 text-right"><span class="font-bold {{ $s['achievement_pct'] >= 100 ? 'text-bps-green-700' : ($s['achievement_pct'] < 50 ? 'text-rose-600' : '') }}">{{ $s['achievement_pct'] }}%</span></td>
                                <td class="px-3 py-2.5 text-right">{{ round(min($s['achievement_pct'], 100) * 0.7, 2) }}%</td>
                                <td class="px-3 py-2.5 text-right">{{ $s['done_count'] }}</td>
                                <td class="px-5 py-2.5 text-right"><a href="{{ route('portfolio.export', ['user' => $s['user']->id, 'bulan' => $month]) }}" class="btn btn-quiet btn-sm" title="Portofolio"><x-icon name="download" class="size-4" /></a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="border-t border-line px-5 py-3 text-xs text-navy-500">Komponen kinerja = min(capaian, 100%) × 70%. Capaian di atas 100% dicatat sebagai prestasi tambahan, tidak menambah tunjangan.</p>
        </section>

        <aside class="space-y-6">
            <section class="card card-pad">
                <h2 class="font-bold">Rata-rata per tim</h2>
                <div class="mt-4 space-y-4">
                    @foreach ($this->teamStats as $ts)
                        <div>
                            <div class="flex justify-between text-sm"><span class="font-medium">{{ $ts['team']->name }}</span><span class="font-semibold">{{ $ts['avg_achievement'] }}%</span></div>
                            <div class="slant-track mt-1.5 h-2"><div class="absolute inset-y-0 left-0 {{ $ts['team']->color() }}" style="width: {{ min($ts['avg_achievement'], 100) }}%"></div></div>
                            <p class="mt-1 text-xs text-navy-500">{{ $ts['members'] }} anggota · {{ poin($ts['verified']) }} poin · {{ $ts['overdue'] }} terlambat</p>
                        </div>
                    @endforeach
                </div>
            </section>
            <section class="card">
                <h2 class="px-5 pt-5 font-bold">Kinerja manajerial ketua tim</h2>
                <ul class="mt-2 divide-y divide-line text-sm">
                    @forelse ($this->leaders as $l)
                        <li class="px-5 py-3">
                            <p class="font-medium">{{ $l['user']->display_name }} <span class="text-xs font-normal text-navy-500">({{ $l['user']->ledTeams->pluck('name')->join(', ') }})</span></p>
                            <p class="mt-0.5 text-xs text-navy-500">{{ $l['delegated'] }} tugas didelegasikan · {{ $l['reviewed'] }} diverifikasi ·
                                <span class="{{ $l['slaBreaches'] ? 'font-semibold text-rose-600' : '' }}">{{ $l['slaBreaches'] }} lewat SLA</span></p>
                        </li>
                    @empty
                        <li class="px-5 pb-5 text-navy-500">Belum ada ketua tim.</li>
                    @endforelse
                </ul>
            </section>
        </aside>
    </div>
</div>
