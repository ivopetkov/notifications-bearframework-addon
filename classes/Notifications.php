<?php

/*
 * Notifications addon for Bear Framework
 * https://github.com/ivopetkov/notifications-bearframework-addon
 * Copyright (c) Ivo Petkov
 * Free to use under the MIT license.
 */

namespace IvoPetkov\BearFrameworkAddons;

use BearFramework\App;
use IvoPetkov\BearFrameworkAddons\Notifications\Notification;

/**
 * Notifications utilities.
 */
class Notifications
{

    use \BearFramework\EventsTrait;

    /**
     *
     */
    private static $newNotificationCache = null;

    /**
     * Constructs a new notification and returns it.
     * 
     * @param ?string $title The notification title.
     * @param ?string $text The notification text.
     * @return \IvoPetkov\BearFrameworkAddons\Notifications\Notification
     */
    public function make(?string $title = null, ?string $text = null): Notification
    {
        if (self::$newNotificationCache === null) {
            self::$newNotificationCache = new Notification();
        }
        $notification = clone (self::$newNotificationCache);
        if ($title !== null) {
            $notification->title = $title;
        }
        if ($text !== null) {
            $notification->text = $text;
        }
        return $notification;
    }

    /**
     * Sends a notification.
     * 
     * @param string $recipientID The recipient ID.
     * @param \IvoPetkov\BearFrameworkAddons\Notifications\Notification $notification The notification to send.
     * @return void No value is returned.
     * @throws \Exception
     */
    public function send(string $recipientID, Notification $notification): void
    {
        if ($notification->id === null) {
            $notification->id = 'n' . uniqid() . 'x' . base_convert(rand(0, 999999999), 10, 16);
        }
        if ($notification->dateCreated === null) {
            $notification->dateCreated = time();
        }

        if ($this->hasEventListeners('beforeSendNotification')) {
            $eventDetails = new \IvoPetkov\BearFrameworkAddons\Notifications\BeforeSendNotificationEventDetails($recipientID, $notification);
            $this->dispatchEvent('beforeSendNotification', $eventDetails);
            if ($eventDetails->preventDefault) {
                return;
            }
        }

        if (strlen((string)$notification->type) > 0) {
            $otherNotifications = $this->getList($recipientID);
            foreach ($otherNotifications as $otherNotification) {
                if ((string) $notification->type === (string) $otherNotification->type) {
                    $this->delete($recipientID, $otherNotification->id);
                }
            }
        }

        $this->set($recipientID, $notification);

        if ($this->hasEventListeners('sendNotification')) {
            $eventDetails = new \IvoPetkov\BearFrameworkAddons\Notifications\SendNotificationEventDetails($recipientID, $notification);
            $this->dispatchEvent('sendNotification', $eventDetails);
        }
    }

    /**
     * 
     * @param string $recipientID
     * @param Notification $notification
     */
    private function set(string $recipientID, Notification $notification): void
    {
        $app = App::get();

        $notificationID = $notification->id;
        $dataItem = $app->data->make($this->getNotificationDataKey($recipientID, $notificationID), $notification->toJSON());
        $app->data->set($dataItem);
    }

    /**
     * 
     * @param string $recipientID
     * @param string $notificationID
     * @return Notification|null
     */
    public function get(string $recipientID, string $notificationID): ?Notification
    {
        $app = App::get();

        $notificationRawData = $app->data->getValue($this->getNotificationDataKey($recipientID, $notificationID));
        if ($notificationRawData === null) {
            return null;
        }
        return $this->constructNotificationFromRawData($notificationRawData);
    }

    /**
     * 
     * @param string $rawData
     * @return Notification
     */
    private function constructNotificationFromRawData(string $rawData): Notification
    {
        $data = json_decode($rawData, true);
        if (isset($data['clickUrl'])) {
            $data['clickURL'] = $data['clickUrl'];
        }
        return Notification::fromArray($data);
    }

    /**
     * 
     * @param string $recipientID
     * @param string $notificationID
     */
    public function markAsRead(string $recipientID, string $notificationID): void
    {
        $this->setReadStatus($recipientID, $notificationID, 'read');
    }

    /**
     * 
     * @param string $recipientID
     * @param string $notificationID
     */
    public function markAsUnread(string $recipientID, string $notificationID): void
    {
        $this->setReadStatus($recipientID, $notificationID, 'unread');
    }

    /**
     * 
     * @param string $recipientID
     * @param string $notificationID
     * @param string $status
     */
    private function setReadStatus(string $recipientID, string $notificationID, string $status): void
    {
        $notification = $this->get($recipientID, $notificationID);
        if ($notification instanceof Notification) {
            if ($notification->status !== $status) {
                $notification->status = $status;
                $this->set($recipientID, $notification);
            }
        }
    }

    /**
     * 
     * @param string $recipientID
     * @param string $notificationID
     */
    public function delete(string $recipientID, string $notificationID): void
    {
        $app = App::get();
        $app->data->delete($this->getNotificationDataKey($recipientID, $notificationID));
    }

    /**
     * 
     * @param string $recipientID
     */
    public function deleteAll(string $recipientID): void
    {
        $app = App::get();
        $notificationDataItems = $app->data->getList()->filterBy('key', $this->getRecipientDataKeyPrefix($recipientID), 'startWith');
        foreach ($notificationDataItems as $notificationDataItem) {
            $app->data->delete($notificationDataItem->key);
        }
    }

    /**
     * 
     * @param string $recipientID
     */
    public function deleteOld(string $recipientID): void
    {
        $app = App::get();
        $notificationDataItems = $app->data->getList()->filterBy('key', $this->getRecipientDataKeyPrefix($recipientID), 'startWith');
        foreach ($notificationDataItems as $notificationDataItem) {
            $notification = $this->constructNotificationFromRawData($notificationDataItem->value);
            $this->deleteIfOld($recipientID, $notification);
        }
    }

    /**
     * 
     * @param string $recipientID
     * @return \IvoPetkov\DataList
     */
    public function getList(string $recipientID): \IvoPetkov\DataList
    {
        $app = App::get();
        return new \IvoPetkov\DataList(function ($context) use ($app, $recipientID) {
            $result = [];
            $notificationDataItems = $app->data->getList()->filterBy('key', $this->getRecipientDataKeyPrefix($recipientID), 'startWith');
            foreach ($notificationDataItems as $notificationDataItem) {
                $notification = $this->constructNotificationFromRawData($notificationDataItem->value);
                if (!$this->deleteIfOld($recipientID, $notification)) {
                    $result[] = $notification;
                }
            }
            return $result;
        });
    }

    /**
     * 
     * @param string $recipientID
     * @return int
     */
    public function getUnreadCount(string $recipientID): int
    {
        $list = $this->getList($recipientID);
        $count = 0;
        foreach ($list as $notification) {
            if ($notification->status === 'unread') {
                $count++;
            }
        }
        return $count;
    }

    /**
     * 
     * @param string $recipientID
     * @param Notification $notification
     * @return bool
     */
    private function deleteIfOld(string $recipientID, Notification $notification): bool
    {
        if ($notification->dateCreated + $notification->maxAge < time()) {
            $this->delete($recipientID, $notification->id);
            return true;
        }
        return false;
    }

    /**
     * 
     * @param string $recipientID
     * @return string
     */
    private function getRecipientDataKeyPrefix(string $recipientID): string
    {
        $recipientIDMD5 = md5($recipientID);
        return 'notifications/recipients/recipient/' . substr($recipientIDMD5, 0, 2) . '/' . substr($recipientIDMD5, 2, 2) . '/' . $recipientIDMD5 . '/';
    }

    /**
     * 
     * @param string $recipientID
     * @param string $notificationID
     * @return string
     */
    private function getNotificationDataKey(string $recipientID, string $notificationID): string
    {
        $notificationIDMD5 = md5($notificationID);
        return $this->getRecipientDataKeyPrefix($recipientID) . 'notifications/notification/' . substr($notificationIDMD5, 0, 2) . '/' . substr($notificationIDMD5, 2, 2) . '/' . $notificationIDMD5 . '.json';
    }

    /**
     * 
     * @param string $recipientID
     * @return string
     */
    private function getChannelsDataKey(string $recipientID): string
    {
        $recipientIDMD5 = md5($recipientID);
        return 'notifications/channels/' . substr($recipientIDMD5, 0, 2) . '/' . substr($recipientIDMD5, 2, 2) . '/' . $recipientIDMD5 . '.json';
    }

    /**
     * 
     * @param string $recipientID
     * @return array
     */
    private function getSubscriptionsData(string $recipientID): array
    {
        $app = App::get();
        $dataKey = $this->getChannelsDataKey($recipientID);
        $value = $app->data->getValue($dataKey);
        return $value !== null ? json_decode($value, true) : [];
    }

    /**
     * 
     * @param string $recipientID
     * @param string $channel
     * @param array $data
     * @return void
     */
    public function subscribe(string $recipientID, string $channel, array $data = []): void
    {
        $app = App::get();
        $dataKey = $this->getChannelsDataKey($recipientID);
        $subscriptionsData = $this->getSubscriptionsData($recipientID);
        $subscriptionsData[$channel] = [time(), $data];
        $app->data->setValue($dataKey, json_encode($subscriptionsData));
    }

    /**
     * 
     * @param string $recipientID
     * @param string $channel
     * @return void
     */
    public function unsubscribe(string $recipientID, string $channel): void
    {
        $app = App::get();
        $dataKey = $this->getChannelsDataKey($recipientID);
        $data = $this->getSubscriptionsData($recipientID);
        unset($data[$channel]);
        if (empty($data)) {
            $app->data->delete($dataKey);
        } else {
            $app->data->setValue($dataKey, json_encode($data));
        }
    }

    /**
     * 
     * @param string $recipientID
     * @param string $channel
     * @return boolean
     */
    public function isSubscribed(string $recipientID, string $channel): bool
    {
        $data = $this->getSubscriptionsData($recipientID);
        return isset($data[$channel]);
    }

    /**
     * 
     * @param string $recipientID
     * @param string $channel
     * @return array|null
     */
    public function getSubscriptionData(string $recipientID, string $channel): ?array
    {
        $data = $this->getSubscriptionsData($recipientID);
        if (isset($data[$channel])) {
            $channelData = $data[$channel];
            return [
                'date' => $channelData[0],
                'data' => $channelData[1],
            ];
        }
        return null;
    }

    /**
     * 
     * @param string $recipientID
     * @return array
     */
    public function getSubscriptions(string $recipientID): array
    {
        $data = $this->getSubscriptionsData($recipientID);
        $result = [];
        foreach ($data as $channel => $channelData) {
            $result[] = [
                'channel' => $channel,
                'date' => $channelData[0],
                'data' => $channelData[1],
            ];
        }
        return $result;
    }
}
