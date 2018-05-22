<?php

namespace dokuwiki\plugin\gdpr\test;

/**
 * Media IP cleaning tests for the gdpr plugin
 *
 * @group plugin_gdpr
 * @group plugins
 */
class CleanMediaTest extends \DokuWikiTest
{
    protected $pluginsEnabled = ['gdpr'];

    protected $yesterday;

    protected $mediaID = 'galaxy:andromeda.jpg';

    public function setUp()
    {
        parent::setUp();

        $changelogFN = mediaMetaFN($this->mediaID, '.changes');
        io_makeFileDir($changelogFN);
        $this->yesterday = time() - 60 * 60 * 24;
        file_put_contents($changelogFN, '1522767335	192.168.0.105	C	galaxy:andromeda.jpg		created		36
1522767349	192.168.0.105	E	galaxy:andromeda.jpg				12
1523956708	192.168.0.105	E	galaxy:andromeda.jpg	admin			23
1524145287	192.168.0.105	E	galaxy:andromeda.jpg	admin			19
1524464616	192.168.0.105	E	galaxy:andromeda.jpg	admin	ok		0
');
        $handle = fopen($changelogFN, 'ab');
        $recentChangelogLine = $this->yesterday . "	192.168.0.105	E	galaxy:andromeda.jpg	admin	ok		0\n";
        fwrite($handle, $recentChangelogLine);
        fclose($handle);
    }

    public function testCleaningChangelog()
    {
        $eventData = [
            'isMedia' => true,
            'trimmedChangelogLines' => [],
            'removedChangelogLines' => ['1526477811	192.168.0.105	C	galaxy:andromeda.jpg	admin	created		1689508'],
        ];

        trigger_event('TASK_RECENTCHANGES_TRIM', $eventData);
        $actualChangelogContent = file_get_contents(mediaMetaFN($this->mediaID, '.changes'));

        $expectedChangelogContent = '1522767335	             	C	galaxy:andromeda.jpg		created		36
1522767349	             	E	galaxy:andromeda.jpg				12
1523956708	             	E	galaxy:andromeda.jpg	admin			23
1524145287	             	E	galaxy:andromeda.jpg	admin			19
1524464616	             	E	galaxy:andromeda.jpg	admin	ok		0
' . $this->yesterday . "	192.168.0.105	E	galaxy:andromeda.jpg	admin	ok		0\n";
        $this->assertEquals($expectedChangelogContent, $actualChangelogContent);

    }
}
