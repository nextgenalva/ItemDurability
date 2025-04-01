<?php

/**
 * MIT License
 *
 * Copyright (c) 2025 ImmoDevs
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * Copyright is perpetual and does not expire.
 *
 * @auto-license
 */

declare(strict_types=1);

namespace ImmoDevs\ItemDurability;

use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\item\Durable;
use pocketmine\item\Item;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;
use pocketmine\player\Player;

class ItemDurability extends PluginBase implements Listener
{
    private array $lastUpdate = [];

    public function onEnable(): void
    {
        $this->saveDefaultConfig();
        $this->reloadConfig();
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getLogger()->info("ItemDurability plugin has been enabled!");
    }

    public function onPlayerJoin(PlayerJoinEvent $event): void
    {
        $this->updateItemDurability($event->getPlayer());
    }

    public function onItemHeld(PlayerItemHeldEvent $event): void
    {
        $this->updateItemDurability($event->getPlayer());
    }

    public function onInteract(PlayerInteractEvent $event): void
    {
        $this->updateItemDurability($event->getPlayer());
    }

    public function onBlockBreak(BlockBreakEvent $event): void
    {
        $this->updateItemDurability($event->getPlayer());
    }

    public function onEntityDamage(EntityDamageByEntityEvent $event): void
    {
        $damager = $event->getDamager();
        if ($damager instanceof Player) {
            $this->updateItemDurability($damager);
        }
    }

    public function onItemUse(PlayerItemUseEvent $event): void
    {
        $this->updateItemDurability($event->getPlayer());
    }

    public function onItemConsume(PlayerItemConsumeEvent $event): void
    {
        $this->updateItemDurability($event->getPlayer());
    }

    public function updateItemDurability(Player $player): void
    {
        $currentTime = microtime(true);
        if (
            isset($this->lastUpdate[$player->getName()]) &&
            $currentTime - $this->lastUpdate[$player->getName()] < 0.1
        ) {
            return;
        }

        $inventory = $player->getInventory();
        $slot = $inventory->getHeldItemIndex();
        $item = $inventory->getItem($slot);

        if ($this->hasValidDurability($item)) {
            try {
                $this->getScheduler()->scheduleDelayedTask(new UpdateDurabilityTask($this, $player, $item), 1);
                $this->lastUpdate[$player->getName()] = $currentTime;
            } catch (\Exception $e) {
                $this->getLogger()->error("Error updating item durability: " . $e->getMessage());
            }
        }
    }

    public function hasValidDurability(Item $item): bool
    {
        return $item instanceof Durable;
    }

    public function updateItemLore(Item $item, Player $player): void
    {
        if (!$this->hasValidDurability($item)) {
            return;
        }

        /** @var Durable $item */
        $maxDurability = $item->getMaxDurability();
        $currentDurability = $item->getDamage();
        $remainingDurability = $maxDurability - $currentDurability;
        
        $durabilityPercentage = ($remainingDurability / $maxDurability) * 100;
        
        $lore = $item->getLore();
        $filteredLore = [];
        
        foreach ($lore as $line) {
            if (strpos($line, "Durability:") === false) {
                $filteredLore[] = $line;
            }
        }

        $format = $this->getConfig()->get("durability_format", "Durability: [%current%/%max%]");
        $durabilityText = str_replace(
            ["%current%", "%max%", "%percent%"], 
            [$remainingDurability, $maxDurability, round($durabilityPercentage)], 
            $format
        );
        
        $color = $this->getDurabilityColor($durabilityPercentage);
        
        $enableLowDurabilityWarning = $this->getConfig()->get("enable_low_durability_warning", true);
        $lowDurabilityPercentage = $this->getConfig()->get("low_durability_percentage", 10);
        
        if ($enableLowDurabilityWarning && $durabilityPercentage <= $lowDurabilityPercentage) {
            $lowDurabilityColorName = $this->getConfig()->get("low_durability_color", "RED");
            $color = $this->getTextFormatColor($lowDurabilityColorName);
        }
        
        $filteredLore[] = $color . $durabilityText;
        
        $item->setLore($filteredLore);
        $inventory = $player->getInventory();
        $slot = $inventory->getHeldItemIndex();
        $inventory->setItem($slot, $item);
    }
    
    /**
     * Get TextFormat color constant from config string
     *
     * @param string $colorName The color name (RED, GREEN, BLUE, etc.)
     * @return string The TextFormat color constant
     */
    private function getTextFormatColor(string $colorName): string
    {
        $colorName = strtoupper($colorName);
        $colors = [
            "BLACK" => TextFormat::BLACK,
            "DARK_BLUE" => TextFormat::DARK_BLUE,
            "DARK_GREEN" => TextFormat::DARK_GREEN,
            "DARK_AQUA" => TextFormat::DARK_AQUA,
            "DARK_RED" => TextFormat::DARK_RED,
            "DARK_PURPLE" => TextFormat::DARK_PURPLE,
            "GOLD" => TextFormat::GOLD,
            "GRAY" => TextFormat::GRAY,
            "DARK_GRAY" => TextFormat::DARK_GRAY,
            "BLUE" => TextFormat::BLUE,
            "GREEN" => TextFormat::GREEN,
            "AQUA" => TextFormat::AQUA,
            "RED" => TextFormat::RED,
            "LIGHT_PURPLE" => TextFormat::LIGHT_PURPLE,
            "YELLOW" => TextFormat::YELLOW,
            "WHITE" => TextFormat::WHITE
        ];
        
        return $colors[$colorName] ?? TextFormat::GREEN;
    }
    
    /**
     * Gets a color based on durability percentage
     * 
     * @param float $percentage The durability percentage
     * @return string The TextFormat color code
     */
    private function getDurabilityColor(float $percentage): string
    {
        if ($percentage >= 80) {
            return TextFormat::GREEN;
        } elseif ($percentage >= 60) {
            return TextFormat::DARK_GREEN;
        } elseif ($percentage >= 40) {
            return TextFormat::YELLOW; 
        } elseif ($percentage >= 20) {
            return TextFormat::GOLD;
        } elseif ($percentage >= 10) {
            return TextFormat::RED;
        } else {
            return TextFormat::DARK_RED;
        }
    }
}
