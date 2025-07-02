<?php

namespace Spatie\Ping;

use Stringable;

class PingResultLine implements Stringable
{
    public string $rawLine = '';

    public float $timeInMs = 0.0;

    public function __construct(string $line = '', float $timeInMs = 0.0)
    {
        $this->rawLine = $line;

        $this->timeInMs = $timeInMs;
    }

    public static function fromLine(string $line): self
    {
        $timeInMs = 0.0;

        if (preg_match('/time[<=]([0-9.]+)\s*ms/i', $line, $matches)) {
            $timeInMs = (float) $matches[1];
        }

        $result = new self;

        $result->rawLine = trim($line);
        $result->timeInMs = $timeInMs;

        return $result;
    }

    public function getRawLine(): string
    {
        return $this->rawLine;
    }

    public function getTimeInMs(): float
    {
        return $this->timeInMs;
    }

    public function toArray(): array
    {
        return [
            'line' => $this->rawLine,
            'time_in_ms' => $this->timeInMs,
        ];
    }

    public function __toString(): string
    {
        return $this->rawLine;
    }
}
