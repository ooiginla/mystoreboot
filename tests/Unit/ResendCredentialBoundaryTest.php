<?php

namespace Tests\Unit;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

class ResendCredentialBoundaryTest extends TestCase
{
    public function test_resend_credentials_are_not_referenced_by_frontend_assets_or_html(): void
    {
        $roots = [resource_path('js'), resource_path('views'), public_path()];
        $extensions = ['blade.php', 'html', 'js', 'jsx', 'mjs', 'ts', 'tsx', 'vue'];

        foreach ($roots as $root) {
            if (! is_dir($root)) {
                continue;
            }

            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

            /** @var SplFileInfo $file */
            foreach ($files as $file) {
                if (! $file->isFile() || ! $this->hasFrontendExtension($file->getFilename(), $extensions)) {
                    continue;
                }

                $contents = (string) file_get_contents($file->getPathname());
                $this->assertDoesNotMatchRegularExpression(
                    '/\b(?:VITE_)?RESEND_API_KEY\b|api\.resend\.com|\bre_[A-Za-z0-9_-]{20,}/i',
                    $contents,
                    "Resend credentials or direct client API access found in {$file->getPathname()}",
                );
            }
        }

        foreach ([base_path('vite.config.js'), base_path('package.json')] as $frontendConfig) {
            if (! is_file($frontendConfig)) {
                continue;
            }

            $this->assertDoesNotMatchRegularExpression(
                '/\b(?:VITE_)?RESEND_API_KEY\b|api\.resend\.com|\bre_[A-Za-z0-9_-]{20,}/i',
                (string) file_get_contents($frontendConfig),
                "Resend credentials or direct client API access found in {$frontendConfig}",
            );
        }
    }

    /** @param list<string> $extensions */
    private function hasFrontendExtension(string $filename, array $extensions): bool
    {
        foreach ($extensions as $extension) {
            if (str_ends_with($filename, '.'.$extension)) {
                return true;
            }
        }

        return false;
    }
}
