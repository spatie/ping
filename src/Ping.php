<?php

namespace Spatie\Ping;

use Symfony\Component\Process\Process;

class Ping
{
    protected array $currentCommand = [];

    public function __construct(
        protected string $hostname,
        protected int $timeoutInSeconds = 5,
        protected int $count = 1,
        protected float $intervalInSeconds = 1.0,
        protected int $packetSizeInBytes = 56,
        protected int $ttl = 64,
    ) {}

    public function run(): PingResult
    {
        $pingCommand = $this->buildPingCommand();
        $processResult = $this->executePingCommand($pingCommand);
        $combinedOutput = $this->combineOutputLines($processResult);

        return PingResult::fromPingOutput(
            output: $combinedOutput,
            returnCode: $processResult->getExitCode(),
            host: $this->hostname,
            timeout: $this->timeoutInSeconds,
            interval: $this->intervalInSeconds,
            packetSize: $this->packetSizeInBytes,
            ttl: $this->ttl,
        );
    }

    protected function executePingCommand(array $command): Process
    {
        $timeoutWithBuffer = $this->timeoutInSeconds + 2;

        $process = new Process($command);
        $process->setTimeout($timeoutWithBuffer);
        $process->run();

        return $process;
    }

    protected function combineOutputLines($processResult): array
    {
        $standardOutput = explode("\n", $processResult->getOutput());

        $errorOutput = $processResult->getErrorOutput();

        if (empty($errorOutput)) {
            return $standardOutput;
        }

        $errorLines = explode("\n", $errorOutput);

        return array_merge($standardOutput, $errorLines);
    }

    public function timeoutInSeconds(int $timeout): self
    {
        $this->timeoutInSeconds = $timeout;

        return $this;
    }

    public function count(int $count): self
    {
        $this->count = $count;

        return $this;
    }

    public function intervalInSeconds(float $interval): self
    {
        $this->intervalInSeconds = $interval;

        return $this;
    }

    public function packetSizeInBytes(int $packetSizeInBytes): self
    {
        $this->packetSizeInBytes = $packetSizeInBytes;

        return $this;
    }

    public function ttl(int $ttl): self
    {
        $this->ttl = $ttl;

        return $this;
    }

    protected function buildPingCommand(): array
    {
        return $this->startWithPingCommand()
            ->addPacketCountOption()
            ->addTimeoutOption()
            ->addOptionalIntervalOption()
            ->addOptionalPacketSizeOption()
            ->addOptionalTtlOption()
            ->addTargetHostname()
            ->getCommand();
    }

    protected function startWithPingCommand(): self
    {
        $this->currentCommand = ['ping'];

        return $this;
    }

    protected function getCommand(): array
    {
        return $this->currentCommand;
    }

    protected function addPacketCountOption(): self
    {
        $this->currentCommand[] = '-c';
        $this->currentCommand[] = (string) $this->count;

        return $this;
    }

    protected function addTimeoutOption(): self
    {
        $this->currentCommand[] = '-W';

        if ($this->isRunningOnMacOS()) {
            $this->currentCommand[] = (string) $this->convertTimeoutToMilliseconds();
        } else {
            $this->currentCommand[] = (string) $this->timeoutInSeconds;
        }

        return $this;
    }

    protected function addOptionalIntervalOption(): self
    {
        if ($this->isCustomInterval()) {
            $this->currentCommand[] = '-i';
            $this->currentCommand[] = (string) $this->intervalInSeconds;
        }

        return $this;
    }

    protected function addOptionalPacketSizeOption(): self
    {
        if ($this->isCustomPacketSize()) {
            $this->currentCommand[] = '-s';
            $this->currentCommand[] = (string) $this->packetSizeInBytes;
        }

        return $this;
    }

    protected function addOptionalTtlOption(): self
    {
        if ($this->isCustomTtl()) {
            $this->currentCommand[] = '-t';
            $this->currentCommand[] = (string) $this->ttl;
        }

        return $this;
    }

    protected function addTargetHostname(): self
    {
        $this->currentCommand[] = $this->hostname;

        return $this;
    }

    protected function isRunningOnMacOS(): bool
    {
        return PHP_OS_FAMILY === 'Darwin';
    }

    protected function convertTimeoutToMilliseconds(): int
    {
        return $this->timeoutInSeconds * 1000;
    }

    protected function isCustomInterval(): bool
    {
        return $this->intervalInSeconds !== 1.0;
    }

    protected function isCustomPacketSize(): bool
    {
        return $this->packetSizeInBytes !== 56;
    }

    protected function isCustomTtl(): bool
    {
        return $this->ttl !== 64;
    }
}
