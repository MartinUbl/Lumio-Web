<?php

namespace App\Presentation\Accessory;

use App\Model\Entities\Event;
use CurlHandle;

final class DiscordIntegration {
    private CurlHandle $curl;
    private string $base_url;

    public function __construct(string $port)
    {
        $this->curl = curl_init();
        curl_setopt($this->curl, CURLOPT_POST, true);
        curl_setopt($this->curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);

        $this->base_url = "bot:" . $port . "/";
    }

    public function postEventNotification(Event $event): void {
        curl_setopt($this->curl, CURLOPT_URL, $this->base_url . $this->getEventEndpoint($event));
        curl_setopt($this->curl, CURLOPT_POSTFIELDS, json_encode([
            'id' => $event->id,
            'name' => $event->name,
            'date' => $event->date->format(DATE_ATOM),
            'organiser' => $event->organiser
        ]));
        curl_exec($this->curl);
    }

    private function getEventEndpoint(Event $event): string {
        return match ($event->status) {
            Event::STATUS_APPROVED => "approved/",
            Event::STATUS_SUGGESTED => "suggested/",
            default => "",
        };

    }
}