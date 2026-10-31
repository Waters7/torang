{{-- Favicon TORANG untuk halaman publik dan panel. Penanda versi memaksa browser mengganti ikon lama yang tersimpan di cache. --}}
@php($version = config('torang.version'))
<link rel="icon" href="/favicon.ico?v={{ $version }}" sizes="any">
<link rel="icon" href="/favicon.svg?v={{ $version }}" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png?v={{ $version }}">
