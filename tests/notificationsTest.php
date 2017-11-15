<?php

/*
 * Notifications addon for Bear Framework
 * https://github.com/ivopetkov/notifications-bearframework-addon
 * Copyright (c) 2017 Ivo Petkov
 * Free to use under the MIT license.
 */

/**
 * @runTestsInSeparateProcesses
 */
class NotificationsTest extends BearFrameworkAddonTestCase
{

    /**
     * 
     */
    public function testBasics()
    {
        $app = $this->getApp();

        $notification = $app->notifications->make('id1', 'Hello 1');
        $app->notifications->send('recipient1', $notification);
        $savedNotification = $app->notifications->get('recipient1', 'id1');
        $this->assertTrue($notification->toJSON() === $savedNotification->toJSON());
        $this->assertTrue($savedNotification->status === 'unread');

        $app->notifications->markAsRead('recipient1', 'id1');
        $savedNotification = $app->notifications->get('recipient1', 'id1');
        $this->assertTrue($savedNotification->status === 'read');

        $app->notifications->markAsUnread('recipient1', 'id1');
        $savedNotification = $app->notifications->get('recipient1', 'id1');
        $this->assertTrue($savedNotification->status === 'unread');

        $app->notifications->delete('recipient1', 'id1');
        $savedNotification = $app->notifications->get('recipient1', 'id1');
        $this->assertTrue($savedNotification === null);
    }

    /**
     * 
     */
    public function testGetList()
    {
        $app = $this->getApp();

        $notification = $app->notifications->make('id1', 'Hello 1');
        $app->notifications->send('recipient1', $notification);

        $notification = $app->notifications->make('id2', 'Hello 2');
        $app->notifications->send('recipient2', $notification);

        $list = $app->notifications->getList('recipient1');
        $this->assertTrue($list->length === 1);
        $this->assertTrue($list[0]->id === 'id1');
    }

    /**
     * 
     */
    public function testGetUnreadCount()
    {
        $app = $this->getApp();

        $this->assertTrue($app->notifications->getUnreadCount('recipient1') === 0);

        $notification = $app->notifications->make('id1', 'Hello 1');
        $app->notifications->send('recipient1', $notification);

        $this->assertTrue($app->notifications->getUnreadCount('recipient1') === 1);

        $notification = $app->notifications->make('id2', 'Hello 2');
        $app->notifications->send('recipient1', $notification);

        $this->assertTrue($app->notifications->getUnreadCount('recipient1') === 2);

        $app->notifications->markAsRead('recipient1', 'id1');

        $this->assertTrue($app->notifications->getUnreadCount('recipient1') === 1);

        $app->notifications->markAsUnread('recipient1', 'id1');

        $this->assertTrue($app->notifications->getUnreadCount('recipient1') === 2);
    }

}
