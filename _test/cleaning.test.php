<?php

/**
 * General tests for the cleandeletedusernames plugin
 *
 * @group plugin_cleandeletedusernames
 * @group plugins
 */
class cleaning_plugin_cleandeletedusernames_test extends DokuWikiTest
{

    protected $pluginsEnabled = ['cleandeletedusernames'];

    public function setUp()
    {
        parent::setUp();

        $changelogFN = metaFN('some:page', '.changes');
        io_makeFileDir($changelogFN);

        file_put_contents($changelogFN, '1522767335	192.168.0.105	C	sidebar		created		36
1522767349	192.168.0.105	E	sidebar				12
1523956708	192.168.0.105	E	sidebar	admin			23
1524145287	192.168.0.105	E	sidebar	user			19
1524464616	192.168.0.105	E	sidebar	admin	ok		0');
    }

    public function testCleaning()
    {
        $deleteEventData = [
            'type' => 'delete',
            'params' => [
                [
                    'admin'
                ]
            ]
        ];

        trigger_event('AUTH_USER_CHANGE', $deleteEventData);
        trigger_event('INDEXER_TASKS_RUN', $data);

        $actualChangelogContent = file_get_contents(metaFN('some:page', '.changes'));
        $expectedChangelogContent = '1522767335	192.168.0.105	C	sidebar		created		36
1522767349	192.168.0.105	E	sidebar				12
1523956708	192.168.0.105	E	sidebar	_deletedUser0_			23
1524145287	192.168.0.105	E	sidebar	user			19
1524464616	192.168.0.105	E	sidebar	_deletedUser0_	ok		0';
        $this->assertEquals($expectedChangelogContent, $actualChangelogContent);
    }
}
