<?php

namespace Tests\Unit\Reservation;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class ReservationIlluminateImportTest extends TestCase
{
    public function test_application_and_domain_sources_do_not_import_illuminate(): void
    {
        $roots = [
            dirname(__DIR__, 3).'/app/Modules/Reservation/Application',
            dirname(__DIR__, 3).'/app/Modules/Reservation/Domain',
        ];

        $imports = [];

        foreach ($roots as $root) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

            /** @var SplFileInfo $file */
            foreach ($files as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $contents = (string) file_get_contents($file->getPathname());

                if (preg_match('/^use Illuminate\\\\/m', $contents) === 1) {
                    $imports[] = $file->getPathname();
                }
            }
        }

        $this->assertSame([], $imports);
    }
}
