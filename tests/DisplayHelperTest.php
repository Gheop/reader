<?php

use PHPUnit\Framework\TestCase;
use Gheop\Reader\DisplayHelper;

/**
 * Comprehensive tests for DisplayHelper
 */
class DisplayHelperTest extends TestCase
{
    private string $testGitHeadPath;
    private string $testVersionPath;

    protected function setUp(): void
    {
        $this->testGitHeadPath = sys_get_temp_dir() . '/test_git_head_' . uniqid();
        $this->testVersionPath = sys_get_temp_dir() . '/test_version_' . uniqid();
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testGitHeadPath)) {
            unlink($this->testGitHeadPath);
        }
        if (file_exists($this->testVersionPath)) {
            unlink($this->testVersionPath);
        }
    }

    public function testGetBranchDisplayValid(): void
    {
        file_put_contents($this->testGitHeadPath, "ref: refs/heads/dev\n");

        $result = DisplayHelper::getBranchDisplay($this->testGitHeadPath);
        $this->assertEquals('dev', $result);
    }

    public function testGetBranchDisplayWithVersion(): void
    {
        file_put_contents($this->testGitHeadPath, "ref: refs/heads/feature\n");
        file_put_contents($this->testVersionPath, "1.2.3\n");

        $result = DisplayHelper::getBranchDisplay($this->testGitHeadPath, $this->testVersionPath);
        $this->assertEquals('feature 1.2.3', $result);
    }

    public function testGetBranchDisplayFileNotExists(): void
    {
        $result = DisplayHelper::getBranchDisplay('/nonexistent/path');
        $this->assertEquals('', $result);
    }

    public function testGetBranchDisplayEmptyFile(): void
    {
        file_put_contents($this->testGitHeadPath, '');

        $result = DisplayHelper::getBranchDisplay($this->testGitHeadPath);
        $this->assertEquals('', $result);
    }

    public function testGetBranchDisplayInvalidFormat(): void
    {
        file_put_contents($this->testGitHeadPath, "invalid content");

        $result = DisplayHelper::getBranchDisplay($this->testGitHeadPath);
        $this->assertEquals('', $result);
    }

    public function testShouldDisplayBranchMaster(): void
    {
        $result = DisplayHelper::shouldDisplayBranch('master');
        $this->assertFalse($result);
    }

    public function testShouldDisplayBranchMasterWithVersion(): void
    {
        $result = DisplayHelper::shouldDisplayBranch('master 1.0.0');
        $this->assertFalse($result);
    }

    public function testShouldDisplayBranchDev(): void
    {
        $result = DisplayHelper::shouldDisplayBranch('dev');
        $this->assertTrue($result);
    }

    public function testShouldDisplayBranchFeature(): void
    {
        $result = DisplayHelper::shouldDisplayBranch('feature/new-feature');
        $this->assertTrue($result);
    }

    public function testFormatBranchBadge(): void
    {
        $result = DisplayHelper::formatBranchBadge('dev 1.0.0');

        $this->assertStringContainsString('dev 1.0.0', $result);
        $this->assertStringContainsString('<span', $result);
        $this->assertStringContainsString('</span>', $result);
        $this->assertStringContainsString('#d43f57', $result);
    }

    public function testFormatBranchBadgeEscaping(): void
    {
        $result = DisplayHelper::formatBranchBadge('dev<script>alert(1)</script>');

        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }

    public function testBuildNavigationMenu(): void
    {
        $result = DisplayHelper::buildNavigationMenu();

        $this->assertStringContainsString('<nav>', $result);
        $this->assertStringContainsString('id="menu"', $result);
        $this->assertStringContainsString('id="fall"', $result);
        $this->assertStringContainsString('onclick="view(\'all\')"', $result);
        $this->assertStringContainsString('id="up"', $result);
        $this->assertStringContainsString('id="export"', $result);
        $this->assertStringContainsString('opml_export.php', $result);
    }

    public function testBuildContentStructure(): void
    {
        $result = DisplayHelper::buildContentStructure();

        $this->assertStringContainsString('id="menu-resizer"', $result);
        $this->assertStringContainsString('<main>', $result);
        $this->assertStringContainsString('</main>', $result);
        $this->assertStringContainsString('<footer>', $result);
        $this->assertStringContainsString('</footer>', $result);
    }

    public function testBuildWelcomeMessage(): void
    {
        $result = DisplayHelper::buildWelcomeMessage();

        $this->assertStringContainsString('<h2>', $result);
        $this->assertStringContainsString('Gheop Reader', $result);
        $this->assertStringContainsString('<fieldset>', $result);
        $this->assertStringContainsString('<legend>Simple</legend>', $result);
        $this->assertStringContainsString('<legend>Comment faire ?</legend>', $result);
        $this->assertStringContainsString('vie privée', $result);
    }

    public function testBuildSearchForm(): void
    {
        $result = DisplayHelper::buildSearchForm();

        $this->assertStringContainsString('id="sdiv"', $result);
        $this->assertStringContainsString('<form', $result);
        $this->assertStringContainsString('id="s"', $result);
        $this->assertStringContainsString('id="bs"', $result);
        $this->assertStringContainsString('search(', $result);
    }

    public function testBuildUserMenu(): void
    {
        $result = DisplayHelper::buildUserMenu('testuser');

        $this->assertStringContainsString('testuser', $result);
        $this->assertStringContainsString('id="disconnect"', $result);
        $this->assertStringContainsString('?a=destroy', $result);
        $this->assertStringContainsString('Se déconnecter', $result);
    }

    public function testBuildUserMenuEscaping(): void
    {
        $result = DisplayHelper::buildUserMenu('<script>alert(1)</script>');

        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }

    public function testBuildGuestMenu(): void
    {
        $result = DisplayHelper::buildGuestMenu('reader.gheop.com');

        $this->assertStringContainsString('S\'enregister', $result);
        $this->assertStringContainsString('S\'identifier', $result);
        $this->assertStringContainsString('gheop.com/register', $result);
        $this->assertStringContainsString('page=reader.gheop.com', $result);
    }

    public function testBuildGuestMenuEscaping(): void
    {
        $result = DisplayHelper::buildGuestMenu('site.com/"><script>alert(1)</script>');

        $this->assertStringNotContainsString('<script>', $result);
    }

    public function testBuildErrorDiv(): void
    {
        $result = DisplayHelper::buildErrorDiv();

        $this->assertStringContainsString('id="error"', $result);
        $this->assertStringContainsString('display:none', $result);
    }

    public function testSanitizeClassNameValid(): void
    {
        $result = DisplayHelper::sanitizeClassName('valid-class_name123');
        $this->assertEquals('valid-class_name123', $result);
    }

    public function testSanitizeClassNameInvalid(): void
    {
        $result = DisplayHelper::sanitizeClassName('invalid class@name!');
        $this->assertEquals('invalidclassname', $result);
    }

    public function testSanitizeClassNameSpecialChars(): void
    {
        $result = DisplayHelper::sanitizeClassName('test<script>alert(1)</script>');
        $this->assertEquals('testscriptalert1script', $result);
    }

    public function testBuildMetaTags(): void
    {
        $result = DisplayHelper::buildMetaTags();

        $this->assertStringContainsString('<meta', $result);
        $this->assertStringContainsString('charset=utf-8', $result);
        $this->assertStringContainsString('favicon.png', $result);
        $this->assertStringContainsString('viewport', $result);
        $this->assertStringContainsString('mobile-web-app-capable', $result);
        $this->assertStringContainsString('apple-mobile-web-app-status-bar-style', $result);
    }

    public function testBuildMetaTagsDescription(): void
    {
        $result = DisplayHelper::buildMetaTags();

        $this->assertStringContainsString('name="description"', $result);
        $this->assertStringContainsString('RSS', $result);
    }
}
