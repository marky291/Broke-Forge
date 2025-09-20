<?php

namespace App\Provision;

abstract class Milestones
{
    /**
     * Get the total count of constant values from milestone
     */
    abstract public function countLabels(): int;
}
