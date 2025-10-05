<?php

namespace App\Enums;

enum ServerProvider: string
{
    case AWS = 'aws';
    case GoogleCloud = 'google-cloud';
    case Azure = 'azure';
    case DigitalOcean = 'digitalocean';
    case Linode = 'linode';
    case Vultr = 'vultr';
    case Hetzner = 'hetzner';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::AWS => 'Amazon Web Services',
            self::GoogleCloud => 'Google Cloud',
            self::Azure => 'Microsoft Azure',
            self::DigitalOcean => 'DigitalOcean',
            self::Linode => 'Linode',
            self::Vultr => 'Vultr',
            self::Hetzner => 'Hetzner',
            self::Custom => 'Custom/Other',
        };
    }
}
