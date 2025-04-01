<?php

declare(strict_types=1);

namespace ImmoDevs\ItemDurability;

use pocketmine\player\Player;
use pocketmine\scheduler\Task;

class UpdateDurabilityTask extends Task
{
    private ItemDurability $plugin;
    private Player $player;
    private int $slot;

    public function __construct(ItemDurability $plugin, Player $player, int $slot)
    {
        $this->plugin = $plugin;
        $this->player = $player;
        $this->slot = $slot;
    }

    public function onRun(): void
    {
        if ($this->player->isOnline()) {
            $inventory = $this->player->getInventory();
            $currentSlot = $inventory->getHeldItemIndex();

            if ($currentSlot === $this->slot) {
                $item = $inventory->getItem($this->slot);
                if ($this->plugin->hasValidDurability($item)) {
                    $this->plugin->updateItemLore($item, $this->player);
                    $inventory->setItem($this->slot, $item);
                }
            }
        }
    }
}
