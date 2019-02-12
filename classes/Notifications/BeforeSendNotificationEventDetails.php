<?php

/*
 * Notifications addon for Bear Framework
 * https://github.com/ivopetkov/notifications-bearframework-addon
 * Copyright (c) Ivo Petkov
 * Free to use under the MIT license.
 */

namespace IvoPetkov\BearFrameworkAddons\Notifications;

/**
 * @property string $recipientID
 * @property \IvoPetkov\BearFrameworkAddons\Notifications\Notification $notification
 * @property bool $preventDefault
 */
class BeforeSendNotificationEventDetails
{

    use \IvoPetkov\DataObjectTrait;

    /**
     * 
     * @param string $recipientID
     * @param \IvoPetkov\BearFrameworkAddons\Notifications\Notification $notification
     */
    public function __construct(string $recipientID, \IvoPetkov\BearFrameworkAddons\Notifications\Notification $notification)
    {
        $this
                ->defineProperty('recipientID', [
                    'type' => 'string'
                ])
                ->defineProperty('notification', [
                    'type' => \IvoPetkov\BearFrameworkAddons\Notifications\Notification::class
                ])
                ->defineProperty('preventDefault', [
                    'type' => 'bool',
                    'init' => function() {
                        return false;
                    }
                ])
        ;
        $this->recipientID = $recipientID;
        $this->notification = $notification;
    }

}
