<?php

namespace App\Infrastructure\Benchmark;

use App\Application\Variant;

final readonly class CellResult
{
    public function __construct(
        public Scenario $scenario,
        public Variant $variant,
        public int $size,
        public CellStatus $status,
        public ?float $medianMs = null,
        public ?int $queries = null,
        public ?int $peakBytes = null,
        public ?string $error = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'scenario' => $this->scenario->value,
            'variant' => $this->variant->value,
            'size' => $this->size,
            'status' => $this->status->value,
            'medianMs' => $this->medianMs,
            'queries' => $this->queries,
            'peakBytes' => $this->peakBytes,
            'error' => $this->error,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Scenario::from($data['scenario']),
            Variant::from($data['variant']),
            $data['size'],
            CellStatus::from($data['status']),
            isset($data['medianMs']) ? (float) $data['medianMs'] : null,
            $data['queries'] ?? null,
            $data['peakBytes'] ?? null,
            $data['error'] ?? null,
        );
    }
}
