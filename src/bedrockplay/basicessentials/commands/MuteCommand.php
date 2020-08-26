<?php

declare(strict_types=1);

namespace bedrockplay\basicessentials\commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Server;

/**
 * Class MuteCommand
 * @package bedrockplay\basicessentials\commands
 */
class MuteCommand extends Command {

    /**
     * MuteCommand constructor.
     */
    public function __construct() {
        parent::__construct("mute", "Mute a player");
        $this->setPermission("bedrockplay.helper");
    }

    /**
     * @param CommandSender $sender
     * @param string $commandLabel
     * @param string[] $args
     */
    public function execute(CommandSender $sender, string $commandLabel, array $args) {
        if(!$this->testPermission($sender)) {
            return;
        }

        if(count($args) < 2) {
            $sender->sendMessage("§cUsage: §7/mute <player> <time>");
            return;
        }

        $player = Server::getInstance()->getPlayer($args[0]);
        if($player === null) {
            $sender->sendMessage("§9Chat> §cCould not find player {$args[0]}.");
            return;
        }

        $sender->sendMessage("§9Chat> §aPlayer muted!");

    }
}