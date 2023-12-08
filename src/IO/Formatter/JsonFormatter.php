<?php

declare(strict_types=1);

/*
 * This file is part of the Composer package "eliashaeussler/composer-update-check".
 *
 * Copyright (C) 2023 Elias Häußler <elias@haeussler.dev>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

namespace EliasHaeussler\ComposerUpdateCheck\IO\Formatter;

use EliasHaeussler\ComposerUpdateCheck\Entity;
use Symfony\Component\Console;

use function count;
use function json_encode;
use function sprintf;

/**
 * JsonFormatter.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 */
final class JsonFormatter implements Formatter
{
    public const FORMAT = 'json';

    public function __construct(
        private ?Console\Style\SymfonyStyle $io = null,
    ) {}

    public function formatResult(Entity\Result\UpdateCheckResult $result): void
    {
        // Early return if IO is missing
        if (null === $this->io) {
            return;
        }

        $outdatedPackages = $result->getOutdatedPackages();
        $excludedPackages = $result->getExcludedPackages();

        // Print message if no packages are outdated
        if ([] === $outdatedPackages) {
            $this->renderUpToDateMessage($excludedPackages);

            return;
        }

        $json = $this->renderTable($outdatedPackages, $excludedPackages);
        $securityAdvisories = $this->renderSecurityAdvisories($outdatedPackages);

        if ([] !== $securityAdvisories) {
            $json['securityAdvisories'] = $securityAdvisories;
        }

        $this->renderJson($json);
    }

    /**
     * @param list<Entity\Package\Package> $excludedPackages
     */
    private function renderUpToDateMessage(array $excludedPackages): void
    {
        $numberOfExcludedPackages = count($excludedPackages);

        if ($numberOfExcludedPackages > 0) {
            $additionalInformation = sprintf(
                ' (skipped %d package%s)',
                $numberOfExcludedPackages,
                1 !== $numberOfExcludedPackages ? 's' : '',
            );
        } else {
            $additionalInformation = '';
        }

        $json = [
            'status' => sprintf('All packages are up to date%s.', $additionalInformation),
        ];

        if ([] !== $excludedPackages) {
            $json['excludedPackages'] = $excludedPackages;
        }

        $this->renderJson($json);
    }

    /**
     * @param list<Entity\Package\OutdatedPackage> $outdatedPackages
     * @param list<Entity\Package\Package>         $excludedPackages
     *
     * @return array{
     *     status: string,
     *     outdatedPackages: list<array{
     *         name: non-empty-string,
     *         outdatedVersion: string,
     *         newVersion: string,
     *         insecure?: bool,
     *     }>,
     *     excludedPackages?: list<Entity\Package\Package>,
     * }
     */
    private function renderTable(array $outdatedPackages, array $excludedPackages): array
    {
        $numberOfOutdatedPackages = count($outdatedPackages);
        $hasInsecurePackages = false;

        // Create status label
        if (1 === $numberOfOutdatedPackages) {
            $statusLabel = '1 package is outdated.';
        } else {
            $statusLabel = sprintf('%d packages are outdated.', $numberOfOutdatedPackages);
        }

        // Print table
        $tableHeader = ['name', 'outdatedVersion', 'newVersion'];
        $tableRows = $this->parseTableRows($outdatedPackages, $hasInsecurePackages);
        $result = [];

        if ($hasInsecurePackages) {
            $tableHeader[] = 'insecure';
        }

        foreach ($tableRows as $tableRow) {
            $result[] = array_combine($tableHeader, $tableRow);
        }

        $json = [
            'status' => $statusLabel,
            'outdatedPackages' => $result,
        ];

        if ([] !== $excludedPackages) {
            $json['excludedPackages'] = $excludedPackages;
        }

        return $json;
    }

    /**
     * @param list<Entity\Package\OutdatedPackage> $outdatedPackages
     *
     * @return list<array{0: non-empty-string, 1: string, 2: string, 3?: bool}>
     */
    private function parseTableRows(array $outdatedPackages, bool &$hasInsecurePackages = false): array
    {
        $tableRows = [];

        foreach ($outdatedPackages as $outdatedPackage) {
            $report = [
                $outdatedPackage->getName(),
                $outdatedPackage->getOutdatedVersion()->get(),
                $outdatedPackage->getNewVersion()->get(),
            ];

            if ($outdatedPackage->isInsecure()) {
                $report[] = $outdatedPackage->isInsecure();
                $hasInsecurePackages = true;
            }

            $tableRows[] = $report;
        }

        return $tableRows;
    }

    /**
     * @param list<Entity\Package\OutdatedPackage> $outdatedPackages
     *
     * @return array<string, list<array{
     *     title: string,
     *     advisoryId: string,
     *     reportedAt: string,
     *     severity: string,
     *     cve: string|null,
     *     link?: string,
     * }>>
     */
    private function renderSecurityAdvisories(array $outdatedPackages): array
    {
        if (true !== $this->io?->isVerbose()) {
            return [];
        }

        $securityAdvisories = [];

        foreach ($outdatedPackages as $outdatedPackage) {
            if (!$outdatedPackage->isInsecure()) {
                continue;
            }

            $securityAdvisories[$outdatedPackage->getName()] = [];

            foreach ($outdatedPackage->getSecurityAdvisories() as $securityAdvisory) {
                $link = $securityAdvisory->getLink();

                $json = [
                    'title' => $securityAdvisory->getTitle(),
                    'advisoryId' => $securityAdvisory->getAdvisoryId(),
                    'reportedAt' => $securityAdvisory->getReportedAt()->format('Y-m-d H:i:s'),
                    'severity' => $securityAdvisory->getSeverity()->value,
                    'cve' => $securityAdvisory->getCVE(),
                ];

                if (null !== $link) {
                    $json['link'] = (string) $link;
                }

                $securityAdvisories[$outdatedPackage->getName()][] = $json;
            }
        }

        return $securityAdvisories;
    }

    /**
     * @param array<string, mixed> $json
     */
    private function renderJson(array $json): void
    {
        // Early return if output is quiet
        if (false !== $this->io?->isQuiet()) {
            return;
        }

        $flags = JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

        // Pretty-print JSON on verbose output
        if ($this->io->isVerbose()) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $this->io->writeln(json_encode($json, $flags));
    }

    public function setIO(Console\Style\SymfonyStyle $io): void
    {
        $this->io = $io;
    }

    public static function getFormat(): string
    {
        return self::FORMAT;
    }
}
