<?php

/*
| Portal Inovasi BPS Kabupaten Pangkajene dan Kepulauan
| (dipindahkan dari https://sites.google.com/view/portal-inovasi)
|
| 'route' = halaman di dalam PANTAU · 'url' = tautan ke luar (dibuka di tab baru)
*/

return [
    'layanan' => [
        ['key' => 'wbk', 'label' => 'WBK', 'desc' => 'Zona Integritas menuju Wilayah Bebas dari Korupsi', 'url' => 'https://sites.google.com/view/si-emozi', 'icon' => 'flag', 'tone' => 'navy'],
        ['key' => 'persuratan', 'label' => 'Persuratan', 'desc' => 'SICATUR · pencatatan & penomoran surat', 'route' => 'portal.sicatur', 'icon' => 'mail', 'tone' => 'blue'],
        ['key' => 'keuangan', 'label' => 'Keuangan', 'desc' => 'SIKU · arsip SPJ, revisi anggaran, permindok', 'route' => 'portal.siku', 'icon' => 'chart', 'tone' => 'green'],
        ['key' => 'pekppp', 'label' => 'PEKPPP', 'desc' => 'Pemantauan & evaluasi kinerja penyelenggaraan pelayanan publik', 'url' => 'https://sites.google.com/view/pekppp-hub/beranda', 'icon' => 'check-circle', 'tone' => 'orange'],
        ['key' => 'mitra', 'label' => 'Mitra', 'desc' => 'SIBEBAN · monitoring honor & beban tugas mitra', 'route' => 'sibeban.monitoring', 'icon' => 'users', 'tone' => 'blue'],
        ['key' => 'antrian', 'label' => 'Antrian PST', 'desc' => 'Antrean Pelayanan Statistik Terpadu', 'url' => 'https://script.google.com/macros/s/AKfycbzoD1zwe4GgfK08eZT53pmcnSLZvspWwiTrDIqn6N8-aIeDkr1ENX72B1ITxlJG12f7/exec', 'icon' => 'inbox', 'tone' => 'green'],
        ['key' => 'sibolu', 'label' => 'SIBOLU', 'desc' => 'Layanan konsultasi via WhatsApp', 'url' => 'https://api.whatsapp.com/send/?phone=6281908110739', 'icon' => 'chat', 'tone' => 'orange'],
        ['key' => 'pantau', 'label' => 'PANTAU', 'desc' => 'Penugasan, beban kerja & kinerja pegawai', 'route' => 'dashboard', 'icon' => 'gauge', 'tone' => 'navy'],
    ],

    'sicatur' => [
        'srikandi' => 'https://srikandi.arsip.go.id/',
    ],

    'siku' => [
        ['label' => 'Arsip SPJ menurut bulan', 'emoji' => '🗄️', 'url' => 'https://drive.google.com/drive/u/0/folders/1pY2gGTFASSZ5D5s7MGd9q93CGfUjDeR4'],
        ['label' => 'Monitoring pencairan SPJ', 'emoji' => '📊', 'url' => 'https://docs.google.com/spreadsheets/d/19verMBNt4-wUiUKS8pS9aTxqvZmYJDOOIWRyLH8FFEU/edit?gid=0#gid=0'],
        ['label' => 'Revisi anggaran', 'emoji' => '📝', 'url' => 'https://docs.google.com/spreadsheets/d/1CQDD2J07Hm39rzJ2QBJwDs6gUU5ejXSj/edit?gid=1973722993#gid=1973722993'],
        ['label' => 'Arsip SPJ menurut RO', 'emoji' => '🧾', 'url' => 'https://drive.google.com/drive/folders/1q2aOlunflr6X_Fe-oEcrKHV_0sXP0CRg'],
        ['label' => 'Kelengkapan dokumen SPJ', 'emoji' => '✅', 'url' => 'https://drive.google.com/drive/u/0/folders/1I-97nwWmhQKMkLfgmNAfGK6hD5tB_lYy'],
        ['label' => 'Permindok', 'emoji' => '📥', 'url' => 'https://drive.google.com/drive/folders/1O6w0ADHpjaEETsWDBG9zc7IILPhOgt-u'],
    ],

    'kepegawaian' => [
        'biodata' => 'https://docs.google.com/spreadsheets/d/15vESJy78KEVEpBnGbzlioAY73QD8B4SC1c_VAh4yPYI/edit?usp=sharing',
    ],
];
