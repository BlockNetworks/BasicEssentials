<?php

declare(strict_types=1);

namespace bedrockplay\basicessentials\task;

use bedrockplay\basicessentials\BasicEssentials;
use pocketmine\scheduler\Task;

/**
 * Class BroadcastTask
 * @package bedrockplay\basicessentials\task
 */
class BroadcastTask extends Task {

    public const BROADCASTER_MESSAGES = [
        "Vote for our server on bit.do/bedrockplay and get lots of advantages!",
        "Visit our online store at bedrockplay.tebex.io!",
        "We have released new web page! Take a look at bedrockplay.eu!",
        "Join our discord server at discord.io/bedrockplay",
        "Change your language using /lang",
        "We host our servers at tradehosting.it!"
    ];

    /** @var BasicEssentials $plugin */
    public $plugin;

    /**
     * BroadcastTask constructor.
     * @param BasicEssentials $plugin
     */
    public function __construct(BasicEssentials $plugin) {
        $this->plugin = $plugin;
    }

    /**
     * @param int $currentTick
     */
    public function onRun(int $currentTick) {
        $message = self::BROADCASTER_MESSAGES[array_rand(self::BROADCASTER_MESSAGES, 1)];
        foreach ($this->plugin->getServer()->getOnlinePlayers() as $player) {
            $player->sendMessage("§9Tip> §7{$message}");
        }
    }
}