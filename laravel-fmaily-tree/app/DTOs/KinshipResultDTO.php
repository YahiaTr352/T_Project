<?php

namespace App\DTOs;

class KinshipResultDTO
{
    public array $personA = [];
    public array $personB = [];
    public array $relationship = [];
    public array $path = [];
    public ?array $secondaryPath = null;
    public array $sharedHighlights = [];
    public ?array $branchA = null;
    public ?array $branchB = null;
    public ?array $upperChain = null;

    public function __construct(
        array $personA = [],
        array $personB = [],
        array $relationship = [],
        array $path = [],
        ?array $secondaryPath = null,
        array $sharedHighlights = [],
        ?array $branchA = null,
        ?array $branchB = null,
        ?array $upperChain = null
    ) {
        $this->personA = $personA;
        $this->personB = $personB;
        $this->relationship = $relationship;
        $this->path = $path;
        $this->secondaryPath = $secondaryPath;
        $this->sharedHighlights = $sharedHighlights;
        $this->branchA = $branchA;
        $this->branchB = $branchB;
        $this->upperChain = $upperChain;
    }

    public function toArray(): array
    {
        return [
            'person_a' => isset($this->personA) ? $this->personA : [],
            'person_b' => isset($this->personB) ? $this->personB : [],
            'relationship' => isset($this->relationship) ? $this->relationship : [],
            'path' => isset($this->path) ? $this->path : [],
            'branch_a' => isset($this->branchA) ? $this->branchA : null,
            'branch_b' => isset($this->branchB) ? $this->branchB : null,
            'upper_chain' => isset($this->upperChain) ? $this->upperChain : null,
            'secondary_path' => isset($this->secondaryPath) ? $this->secondaryPath : null,
            'shared_highlights' => isset($this->sharedHighlights) ? $this->sharedHighlights : [],
        ];
    }
}

