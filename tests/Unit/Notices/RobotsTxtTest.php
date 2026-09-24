<?php

namespace Tests\Unit\Notices;

use App\Services\Notices\Http\RobotsTxt;
use Tests\TestCase;

class RobotsTxtTest extends TestCase
{
    public function test_empty_disallow_allows_everything(): void
    {
        $this->assertTrue((new RobotsTxt("User-agent: *\nDisallow:"))->isAllowed('/notices'));
        $this->assertTrue((new RobotsTxt(''))->isAllowed('/anything'));
    }

    public function test_disallow_all_blocks(): void
    {
        // www.nepalarmy.mil.np style
        $robots = new RobotsTxt("User-agent: *\nUser-agent: AdsBot-Google\nUser-agent: Bingbot\nDisallow: /");
        $this->assertFalse($robots->isAllowed('/notices/notices'));
    }

    public function test_longest_match_and_specific_agent_group(): void
    {
        $robots = new RobotsTxt(implode("\n", [
            'User-agent: *',
            'Disallow: /wp-admin/',
            'Allow: /wp-admin/admin-ajax.php',
            '',
            'User-agent: ExamsNepalBot',
            'Disallow: /private',
        ]));
        $this->assertTrue($robots->isAllowed('/wp-admin/', 'ExamsNepalBot/1.0'), 'our group replaces *');
        $this->assertFalse($robots->isAllowed('/private/x', 'ExamsNepalBot/1.0'));
        $this->assertFalse($robots->isAllowed('/wp-admin/x', 'OtherBot'));
        $this->assertTrue($robots->isAllowed('/wp-admin/admin-ajax.php', 'OtherBot'));
    }

    public function test_wildcards(): void
    {
        $robots = new RobotsTxt("User-agent: *\nDisallow: /*.pdf$");
        $this->assertFalse($robots->isAllowed('/uploads/a.pdf'));
        $this->assertTrue($robots->isAllowed('/uploads/a.pdf?x=1'));
    }
}
