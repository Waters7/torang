<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Pengaturan aplikasi yang bisa diubah dari panel admin.
 *
 * Nilai tersimpan di tabel `settings` (kolom JSON) dan di-cache utuh;
 * key yang belum tersimpan memakai default dari config/settings.php.
 */
class SettingsService
{
    public const CACHE_KEY = 'settings.all';

    public function __construct(private readonly Cache $cache) {}

    public function get(string $key, mixed $default = null): mixed
    {
        $stored = $this->stored();

        if (array_key_exists($key, $stored)) {
            return $stored[$key];
        }

        return array_key_exists($key, $this->defaults()) ? $this->defaults()[$key] : $default;
    }

    /**
     * Semua pengaturan: default ditimpa nilai yang tersimpan.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return array_merge($this->defaults(), $this->stored());
    }

    public function set(string $key, mixed $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value]);

        $this->flush();
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        $this->flush();
    }

    /**
     * Tulis default untuk key yang belum ada, tanpa menimpa nilai yang sudah diubah admin.
     */
    public function seedDefaults(): void
    {
        foreach ($this->defaults() as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }

        $this->flush();
    }

    public function flush(): void
    {
        $this->cache->forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return config('settings.defaults', []);
    }

    /**
     * @return array<string, mixed>
     */
    private function stored(): array
    {
        return $this->cache->rememberForever(
            self::CACHE_KEY,
            fn (): array => Setting::query()->pluck('value', 'key')->all(),
        );
    }
}
