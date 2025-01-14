<?php

/*
 * Notifications addon for Bear Framework
 * https://github.com/ivopetkov/notifications-bearframework-addon
 * Copyright (c) Ivo Petkov
 * Free to use under the MIT license.
 */

/**
 * @runTestsInSeparateProcesses
 */
class NotificationsTest extends BearFramework\AddonTests\PHPUnitTestCase
{

    /**
     * 
     */
    public function testBasics()
    {
        $app = $this->getApp();

        $notification = $app->notifications->make('Hello 1');
        $app->notifications->send('recipient1', $notification);
        $notificationID = $notification->id;

        $savedNotification = $app->notifications->get('recipient1', $notificationID);
        $this->assertTrue($notification->toJSON() === $savedNotification->toJSON());
        $this->assertTrue($savedNotification->status === 'unread');

        $app->notifications->markAsRead('recipient1', $notificationID);
        $savedNotification = $app->notifications->get('recipient1', $notificationID);
        $this->assertTrue($savedNotification->status === 'read');

        $app->notifications->markAsUnread('recipient1', $notificationID);
        $savedNotification = $app->notifications->get('recipient1', $notificationID);
        $this->assertTrue($savedNotification->status === 'unread');

        $app->notifications->delete('recipient1', $notificationID);
        $savedNotification = $app->notifications->get('recipient1', $notificationID);
        $this->assertTrue($savedNotification === null);
    }

    /**
     * 
     */
    public function testGetList()
    {
        $app = $this->getApp();

        $notification = $app->notifications->make('Hello 1');
        $app->notifications->send('recipient1', $notification);

        $notification = $app->notifications->make('Hello 2');
        $app->notifications->send('recipient2', $notification);

        $list = $app->notifications->getList('recipient1');
        $this->assertTrue($list->count() === 1);
        $this->assertTrue($list[0]->title === 'Hello 1');
    }

    /**
     * 
     */
    public function testGetUnreadCount()
    {
        $app = $this->getApp();

        $this->assertTrue($app->notifications->getUnreadCount('recipient1') === 0);

        $notification = $app->notifications->make('Hello 1');
        $app->notifications->send('recipient1', $notification);
        $notification1ID = $notification->id;

        $this->assertTrue($app->notifications->getUnreadCount('recipient1') === 1);

        $notification = $app->notifications->make('Hello 2');
        $app->notifications->send('recipient1', $notification);

        $this->assertTrue($app->notifications->getUnreadCount('recipient1') === 2);

        $app->notifications->markAsRead('recipient1', $notification1ID);

        $this->assertTrue($app->notifications->getUnreadCount('recipient1') === 1);

        $app->notifications->markAsUnread('recipient1', $notification1ID);

        $this->assertTrue($app->notifications->getUnreadCount('recipient1') === 2);
    }

    /**
     * 
     */
    public function testMaxAge()
    {
        $app = $this->getApp();

        $this->assertTrue($app->notifications->getUnreadCount('recipient1') === 0);

        $notification = $app->notifications->make('Hello 1');
        $notification->maxAge = 3;
        $app->notifications->send('recipient1', $notification);

        $this->assertTrue($app->notifications->getUnreadCount('recipient1') === 1);
        sleep(4);
        $this->assertTrue($app->notifications->getUnreadCount('recipient1') === 0);
    }

    /**
     * 
     */
    public function testDeleteAll()
    {
        $app = $this->getApp();

        $this->assertTrue($app->notifications->getUnreadCount('recipient1') === 0);

        $notification = $app->notifications->make('Hello 1');
        $app->notifications->send('recipient1', $notification);

        $notification = $app->notifications->make('Hello 2');
        $app->notifications->send('recipient1', $notification);

        $this->assertTrue($app->notifications->getUnreadCount('recipient1') === 2);

        $app->notifications->deleteAll('recipient1');

        $this->assertTrue($app->notifications->getUnreadCount('recipient1') === 0);
    }

    public function testDeleteOld()
    {
        $app = $this->getApp();

        $this->assertTrue($app->notifications->getUnreadCount('recipient1') === 0);

        $notification = $app->notifications->make('Hello 1');
        $notification->maxAge = 3;
        $app->notifications->send('recipient1', $notification);

        $this->assertTrue($app->data->getList()->filterBy('key', 'notifications/recipients/recipient/', 'startWith')->count() === 1);
        $app->notifications->deleteOld('recipient1');
        $this->assertTrue($app->data->getList()->filterBy('key', 'notifications/recipients/recipient/', 'startWith')->count() === 1);
        sleep(4);
        $app->notifications->deleteOld('recipient1');
        $this->assertTrue($app->data->getList()->filterBy('key', 'notifications/recipients/recipient/', 'startWith')->count() === 0);
    }

    /**
     * 
     */
    public function testType()
    {
        $app = $this->getApp();

        $notification = $app->notifications->make('Hello 1');
        $app->notifications->send('recipient1', $notification);

        $this->assertTrue($app->notifications->getUnreadCount('recipient1') === 1);

        sleep(1);
        $notification = $app->notifications->make('Hello 2');
        $notification->type = 'type1';
        $app->notifications->send('recipient1', $notification);

        $this->assertTrue($app->notifications->getUnreadCount('recipient1') === 2);

        sleep(1);
        $notification = $app->notifications->make('Hello 3');
        $notification->type = 'type1';
        $app->notifications->send('recipient1', $notification);

        $this->assertTrue($app->notifications->getUnreadCount('recipient1') === 2);

        $list = $app->notifications->getList('recipient1')
            ->sortBy('dateCreated', 'desc');
        $this->assertTrue($list->count() === 2);
        $this->assertTrue($list[0]->title === 'Hello 3');
    }

    /**
     * 
     */
    public function testEvents()
    {
        $app = $this->getApp();

        $log = [];

        $app->notifications
            ->addEventListener('beforeSendNotification', function (\IvoPetkov\BearFrameworkAddons\Notifications\BeforeSendNotificationEventDetails $details) use (&$log): void {
                $log[] = 'before-send';
                $log[] = $details->recipientID;
                $log[] = $details->notification->title;
                if ($details->recipientID === 'recipient1') {
                    $details->preventDefault = true;
                }
            })
            ->addEventListener('sendNotification', function (\IvoPetkov\BearFrameworkAddons\Notifications\SendNotificationEventDetails $details) use (&$log): void {
                $log[] = 'send';
                $log[] = $details->recipientID;
                $log[] = $details->notification->title;
            });

        $notification = $app->notifications->make('Hello 1');
        $app->notifications->send('recipient1', $notification);

        $notification = $app->notifications->make('Hello 2');
        $app->notifications->send('recipient2', $notification);

        $this->assertEquals($log, [
            "before-send",
            "recipient1",
            "Hello 1",
            "before-send",
            "recipient2",
            "Hello 2",
            "send",
            "recipient2",
            "Hello 2"
        ]);
    }
}
