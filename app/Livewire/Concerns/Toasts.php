<?php

namespace App\Livewire\Concerns;

use Illuminate\Validation\ValidationException;

trait Toasts
{
    protected function toast(string $message, string $type = 'success'): void
    {
        $this->dispatch('toast', message: $message, type: $type);
    }

    /** Jalankan aksi; ValidationException ditampilkan sebagai toast error. */
    protected function attempt(callable $fn, ?string $success = null): bool
    {
        try {
            $fn();
            if ($success) {
                $this->toast($success);
            }

            return true;
        } catch (ValidationException $e) {
            $this->toast(collect($e->errors())->flatten()->first() ?? 'Terjadi kesalahan.', 'error');

            return false;
        }
    }
}
