<?php

namespace App\Livewire\Tasks;

use App\Livewire\Concerns\Toasts;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Services\AttachmentService;
use App\Services\TaskService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class Show extends Component
{
    use Toasts, WithFileUploads;

    public Task $task;

    // Unggah instan per kategori
    public $uploadSurat = [];

    public $uploadDokumen = [];

    public $uploadGambar = [];

    public $uploadOutput = [];

    public $uploadFolder = [];

    public string $folderName = '';

    public string $folderCategory = 'dokumen';

    // Link
    public string $linkCategory = '';

    public string $linkUrl = '';

    public string $linkName = '';

    // Komentar & catatan
    public string $comment = '';

    public string $actionNote = '';

    public ?string $actionModal = null; // submit | approve | revise | reject | cancel | extension

    public ?string $extensionDate = null;

    public int $progress = 0;

    public function mount(Task $task): void
    {
        abort_unless(app(TaskService::class)->canView($task, Auth::user()), 403, 'Anda tidak memiliki akses ke tugas ini.');
        $this->task = $task;
        $this->progress = $task->progress;
    }

    #[On('tasks-changed')]
    public function reload(): void
    {
        $this->task->refresh();
        $this->progress = $this->task->progress;
    }

    protected function svc(): TaskService
    {
        return app(TaskService::class);
    }

    /* ======================================================= Computed */

    #[Computed]
    public function attachmentsByCategory()
    {
        return $this->task->attachments()->with('user')->get()->groupBy('category');
    }

    #[Computed]
    public function can(): array
    {
        $u = Auth::user();
        $t = $this->task;
        $s = $this->svc();
        $isAssignee = $t->assignee_id === $u->id;
        $manage = $s->canManage($t, $u);

        return [
            'assignee' => $isAssignee,
            'manage' => $manage,
            'verify' => $s->canVerify($t, $u),
            'start' => $isAssignee && in_array($t->status, ['todo']),
            'submit' => $isAssignee && in_array($t->status, ['todo', 'in_progress', 'revisi']),
            'review' => $t->status === 'review' && $s->canVerify($t, $u),
            'decideSubmission' => $t->status === 'pengajuan' && $s->canVerify($t, $u),
            'delegate' => $isAssignee && $u->canAssign() && in_array($t->status, ['todo', 'in_progress', 'revisi']),
            'edit' => ($manage && ! in_array($t->status, ['selesai', 'batal'])) || ($isAssignee && $t->status === 'pengajuan'),
            'cancel' => $manage && ! in_array($t->status, ['selesai', 'batal', 'ditolak']),
            'reopen' => ($manage || $s->canVerify($t, $u)) && in_array($t->status, ['selesai', 'batal']),
            'delete' => $u->is_admin || ($t->creator_id === $u->id && in_array($t->status, ['todo', 'pengajuan', 'batal', 'ditolak'])),
            'extension' => $isAssignee && in_array($t->status, ['todo', 'in_progress', 'revisi']) && $t->due_date,
            'decideExtension' => ($manage || $s->canVerify($t, $u)) && $s->pendingExtension($t),
            'upload' => $isAssignee || $manage || $t->creator_id === $u->id,
            'uploadOutput' => $isAssignee && ! in_array($t->status, ['selesai', 'batal']),
        ];
    }

    #[Computed]
    public function pendingExtension(): ?array
    {
        return $this->svc()->pendingExtension($this->task);
    }

    #[Computed]
    public function timeline()
    {
        $acts = $this->task->activities()->with('user')->get()->map(fn ($a) => ['type' => 'activity', 'at' => $a->created_at, 'item' => $a]);
        $coms = $this->task->comments()->with('user')->get()->map(fn ($c) => ['type' => 'comment', 'at' => $c->created_at, 'item' => $c]);

        return $acts->concat($coms)->sortByDesc(fn ($x) => $x['at']->timestamp * 10 + ($x['type'] === 'comment' ? 1 : 0))->values();
    }

    #[Computed]
    public function children()
    {
        return $this->task->children()->with('assignee')->get();
    }

    #[Computed]
    public function reviewerOutOfOffice()
    {
        $v = $this->task->verifier;

        return $v && $v->isOutOfOffice() ? $v->currentLeave() : null;
    }

    /* ======================================================= Upload */

    private function storeUploads(array $files, string $category, ?string $folder = null): void
    {
        abort_unless($category === 'output' ? ($this->can['uploadOutput'] || $this->can['manage']) : $this->can['upload'], 403);
        $svc = app(AttachmentService::class);
        $n = 0;
        foreach ($files as $f) {
            $svc->storeFile($this->task, $f, $category, Auth::user(), $folder);
            $n++;
        }
        if ($n) {
            $this->svc()->log($this->task, Auth::user(), 'attachment', "mengunggah {$n} file ke ".TaskAttachment::CATEGORIES[$category]['label'].($folder ? " (folder {$folder})" : ''));
            $this->toast($n.' file berhasil diunggah'.(app(\App\Services\GoogleDriveService::class)->enabled() ? ' & sedang disinkronkan ke Google Drive.' : '.'));
        }
        unset($this->attachmentsByCategory, $this->timeline);
    }

    public function updatedUploadSurat(): void
    {
        $this->validate(['uploadSurat.*' => 'file|max:51200|mimes:pdf,doc,docx,jpg,jpeg,png,odt']);
        $this->storeUploads($this->uploadSurat, 'surat_tugas');
        $this->uploadSurat = [];
    }

    public function updatedUploadDokumen(): void
    {
        $this->validate(['uploadDokumen.*' => 'file|max:51200']);
        $this->storeUploads($this->uploadDokumen, 'dokumen');
        $this->uploadDokumen = [];
    }

    public function updatedUploadGambar(): void
    {
        $this->validate(['uploadGambar.*' => 'image|max:20480']);
        $this->storeUploads($this->uploadGambar, 'gambar');
        $this->uploadGambar = [];
    }

    public function updatedUploadOutput(): void
    {
        $this->validate(['uploadOutput.*' => 'file|max:51200']);
        $this->storeUploads($this->uploadOutput, 'output');
        $this->uploadOutput = [];
    }

    public function updatedUploadFolder(): void
    {
        $this->validate(['uploadFolder.*' => 'file|max:51200']);
        $cat = in_array($this->folderCategory, ['dokumen', 'output', 'gambar']) ? $this->folderCategory : 'dokumen';
        $this->storeUploads($this->uploadFolder, $cat, $this->folderName ?: 'Folder '.now()->format('d-m-Y H.i'));
        $this->uploadFolder = [];
        $this->folderName = '';
    }

    public function openLink(string $category): void
    {
        $this->linkCategory = $category;
        $this->linkUrl = '';
        $this->linkName = '';
    }

    public function saveLink(AttachmentService $svc): void
    {
        $this->validate(['linkUrl' => 'required|string|max:2000', 'linkName' => 'nullable|string|max:200'], [], ['linkUrl' => 'link']);
        $cat = array_key_exists($this->linkCategory, TaskAttachment::CATEGORIES) ? $this->linkCategory : 'dokumen';
        abort_unless($cat === 'output' ? ($this->can['uploadOutput'] || $this->can['manage']) : $this->can['upload'], 403);

        $svc->addLink($this->task, trim($this->linkUrl), $this->linkName ?: null, $cat, Auth::user());
        $this->svc()->log($this->task, Auth::user(), 'attachment', 'menambahkan link ke '.TaskAttachment::CATEGORIES[$cat]['label']);
        $this->linkCategory = '';
        unset($this->attachmentsByCategory, $this->timeline);
        $this->toast('Link ditambahkan.');
    }

    public function deleteAttachment(int $id, AttachmentService $svc): void
    {
        $a = $this->task->attachments()->findOrFail($id);
        abort_unless($a->user_id === Auth::id() || $this->can['manage'], 403);
        abort_if($this->task->status === 'selesai' && ! Auth::user()->is_admin, 403, 'Lampiran tugas yang sudah selesai tidak dapat dihapus.');
        $svc->delete($a);
        $this->svc()->log($this->task, Auth::user(), 'attachment', 'menghapus lampiran "'.$a->name.'"');
        unset($this->attachmentsByCategory, $this->timeline);
        $this->toast('Lampiran dihapus.');
    }

    public function retrySync(int $id): void
    {
        $a = $this->task->attachments()->findOrFail($id);
        if ($a->local_path && $a->sync_status === 'failed') {
            $a->update(['sync_status' => 'pending', 'sync_error' => null]);
            \App\Jobs\SyncAttachmentToDrive::dispatch($a->id);
            $this->toast('Sinkronisasi diulang.');
        }
        unset($this->attachmentsByCategory);
    }

    /* ======================================================= Aksi alur */

    public function openAction(string $action): void
    {
        $this->actionModal = $action;
        $this->actionNote = '';
        $this->extensionDate = $this->task->effectiveDueDate()?->copy()->addDays(3)->toDateString();
        $this->resetValidation();
    }

    public function start(): void
    {
        $this->attempt(fn () => $this->svc()->start($this->task, Auth::user()), 'Selamat bekerja! Status: Sedang Dikerjakan.');
        $this->after();
    }

    public function confirmAction()
    {
        $u = Auth::user();
        $note = trim($this->actionNote);

        if (in_array($this->actionModal, ['revise', 'reject']) && $note === '') {
            $this->addError('actionNote', 'Catatan wajib diisi.');

            return;
        }

        $ok = match ($this->actionModal) {
            'submit' => $this->attempt(fn () => $this->svc()->submit($this->task, $u, $note ?: null), 'Penyelesaian diajukan. Menunggu verifikasi.'),
            'approve' => $this->attempt(fn () => $this->svc()->approve($this->task, $u, $note ?: null), 'Tugas diverifikasi & dinyatakan selesai.'),
            'revise' => $this->attempt(fn () => $this->svc()->requestRevision($this->task, $u, $note), 'Permintaan revisi terkirim.'),
            'accept' => $this->attempt(fn () => $this->svc()->approveSubmission($this->task, $u), 'Pengajuan disetujui.'),
            'reject' => $this->attempt(fn () => $this->svc()->reject($this->task, $u, $note), 'Pengajuan ditolak.'),
            'cancel' => $this->attempt(fn () => $this->svc()->cancel($this->task, $u, $note ?: null), 'Tugas dibatalkan.'),
            'reopen' => $this->attempt(fn () => $this->svc()->reopen($this->task, $u), 'Tugas dibuka kembali.'),
            'extension' => $this->requestExtension($note),
            'delete' => $this->deleteTask(),
            default => false,
        };

        if ($this->actionModal === 'delete' && $ok) {
            return $this->redirectRoute('tasks.index', navigate: true);
        }

        if ($ok) {
            $this->actionModal = null;
        }
        $this->after();
    }

    private function requestExtension(string $note): bool
    {
        $this->validate(['extensionDate' => 'required|date|after:today'], [], ['extensionDate' => 'tanggal perpanjangan']);
        if ($note === '') {
            $this->addError('actionNote', 'Alasan perpanjangan wajib diisi.');

            return false;
        }

        return $this->attempt(fn () => $this->svc()->requestExtension($this->task, Auth::user(), $this->extensionDate, $note), 'Pengajuan perpanjangan terkirim.');
    }

    private function deleteTask(): bool
    {
        abort_unless($this->can['delete'], 403);
        $this->svc()->log($this->task, Auth::user(), 'deleted', 'menghapus tugas');
        $this->task->delete();
        $this->toast('Tugas dihapus.');

        return true;
    }

    public function decideExtension(bool $approve): void
    {
        $this->svc()->decideExtension($this->task, Auth::user(), $approve);
        $this->toast($approve ? 'Perpanjangan disetujui.' : 'Perpanjangan ditolak.');
        $this->after();
    }

    public function saveProgress(): void
    {
        $this->attempt(fn () => $this->svc()->updateProgress($this->task, Auth::user(), (int) $this->progress), 'Progres disimpan.');
        $this->after();
    }

    public function postComment(): void
    {
        $this->validate(['comment' => 'required|string|max:5000'], [], ['comment' => 'komentar']);
        $this->svc()->comment($this->task, Auth::user(), trim($this->comment));
        $this->comment = '';
        unset($this->timeline);
    }

    private function after(): void
    {
        $this->task->refresh();
        $this->progress = $this->task->progress;
        unset($this->can, $this->timeline, $this->attachmentsByCategory, $this->pendingExtension, $this->children);
    }

    public function render()
    {
        $this->task->loadMissing(['assignee', 'creator', 'verifier', 'team', 'catalog', 'parent', 'approver']);

        return view('livewire.tasks.show')->title($this->task->code.' · '.$this->task->title);
    }
}
