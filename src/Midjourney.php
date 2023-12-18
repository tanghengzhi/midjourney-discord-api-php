<?php

namespace Ferranfg\MidjourneyPhp;

use Exception;
use GuzzleHttp\Client;

class Midjourney {

    private const API_URL = 'https://discord.com/api/v9';

    protected const APPLICATION_ID = '936929561302675456';

    protected const SESSION_ID = '2fb980f65e5c9a77c96ca01f2c242cf6';

    private static $client;

    private static $channel_id;

    private static $oauth_token;

    private static $guild_id;

    private static $user_id;

    private $data_id;

    private $data_version;

    public function __construct($channel_id, $oauth_token)
    {
        self::$channel_id = $channel_id;
        self::$oauth_token = $oauth_token;

        self::$client = new Client([
            'base_uri' => self::API_URL,
            'headers' => [
                'Authorization' => self::$oauth_token
            ]
        ]);

        $request = self::$client->get('channels/' . self::$channel_id);
        $response = json_decode((string) $request->getBody());

        self::$guild_id = $response->guild_id;

        $response = self::$client->get('applications/' . self::APPLICATION_ID . '/commands');
        $body = $response->getBody()->getContents();
        $json = json_decode($body, true);

        $this->data_id       = $json[0]['id'];
        $this->data_version  = $json[0]['version'];

        $request = self::$client->get('users/@me');
        $response = json_decode((string) $request->getBody());

        self::$user_id = $response->id;
    }

    private static function strEndsWith($message, $content)
    {
        \Log::error("Discord Message", ['message' => $message, 'content' => $content]);

        foreach ($message as $item)
        {
            if (str_ends_with($item->content, $content))
            {
                return $item;
            }
        }

        return null;
    }

    private static function strContains($message, $content)
    {
        \Log::error("Discord Message", ['message' => $message, 'content' => $content]);

        foreach ($message as $item)
        {
            if (str_contains($item->content, $content))
            {
                return $item;
            }
        }

        return null;
    }

    public function imagine(Prompts $prompt)
    {
        $params = [
            'type' => 2,
            'application_id' => self::APPLICATION_ID,
            'guild_id' => self::$guild_id,
            'channel_id' => self::$channel_id,
            'session_id' => self::SESSION_ID,
            'data' => [
                'version' => $this->data_version,
                'id' => $this->data_id,
                'name' => 'imagine',
                'type' => 1,
                'options' => [[
                    'type' => 3,
                    'name' => 'prompt',
                    'value' => $prompt->toString()
                ]],
                'application_command' => [
                    'id' => $this->data_id,
                    'application_id' => self::APPLICATION_ID,
                    'version' => $this->data_version,
                    'default_member_permissions' => null,
                    'type' => 1,
                    'nsfw' => false,
                    'name' => 'imagine',
                    'description' => 'Create images with Midjourney',
                    'dm_permission' => true,
                    'options' => [[
                        'type' => 3,
                        'name' => 'prompt',
                        'description' => 'The prompt to imagine',
                        'required' => true
                    ]]
                ],
                'attachments' => []
            ]
        ];

        $response = self::$client->post('interactions', [
            'json' => $params
        ]);

        \Log::error("Imagine", [
            'responseStatus' => $response->getStatusCode(),
            'responseBody' => $response->getBody(),
        ]);

        $try = 0;
        $imagineMessage = (object)['status' => ImagineStatus::NOT_START];

        while ($imagineMessage->status != ImagineStatus::FINISHED)
        {
            if ($try >= 3 && $imagineMessage->status == ImagineStatus::NOT_START) {
                throw new Exception("Imagine Start Timeout");
            }

            if ($try >= 10) {
                throw new Exception("Imagine Timeout");
            }

            sleep(6);
            $imagineMessage = $this->getImagine($prompt);
            $try++;
        }

        return $imagineMessage;
    }

    public function getImagine(Prompts $prompt)
    {
        $response = self::$client->get('channels/' . self::$channel_id . '/messages');
        $response = json_decode((string) $response->getBody());

        if ($raw_message = self::strEndsWith($response, "{$prompt->withoutImagePrompts()}** - <@" . self::$user_id . '> (fast)')) {
            return (object) [
                'status' => ImagineStatus::FINISHED,
                'id' => $raw_message->id,
                'prompt' => $prompt,
                'raw_message' => $raw_message
            ];
        }

        if ($raw_message = self::strContains($response, "{$prompt->withoutImagePrompts()}** - <@" . self::$user_id . '>')) {
            return (object) [
                'status' => ImagineStatus::RUNNING,
                'id' => $raw_message->id,
                'prompt' => $prompt,
                'raw_message' => $raw_message,
            ];
        }

        return (object)['status' => ImagineStatus::NOT_START];
    }

    public function upscale($message, int $upscale_index = 0)
    {
        if ( ! property_exists($message, 'raw_message'))
        {
            throw new Exception('Upscale requires a message object obtained from the imagine/getImagine methods.');
        }

        if ($upscale_index < 0 or $upscale_index > 3)
        {
            throw new Exception('Upscale index must be between 0 and 3.');
        }

        $upscale_hash = null;
        $raw_message = $message->raw_message;

        if (property_exists($raw_message, 'components') and is_array($raw_message->components))
        {
            $upscales = $raw_message->components[0]->components;

            $upscale_hash = $upscales[$upscale_index]->custom_id;
        }

        $params = [
            'type' => 3,
            'guild_id' => self::$guild_id,
            'channel_id' => self::$channel_id,
            'message_flags' => 0,
            'message_id' => $message->id,
            'application_id' => self::APPLICATION_ID,
            'session_id' => self::SESSION_ID,
            'data' => [
                'component_type' => 2,
                'custom_id' => $upscale_hash
            ]
        ];

        self::$client->post('interactions', [
            'json' => $params
        ]);

        $try = 0;
        $upscaled_photo_url = null;

        while (is_null($upscaled_photo_url))
        {
            if ($try >= 10) {
                throw new Exception("Upscale Timeout");
            }

            sleep(3);
            $upscaled_photo_url = $this->getUpscale($message, $upscale_index);
            $try++;
        }

        return $upscaled_photo_url;
    }

    public function getUpscale($message, $upscale_index = 0)
    {
        if ( ! property_exists($message, 'raw_message'))
        {
            throw new Exception('Upscale requires a message object obtained from the imagine/getImagine methods.');
        }

        if ($upscale_index < 0 or $upscale_index > 3)
        {
            throw new Exception('Upscale index must be between 0 and 3.');
        }

        /**
         * @var Prompts $prompt
         */
        $prompt = $message->prompt;

        $response = self::$client->get('channels/' . self::$channel_id . '/messages');
        $response = json_decode((string) $response->getBody());

        $message_index = $upscale_index + 1;
        $message = self::strEndsWith($response, "{$prompt->withoutImagePrompts()}** - Image #{$message_index} <@" . self::$user_id . '>');

        if (is_null($message))
        {
            $message = self::strEndsWith($response, "{$prompt->withoutImagePrompts()}** - Upscaled by <@" . self::$user_id . '> (fast)');
        }

        if (is_null($message)) return null;

        if (property_exists($message, 'attachments') and is_array($message->attachments))
        {
            $attachment = $message->attachments[0];

            return $attachment->url;
        }

        return null;
    }

    public function generate(Prompts $prompt, $upscale_index = 0)
    {
        $imagine = $this->imagine($prompt);

        $upscaled_photo_url = $this->upscale($imagine, $upscale_index);

        return (object) [
            'imagine_message_id' => $imagine->id,
            'upscaled_photo_url' => $upscaled_photo_url
        ];
    }
}
