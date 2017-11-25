<?php

namespace Pecee\Pixie;

use Pecee\Pixie\QueryBuilder\QueryBuilderHandler;
use Pecee\Pixie\QueryBuilder\Raw;

/**
 * Class EventHandler
 *
 * @package Pecee\Pixie
 */
class EventHandler
{
    /**
     * @var array
     */
    protected $events = [];

    /**
     * @var array
     */
    protected $firedEvents = [];

    /**
     * @return array
     */
    public function getEvents()
    {
        return $this->events;
    }

    /**
     * @param string $event
     * @param string|null $table
     * @return callable|null
     */
    public function getEvent($event, $table = null)
    {
        $table = $table ?: ':any';

        if ($table instanceof Raw) {
            return null;
        }

        // Find event with *
        if (isset($this->events[$table]) === true) {
            foreach ((array)$this->events[$table] as $name => $e) {
                if (strpos($name, '*') !== false) {
                    $name = substr($name, 0, strpos($name, '*'));
                    if (stripos($event, $name) !== false) {
                        return $e;
                    }
                }
            }
        }

        return isset($this->events[$table][$event]) ? $this->events[$table][$event] : null;
    }

    /**
     * @param string $event
     * @param string $table
     * @param \Closure $action
     * @return void
     */
    public function registerEvent($event, $table, \Closure $action)
    {
        $table = $table ?: ':any';

        $this->events[$table][$event] = $action;
    }

    /**
     * @param string $event
     * @param string $table
     * @return void
     */
    public function removeEvent($event, $table = null)
    {
        $table = $table ?: 'any';
        unset($this->events[$table][$event]);
    }

    /**
     * @param QueryBuilderHandler $queryBuilder
     * @param string $event
     * @return mixed|null
     */
    public function fireEvents($queryBuilder, $event)
    {
        $statements = $queryBuilder->getStatements();
        $tables = isset($statements['tables']) ? $statements['tables'] : [];

        // Events added with :any will be fired in case of any table,
        // we are adding :any as a fake table at the beginning.
        array_unshift($tables, ':any');

        $handlerParams = func_get_args();
        unset($handlerParams[1]);

        // Fire all events
        foreach ($tables as $table) {
            // Fire before events for :any table
            $action = $this->getEvent($event, $table);
            if ($action !== null) {

                // Make an event id, with event type and table
                $eventId = $event . $table;

                // Fire event and add to fired list
                $this->firedEvents[] = $eventId;
                $result = call_user_func_array($action, $handlerParams);
                if ($result !== null) {
                    return $result;
                }
            }
        }

        return null;
    }
}
