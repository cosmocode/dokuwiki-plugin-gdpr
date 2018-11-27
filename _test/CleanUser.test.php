<?php

namespace dokuwiki\plugin\gdpr\test;

/**
 * User cleaning tests for the gdpr plugin
 *
 * @group plugin_gdpr
 * @group plugins
 */
class CleanUserTest extends \DokuWikiTest
{

    protected $pluginsEnabled = ['gdpr'];

    public function setUp()
    {
        parent::setUp();

        $changelogFN = metaFN('some:page', '.changes');
        io_makeFileDir($changelogFN);

        file_put_contents($changelogFN, '1522767335	192.168.0.105	C	sidebar		created		36
1522767349	192.168.0.105	E	sidebar	pubcie			12
1523956708	192.168.0.105	E	sidebar	admin			23
1524145287	192.168.0.105	E	sidebar	user			19
1524464616	192.168.0.105	E	sidebar	admin	ok		0');
    }

    public function tearDown()
    {
        parent::tearDown();
        unlink(getCacheName('_cleandeletedusernames_counter', '.counter.gdpr'));
    }

    public function testCleaningOneUser()
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
1522767349	192.168.0.105	E	sidebar	pubcie			12
1523956708	192.168.0.105	E	sidebar	_deletedUser0_			23
1524145287	192.168.0.105	E	sidebar	user			19
1524464616	192.168.0.105	E	sidebar	_deletedUser0_	ok		0';
        $this->assertEquals($expectedChangelogContent, $actualChangelogContent);
    }

    public function testCleaningTwoUsers()
    {
        $deleteEventDataAdmin = [
            'type' => 'delete',
            'params' => [
                [
                    'admin'
                ]
            ]
        ];
        $deleteEventDataPubcie = [
            'type' => 'delete',
            'params' => [
                [
                    'pubcie'
                ]
            ]
        ];

        trigger_event('AUTH_USER_CHANGE', $deleteEventDataAdmin);
        trigger_event('AUTH_USER_CHANGE', $deleteEventDataPubcie);
        trigger_event('INDEXER_TASKS_RUN', $data);
        trigger_event('INDEXER_TASKS_RUN', $data);
        trigger_event('INDEXER_TASKS_RUN', $data);
        trigger_event('INDEXER_TASKS_RUN', $data);

        $actualChangelogContentFile1 = file_get_contents(metaFN('some:page', '.changes'));
        $expectedChangelogContentFile1 = '1522767335	192.168.0.105	C	sidebar		created		36
1522767349	192.168.0.105	E	sidebar	_deletedUser1_			12
1523956708	192.168.0.105	E	sidebar	_deletedUser0_			23
1524145287	192.168.0.105	E	sidebar	user			19
1524464616	192.168.0.105	E	sidebar	_deletedUser0_	ok		0';
        $this->assertEquals($expectedChangelogContentFile1, $actualChangelogContentFile1);

        $actualChangelogContentFile2 = file_get_contents(metaFN('mailinglist', '.changes'));
        $expectedChangelogContentFile2 = '1360110636	127.0.0.1	C	mailinglist	_deletedUser1_	aangemaakt	
1361901536	127.0.0.1	E	mailinglist	_deletedUser1_		
1362524799	127.0.0.1	E	mailinglist	_deletedUser1_		
1362525145	127.0.0.1	E	mailinglist	_deletedUser1_		
1362525359	127.0.0.1	E	mailinglist	_deletedUser1_	[Data entry] 	
1362525899	127.0.0.1	E	mailinglist	_deletedUser1_	[Data entry] 	
1362525926	127.0.0.1	E	mailinglist	_deletedUser1_		
1362526039	127.0.0.1	E	mailinglist	_deletedUser1_	[Data entry] 	
1362526119	127.0.0.1	E	mailinglist	_deletedUser1_		
1362526167	127.0.0.1	E	mailinglist	_deletedUser1_	[Data entry] 	
1362526767	127.0.0.1	E	mailinglist	_deletedUser1_	[Data entry] 	
1362526861	127.0.0.1	E	mailinglist	_deletedUser1_	[Data entry] 	
1362527046	127.0.0.1	E	mailinglist	_deletedUser1_	[Data entry] 	
1362527164	127.0.0.1	E	mailinglist	_deletedUser1_	[Data entry] 	
1363436892	127.0.0.1	E	mailinglist	_deletedUser1_		
1368575634	127.0.0.1	E	mailinglist	_deletedUser1_		
1368609772	127.0.0.1	E	mailinglist	_deletedUser1_		
1368612506	127.0.0.1	E	mailinglist	_deletedUser1_	[Data entry] 	
1368612599	127.0.0.1	E	mailinglist	_deletedUser1_	[Data entry] 	
1368622152	127.0.0.1	E	mailinglist	_deletedUser1_	[Data entry] 	
1368622195	127.0.0.1	E	mailinglist	_deletedUser1_		
1368622240	127.0.0.1	E	mailinglist	_deletedUser1_	[Data entry] 	
1371579614	127.0.0.1	E	mailinglist	_deletedUser1_		
1374261194	127.0.0.1	E	mailinglist	_deletedUser1_		
';
        $this->assertEquals($expectedChangelogContentFile2, $actualChangelogContentFile2);
    }
}
