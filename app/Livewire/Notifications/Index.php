<?php

namespace App\Livewire\Notifications;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Notifikasi')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $filter = 'semua'; // semua | belum

    public function markAllRead(): void
    {
        Auth::user()->unreadNotifications->markAsRead();
        $this->dispatch('notifications-updated');
    }

    public function open(string $id)
    {
        $n = Auth::user()->notifications()->findOrFail($id);
        $n->markAsRead();

        return $this->redirect($n->data['url'] ?? route('dashboard'), navigate: true);
    }

    public function remove(string $id): void
    {
        Auth::user()->notifications()->whereKey($id)->delete();
    }

    public function render()
    {
        $q = $this->filter === 'belum' ? Auth::user()->unreadNotifications() : Auth::user()->notifications();

        return view('livewire.notifications.index', [
            'items' => $q->latest()->paginate(20),
            'devices' => Auth::user()->pushSubscriptions()->latest()->get(),
            'pushReady' => \App\Notifications\Channels\WebPushChannel::configured(),
        ]);
    }
}
