<?php

namespace Tests\Unit\Room;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class RoomIlluminateImportTest extends TestCase
{
    public function test_application_domain_and_room_not_found_sources_do_not_import_illuminate(): void
    {
        $roots = [
            dirname(__DIR__, 3).'/app/Modules/Room/Application',
            dirname(__DIR__, 3).'/app/Modules/Room/Domain',
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
