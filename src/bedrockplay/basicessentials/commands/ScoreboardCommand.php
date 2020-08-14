<?php

declare(strict_types=1);

namespace bedrockplay\basicessentials\commands;

use bedrockplay\openapi\scoreboard\ScoreboardBuilder;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;

/**
 * Class ScoreboardCommand
 * @package bedrockplay\basicessentials\commands
 */
class ScoreboardCommand extends Command {

    /**
     * ScoreboardCommand constructor.
     */
    public function __construct() {
        parent::__construct("scoreboard", "Sends scoreboard text to player");
    }

    /**
     * @param CommandSender $sender
     * @param string $commandLabel
     * @param string[] $args
     *
     * @return void
     */
    public function execute(CommandSender $sender, string $commandLabel, array $args) {
        if(!$sender instanceof Player) {
            return;
        }
        if(!$sender->getName() === "VixikCZ") {
            return;
        }
        if(empty($args)) {
            return;
        }
        ScoreboardBuilder::sendScoreBoard($sender, str_replace(".", "\n", implode(" ", $args)));
    }
}