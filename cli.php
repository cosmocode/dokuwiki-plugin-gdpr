<?php
/**
 * DokuWiki Plugin gdpr (CLI Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Michael Große <dokuwiki@cosmocode.de>
 */


use splitbrain\phpcli\Options;

class cli_plugin_gdpr extends DokuWiki_CLI_Plugin
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
        /** @var helper_plugin_gdpr_utils $gdprUtils */
        $gdprUtils = plugin_load('helper', 'gdpr_utils');
        $pageChangelogs = $gdprUtils->collectChangelogs(dir($conf['metadir']));
        $this->log('info', count($pageChangelogs) . ' pages found.');
        $this->log('info', 'Cleaning page changelogs...');
        /** @var action_plugin_gdpr_oldips $action */
        $action = plugin_load('action', 'gdpr_oldips');
        foreach ($pageChangelogs as $changelogFN) {
            $this->log('debug', 'Cleaning changelog ' . $changelogFN);
            $action->cleanChangelog($changelogFN);
        }
        $this->log('success', 'The page changelogs have been cleaned.');

        $this->log('info', 'Collecting media files...');
        $mediaChangelogs = $gdprUtils->collectChangelogs(dir($conf['mediametadir']));
        $this->log('info', count($mediaChangelogs) . ' media files found.');
        $this->log('info', 'Cleaning media changelogs...');
        foreach ($mediaChangelogs as $changelogFN) {
            $this->log('debug', 'Cleaning media changelog ' . $changelogFN);
            $action->cleanChangelog($changelogFN);
        }
        $this->log('success', 'The media changelogs have been cleaned.');
    }
}
