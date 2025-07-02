<?php

namespace Spatie\Ping;

use Stringable;

class PingResult implements Stringable
{
    protected bool $success = false;

    protected ?PingError $error = null;

    protected ?string $host = null;

    protected ?int $packetLossPercentage = null;

    protected ?int $numberOfPacketsTransmitted = null;

    protected ?int $numberOfPacketsReceived = null;

    protected ?int $timeout = null;

    protected float $interval = 1.0;

    protected int $packetSize = 56;

    protected int $ttl = 64;

    protected ?float $minimumTimeInMs = null;

    protected ?float $maximumTimeInMs = null;

    protected ?float $averageTimeInMs = null;

    protected ?float $standardDeviationTimeInMs = null;

    public string $raw = '';

    /** @var array<int, \Spatie\Ping\PingResultLine> */
    protected array $lines = [];

    public static function fromPingOutput(
        array $output,
        int $returnCode,
        string $host,
        int $timeout,
        float $interval = 1.0,
        int $packetSize = 56,
        int $ttl = 64
    ): self {
        $outputString = implode("\n", $output);

        $result = new self;
        $result->raw = $outputString;
        $result->host = $host;
        $result->timeout = $timeout;
        $result->interval = $interval;
        $result->packetSize = $packetSize;
        $result->ttl = $ttl;

        if ($returnCode !== 0) {
            $result->error = self::determineErrorFromOutput($outputString);
            $result->packetLossPercentage = 100;
            $result->success = false;

            return $result;
        }

        $result->lines = self::parsePingLines($output);

        self::extractStatistics($result, $outputString);

        $result->success = ($result->packetLossPercentage ?? 0) < 100;

        return $result;
    }

    public static function fromArray(array $data): self
    {
        $result = new self;

        $result->success = $data['success'] ?? false;
        $result->error = isset($data['error']) ? PingError::from($data['error']) : null;
        $result->host = $data['host'] ?? null;
        $result->packetLossPercentage = $data['packet_loss'] ?? null;
        $result->numberOfPacketsTransmitted = $data['packets_transmitted'] ?? null;
        $result->numberOfPacketsReceived = $data['packets_received'] ?? null;

        $result->timeout = $data['options']['timeout'] ?? null;
        $result->interval = $data['options']['interval'] ?? 1.0;
        $result->packetSize = $data['options']['packet_size'] ?? 56;
        $result->ttl = $data['options']['ttl'] ?? 64;

        $result->minimumTimeInMs = $data['timings']['minimum_time'] ?? null;
        $result->maximumTimeInMs = $data['timings']['maximum_time'] ?? null;
        $result->averageTimeInMs = $data['timings']['average_time'] ?? null;
        $result->standardDeviationTimeInMs = $data['timings']['standard_deviation_time'] ?? null;

        $result->raw = $data['raw_output'] ?? '';
        $result->lines = array_map(
            fn ($lineData) => PingResultLine::fromLine($lineData['line']),
            $data['lines'] ?? []
        );

        return $result;
    }

    /**
     * @return array<int, PingResultLine>
     */
    public function lines(): array
    {
        return $this->lines;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function error(): ?PingError
    {
        return $this->error;
    }

    public function hasError(): bool
    {
        return $this->error !== null;
    }

    public function packetsTransmitted(): ?int
    {
        return $this->numberOfPacketsTransmitted;
    }

    public function packetsReceived(): ?int
    {
        return $this->numberOfPacketsReceived;
    }

    public function minimumTime(): ?float
    {
        return $this->minimumTimeInMs;
    }

    public function maximumTime(): ?float
    {
        return $this->maximumTimeInMs;
    }

    public function averageTime(): ?float
    {
        return $this->averageTimeInMs;
    }

    public function standardDeviationTime(): ?float
    {
        return $this->standardDeviationTimeInMs;
    }

    public function averageResponseTimeInMs(): float
    {
        // Return average time if available, otherwise calculate from lines
        if ($this->averageTimeInMs !== null) {
            return $this->averageTimeInMs;
        }

        if (empty($this->lines)) {
            return 0.0;
        }

        $total = 0.0;
        foreach ($this->lines as $line) {
            $total += $line->timeInMs;
        }

        return $total / count($this->lines);
    }

    public function packetLossPercentage(): ?int
    {
        return $this->packetLossPercentage;
    }

    public function getHost(): ?string
    {
        return $this->host;
    }

    public function timeout(): ?int
    {
        return $this->timeout;
    }

    protected static function determineErrorFromOutput(string $output): PingError
    {
        $output = strtolower($output);

        if (str_contains($output, 'unknown host') || str_contains($output, 'name or service not known')) {
            return PingError::HostnameNotFound;
        }

        if (str_contains($output, 'no route to host') || str_contains($output, 'host unreachable')) {
            return PingError::HostUnreachable;
        }

        if (str_contains($output, 'permission denied')) {
            return PingError::PermissionDenied;
        }

        if (str_contains($output, 'timeout') || str_contains($output, 'timed out')) {
            return PingError::Timeout;
        }

        return PingError::UnknownError;
    }

    protected static function parsePingLines(array $output): array
    {
        $pingResponseLines = [];

        foreach ($output as $rawLine) {
            $cleanLine = trim($rawLine);

            if (self::isEmptyLine($cleanLine)) {
                continue;
            }

            if (self::isPingResponseLine($cleanLine)) {
                $pingResponseLines[] = PingResultLine::fromLine($cleanLine);
            }
        }

        return $pingResponseLines;
    }

    private static function isEmptyLine(string $line): bool
    {
        return empty($line);
    }

    private static function isPingResponseLine(string $line): bool
    {
        return preg_match('/time[<=]([0-9.]+)\s*ms/i', $line) === 1;
    }

    protected static function extractStatistics(self $result, string $output): void
    {
        self::extractPacketStatistics($result, $output);
        self::extractTimingStatistics($result, $output);
        self::extractPacketLossPercentageFallback($result, $output);
    }

    private static function extractPacketStatistics(self $result, string $output): void
    {
        $packetPattern = '/(\d+)\s+packets?\s+transmitted,\s+(\d+)\s+(?:packets?\s+)?received/i';

        if (! preg_match($packetPattern, $output, $matches)) {
            return;
        }

        $packetsTransmitted = (int) $matches[1];
        $packetsReceived = (int) $matches[2];

        $result->numberOfPacketsTransmitted = $packetsTransmitted;
        $result->numberOfPacketsReceived = $packetsReceived;
        $result->packetLossPercentage = self::calculatePacketLossPercentage(
            $packetsTransmitted,
            $packetsReceived
        );
    }

    private static function calculatePacketLossPercentage(int $transmitted, int $received): int
    {
        if ($transmitted === 0) {
            return 100;
        }

        $lossPercentage = (($transmitted - $received) / $transmitted) * 100;

        return (int) round($lossPercentage);
    }

    private static function extractTimingStatistics(self $result, string $output): void
    {
        $timingPattern = '/min\/avg\/max\/(?:stddev|mdev)\s*=\s*([0-9.]+)\/([0-9.]+)\/([0-9.]+)\/([0-9.]+)\s*ms/i';

        if (! preg_match($timingPattern, $output, $matches)) {
            return;
        }

        $result->minimumTimeInMs = (float) $matches[1];
        $result->averageTimeInMs = (float) $matches[2];
        $result->maximumTimeInMs = (float) $matches[3];
        $result->standardDeviationTimeInMs = (float) $matches[4];
    }

    private static function extractPacketLossPercentageFallback(self $result, string $output): void
    {
        if ($result->packetLossPercentage !== null) {
            return; // Already extracted from packet statistics
        }

        $fallbackPatterns = [
            '/(\d+)%\s*packet\s*loss/i',
            '/(\d+)%\s*loss/i',
        ];

        foreach ($fallbackPatterns as $pattern) {
            if (preg_match($pattern, $output, $matches)) {
                $result->packetLossPercentage = (int) $matches[1];

                return;
            }
        }
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'error' => $this->error?->value,
            'host' => $this->host,
            'packet_loss' => $this->packetLossPercentage ?? 0,
            'packets_transmitted' => $this->numberOfPacketsTransmitted,
            'packets_received' => $this->numberOfPacketsReceived,
            'response_time' => $this->averageResponseTimeInMs(),
            'options' => [
                'timeout' => $this->timeout,
                'interval' => $this->interval,
                'packet_size' => $this->packetSize,
                'ttl' => $this->ttl,
            ],
            'timings' => [
                'minimum_time' => $this->minimumTimeInMs,
                'maximum_time' => $this->maximumTimeInMs,
                'average_time' => $this->averageTimeInMs,
                'standard_deviation_time' => $this->standardDeviationTimeInMs,
            ],
            'raw_output' => $this->raw,
            'lines' => array_map(fn ($line) => $line->toArray(), $this->lines),
        ];
    }

    public function __toString(): string
    {
        return $this->raw;
    }
}
