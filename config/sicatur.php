<?php

/*
| SICATUR — disalin dari Config.gs aplikasi Apps Script lama.
|
| form   : isian sesuai urutan di layar. kolom = label (nama kolom di spreadsheet lama), attr = kolom database.
|          tipe: klasifikasi | tanggal | pembuat | pilihan | deskripsi | tautan
| tanggal: kolom tanggal utama (dasar nomor & urutan). FP memakai tanggal terbit (hari ini) sebagai Tanggal Surat.
| kunciUbah : isian yang tidak boleh diubah setelah nomor terbit (nomor urut dihitung menurutnya)
| urutan : nomor urut mulai ulang per 'tahun', atau per 'bulan' + kolom (Form Permintaan: per bulan per tim teknis)
| digit  : 3 → 001 · 0 → 1
*/

$timTeknis = [
    'SubBagian Umum/TU' => '01',
    'Sosial' => '02',
    'Produksi' => '03',
    'Distribusi' => '04',
    'Nerwilis' => '05',
    'IPDS' => '06',
];

$jenisFp = [
    'Belanja Bahan' => 'KU.310',
    'Belanja Barang' => 'KU.320',
    'Belanja Jasa' => 'KU.330',
    'Belanja Perjalanan' => 'KU.340',
    'Belanja Pegawai' => 'KU.350',
    'Meeting Dalam Kota' => 'KU.360',
    'Meeting Luar Kota' => 'KU.370',
    'Kombinasi' => 'KU.380',
];

return [
    'nama_instansi' => 'Badan Pusat Statistik (BPS) Kabupaten Pangkep',
    'kode_satker' => '73090',
    'zona_waktu' => 'Asia/Makassar',
    'url_pedoman' => 'https://drive.google.com/file/d/1XQPPgskHfjWozWJK8zyXKYYHe1cT-W4A/view?usp=drive_link',
    'per_halaman' => 20,

    'sifat' => ['Biasa' => 'B-', 'Terbatas' => 'T-', 'Rahasia' => 'R-'],
    'tim_teknis' => $timTeknis,
    'jenis_fp' => $jenisFp,

    // Alur Link Surat (SK): status Proses saat nomor terbit, Selesai saat link surat diisi
    'lengkapi' => ['jenis' => 'sk', 'label' => 'Link Surat', 'proses' => 'Proses', 'selesai' => 'Selesai'],

    'jenis' => [
        'st' => [
            'label' => 'Surat Tugas',
            'judul' => 'Surat Tugas / Perintah',
            'sheet' => 'Surat Tugas/Perintah',
            'form' => [
                ['kolom' => 'Kode Klasifikasi', 'tipe' => 'klasifikasi', 'attr' => 'klasifikasi'],
                ['kolom' => 'Tanggal Surat', 'tipe' => 'tanggal', 'attr' => 'tanggal'],
                ['kolom' => 'Pembuat Surat', 'tipe' => 'pembuat', 'attr' => 'pembuat'],
                ['kolom' => 'Tujuan Surat', 'tipe' => 'deskripsi', 'attr' => 'tujuan'],
                ['kolom' => 'Perihal', 'tipe' => 'deskripsi', 'attr' => 'perihal'],
            ],
            'tanggal' => 'tanggal',
            'kunciUbah' => [],
            'urutan' => 'tahun',
            'digit' => 3,
        ],
        'fp' => [
            'label' => 'Form Permintaan',
            'judul' => 'Form Permintaan',
            'sheet' => 'Form Permintaan',
            'form' => [
                ['kolom' => 'Tim Teknis', 'tipe' => 'pilihan', 'attr' => 'tim_teknis', 'opsi' => array_keys($timTeknis)],
                ['kolom' => 'Jenis FP', 'tipe' => 'pilihan', 'attr' => 'jenis_fp', 'opsi' => array_keys($jenisFp)],
                ['kolom' => 'Tanggal FP', 'tipe' => 'tanggal', 'attr' => 'tanggal_fp'],
                ['kolom' => 'Pembuat FP', 'tipe' => 'pembuat', 'attr' => 'pembuat'],
                ['kolom' => 'Perihal', 'tipe' => 'deskripsi', 'attr' => 'perihal'],
            ],
            'tanggal' => null,      // Tanggal Surat = tanggal terbit (hari ini)
            'kunciUbah' => ['tim_teknis'],
            'urutan' => 'bulan_tim',
            'digit' => 3,
        ],
        'sk' => [
            'label' => 'SK',
            'judul' => 'Surat Keputusan (SK)',
            'sheet' => 'Surat Keputusan (SK)',
            'form' => [
                ['kolom' => 'Tanggal Surat', 'tipe' => 'tanggal', 'attr' => 'tanggal'],
                ['kolom' => 'Pembuat Surat', 'tipe' => 'pembuat', 'attr' => 'pembuat'],
                ['kolom' => 'Perihal SK', 'tipe' => 'deskripsi', 'attr' => 'perihal'],
                ['kolom' => 'Nama Petugas', 'tipe' => 'tautan', 'attr' => 'nama_petugas'],
                ['kolom' => 'Honor', 'tipe' => 'deskripsi', 'attr' => 'honor'],
                ['kolom' => 'Mulai Berlaku', 'tipe' => 'tanggal', 'attr' => 'tanggal_mulai'],
                ['kolom' => 'Selesai Berlaku', 'tipe' => 'tanggal', 'attr' => 'tanggal_selesai'],
            ],
            'tanggal' => 'tanggal',
            'kunciUbah' => [],
            'urutan' => 'tahun',
            'digit' => 0,
        ],
        'bast' => [
            'label' => 'BAST',
            'judul' => 'Berita Acara Serah Terima (BAST)',
            'sheet' => 'BAST',
            'form' => [
                ['kolom' => 'Kode Klasifikasi', 'tipe' => 'klasifikasi', 'attr' => 'klasifikasi'],
                ['kolom' => 'Tanggal BAST', 'tipe' => 'tanggal', 'attr' => 'tanggal'],
                ['kolom' => 'Pembuat BAST', 'tipe' => 'pembuat', 'attr' => 'pembuat'],
                ['kolom' => 'Perihal', 'tipe' => 'deskripsi', 'attr' => 'perihal'],
            ],
            'tanggal' => 'tanggal',
            'kunciUbah' => [],
            'urutan' => 'tahun',
            'digit' => 3,
        ],
        'bapp' => [
            'label' => 'BAPP',
            'judul' => 'Berita Acara Penyelesaian Pekerjaan (BAPP)',
            'sheet' => 'BAPP',
            'form' => [
                ['kolom' => 'Kode Klasifikasi', 'tipe' => 'klasifikasi', 'attr' => 'klasifikasi'],
                ['kolom' => 'Tanggal BAPP', 'tipe' => 'tanggal', 'attr' => 'tanggal'],
                ['kolom' => 'Pembuat BAPP', 'tipe' => 'pembuat', 'attr' => 'pembuat'],
                ['kolom' => 'Perihal', 'tipe' => 'deskripsi', 'attr' => 'perihal'],
            ],
            'tanggal' => 'tanggal',
            'kunciUbah' => [],
            'urutan' => 'tahun',
            'digit' => 3,
        ],
        'ba' => [
            'label' => 'BA',
            'judul' => 'Berita Acara (BA)',
            'sheet' => 'BA',
            'form' => [
                ['kolom' => 'Kode Klasifikasi', 'tipe' => 'klasifikasi', 'attr' => 'klasifikasi'],
                ['kolom' => 'Tanggal BA', 'tipe' => 'tanggal', 'attr' => 'tanggal'],
                ['kolom' => 'Pembuat BA', 'tipe' => 'pembuat', 'attr' => 'pembuat'],
                ['kolom' => 'Perihal', 'tipe' => 'deskripsi', 'attr' => 'perihal'],
            ],
            'tanggal' => 'tanggal',
            'kunciUbah' => [],
            'urutan' => 'tahun',
            'digit' => 3,
        ],
    ],

    // Data versi PANTAU sebelumnya (hanya tampil di Riwayat)
    'lama' => ['keluar' => 'Surat Keluar', 'masuk' => 'Surat Masuk'],
];
