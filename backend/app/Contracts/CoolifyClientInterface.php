<?php

namespace App\Contracts;

use App\Models\CoolifyResourceLink;
use App\Models\Deployment;

interface CoolifyClientInterface
{
    /** @return array<string, mixed> */
    public function status(): array;

    /** @return array<int, array<string, mixed>> */
    public function capabilities(): array;

    /** @return array<int, array<string, mixed>> */
    public function resources(?string $type = null): array;

    /** @return array<string, mixed>|null */
    public function resource(string $type, string $uuid): ?array;

    /** @return array<string, mixed> */
    public function deploy(CoolifyResourceLink $link, Deployment $deployment): array;

    /** @return array<string, mixed> */
    public function cancelDeployment(string $deploymentUuid): array;

    /** @return array<int, array<string, mixed>> */
    public function deployments(?CoolifyResourceLink $link = null): array;

    /** @return array<string, mixed>|null */
    public function deployment(string $deploymentUuid): ?array;

    /** @return array<string, mixed> */
    public function deploymentLogs(string $deploymentUuid): array;

    /** @return array<string, mixed> */
    public function resourceAction(CoolifyResourceLink $link, string $action): array;
}
