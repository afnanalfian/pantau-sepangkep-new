<?php

namespace App\Livewire\Tasks;

use App\Models\Task;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Kalender Tugas')]
class Calendar extends Component
{
    #[Url]
    public string $month = '';

    #[Url]
    public string $scope = 'mine';

    public function mount(): void
    {
        $this->month = $this->month ?: now()->format('Y-m');
    }

    public function shift(int $n): void
    {
        $this->month = Carbon::parse($this->month.'-01')->addMonths($n)->format('Y-m');
    }

    public function render()
    {
        $u = Auth::user();
        $start = Carbon::parse($this->month.'-01')->startOfMonth();
        $gridStart = $start->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $start->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $q = Task::with('assignee')->whereNotIn('status', ['batal', 'ditolak'])
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [$gridStart->toDateString(), $gridEnd->toDateString()]);
        $this->scope === 'all' && $u->canMonitor() ? $q->visibleTo($u) : $q->where('assignee_id', $u->id);
        $tasks = $q->get()->groupBy(fn ($t) => $t->effectiveDueDate()->toDateString());

        $days = [];
        for ($d = $gridStart->copy(); $d->lte($gridEnd); $d->addDay()) {
            $days[] = ['date' => $d->copy(), 'tasks' => $tasks->get($d->toDateString(), collect())];
        }

        return view('livewire.tasks.calendar', ['days' => $days, 'start' => $start]);
    }
}
