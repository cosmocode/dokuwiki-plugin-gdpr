<?php

namespace dokuwiki\plugin\gdpr\test;

/**
 * Page IP cleaning tests for the gdpr plugin
 *
 * @group plugin_gdpr
 * @group plugins
 */
class CleanIpTest extends \DokuWikiTest
{
    protected $pluginsEnabled = ['gdpr'];

    protected $yesterday;

    public function setUp()
    {
        parent::setUp();

        global $ID;
        $ID = 'some:page';
        $changelogFN = metaFN($ID, '.changes');
        io_makeFileDir($changelogFN);
        $this->yesterday = time() - 60 * 60 * 24;
        file_put_contents($changelogFN, '1522767335	192.168.0.105	C	sidebar		created		36
1522767349	192.168.0.105	E	sidebar				12
1523956708	192.168.0.105	E	sidebar	admin			23
1524145287	192.168.0.105	E	sidebar	admin			19
1524464616	192.168.0.105	E	sidebar	admin	ok		0
');
        $handle = fopen($changelogFN, 'ab');
        $recentChangelogLine = $this->yesterday . "	192.168.0.105	E	sidebar	admin	ok		0\n";
        fwrite($handle, $recentChangelogLine);
        fclose($handle);
    }

    public function testCleaningChangelog()
    {
        global $ID;
        trigger_event('INDEXER_TASKS_RUN', $data);
        $actualChangelogContent = file_get_contents(metaFN($ID, '.changes'));

        $expectedChangelogContent = '1522767335	             	C	sidebar		created		36
1522767349	             	E	sidebar				12
1523956708	             	E	sidebar	admin			23
1524145287	             	E	sidebar	admin			19
1524464616	             	E	sidebar	admin	ok		0
' . $this->yesterday . "	192.168.0.105	E	sidebar	admin	ok		0\n";
        $this->assertEquals($expectedChangelogContent, $actualChangelogContent);

    }

    public function dataProvider_validateStartPosition()
    {
        return [
            [
                0,
                0,
                'Startposition 0 should remain 0',
            ],
            [
                48,
                48,
                'valid start position should remain unchanged',
            ],
            [
                55,
                0,
                'Startposition in the middle of a string should become 0',
            ],
            [
                350,
                0,
                'Startposition that is outside the file should become 0',
            ],
        ];
    }

    /**
     * @dataProvider dataProvider_validateStartPosition
     *
     * @param $inputStartPosition
     * @param $expectedStartPosition
     * @param $msg
     */
    public function test_validateStartPosition($inputStartPosition, $expectedStartPosition, $msg)
    {
        global $ID;
        /** @var \action_plugin_gdpr_oldips $changelogCleaner */
        $changelogCleaner = plugin_load('action', 'gdpr_oldips');

        $actualStartPosition = $changelogCleaner->validateStartPosition($inputStartPosition, metaFN($ID, '.changes'));

        $this->assertEquals($expectedStartPosition, $actualStartPosition, $msg);
    }

}
