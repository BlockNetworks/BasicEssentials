<?php

declare(strict_types=1);

namespace bedrockplay\basicessentials;

use bedrockplay\basicessentials\commands\AddCoinsCommand;
use bedrockplay\basicessentials\commands\BanCommand;
use bedrockplay\basicessentials\commands\CoinsCommand;
use bedrockplay\basicessentials\commands\MuteCommand;
use bedrockplay\basicessentials\commands\ScoreboardCommand;
use bedrockplay\basicessentials\commands\SetRankCommand;
use bedrockplay\basicessentials\task\BroadcastTask;
use bedrockplay\openapi\bossbar\BossBarBuilder;
use bedrockplay\openapi\lang\Translator;
use bedrockplay\openapi\ranks\RankDatabase;
use bedrockplay\openapi\servers\ServerManager;
use bedrockplay\openapi\utils\DeviceData;
use pocketmine\command\defaults\BanIpCommand;
use pocketmine\command\defaults\PardonCommand;
use pocketmine\command\defaults\PardonIpCommand;
use pocketmine\entity\projectile\Arrow;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\level\sound\GenericSound;
use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\network\mcpe\protocol\LevelEventPacket;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\LoginPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;

/**
 * Class BasicEssentials
 * @package bedrockplay\basicessentials
 */
class BasicEssentials extends PluginBase implements Listener {

    /** @var BasicEssentials $instance */
    private static $instance;

    /** @var float[] $chatDelays */
    public $chatDelays = [];
    /** @var array $muted */
    public $muted = [];

    public function onEnable() {
        self::$instance = $this;

        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        $this->getScheduler()->scheduleRepeatingTask(new BroadcastTask($this), 20 * 60 * 5); // Every 5 minutes

        foreach ($this->getServer()->getCommandMap()->getCommands() as $command) {
            if(in_array(get_class($command), [\pocketmine\command\defaults\BanCommand::class, BanIpCommand::class, PardonCommand::class, PardonIpCommand::class])) {
                $this->getServer()->getCommandMap()->unregister($command);
            }
        }

        $this->getServer()->getCommandMap()->register("BasicEssentials", new AddCoinsCommand());
        $this->getServer()->getCommandMap()->register("BasicEssentials", new BanCommand());
        $this->getServer()->getCommandMap()->register("BasicEssentials", new CoinsCommand());
        $this->getServer()->getCommandMap()->register("BasicEssentials", new MuteCommand());
        $this->getServer()->getCommandMap()->register("BasicEssentials", new ScoreboardCommand());
        $this->getServer()->getCommandMap()->register("BasicEssentials", new SetRankCommand());
    }

    public function onDisable() {
        $sleepTime = max(3, ((int)(count($this->getServer()->getOnlinePlayers()) / 10)));

        foreach ($this->getServer()->getOnlinePlayers() as $player) {
            $server = ServerManager::getServerGroup("Lobby")->getFitServer($player);
            $player->sendMessage("§9Server> §6Transferring to {$server->getServerName()} due to server restart.");
            $server->transferPlayerHere($player);
        }

        sleep($sleepTime);
    }

    /**
     * @param DataPacketReceiveEvent $event
     */
    public function onPacketReceive(DataPacketReceiveEvent $event) {
        $packet = $event->getPacket();
        if($packet instanceof LoginPacket) {
            if(in_array($packet->protocol, [407, 408, 409])) {
                $packet->protocol = ProtocolInfo::CURRENT_PROTOCOL;
            }
        }
    }

    /**
     * @param PlayerChatEvent $event
     *
     * @priority LOW
     */
    public function onChat(PlayerChatEvent $event) {
        $player = $event->getPlayer();

        if(isset($this->muted[$player->getName()])) {
            $unmuteTime = $this->muted[$player->getName()];
            if(time() < $unmuteTime) {
                $player->sendMessage("§9Chat> §cYou are muted.");
                $event->setCancelled(true);
                return;
            }

            unset($this->muted[$player->getName()]);
        }

        // Chat delay
        $delay = 2;
        if($player->hasPermission("bedrockplay.vip")) {
            $delay = 0.5;
        }
        if($player->hasPermission("bedrockplay.mvp")) {
            $delay = 0;
        }

        if($delay > 0) {
            if(isset($this->chatDelays[$player->getName()]) && microtime(true) - $this->chatDelays[$player->getName()] <= $delay) {
                $player->sendMessage(Translator::translateMessageWithPrefix($player, "chat-limit", Translator::PREFIX_CHAT, [(string)round($delay - abs( $this->chatDelays[$player->getName()] - microtime(true)), 2)]));
                $event->setCancelled(true);
            }
            else {
                $this->chatDelays[$player->getName()] = microtime(true);
            }
        }

        // CAPS detector
        if(!$player->hasPermission("bedrockplay.vip")) {
            $upperLetters = 0;
            foreach (str_split($event->getMessage()) as $letter) {
                if(ctype_upper($letter)) {
                    $upperLetters++;
                }
            }

            if($upperLetters > 5) {
                $player->sendMessage(Translator::translateWithPrefix($player,"chat-caps", Translator::PREFIX_CHAT));
                $event->setMessage(ucfirst(strtolower($event->getMessage())));
            }
        }

        // Anti advertisement
        if(!$player->hasPermission("bedrockplay.mvp")) {
            $wrong = [".cz", ".one", ".pe", "hicoria.", ".net", "mc-play", "play.", "leet.", ".cc", ".eu", ".com", ":19132", "aternos.", ".aternos", "muj server", "můj server", "nbb.one", "nbbone", "nbb.wtf", "nbbwtf"];
            $problemFound = false;

            $fixedMessage = str_replace([",", "-"], [".", "."], strtolower($event->getMessage()));
            foreach($wrong as $word) {
                if(strpos($fixedMessage, $word) !== false) {
                    $problemFound = true;
                    break;
                }
            }

            if($problemFound) {
                $player->sendMessage(Translator::translateMessageWithPrefix($player,"chat-advertisement",Translator::PREFIX_CHAT));
                $event->setCancelled(true);
            }
        }

        // Format
        $rank = RankDatabase::getPlayerRank($player);
        $chatColor = $player->hasPermission("bedrockplay.vip") ? "§f" : "§7";
        $fontHeightParameter = $rank->getName() === "Guest" ? "՗" : " ";
        $event->setFormat("{$rank->getFormatForChat()}§r§7{$player->getName()}§8:{$chatColor}{$fontHeightParameter}{$event->getMessage()}");
    }

    /**
     * @param PlayerJoinEvent $event
     */
    public function onJoin(PlayerJoinEvent $event) {
        $player = $event->getPlayer();
        $player->setNameTag(RankDatabase::getPlayerRank($player)->getFormatForNameTag() . "§7{$player->getName()}\n§5" . DeviceData::getDeviceName($player));
        $player->setImmobile();

        $this->getScheduler()->scheduleDelayedTask(new ClosureTask(function (int $currentTick) use ($player): void {
            if($player === null || !$player->isOnline()) {
                return;
            }

            $player->setImmobile(false);
//            BossBarBuilder::sendBossBarText($player, "§eBedrock§6Play §7| §a". ServerManager::getCurrentServer()->getServerName());
        }), 40);
    }

    /**
     * @param PlayerMoveEvent $event
     */
    public function onMove(PlayerMoveEvent $event) {
        $player = $event->getPlayer();
        if(!$event->isCancelled()) {
            $player->sendPosition($player, $player->yaw, $player->pitch, MovePlayerPacket::MODE_NORMAL, $player->getViewers());
        }
    }

    /**
     * @param EntityDamageEvent $event
     */
    public function onDamage(EntityDamageEvent $event) {
        $player = $event->getEntity();

        if(strpos(ServerManager::getCurrentServer()->getServerName(), "Lobby") === false && $player instanceof Player && $event instanceof EntityDamageByChildEntityEvent) {
            $arrow = $event->getChild();
            $damager = $event->getDamager();
            if($arrow instanceof Arrow && $damager instanceof Player) {
                $damager->sendMessage("§9Game> §aPlayer is on " . (string)round($player->getHealth() - $event->getFinalDamage()) . " HP!");
                $damager->getLevel()->addSound(new GenericSound($damager, LevelEventPacket::EVENT_SOUND_ORB), [$damager]);
            }
        }
    }

    /**
     * @return BasicEssentials
     */
    public static function getInstance(): BasicEssentials {
        return self::$instance;
    }
}