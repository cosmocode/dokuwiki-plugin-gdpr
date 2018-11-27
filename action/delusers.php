<?php
/**
 * DokuWiki Plugin cleandeletedusernames (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Michael Große <dokuwiki@cosmocode.de>
 */

class action_plugin_gdpr_delusers extends DokuWiki_Action_Plugin
{

    protected $didMeaningfulWork = false;

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller DokuWiki's event controller object
     *
     * @return void
     */
    public function register(Doku_Event_Handler $controller)
    {
        $controller->register_hook('AUTH_USER_CHANGE', 'AFTER', $this, 'handleAuthUserChange');
        $controller->register_hook('INDEXER_TASKS_RUN', 'BEFORE', $this, 'handleIndexerTaskRun');
    }

    /**
     * [Custom event handler which performs action]
     *
     * Called for event: AUTH_USER_CHANGE
     *
     * @param Doku_Event $event  event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     *
     * @return void
     */
    public function handleAuthUserChange(Doku_Event $event, $param)
    {
        if ($event->data['type'] !== 'delete') {
            return;
        }

        $username = $event->data['params'][0][0];
        $cacheFN = $this->getCacheFN('users');
        file_put_contents($cacheFN, $username . "\n", FILE_APPEND);
    }

    /**
     * [Custom event handler which performs action]
     *
     * Called for event: INDEXER_TASKS_RUN
     *
     * @param Doku_Event $event  event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     *
     * @return void
     */
    public function handleIndexerTaskRun(Doku_Event $event, $param)
    {
        $deleteUserCacheFile = $this->getCacheFN('users');
        if (!file_exists($deleteUserCacheFile) || filesize($deleteUserCacheFile) === 0) {
            return;
        }

        $usersToDelete = file($deleteUserCacheFile);
        $this->cleanUsernameFromChangelogs(trim($usersToDelete[0]));

        if ($this->didMeaningfulWork) {
            $event->preventDefault();
            $event->stopPropagation();
        }
    }

    /**
     * Clean the username form all changelogs and replace it with a unique placeholder
     *
     * @param $username
     *
     * @return void
     */
    protected function cleanUsernameFromChangelogs($username)
    {
        $deletedUserCount = $this->getCleanedUserCount();

        while ($changelog = $this->getTopChangelog()) {
            if (!$this->cleanChangelog($changelog, $username, $deletedUserCount)) {
                return;
            }
            $this->removeTopChangelogFromList();
            $this->didMeaningfulWork = true;
        }
        $this->incrementCleanedUserCounter();
        $this->removeTopUsernameFromList();
    }

    /**
     * Remove the first username from the list of deleted usernames that have still to be cleaned
     *
     * @return void
     */
    protected function removeTopUsernameFromList()
    {
        $deleteUserCacheFile = $this->getCacheFN('users');
        $usersToDelete = file($deleteUserCacheFile);
        array_shift($usersToDelete);
        file_put_contents($deleteUserCacheFile, $usersToDelete);
    }

    /**
     * Increment the counter of users that have already been cleaned
     */
    protected function incrementCleanedUserCounter()
    {
        $cacheFN = $this->getCacheFN('counter');
        $count = (int)file_get_contents($cacheFN);
        file_put_contents($cacheFN, $count + 1);
    }

    /**
     * Remove the first changelog from the list of changelogs that have still to be cleaned for the current deleted user
     */
    protected function removeTopChangelogFromList()
    {
        $changelogCacheFN = $this->getCacheFN('changelogs');
        $lines = file($changelogCacheFN);
        array_shift($lines);
        file_put_contents($changelogCacheFN, implode('', $lines));
    }

    /**
     * Get the number of users that have already been cleaned
     *
     * @return bool|string
     */
    protected function getCleanedUserCount()
    {
        $cacheFN = $this->getCacheFN('counter');
        if (!file_exists($cacheFN)) {
            file_put_contents($cacheFN, '0');
            return '0';
        }
        return file_get_contents($cacheFN);
    }


    /**
     * Try to clean a username from a changelog and replace it with a placeholder, locks the page
     *
     * @param string $changelogFN path to the changelog to be cleaned
     * @param string $nameToBeCleaned username to be cleaned
     * @param string $count number of users that have already been cleaned, will be appended to the placeholder
     *
     * @return bool if the changelog has been successfully cleaned, false if page was locked and nothing was done
     */
    protected function cleanChangelog($changelogFN, $nameToBeCleaned, $count)
    {
        $pageid = $this->getPageIDfromChangelogFN($changelogFN);
        if ($pageid && checklock($pageid) !== false) {
            return false;
        }

        $pageid && lock($pageid);
        $cleanChangelogLines = [];

        $handle = fopen($changelogFN, 'rb+');
        flock($handle, LOCK_EX);
        while ($line = fgets($handle)) {
            $parts = explode("\t", $line);
            if ($parts[4] !== $nameToBeCleaned) {
                $cleanChangelogLines[] = $line;
                continue;
            }
            $parts[4] = '_deletedUser' . $count . '_';
            $cleanChangelogLines[] = implode("\t", $parts);
        }
        ftruncate($handle, 0);
        fseek($handle, 0);
        fwrite($handle, implode('', $cleanChangelogLines));
        flock($handle, LOCK_UN);
        fclose($handle);
        $pageid && unlock($pageid);
        return true;
    }

    /**
     * Parse the pageid from the changelog filename
     *
     * @param string $changelogFN
     *
     * @return false|string pageid or false if media changelog
     */
    protected function getPageIDfromChangelogFN($changelogFN)
    {
        global $conf;
        if (strpos($changelogFN, $conf['mediametadir']) === 0) {
            return false;
        }
        $pageid = substr($changelogFN, strlen($conf['metadir']), -1 * strlen('.changes'));
        return str_replace('/', ':', $pageid);
    }

    /**
     * Get the next changelog to clean
     *
     * @return bool|string the next changelog or false if we are done
     */
    protected function getTopChangelog()
    {
        $changelogCacheFN = $this->getCacheFN('changelogs');
        if (!file_exists($changelogCacheFN)) {
            global $conf;
            /** @var helper_plugin_gdpr_utils $gdprUtils */
            $gdprUtils = plugin_load('helper', 'gdpr_utils');

            $metaDir = $conf['metadir'];
            $mediaMetaDir = $conf['mediametadir'];

            $changelogs = $gdprUtils->collectChangelogs(dir($metaDir));
            $changelogs = array_merge($changelogs, $gdprUtils->collectChangelogs(dir($mediaMetaDir)));

            file_put_contents($changelogCacheFN, implode("\n", $changelogs));
            $this->didMeaningfulWork = true;
            return $changelogs[0];
        }

        if (filesize($changelogCacheFN) > 0) {
            $handle = fopen($changelogCacheFN, 'rb');
            $firstLine = fgets($handle);
            fclose($handle);
            return trim($firstLine);
        }

        unlink($changelogCacheFN);
        return false;
    }

    /**
     * Get the cache filname for a given key
     *
     * @param string $key
     *
     * @return string
     */
    protected function getCacheFN($key)
    {
        switch ($key) {
            case 'users':
                return getCacheName('_cleandeletedusernames_users', ".$key.gdpr"); // 24603dd46ffc3f959fda54e307304714
            case 'changelogs':
                return getCacheName('_cleandeletedusernames_changelogs', ".$key.gdpr"); // c665cd8d3071e0e7c57ae12d97a869cd
            case 'counter':
                return getCacheName('_cleandeletedusernames_counter', ".$key.gdpr"); // 141cd02168fca864e927e12639b0272e
            default:
                throw new InvalidArgumentException('Unknown cache key provided: ' . $key);
        }
    }
}
