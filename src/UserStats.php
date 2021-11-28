<?php

namespace MediaWiki\Extension\UserAchievements;

use User;

class UserStats {
    /**
     * @var string[][]
     */
    protected $eventTimes = [];

    /**
     * @var User
     */
    protected $user;

    /**
     * @var array
     */
    protected $values = [];

    /**
     * @param array $stats An array containing stat ids as keys and the initial values as values
     */
    public function __construct( array $stats, User $user = null ) {
        $this->user = $user ?: new User();

        foreach( $stats as $stat => $defaultValue ) {
            $this->eventTimes[ $stat ] = [];
            $this->values[ $stat ] = $defaultValue;
        }
    }

    /**
     * @param string $stat
     * @param string $time
     * @return bool
     */
    public function addEventTime( string $stat, string $time ): bool {
        if( !$this->hasStat( $stat ) ) {
            return false;
        }

        $this->eventTimes[ $stat ][] = $time;

        return true;
    }

    /**
     * @param string $stat
     * @param int $event
     * @return string|false
     */
    public function getEventTime( string $stat, int $event = 1 ) {
        if( !$this->hasStat( $stat ) || $event < 1 || $event > count( $this->eventTimes[ $stat ] ) ) {
            return false;
        }

        // Event starts at 1, but is indexed from 0
        $event--;

        $this->sortEventTimes( $stat );

        return $this->eventTimes[ $stat ][ $event ];
    }

    /**
     * @param string $stat
     * @return string[]|false
     */
    public function getEventTimes( string $stat ) {
        if( !$this->hasStat( $stat ) ) {
            return false;
        }

        $this->sortEventTimes( $stat );

        return $this->eventTimes[ $stat ];
    }

    /**
     * @return User
     */
    public function getUser(): User {
        return $this->user;
    }

    /**
     * @param string $stat
     * @return mixed
     */
    public function getValue( string $stat ) {
        return $this->values[ $stat ];
    }

    /**
     * @param string $stat
     * @return bool
     */
    public function hasStat( string $stat ): bool {
        return array_key_exists( $stat, $this->values );
    }

    /**
     * @param string $stat
     * @param mixed $value
     */
    public function setValue( string $stat, $value ) {
        $this->values[ $stat ] = $value;
    }

    protected function sortEventTimes( string $stat ) {
        sort( $this->eventTimes[ $stat ] );
    }
}