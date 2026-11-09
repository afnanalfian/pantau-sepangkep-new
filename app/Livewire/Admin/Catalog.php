<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\Toasts;
use App\Models\ActivityCatalog;
use App\Models\Team;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Katalog Kegiatan')]
class Catalog extends Component
{
    use Toasts;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $rumpun = '';

    public bool $showForm = false;

    public ?int $editId = null;

    public array $form = [];

    public function create(): void
    {
        $this->resetValidation();
        $this->editId = null;
        $this->form = ['rumpun' => 'Umum', 'name' => '', 'unit' => 'kegiatan', 'points' => 1, 'complexity' => 'rendah', 'rhk' => '', 'team_id' => null, 'is_active' => true];
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->resetValidation();
        $c = ActivityCatalog::findOrFail($id);
        $this->editId = $c->id;
        $this->form = $c->only(['rumpun', 'name', 'unit', 'points', 'complexity', 'rhk', 'team_id', 'is_active']);
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'form.rumpun' => 'nullable|string|max:100',
            'form.name' => 'required|string|max:255',
            'form.unit' => 'required|string|max:100',
            'form.points' => 'required|numeric|min:0.1|max:200',
            'form.complexity' => 'required|in:rendah,sedang,tinggi',
            'form.rhk' => 'nullable|string|max:2000',
            'form.team_id' => 'nullable|exists:teams,id',
            'form.is_active' => 'boolean',
        ], [], ['form.name' => 'nama kegiatan', 'form.points' => 'poin', 'form.unit' => 'satuan'])['form'];

        $data['team_id'] = $data['team_id'] ?: null;
        $this->editId ? ActivityCatalog::findOrFail($this->editId)->update($data) : ActivityCatalog::create($data);
        $this->showForm = false;
        $this->toast('Katalog kegiatan disimpan.');
    }

    public function toggle(int $id): void
    {
        $c = ActivityCatalog::findOrFail($id);
        $c->update(['is_active' => ! $c->is_active]);
    }

    public function delete(int $id): void
    {
        $c = ActivityCatalog::findOrFail($id);
        if (\App\Models\Task::where('catalog_id', $id)->exists()) {
            $c->update(['is_active' => false]);
            $this->toast('Kegiatan sudah dipakai tugas, jadi dinonaktifkan (tidak dihapus).', 'info');

            return;
        }
        $c->delete();
        $this->toast('Kegiatan dihapus.');
    }

    public function render()
    {
        $items = ActivityCatalog::with('team')
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->rumpun, fn ($q) => $q->where('rumpun', $this->rumpun))
            ->orderBy('rumpun')->orderBy('name')->get();

        return view('livewire.admin.catalog', [
            'items' => $items->groupBy(fn ($c) => $c->rumpun ?: 'Lainnya'),
            'teams' => Team::orderBy('sort_order')->get(),
            'rumpuns' => ActivityCatalog::query()->whereNotNull('rumpun')->distinct()->pluck('rumpun')->merge(ActivityCatalog::RUMPUN)->unique()->values(),
        ]);
    }
}
