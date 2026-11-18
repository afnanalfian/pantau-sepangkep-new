<?php

namespace App\Livewire\Portal;

use App\Livewire\Concerns\Toasts;
use App\Models\PortalInovator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Portal Inovasi')]
#[Layout('layouts.portal')]
class Home extends Component
{
    use Toasts, WithFileUploads;

    public bool $showForm = false;

    public ?int $editId = null;

    public array $form = ['nama' => '', 'inovasi' => '', 'keterangan' => ''];

    public $foto;

    public function create(): void
    {
        abort_unless(Auth::user()->is_admin, 403);
        $this->reset('editId', 'foto');
        $this->form = ['nama' => '', 'inovasi' => '', 'keterangan' => ''];
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        abort_unless(Auth::user()->is_admin, 403);
        $i = PortalInovator::findOrFail($id);
        $this->editId = $i->id;
        $this->foto = null;
        $this->form = $i->only(['nama', 'inovasi', 'keterangan']);
        $this->showForm = true;
    }

    public function save(): void
    {
        abort_unless(Auth::user()->is_admin, 403);
        $data = $this->validate([
            'form.nama' => 'required|string|max:150',
            'form.inovasi' => 'nullable|string|max:150',
            'form.keterangan' => 'nullable|string|max:255',
            'foto' => 'nullable|image|max:4096',
        ], [], ['form.nama' => 'nama', 'foto' => 'foto'])['form'];

        $i = $this->editId ? PortalInovator::findOrFail($this->editId) : new PortalInovator(['urutan' => (int) PortalInovator::max('urutan') + 1]);
        $i->fill($data);
        if ($this->foto) {
            if ($i->foto_path) {
                Storage::disk('public')->delete($i->foto_path);
            }
            $i->foto_path = $this->foto->store('inovator', 'public');
        }
        $i->save();
        $this->showForm = false;
        $this->toast('Inovator disimpan.');
    }

    public function delete(int $id): void
    {
        abort_unless(Auth::user()->is_admin, 403);
        $i = PortalInovator::findOrFail($id);
        if ($i->foto_path) {
            Storage::disk('public')->delete($i->foto_path);
        }
        $i->delete();
        $this->toast('Inovator dihapus.');
    }

    public function render()
    {
        return view('livewire.portal.home', [
            'layanan' => config('portal.layanan'),
            'inovators' => PortalInovator::orderBy('urutan')->get(),
        ]);
    }
}
