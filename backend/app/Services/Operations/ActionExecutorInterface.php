<?php

namespace App\Services\Operations;

use App\Data\ActionRunResultData;
use App\Models\ActionExecution;

interface ActionExecutorInterface
{
    /**
     * @param  array<string, mixed>  $definition
     */
    public function run(ActionExecution $execution, array $definition, string $workingDirectory): ActionRunResultData;
}
