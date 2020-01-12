<?php


namespace RubyTemple\DeathMoney;


use onebone\economyapi\EconomyAPI;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;
use function str_replace;

class Main extends PluginBase implements Listener{
	/** @var array */
	private $active;
	/** @var EconomyAPI */
	private $economy;

	public function onEnable(){
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->economy = $this->getServer()->getPluginManager()->getPlugin('EconomyAPI');
		if($this->getConfig()->get('activeon') !== null){
			foreach($this->getConfig()->get('activeon') as $world => $number){
				$this->active[$world] = $number;
			}
		}
		else{
			$this->getLogger()->error('Config: activeon is empty');
			$this->getServer()->getPluginManager()->disablePlugin($this);
		}
	}

	/**
	 * @param Player $player
	 *
	 * @return bool
	 */
	public function isValidWorld(Player $player) : bool{
		if(isset($this->active[$player->getLevel()->getFolderName()])) return true;
		return false;
	}

	/**
	 * @param string $message
	 * @param float  $percent
	 * @param float  $money
	 * @param string $damager
	 * @param string $player
	 *
	 * @return string
	 */
	public function correctMessage(string $message, float $percent, float $money, string $damager, string $player): string {
		return str_replace(
			array(
				"%PREFIX%",
				"%PERCENTAGE%",
				"%SUBTRACTED%",
				"%KILLERNAME%",
				"%KILLEDNAME%"
			), array(
				$this->getConfig()->get('Prefix'),
				$percent . "%",
				$money . "$",
				$damager,
				$player
			),
			$message);
	}

	/**
	 * @param PlayerDeathEvent $event
	 */
	public function onDeath(PlayerDeathEvent $event): void{
		$player = $event->getPlayer();
		$cause = $player->getLastDamageCause();
		$money = $this->economy->myMoney($player);
		if($this->isValidWorld($player)){
			$level = $player->getLevel()->getFolderName();
			$percent = $this->active[$level];
			$money = $percent * $money / 100;
			if($cause->getCause() === EntityDamageEvent::CAUSE_ENTITY_ATTACK){
				$damager = $cause->getEntity();
				if($damager instanceof Player){
					$player->sendMessage($this->correctMessage($this->getConfig()->get('killed-message'),$percent,$money,$damager->getName(),$player->getName()));
					$this->economy->reduceMoney($player, $money);
					$damager->sendMessage($this->correctMessage($this->getConfig()->get('killer-message'),$percent,$money,$damager->getName(),$player->getName()));
					$this->economy->addMoney($damager, $money);
				}else{
					$player->sendMessage($this->correctMessage($this->getConfig()->get('death-message'),$percent,$money,"",$player->getName()));
					$this->economy->reduceMoney($player, $money);
				}
			}else{
				$player->sendMessage($this->correctMessage($this->getConfig()->get('death-message'),$percent,$money,"",$player->getName()));
				$this->economy->reduceMoney($player, $money);
			}
		}
	}
}
