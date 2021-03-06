<?php

namespace ifteam\Purge;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\Player;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\level\Level;
use pocketmine\event\entity\EntityCombustByBlockEvent;
use pocketmine\block\Fire;
use pocketmine\event\entity\EntityCombustEvent;
use pocketmine\event\entity\ExplosionPrimeEvent;
use pocketmine\entity\Entity;
use pocketmine\entity\Arrow;

class Purge extends PluginBase implements Listener {
	public $purgeStarted = false;
	public function onEnable() {
		@mkdir ( $this->getDataFolder () );
		
		$this->initMessage ();
		$this->getServer ()->getScheduler ()->scheduleRepeatingTask ( new PurgeScheduleTask ( $this ), 80 );
		
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
	}
	public function initMessage() {
		$this->saveResource ( "messages.yml", false );
		$this->messages = (new Config ( $this->getDataFolder () . "messages.yml", Config::YAML ))->getAll ();
	}
	public function get($var) {
		return $this->messages [$this->messages ["default-language"] . "-" . $var];
	}
	public function purgeSchedule() {
		$time = $this->getServer ()->getDefaultLevel ()->getTime ();
		$isNight = ($time >= Level::TIME_NIGHT and $time < Level::TIME_SUNRISE);
		if ($this->purgeStarted) {
			if (! $isNight) $this->purgeStop ();
		} else {
			if ($isNight) $this->purgeStart ();
		}
	}
	public function purgeStart() {
		foreach ( $this->getServer ()->getOnlinePlayers () as $player ) {
			$this->alert ( $player, $this->get ( "purge-is-started" ) );
			$this->purgeStarted = true;
		}
	}
	public function purgeStop() {
		foreach ( $this->getServer ()->getOnlinePlayers () as $player ) {
			$this->alert ( $player, $this->get ( "purge-is-stopped" ) );
			$this->purgeStarted = false;
		}
	}
	public function onDamage(EntityDamageEvent $event) {
		if ($event instanceof EntityDamageByEntityEvent) {
			if ($this->purgeStarted) return;
			if ($event->getEntity () instanceof Player and $event->getDamager () instanceof Player) {
				$event->setCancelled ();
			}
		}
	}
	public function onExplode(ExplosionPrimeEvent $event) {
		if ($event->getEntity () instanceof Entity) {
			foreach ( $event->getEntity ()->getLevel ()->getEntities () as $entity ) {
				if (isset ( $event->getEntity ()->shootingEntity )) {
					if ($entity == $event->getEntity ()->shootingEntity) continue;
				}
				if ($entity instanceof Player) if ($event->getEntity ()->distance ( $entity ) <= 6) {
					if (! $this->purgeStarted) $event->setCancelled ();
					break;
				}
			}
		}
	}
	public function onCombust(EntityCombustEvent $event) {
		if ($event instanceof EntityCombustByBlockEvent) {
			if ($this->purgeStarted) return;
			if ($event->getEntity () instanceof Player and $event->getCombuster () instanceof Fire) {
				$event->setCancelled ();
			}
		}
	}
	public function message($player, $text = "", $mark = null) {
		if ($mark == null) $mark = $this->get ( "default-prefix" );
		$player->sendMessage ( TextFormat::DARK_AQUA . $mark . " " . $text );
	}
	public function alert($player, $text = "", $mark = null) {
		if ($mark == null) $mark = $this->get ( "default-prefix" );
		$player->sendMessage ( TextFormat::RED . $mark . " " . $text );
	}
}
?>