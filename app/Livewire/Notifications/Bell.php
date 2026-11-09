<?php

namespace App\Livewire\Notifications;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class Bell extends Component
{
    public int $lastCount = 0;

    public function mount(): void
    {
        $this->lastCount = Auth::user()->unreadNotifications()->count();
    }

    #[On('notifications-updated')]
    public function refreshBell(): void {}

    public function markAllRead(): void
    {
        Auth::user()->unreadNotifications->markAsRead();
        $this->lastCount = 0;
    }

    public function open(string $id)
    {
        $n = Auth::user()->notifications()->findOrFail($id);
        $n->markAsRead();

        return $this->redirect($n->data['url'] ?? route('notifications'), navigate: true);
    }

    public function render()
    {
        $user = Auth::user();
        $count = $user->unreadNotifications()->count();
        if ($count > $this->lastCount) {
            // Ada notifikasi baru sejak polling terakhir → tampilkan toast di halaman
            $latest = $user->unreadNotifications()->latest()->first();
            $this->dispatch('toast', message: $latest->data['title'] ?? 'Notifikasi baru', type: 'info');
        }
        $this->lastCount = $count;

        return view('livewire.notifications.bell', [
            'count' => $count,
            'items' => $user->notifications()->latest()->limit(8)->get(),
        ]);
    }
}
