<?php

class helper_plugin_gdpr_utils extends DokuWiki_Plugin
{


    /**
     * Recursively collect all (page|media) changlogs within the current directory
     *
     * @param Directory $dir
     *
     * @return string[] filenames of changelogs in the current directory and subdirectories
     */
    public function collectChangelogs(Directory $dir)
    {
        $changlogs = [];
        while (false !== ($entry = $dir->read())) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $fn = $dir->path . '/' . $entry;
            if (is_dir($fn)) {
                $changlogs = array_merge($changlogs, $this->collectChangelogs(dir($fn)));
                continue;
            }
            list($extension, $basename) = explode('.', strrev($entry), 2);
            $extension = strrev($extension);
            $basename = strrev($basename);
            if ($extension !== 'changes') {
                continue;
            }
            if ($basename[0] === '_') {
                continue;
            }
            $changlogs[] = $dir->path . '/' . $entry;
        }
        return $changlogs;
    }
}
