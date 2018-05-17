<?php
/**
 * DokuWiki Plugin cleanoldips (CLI Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Michael Große <dokuwiki@cosmocode.de>
 */


use splitbrain\phpcli\Options;

class cli_plugin_cleanoldips extends DokuWiki_CLI_Plugin
{

    /**
     * Register options and arguments on the given $options object
     *
     * @param Options $options
     *
     * @return void
     */
    protected function setup(Options $options)
    {
        $options->setHelp('Clean ips from all changelog entries older than $conf[\'recent_days\']');
    }

    /**
     * Your main program
     *
     * Arguments and options have been parsed when this is run
     *
     * @param Options $options
     *
     * @return void
     */
    protected function main(Options $options)
    {
        global $conf;
        $searchOpts = array('depth' => 0, 'skipacl' => true);

        $this->log('info', 'Collecting pages...');
        $pagedata = [];
        search($pagedata, $conf['datadir'], 'search_allpages', $searchOpts);
        $pages = array_column($pagedata, 'id');
        $this->log('info', count($pages) . ' pages found.');
        $this->log('info', 'Cleaning page changelogs...');
        /** @var action_plugin_cleanoldips $action */
        $action = plugin_load('action', 'cleanoldips');
        foreach ($pages as $pageid) {
            $this->log('debug', 'Cleaning changelog for page ' . $pageid);
            $action->cleanChangelog($pageid, metaFN($pageid, '.changes'));
        }
        $this->log('success', 'The page changelogs have been cleaned.');

        $this->log('info', 'Collecting media files...');
        $mediadata = [];
        search($mediadata, $conf['mediadir'], 'search_media', $searchOpts);
        $media = array_column($mediadata, 'id');
        $this->log('info', count($media) . ' media files found.');
        $this->log('info', 'Cleaning media changelogs...');
        foreach ($media as $mediaid) {
            $this->log('debug', 'Cleaning changelog for media file ' . $mediaid);
            $action->cleanChangelog($mediaid, mediaMetaFN($mediaid, '.changes'));
        }
        $this->log('success', 'The media changelogs have been cleaned.');
    }
}
