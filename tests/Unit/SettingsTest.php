<?php

namespace samuelreichor\coPilot\tests\Unit;

use PHPUnit\Framework\TestCase;
use samuelreichor\coPilot\enums\SectionAccess;
use samuelreichor\coPilot\models\Settings;

class SettingsTest extends TestCase
{
    public function testGetSectionAccessLevelDefaultsToReadWrite(): void
    {
        $settings = new Settings();

        $result = $settings->getSectionAccessLevel('some-unknown-uid');

        $this->assertSame(SectionAccess::ReadWrite, $result);
    }

    public function testGetSectionAccessLevelWithConfiguredValues(): void
    {
        $settings = new Settings();
        $settings->sectionAccess = [
            'uid-blog' => 'readWrite',
            'uid-products' => 'readOnly',
            'uid-internal' => 'blocked',
        ];

        $this->assertSame(SectionAccess::ReadWrite, $settings->getSectionAccessLevel('uid-blog'));
        $this->assertSame(SectionAccess::ReadOnly, $settings->getSectionAccessLevel('uid-products'));
        $this->assertSame(SectionAccess::Blocked, $settings->getSectionAccessLevel('uid-internal'));
    }

    public function testGetSectionAccessLevelWithInvalidValue(): void
    {
        $settings = new Settings();
        $settings->sectionAccess = [
            'uid-test' => 'invalidValue',
        ];

        // Invalid values should default to ReadWrite
        $this->assertSame(SectionAccess::ReadWrite, $settings->getSectionAccessLevel('uid-test'));
    }
}
