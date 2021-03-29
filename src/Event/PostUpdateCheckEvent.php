<?php
declare(strict_types=1);
namespace EliasHaeussler\ComposerUpdateCheck\Event;

/*
 * This file is part of the Composer package "eliashaeussler/composer-update-check".
 *
 * Copyright (C) 2020 Elias Häußler <elias@haeussler.dev>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

use Composer\Plugin\CommandEvent;
use EliasHaeussler\ComposerUpdateCheck\Package\UpdateCheckResult;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * PostUpdateCheckEvent
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 * @codeCoverageIgnore
 */
class PostUpdateCheckEvent extends CommandEvent
{
    /**
     * @var UpdateCheckResult|null
     */
    private $updateCheckResult;

    /**
     * @param string $name
     * @param string $commandName
     * @param InputInterface$input
     * @param OutputInterface $output
     * @param string[] $args
     * @param string[] $flags
     * @param UpdateCheckResult|null $updateCheckResult
     */
    public function __construct(
        $name,
        $commandName,
        $input,
        $output,
        array $args = [],
        array $flags = [],
        UpdateCheckResult $updateCheckResult = null
    ) {
        parent::__construct($name, $commandName, $input, $output, $args, $flags);
        $this->updateCheckResult = $updateCheckResult;
    }

    public function getUpdateCheckResult(): ?UpdateCheckResult
    {
        return $this->updateCheckResult;
    }
}
