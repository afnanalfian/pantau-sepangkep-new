<?php

/*
| Portal Inovasi BPS Kabupaten Pangkajene dan Kepulauan
| (dipindahkan dari https://sites.google.com/view/portal-inovasi)
|
| 'route' = halaman di dalam PANTAU · 'url' = tautan ke luar (dibuka di tab baru)
*/

return [
    /*
    | nama  = nama aplikasi/sistem (judul besar)
    | label = bidang layanan (keterangan di bawahnya)
    | logo  = file di public/images/portal/ — ganti file PNG-nya untuk memperbarui logo
    */
    'layanan' => [
        ['key' => 'wbk', 'nama' => 'SI-EMOZI', 'label' => 'WBK', 'desc' => 'Zona Integritas menuju Wilayah Bebas dari Korupsi', 'logo' => 'si-emozi.png', 'url' => 'https://sites.google.com/view/si-emozi', 'tone' => 'navy'],
        ['key' => 'persuratan', 'nama' => 'SI-CATUR', 'label' => 'Persuratan', 'desc' => 'Pencatatan & penomoran surat masuk dan keluar', 'logo' => 'si-catur.png', 'route' => 'portal.sicatur', 'tone' => 'orange'],
        ['key' => 'keuangan', 'nama' => 'SI-KU', 'label' => 'Keuangan', 'desc' => 'Arsip SPJ, revisi anggaran, permindok', 'logo' => 'si-ku.png', 'route' => 'portal.siku', 'tone' => 'green'],
        ['key' => 'pekppp', 'nama' => 'SIGAP', 'label' => 'PEKPPP', 'desc' => 'Pemantauan & evaluasi kinerja pelayanan publik', 'logo' => 'sigap.png', 'url' => 'https://sites.google.com/view/pekppp-hub/beranda', 'tone' => 'blue'],
        ['key' => 'mitra', 'nama' => 'SIBEBAN', 'label' => 'Mitra', 'desc' => 'Monitoring honor & beban tugas mitra', 'logo' => 'sibeban.png', 'route' => 'sibeban.monitoring', 'tone' => 'blue'],
        ['key' => 'antrian', 'nama' => 'SIPANTAS', 'label' => 'Antrian PST', 'desc' => 'Antrean Pelayanan Statistik Terpadu', 'logo' => 'sipantas.png', 'url' => 'https://script.google.com/macros/s/AKfycbzoD1zwe4GgfK08eZT53pmcnSLZvspWwiTrDIqn6N8-aIeDkr1ENX72B1ITxlJG12f7/exec', 'tone' => 'orange'],
        ['key' => 'sibolu', 'nama' => 'SIBOLU', 'label' => 'Konsultasi', 'desc' => 'Layanan konsultasi via WhatsApp', 'logo' => 'sibolu.png', 'url' => 'https://api.whatsapp.com/send/?phone=6281908110739', 'tone' => 'green'],
        ['key' => 'pantau', 'nama' => 'PANTAU SEPANGKEP', 'label' => 'Pantau', 'desc' => 'Penugasan, beban kerja & kinerja pegawai', 'logo' => 'pantau.png', 'route' => 'dashboard', 'tone' => 'navy'],
    ],

    'sicatur' => [
        'srikandi' => 'https://srikandi.arsip.go.id/',
    ],

    'siku' => [
        ['label' => 'Arsip SPJ menurut bulan', 'emoji' => '🗄️', 'url' => 'https://drive.google.com/drive/u/0/folders/1pY2gGTFASSZ5D5s7MGd9q93CGfUjDeR4'],
        ['label' => 'Monitoring pencairan SPJ', 'emoji' => '📊', 'url' => 'https://script.google.com/macros/s/AKfycbydaJAPYqL65SmCv0BeWtW2bFZ16VXCtM7kxvzUNQVNvdvYqPPe63_JhypCSqnSsf4n/exec'],
        ['label' => 'Revisi anggaran', 'emoji' => '📝', 'url' => 'https://docs.google.com/spreadsheets/d/1CQDD2J07Hm39rzJ2QBJwDs6gUU5ejXSj/edit?gid=1973722993#gid=1973722993'],
        ['label' => 'Arsip SPJ menurut RO', 'emoji' => '🧾', 'url' => 'https://drive.google.com/drive/folders/1q2aOlunflr6X_Fe-oEcrKHV_0sXP0CRg'],
        ['label' => 'Kelengkapan dokumen SPJ', 'emoji' => '✅', 'url' => 'https://drive.google.com/drive/u/0/folders/1I-97nwWmhQKMkLfgmNAfGK6hD5tB_lYy'],
        ['label' => 'Permindok', 'emoji' => '📥', 'url' => 'https://drive.google.com/drive/folders/1O6w0ADHpjaEETsWDBG9zc7IILPhOgt-u'],
    ],

    'kepegawaian' => [
        'biodata' => 'https://docs.google.com/spreadsheets/d/15vESJy78KEVEpBnGbzlioAY73QD8B4SC1c_VAh4yPYI/edit?usp=sharing',
    ],
];
