<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Akun seluruh pegawai BPS Kab. Pangkep (sumber: DATA_PEGAWAI_BPS_PANGKEP_2026.xlsx).
 *
 *   php artisan db:seed --class=PegawaiSeeder --force
 *
 * - Semua akun BARU memakai password yang sama (PEGAWAI_PASSWORD di .env, default di bawah)
 *   dan wajib menggantinya saat login pertama.
 * - Aman dijalankan ulang: akun yang sudah ada (dicocokkan lewat NIP baru, lalu username)
 *   hanya diperbarui identitasnya; password, peran, dan timnya tidak diubah.
 */
class PegawaiSeeder extends Seeder
{
    /** Username yang otomatis diberi peran khusus saat akunnya pertama kali dibuat. */
    private const PIMPINAN = ['ayub.parlin'];

    private const ADMIN = ['afnanalfian'];

    public function run(): void
    {
        $password = env('PEGAWAI_PASSWORD', 'bpspangkep7309');
        $baru = $diperbarui = 0;

        foreach ($this->data() as [$nama, $nipBaru, $nipLama, $jabatan, $golongan, $pangkat, $username, $wa]) {
            $identitas = [
                'name' => $nama,
                'nip' => $nipBaru,
                'nip_lama' => $nipLama,
                'jabatan' => $jabatan,
                'golongan' => $golongan,
                'pangkat' => $pangkat,
                'phone' => $wa,
            ];

            $user = User::where('nip', $nipBaru)->first() ?? User::where('username', $username)->first();

            if ($user) {
                // Jangan timpa nomor WA yang sudah diisi pegawai sendiri dengan nilai kosong.
                $user->update(array_filter($identitas, fn ($v) => $v !== null));
                $diperbarui++;

                continue;
            }

            User::create($identitas + [
                'username' => $username,
                'password' => $password,
                'must_change_password' => true,
                'is_active' => true,
                'is_pegawai' => true,
                'is_pimpinan' => in_array($username, self::PIMPINAN, true),
                'is_admin' => in_array($username, self::ADMIN, true),
            ]);
            $baru++;
        }

        $this->command?->info("Pegawai: {$baru} akun baru, {$diperbarui} akun diperbarui.");
        if ($baru) {
            $this->command?->warn("Password awal akun baru: {$password} (wajib diganti saat login pertama).");
        }
    }

    /** [Nama, NIP Baru, NIP Lama, Jabatan, Golongan Akhir, Pangkat, username, nomor_wa] */
    private function data(): array
    {
        return [
            ['Dr. Ayub Parlin Ampulembang S.Si, M.Si', '197701251999011001', '340015773', 'Kepala BPS', 'IV/b', 'Pembina Tingkat I', 'ayub.parlin', '6281235031138'],
            ['Hadrawati SE', '196910121994012001', '340014261', 'Staf BPS Kabupaten/Kota', 'III/d', 'Penata Tingkat I', 'hadra', '6285299795717'],
            ['Syahruddin S.Sos', '197609102009111001', '340053131', 'Staf BPS Kabupaten/Kota', 'III/a', 'Penata Muda', 'syahruddin', '6281342984757'],
            ['Muh. Takdir SE, S.Sos.', '198210172009111001', '340053128', 'Staf BPS Kabupaten/Kota', 'III/a', 'Penata Muda', 'muh.takdir', '6285255294754'],
            ['Muhammad Afnan Alfian S.Tr.Stat.', '200205192026031001', '340066363', 'Staf BPS Kabupaten/Kota', 'III/a', 'Penata Muda', 'afnanalfian', '6282154734819'],
            ['S. Hasan SE.', '196812311991011002', '340012694', 'Pranata Komputer Ahli Muda BPS Kabupaten/Kota', 'III/d', 'Penata Tingkat I', 'shasan', '6281258593751'],
            ['Agussalim Ab SH, M.Si', '196908281989021001', '340012134', 'Statistisi Ahli Muda BPS Kabupaten/Kota', 'IV/a', 'Pembina', 'agussalim.ab', '6282188844666'],
            ['Muhammad Anwar SE.', '197101032002121003', '340016778', 'Statistisi Mahir BPS Kabupaten/Kota', 'III/b', 'Penata Muda Tingkat I', 'muh.anwar', '6285398956962'],
            ['Muhammad Taufiq AY S.E.', '197505172007011003', '340019386', 'Pranata Keuangan APBN Mahir BPS Kabupaten/Kota', 'III/a', 'Penata Muda', 'taufiq.ay', '6281342937818'],
            ['Herman SE, S.Sos.', '197512312007011008', '340019721', 'Statistisi Ahli Pertama BPS Kabupaten/Kota', 'III/a', 'Penata Muda', 'herman7', '6285231527941'],
            ['Anastasia Surianti SE', '197904182006042003', '340018153', 'Statistisi Ahli Muda BPS Kabupaten/Kota', 'III/d', 'Penata Tingkat I', 'anastasia', '6285242003039'],
            ['Lindha Yuningsi Kasiuhe SE', '198206122001122001', '340016370', 'Statistisi Ahli Muda BPS Kabupaten/Kota', 'III/d', 'Penata Tingkat I', 'lindha.kasiuhe', '6282199288595'],
            ['Muhammad Idris SE', '198302272010031002', '340054007', 'Analis Pengelolaan Keuangan APBN Ahli Muda BPS Kabupaten/Kota', 'III/d', 'Penata Tingkat I', 'm.idris', '62811487973'],
            ['Hikmawati S.Si.', '198607202011012015', '340055455', 'Statistisi Ahli Muda BPS Kabupaten/Kota', 'III/d', 'Penata Tingkat I', 'hikmawati', '6285255270255'],
            ['Sarmiati Zainuddin S.Si', '198612262012122001', '340056152', 'Statistisi Ahli Muda BPS Kabupaten/Kota', 'III/d', 'Penata Tingkat I', 'sarmiatiz', '6285273997537'],
            ['Haerul SST', '198807152014101002', '340056967', 'Statistisi Ahli Muda BPS Kabupaten/Kota', 'III/c', 'Penata', 'haerul', '6281287012025'],
            ['Mardan A.Md', '198809012022031004', '340061400', 'Statistisi Terampil BPS Kabupaten/Kota', 'II/c', 'Pengatur', 'mardan', '6285299004802'],
            ['Sirajuddin A.Md.Kom', '199112072022031007', '340061533', 'Statistisi Terampil BPS Kabupaten/Kota', 'II/c', 'Pengatur', 'sirajuddin', '6285242225284'],
            ['Paramudita Indraswari SST, M.SM.', '199211062014102001', '340056692', 'Statistisi Ahli Muda BPS Kabupaten/Kota', 'III/c', 'Penata', 'paramudita', '6282144733137'],
            ['Yulia Fauzia Ristiani S.Si.', '199407032019032002', '340059315', 'Statistisi Ahli Pertama BPS Kabupaten/Kota', 'III/b', 'Penata Muda Tingkat I', 'yulia.ristiani', '6285824254324'],
            ['Mega Ayu Rahma Md S.Stat.', '199806282021042001', '340060411', 'Statistisi Ahli Pertama BPS Kabupaten/Kota', 'III/a', 'Penata Muda', 'mega.ayu', '6285156833020'],
            ['Haris Munandar S.Tr.Stat.', '199808102021041001', '340060131', 'Pranata Komputer Ahli Pertama BPS Kabupaten/Kota', 'III/b', 'Penata Muda Tingkat I', 'haris.munandar', '6281383833719'],
            ['Muhammad Tharieq Pahlevi S.Tr.Stat.', '199810282021041001', '340060231', 'Statistisi Ahli Pertama BPS Kabupaten/Kota', 'III/b', 'Penata Muda Tingkat I', 'tharieq.pahlevi', '6287810909365'],
            ['Habibah Dayana Anggrekalista S.Tr.Stat.', '200001242023102002', '340062470', 'Statistisi Ahli Pertama BPS Kabupaten/Kota', 'III/a', 'Penata Muda', 'habibah.dayana', '6285797289228'],
            ['Andi Muh. Reza Pahlevi A.Md.Stat.', '200211282024121004', '340063020', 'Statistisi Terampil BPS Kabupaten/Kota', 'II/c', 'Pengatur', 'andi.reza', '62895806133149'],
            ['Risal SE', '196904032006041015', '340019080', 'Fungsional Umum BPS Kabupaten/Kota', 'III/a', 'Penata Muda', 'risal2', '6285399208749'],
            ['Zainal Arifin SE', '197005172007011005', '340019492', 'Fungsional Umum BPS Kabupaten/Kota', 'III/a', 'Penata Muda', 'zainal.arf', '6281343832129'],
            ['Hasna', '197009171994032004', '340014691', 'Fungsional Umum BPS Kabupaten/Kota', 'III/b', 'Penata Muda', 'hasna', '6285241758670'],
            ['Andi Ahmad Falih Arkan A.Md.', '199701122025061002', '340063679', 'Pranata Keuangan APBN Terampil Subbagian Umum', 'II/c', 'Pengatur', 'andi.ahmad', '6285342510055'],
            ['Hisbul Wathan', '198212112025211037', '340064754', 'Staf Subbagian Umum', 'II/a', null, 'hisbulwathan-pppk', null],
            ['Haerani', '198304212025212050', '340064657', 'Staf Subbagian Umum', 'II/a', null, 'haerani-pppk', null],
            ['Idzar Ashari', '199611192025211032', '340064798', 'Staf Subbagian Umum', 'II/a', null, 'idzarashari-pppk', null],
            ['Alimuddin SE', '197707072025211072', '340064149', 'Staf BPS Kabupaten/Kota', 'III/a', null, 'alimuddin-pppk', null],
        ];
    }
}
