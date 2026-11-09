<?php

namespace App\Services;

use App\Models\Leave;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;
use App\Support\WorkingDays;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Perhitungan beban kerja & capaian poin (Workload Capacity Meter).
 *  - Poin Terverifikasi : tugas "selesai" yang disetujui pada bulan tsb (dasar Tukin)
 *  - Poin Berjalan      : tugas aktif (todo/dikerjakan/revisi/menunggu verifikasi), tidak termasuk yg overdue (dibekukan)
 *  - Proyeksi           : (terverifikasi + berjalan) / target
 *  - Target             : target standar × (hari kerja aktif / hari kerja efektif)  (prorata cuti/DL)
 */
class WorkloadService
{
    public const LEVELS = [
        'overloaded' => ['label' => 'Overload', 'class' => 'bg-rose-500', 'soft' => 'bg-rose-50 text-rose-700 ring-rose-600/20'],
        'optimal' => ['label' => 'Optimal', 'class' => 'bg-bps-green', 'soft' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20'],
        'normal' => ['label' => 'Cukup', 'class' => 'bg-bps-blue', 'soft' => 'bg-sky-50 text-sky-700 ring-sky-600/20'],
        'underloaded' => ['label' => 'Underload', 'class' => 'bg-amber-400', 'soft' => 'bg-amber-50 text-amber-700 ring-amber-600/20'],
    ];

    public const ACTIVITY = [
        'aktif' => ['label' => 'Aktif', 'dot' => 'bg-emerald-500'],
        'pasif' => ['label' => 'Pasif', 'dot' => 'bg-amber-400'],
        'dorman' => ['label' => 'Dorman', 'dot' => 'bg-rose-500'],
    ];

    /**
     * @param  Collection<int, User>  $users
     * @return Collection<int, array>  keyed by user id
     */
    public function summarize(Collection $users, ?Carbon $month = null): Collection
    {
        $month = ($month ?? now())->copy()->startOfMonth();
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $isCurrent = $month->isSameMonth(now());
        $ids = $users->pluck('id')->all();
        if (! $ids) {
            return collect();
        }
        $today = now()->toDateString();

        $verified = Task::query()->whereIn('assignee_id', $ids)->where('status', 'selesai')
            ->whereBetween('completed_at', [$start, $end])
            ->selectRaw('assignee_id, SUM(points) as pts, COUNT(*) as cnt')
            ->groupBy('assignee_id')->get()->keyBy('assignee_id');

        $activeQuery = Task::query()->whereIn('assignee_id', $ids)->whereIn('status', Task::ACTIVE_STATUSES);
        if (! $isCurrent) {
            $activeQuery->whereBetween('due_date', [$start->toDateString(), $end->toDateString()]);
        }
        $active = $activeQuery->get(['id', 'assignee_id', 'points', 'status', 'due_date', 'extension_until']);

        $pending = Task::query()->whereIn('assignee_id', $ids)->where('status', 'pengajuan')
            ->selectRaw('assignee_id, SUM(points) as pts')->groupBy('assignee_id')->pluck('pts', 'assignee_id');

        $lastActivity = TaskActivity::query()->whereIn('user_id', $ids)
            ->selectRaw('user_id, MAX(created_at) as last_at')->groupBy('user_id')->pluck('last_at', 'user_id');

        $leaves = Leave::query()->whereIn('user_id', $ids)
            ->whereDate('start_date', '<=', $end)->whereDate('end_date', '>=', $start)->get()->groupBy('user_id');

        $workdays = max(WorkingDays::inMonth($month), 1);

        return $users->mapWithKeys(function (User $u) use ($verified, $active, $pending, $lastActivity, $leaves, $workdays, $start, $end, $today) {
            $mine = $active->where('assignee_id', $u->id);
            $overdue = $mine->filter(fn ($t) => $t->status !== 'review' && ($d = $t->extension_until ?? $t->due_date) && $d->toDateString() < $today);
            $committed = $mine->diffKeys($overdue)->sum('points');

            $leaveDays = 0;
            foreach ($leaves->get($u->id, collect()) as $l) {
                $leaveDays += WorkingDays::between($l->start_date->max($start), $l->end_date->min($end));
            }
            $baseTarget = $u->monthlyTarget();
            $target = round($baseTarget * max($workdays - $leaveDays, 0) / $workdays, 1);

            $v = (float) ($verified[$u->id]->pts ?? 0);
            $c = (float) $committed;
            $loadPct = $target > 0 ? ($v + $c) / $target * 100 : 0;

            $level = match (true) {
                $loadPct > 110 => 'overloaded',
                $loadPct >= 80 => 'optimal',
                $loadPct >= 50 => 'normal',
                default => 'underloaded',
            };

            $last = $lastActivity[$u->id] ?? null;
            $lastAt = $last ? Carbon::parse($last) : null;
            $idleDays = $lastAt ? WorkingDays::between($lastAt->copy()->addDay(), now()) : 99;
            $activity = match (true) {
                $idleDays <= 1 => 'aktif',
                $idleDays <= 3 && $mine->isNotEmpty() => 'pasif',
                $idleDays <= 4 => 'pasif',
                default => 'dorman',
            };

            return [$u->id => [
                'user' => $u,
                'target' => $target,
                'base_target' => $baseTarget,
                'leave_days' => $leaveDays,
                'verified' => round($v, 2),
                'committed' => round($c, 2),
                'frozen' => round((float) $overdue->sum('points'), 2),
                'pending' => round((float) ($pending[$u->id] ?? 0), 2),
                'done_count' => (int) ($verified[$u->id]->cnt ?? 0),
                'active_count' => $mine->count(),
                'overdue_count' => $overdue->count(),
                'achievement_pct' => $target > 0 ? round($v / $target * 100, 1) : 0,
                'projection_pct' => round($loadPct, 1),
                'load_pct' => round($loadPct, 1),
                'level' => $level,
                'activity' => $activity,
                'last_activity_at' => $lastAt,
            ]];
        });
    }

    public function forUser(User $user, ?Carbon $month = null): array
    {
        return $this->summarize(collect([$user]), $month)->first();
    }

    /** Statistik ketepatan waktu & SLA untuk ketua tim (poin manajerial). */
    public function leaderStats(User $leader, Carbon $month): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $reviewed = Task::query()->where('approved_by', $leader->id)->whereBetween('completed_at', [$start, $end])->count();
        $slaBreaches = TaskActivity::query()->where('action', 'sla_breach')
            ->whereBetween('created_at', [$start, $end])
            ->where('meta->verifier_id', $leader->id)->count();
        $delegated = Task::query()->where('creator_id', $leader->id)->where('assignee_id', '!=', $leader->id)
            ->whereBetween('created_at', [$start, $end])->count();

        return compact('reviewed', 'slaBreaches', 'delegated');
    }

    /** Rekap bulanan per tim: rata-rata utilisasi. */
    public function teamUtilization(Collection $teams, Carbon $month): Collection
    {
        $members = $teams->flatMap->members->unique('id');
        $summary = $this->summarize($members, $month);

        return $teams->map(function ($team) use ($summary) {
            $rows = $team->members->map(fn ($m) => $summary[$m->id] ?? null)->filter();

            return [
                'team' => $team,
                'avg_load' => round($rows->avg('load_pct') ?? 0, 1),
                'avg_achievement' => round($rows->avg('achievement_pct') ?? 0, 1),
                'verified' => $rows->sum('verified'),
                'committed' => $rows->sum('committed'),
                'members' => $rows->count(),
                'overdue' => $rows->sum('overdue_count'),
            ];
        });
    }

    /** Distribusi poin per hari selama bulan (untuk grafik tren). */
    public function dailyTrend(Collection $userIds, Carbon $month): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $rows = Task::query()->whereIn('assignee_id', $userIds)->where('status', 'selesai')
            ->whereBetween('completed_at', [$start, $end])
            ->get(['completed_at', 'points'])
            ->groupBy(fn ($t) => $t->completed_at->format('j'))
            ->map->sum('points');

        $out = [];
        for ($d = 1; $d <= $end->day; $d++) {
            $out[$d] = round((float) ($rows[$d] ?? 0), 2);
        }

        return $out;
    }
}
