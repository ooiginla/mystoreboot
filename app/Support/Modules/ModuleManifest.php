<?php

declare(strict_types=1);

namespace App\Support\Modules;

use Illuminate\Support\Collection;

final class ModuleManifest
{
    /**
     * @return Collection<string, array{enabled: bool, provider: class-string, depends_on: list<string>}>
     */
    public function all(): Collection
    {
        return collect(config('modules.registry', []));
    }

    /**
     * @return Collection<string, array{enabled: bool, provider: class-string, depends_on: list<string>}>
     */
    public function enabled(): Collection
    {
        return $this->all()->filter(
            fn (array $module): bool => (bool) ($module['enabled'] ?? false),
        );
    }

    /**
     * @return list<class-string>
     */
    public function enabledProviders(): array
    {
        return $this->enabled()
            ->pluck('provider')
            ->filter(fn (mixed $provider): bool => is_string($provider) && class_exists($provider))
            ->values()
            ->all();
    }
}
