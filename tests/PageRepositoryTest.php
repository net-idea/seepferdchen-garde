<?php
declare(strict_types=1);

namespace Website\Tests;

use PHPUnit\Framework\TestCase;
use Website\PageRepository;

final class PageRepositoryTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/cms_content_' . bin2hex(random_bytes(6));

        if (!is_dir($this->tmpDir)) {
            mkdir($this->tmpDir, 0777, true);
        }

        file_put_contents($this->tmpDir . '/_pages.php', "<?php\nreturn [];\n");

        if (!defined('CMS_CONTENT_DIR')) {
            define('CMS_CONTENT_DIR', $this->tmpDir);
        }
    }

    protected function tearDown(): void
    {
        @unlink($this->tmpDir . '/_pages.php');
        @rmdir($this->tmpDir);
    }

    public function testItCanBeInstantiated(): void
    {
        $this->assertInstanceOf(PageRepository::class, new PageRepository($this->tmpDir));
    }
}
