<?php

namespace Tests\Unit\Architecture;

use Tests\TestCase;

/**
 * Architecture test enforcing the Single Environment Gate.
 *
 * No file under app/ may read APP_ENV, app.env, pos.midtrans.*, or raw env
 * ($_ENV, $_SERVER, getenv, env()) except App\Payments\Environment\EnvironmentClass.
 */
class SingleEnvironmentGateTest extends TestCase
{
    public function testNoEnvBranchingOutsideEnvironmentClass(): void
    {
        $allowedFile = 'app/Payments/Environment/EnvironmentClass.php';
        $violations = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path())
        );

        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getPathname();
            $relativePath = str_replace('\\', '/', str_replace(base_path() . DIRECTORY_SEPARATOR, '', $path));

            if ($relativePath === $allowedFile) {
                continue;
            }

            $content = file_get_contents($path);
            if ($content === false) {
                continue;
            }
            $lines = explode("\n", $content);

            foreach ($lines as $lineNumber => $line) {
                $trimmed = trim($line);

                // Skip comments
                if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '#') || str_starts_with($trimmed, '*')) {
                    continue;
                }

                // Forbidden patterns
                $forbiddenPatterns = [
                    'app.env' => 'reads app.env',
                    'APP_ENV' => 'reads APP_ENV',
                    "config('pos.midtrans" => 'reads pos.midtrans config',
                    'config("pos.midtrans' => 'reads pos.midtrans config',
                    "env('MIDTRANS" => 'reads MIDTRANS_ env',
                    'env("MIDTRANS' => 'reads MIDTRANS_ env',
                    "env('POS_MIDTRANS" => 'reads POS_MIDTRANS_ env',
                    'env("POS_MIDTRANS' => 'reads POS_MIDTRANS_ env',
                    'getenv(' => 'calls getenv()',
                    '$_ENV[' => 'reads $_ENV',
                    '$_SERVER[' => 'reads $_SERVER',
                    'app()->environment' => 'calls app()->environment()',
                    '->environment(' => 'calls ->environment()',
                ];

                foreach ($forbiddenPatterns as $pattern => $description) {
                    if (str_contains($line, $pattern)) {
                        $violations[] = sprintf(
                            "%s:%d - %s: %s",
                            $relativePath,
                            $lineNumber + 1,
                            $description,
                            trim($line)
                        );
                    }
                }
            }
        }

        $this->assertEmpty($violations, "Forbidden environment probes found:\n" . implode("\n", $violations));
    }

    public function testEnvironmentClassIsTheOnlyGate(): void
    {
        // Verify the allowed file exists and contains the gate methods
        $allowedPath = app_path('Payments/Environment/EnvironmentClass.php');
        $this->assertFileExists($allowedPath);

        $content = file_get_contents($allowedPath);
        $this->assertIsString($content);
        $this->assertStringContainsString('public static function tier()', $content);
        $this->assertStringContainsString('public static function isProduction()', $content);
        $this->assertStringContainsString('public static function isEnabled()', $content);
        $this->assertStringContainsString('public static function configErrors()', $content);
        $this->assertStringContainsString('public static function failClosedReasons()', $content);
        $this->assertStringContainsString('public static function isFailClosed()', $content);
        $this->assertStringContainsString('public static function allowsMock()', $content);
    }
}