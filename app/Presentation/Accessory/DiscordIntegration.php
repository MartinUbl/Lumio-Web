<?php

namespace App\Presentation\Accessory;

use App\Model\Entities\Event;
use CurlHandle;

final class DiscordIntegration {
    private CurlHandle $curl;
    private string $baseUrl;

    public function __construct(string $baseUrl)
    {
        $this->curl = curl_init();
        curl_setopt($this->curl, CURLOPT_POST, true);
        curl_setopt($this->curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curl, CURLOPT_CONNECTTIMEOUT_MS, 500);
        curl_setopt($this->curl, CURLOPT_TIMEOUT_MS, 1500);

        $this->baseUrl = rtrim($baseUrl, '/') . '/';
    }

    public function postEventNotification(Event $event): void {
        $endpoint = $this->getEventEndpoint($event);
        if ($endpoint === null || $event->id === null || $event->date === null) {
            return;
        }

        curl_setopt($this->curl, CURLOPT_URL, $this->baseUrl . $endpoint);
        curl_setopt($this->curl, CURLOPT_POSTFIELDS, json_encode([
            'id' => $event->id,
            'name' => $event->name,
            'date' => $event->date->format(DATE_ATOM),
            'organiser' => $event->organiser
        ]));

        if (curl_exec($this->curl) === false) {
            error_log('Discord notification failed: ' . curl_error($this->curl));
        }
    }

    private function getEventEndpoint(Event $event): ?string {
        return match ($event->status) {
            Event::STATUS_APPROVED => "approved/",
            Event::STATUS_SUGGESTED => "suggested/",
            default => null,
        };

    }
}
