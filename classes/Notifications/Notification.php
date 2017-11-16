<?php

/*
 * Notifications addon for Bear Framework
 * https://github.com/ivopetkov/notifications-bearframework-addon
 * Copyright (c) 2017 Ivo Petkov
 * Free to use under the MIT license.
 */

namespace IvoPetkov\BearFrameworkAddons\Notifications;

/**
 * @property string|null $id The id of the notification.
 * @property string|null $text The text content of the notification.
 * @property int $priority Notification priority: Available values: 1 (highest), 2 (high), 3 (normal), 4 (low), 5 (lowest).
 * @property string $status Notification status: Available values: read and unread.
 * @property ?int $dateCreated Notification creation timestamp.
 * @property int $maxAge Notification max age (in seconds).
 */
class Notification
{

    use \IvoPetkov\DataObjectTrait;
    use \IvoPetkov\DataObjectToArrayTrait;
    use \IvoPetkov\DataObjectToJSONTrait;

    function __construct()
    {
        $this->defineProperty('id', [
            'type' => '?string'
        ]);
        $this->defineProperty('text', [
            'type' => '?string'
        ]);
        $this->defineProperty('priority', [
            'type' => 'int',
            'init' => function() {
                return 3;
            },
            'set' => function($value) {
                if ($value < 1 || $value > 5) {
                    throw new Exception('The priority value must be 1 (highest), 2 (high), 3 (normal), 4 (low), 5 (lowest)');
                }
                return $value;
            }
        ]);
        $this->defineProperty('status', [
            'type' => 'string',
            'init' => function() {
                return 'unread';
            },
            'set' => function($value) {
                if ($value !== 'read' && $value !== 'unread') {
                    throw new Exception('The status value must be read or unread');
                }
                return $value;
            }
        ]);
        $this->defineProperty('dateCreated', [
            'type' => '?int'
        ]);
        
        $this->defineProperty('maxAge', [
            'type' => 'int'
        ]);
    }

}
