<?php
/**
 * DokuWiki Plugin limituserips (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Michael Große <dokuwiki@cosmocode.de>
 */

class action_plugin_limituserips extends DokuWiki_Action_Plugin
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
        if (!file_exists(metaFN($ID, '.changes'))) {
            return;
        }
        $cacheFile = $this->getOurCacheFilename($ID);
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < self::SECONDS_IN_A_DAY)) {
            // we already cleaned this page in the last 24h
            return;
        }

        $event->preventDefault();
        $event->stopPropagation();

        touch($cacheFile);

        $this->cleanPageChangelog($ID);
    }

    /**
     * Remove all changelog entries that are older than $conf['recent_days']
     *
     * @param $pageid
     */
    protected function cleanPageChangelog($pageid)
    {
        global $conf;
        $cacheFile = $this->getOurCacheFilename($pageid);
        $changelogFile = metaFN($pageid, '.changes');
        $handle = fopen($changelogFile, 'rb+');

        $startPosition = (int)file_get_contents($cacheFile);
        fseek($handle, $startPosition);
        $ageCutoff = (int)$conf['recent_days'] * self::SECONDS_IN_A_DAY;
        // loop start
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
                throw new RuntimeException('There was an unknown error writing the changlog for page ' . $pageid);
            }
        }
        // loop end

        file_put_contents($cacheFile, ftell($handle));
        fclose($handle);
    }

    /**
     * Get the filename of this plugin's cachefile for a page
     *
     * @param string $pageid full id of the page
     *
     * @return string the filename of this plugin's cachefile
     */
    protected function getOurCacheFilename($pageid)
    {
        return getCacheName($pageid . 'limituserips');
    }
}
