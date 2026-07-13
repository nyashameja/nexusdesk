<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface ReportRepositoryInterface
{
    /** @return array<string,mixed> Headline KPIs. */
    public function summary(int $days): array;
    /** @return array{labels:string[],created:int[],resolved:int[]} */
    public function ticketVolume(int $days): array;
    /** @return array{labels:string[],counts:int[]} */
    public function statusDistribution(): array;
    /** @return array<int,array<string,mixed>> */
    public function agentPerformance(int $days): array;
    /** @return array<int,array<string,mixed>> */
    public function departmentPerformance(int $days): array;
    /** @return array{labels:string[],counts:int[],average:float} */
    public function csatDistribution(int $days): array;
    /** @return array{compliant:int,breached:int,pct:float} */
    public function slaCompliance(int $days): array;
}
