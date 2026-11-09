@include('errors.layout', ['code' => 403, 'title' => 'Akses ditolak', 'message' => $exception->getMessage() ?: 'Anda tidak memiliki akses ke halaman ini.'])
