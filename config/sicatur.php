<?php

/*
| SICATUR — jenis nomor yang diterbitkan (mengikuti SICATUR lama di Google Sites).
|
| format : pola nomor. Kode yang tersedia:
|          {nomor} {klasifikasi} {sifat} {hari} {bulan} {bulan_romawi} {tahun}
|          Format bisa diubah admin di SICATUR › Pengaturan (tersimpan di database).
| digit  : panjang nomor urut (3 → 001, 0 → tanpa nol di depan)
| klasifikasi : kode klasifikasi bawaan (dipakai bila format memuat {klasifikasi})
| isian  : kolom form selain tanggal & klasifikasi. Label = teks yang tampil di form.
|          perihal (wajib) · pihak · pegawai (pilih pegawai) · pelaksanaan (tanggal mulai–selesai) · tempat
*/

return [
    'jenis' => [
        'st' => [
            'label' => 'Surat Tugas / Perintah',
            'singkat' => 'Surat Tugas',
            'format' => 'B-{nomor}/73090/{klasifikasi}/{tahun}',
            'digit' => 3,
            'klasifikasi' => 'SS.150',
            'warna' => 'blue',
            'isian' => [
                'perihal' => 'Maksud / kegiatan penugasan',
                'pegawai' => 'Pegawai yang ditugaskan',
                'pelaksanaan' => 'Tanggal pelaksanaan',
                'tempat' => 'Tempat / tujuan',
            ],
        ],
        'fp' => [
            'label' => 'Form Permintaan',
            'singkat' => 'Form Permintaan',
            'format' => '{nomor}/73090/{klasifikasi}/{hari}/{bulan}/{tahun}',
            'digit' => 3,
            'klasifikasi' => 'KU.320',
            'warna' => 'green',
            'isian' => [
                'perihal' => 'Barang / keperluan yang diminta',
                'pihak' => 'Diminta oleh (tim / unit)',
            ],
        ],
        'sk' => [
            'label' => 'Surat Keputusan (SK)',
            'singkat' => 'SK',
            'format' => 'NOMOR {nomor} TAHUN {tahun}',
            'digit' => 0,
            'klasifikasi' => null,
            'warna' => 'orange',
            'isian' => [
                'perihal' => 'Tentang',
            ],
        ],
        'bast' => [
            'label' => 'Berita Acara Serah Terima (BAST)',
            'singkat' => 'BAST',
            'format' => 'BAST-{nomor}/73090/{klasifikasi}/{tahun}',
            'digit' => 3,
            'klasifikasi' => 'SS.230',
            'warna' => 'navy',
            'isian' => [
                'perihal' => 'Barang / pekerjaan yang diserahterimakan',
                'pihak' => 'Pihak kedua',
            ],
        ],
        'bapp' => [
            'label' => 'Berita Acara Penyelesaian Pekerjaan (BAPP)',
            'singkat' => 'BAPP',
            'format' => 'BAPP-{nomor}/73090/{klasifikasi}/{tahun}',
            'digit' => 3,
            'klasifikasi' => 'SS.230',
            'warna' => 'blue',
            'isian' => [
                'perihal' => 'Pekerjaan yang diselesaikan',
                'pihak' => 'Pelaksana pekerjaan',
            ],
        ],
        'ba' => [
            'label' => 'Berita Acara (BA)',
            'singkat' => 'BA',
            'format' => 'BA-{nomor}/73090/{klasifikasi}/{tahun}',
            'digit' => 3,
            'klasifikasi' => null,
            'warna' => 'green',
            'isian' => [
                'perihal' => 'Berita acara tentang',
                'pihak' => 'Pihak terkait (opsional)',
            ],
        ],
    ],

    // Data lama dari versi sebelumnya (hanya tampil di Riwayat)
    'lama' => ['keluar' => 'Surat Keluar', 'masuk' => 'Surat Masuk'],

    // Saran kode klasifikasi (bisa diketik bebas)
    'klasifikasi' => ['SS.150', 'SS.230', 'KU.320', 'KP.320', 'KP.650', 'KU.010', 'KU.100', 'HM.340', 'PL.200', 'PR.300', 'UM.100'],
];
