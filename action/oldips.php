<?php
/**
 * DokuWiki Plugin gdpr (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Michael Große <dokuwiki@cosmocode.de>
 */

class action_plugin_gdpr_oldips extends DokuWiki_Action_Plugin
{

    const SECONDS_IN_A_DAY = 86400;

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller DokuWiki's event controller object
     *
     * @return void
     */
    public function register(Doku_Event_Handler $controller)
    {
        $controller->register_hook('INDEXER_TASKS_RUN', 'BEFORE', $this, 'handleIndexerTasksRun');
        $controller->register_hook('TASK_RECENTCHANGES_TRIM', 'BEFORE', $this, 'initiateMediaChangelogClean');
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
    public function handleIndexerTasksRun(Doku_Event $event, $param)
    {
        global $ID;
        $changelogFN = metaFN($ID, '.changes');
        if (!file_exists($changelogFN)) {
            return;
        }
        $cacheFile = $this->getOurCacheFilename($changelogFN);
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < self::SECONDS_IN_A_DAY)) {
            // we already cleaned this page in the last 24h
            return;
        }

        $event->preventDefault();
        $event->stopPropagation();

        touch($cacheFile);

        $this->cleanChangelog($changelogFN);
    }

    /**
     * [Custom event handler which performs action]
     *
     * Called for event: TASK_RECENTCHANGES_TRIM
     *
     * @param Doku_Event $event  event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     *
     * @return void
     */
    public function initiateMediaChangelogClean(Doku_Event $event, $param)
    {
        if (!$event->data['isMedia']) {
            return;
        }

        foreach ($event->data['removedChangelogLines'] as $mediaChangelogLine) {
            list(, , , $mediaID,) = explode("\t", $mediaChangelogLine, 5);

            $changelogFN = mediaMetaFN($mediaID, '.changes');
            if (!file_exists($changelogFN)) {
                continue;
            }

            $this->cleanChangelog($changelogFN);
        }
    }

    /**
     * Remove IPs from changelog entries that are older than $conf['recent_days']
     *
     * @param string $changelogFN
     */
    public function cleanChangelog($changelogFN)
    {
        if (!file_exists($changelogFN)) {
            return;
        }
        global $conf;

        $cacheFile = $this->getOurCacheFilename($changelogFN, true);
        $cacheStartPosition = (int)file_get_contents($cacheFile);
        $startPosition = $this->validateStartPosition($cacheStartPosition, $changelogFN);

        $handle = fopen($changelogFN, 'rb+');
        fseek($handle, $startPosition);
        $ageCutoff = (int)$conf['recent_days'] * self::SECONDS_IN_A_DAY;

        while (($line = fgets($handle)) !== false) {
            list($timestamp, $ip, $rest) = explode("\t", $line, 3);
            $ageOfEntry = time() - (int)$timestamp;
            if ($ageOfEntry < $ageCutoff) {
                // this and the remaining lines are newer than $conf['recent_days']
                $positionAtBeginningOfLine = ftell($handle) - strlen($line);
                fseek($handle, $positionAtBeginningOfLine);
                break;
            }

            $cleanedLine = implode("\t", [$timestamp, str_pad('', strlen($ip)), $rest]);
            $writeOffset = ftell($handle) - strlen($line);
            fseek($handle, $writeOffset);
            $bytesWritten = fwrite($handle, $cleanedLine);
            if ($bytesWritten === false) {
                throw new RuntimeException('There was an unknown error writing the changlog ' . $changelogFN);
            }
        }

        file_put_contents($cacheFile, ftell($handle));
        fclose($handle);
    }

    /**
     * Get the start position from cache and ensure its valid by performing some sanity checks
     *
     * @param int $cacheStartPosition
     * @param string $changelogFile the changelog for pageid
     *
     * @return int the start position
     */
    public function validateStartPosition($cacheStartPosition, $changelogFile)
    {
        if ($cacheStartPosition > filesize($changelogFile)) {
            return 0;
        }
        if ($cacheStartPosition > 0) {
            $handle = fopen($changelogFile, 'rb');
            fseek($handle, $cacheStartPosition - 1);
            $previousChar = fread($handle, 1);
            fclose($handle);

            if ($previousChar !== "\n") {
                return 0;
            }
        }
        return $cacheStartPosition;
    }

    /**
     * Get the filename of this plugin's cachefile for a page
     *
     * @param string $changelogFN filename of the changelog
     * @param bool   $create      create the cache file if it doesn't exists
     *
     * @return string the filename of this plugin's cachefile
     */
    protected function getOurCacheFilename($changelogFN, $create = false)
    {
        $cacheFN = getCacheName('_' . $changelogFN, '.plugin_gdpr');
        if ($create && !file_exists($cacheFN)) {
            touch($cacheFN);
        }
        return $cacheFN;
    }
}
