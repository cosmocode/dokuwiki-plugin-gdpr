<?php
/**
 * DokuWiki Plugin cleandeletedusernames (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Michael Große <dokuwiki@cosmocode.de>
 */

class action_plugin_cleandeletedusernames extends DokuWiki_Action_Plugin
{

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
        $controller->register_hook('INDEXER_TASKS_RUN', 'BEFORE', $this, 'deleteUsername');
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

    }
}
