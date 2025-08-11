<?php

namespace Tests\Feature\PingCheck;

use Exception;
use ReflectionClass;
use Spatie\Ping\Enums\PingError;
use Spatie\Ping\Ping;
use Spatie\Ping\PingResult;
use Spatie\Ping\PingResultLine;

it('can perform a successful ping check', function () {
    $checker = new Ping('8.8.8.8', timeoutInSeconds: 5, count: 3);

    $result = $checker->run();

    expect($result)->toBeInstanceOf(PingResult::class);
    expect($result->isSuccess())->toBeTrue();
    expect($result->packetLossPercentage())->toBeLessThan(100);
    expect($result->averageResponseTimeInMs())->toBeGreaterThan(0);
    expect($result->rawOutput())->toContain('8.8.8.8');
})->skipOnGitHubActions();

it('can handle failed ping to non-existent host', function () {
    $checker = new Ping('non-existent-host-12345.invalid', timeoutInSeconds: 2, count: 1);

    $result = $checker->run();

    expect($result)->toBeInstanceOf(PingResult::class);
    expect($result->isSuccess())->toBeFalse();
    expect($result->packetLossPercentage())->toBe(100);
    expect($result->hasError())->toBeTrue();
    expect($result->error())->toBe(PingError::HostnameNotFound);
})->skipOnGitHubActions();

it('can parse ping result with multiple packets', function () {
    $checker = new Ping('8.8.8.8', timeoutInSeconds: 5, count: 4);

    $result = $checker->run();

    expect($result)->toBeInstanceOf(PingResult::class);
    expect($result->lines())->toHaveCount(4);

    foreach ($result->lines() as $line) {
        expect($line)->toBeInstanceOf(PingResultLine::class);
        expect($line->getTimeInMs())->toBeGreaterThan(0);
        expect($line->getRawLine())->toContain('time');
    }
})->skipOnGitHubActions();

it('can extract packet statistics', function () {
    $checker = new Ping('8.8.8.8', timeoutInSeconds: 5, count: 3);

    $result = $checker->run();

    expect($result->packetsTransmitted())->toBe(3);
    expect($result->packetsReceived())->toBeGreaterThanOrEqual(0);
    expect($result->packetsReceived())->toBeLessThanOrEqual(3);

    if ($result->minimumTimeInMs() !== null) {
        expect($result->minimumTimeInMs())->toBeGreaterThan(0);
        expect($result->maximumTimeInMs())->toBeGreaterThanOrEqual($result->minimumTimeInMs());
        expect($result->averageTimeInMs())->toBeBetween($result->minimumTimeInMs(), $result->maximumTimeInMs());
    }
})->skipOnGitHubActions();

it('can convert result to array', function () {
    $checker = new Ping('8.8.8.8', timeoutInSeconds: 3, count: 2, intervalInSeconds: 1.5, packetSizeInBytes: 64, ttl: 32);

    $result = $checker->run();
    $array = $result->toArray();

    expect($array)->toHaveKeys([
        'success',
        'error',
        'host',
        'packet_loss_percentage',
        'packets_transmitted',
        'packets_received',
        'options',
        'timings',
        'raw_output',
        'lines',
    ]);

    expect($array['success'])->toBeBool();
    expect($array['packet_loss_percentage'])->toBeInt();
    expect($array['host'])->toBe('8.8.8.8');
    expect($array['lines'])->toBeArray();
    expect(count($array['lines']))->toBe(count($result->lines()));

    expect($array['options'])->toHaveKeys(['timeout_in_seconds', 'interval', 'packet_size_in_bytes', 'ttl']);
    expect($array['options']['timeout_in_seconds'])->toBe(3);
    expect($array['options']['interval'])->toBe(1.5);
    expect($array['options']['packet_size_in_bytes'])->toBe(64);
    expect($array['options']['ttl'])->toBe(32);

    expect($array['timings'])->toHaveKeys(['minimum_time_in_ms', 'maximum_time_in_ms', 'average_time_in_ms', 'standard_deviation_time_in_ms']);
})->skipOnGitHubActions();

it('handles timeout correctly', function () {
    $checker = new Ping('192.0.2.1', timeoutInSeconds: 2, count: 1); // Use TEST-NET-1 address

    $result = $checker->run();

    expect($result)->toBeInstanceOf(PingResult::class);
    // This should timeout or fail - we just ensure it returns a valid result
    expect($result->packetLossPercentage())->toBeInt();
    expect($result->packetLossPercentage())->toBeBetween(0, 100);
})->skipOnGitHubActions();

it('creates ping result line correctly', function () {
    $line = PingResultLine::fromLine('64 bytes from 8.8.8.8: icmp_seq=1 ttl=117 time=15.2 ms');

    expect($line->getRawLine())->toBe('64 bytes from 8.8.8.8: icmp_seq=1 ttl=117 time=15.2 ms');
    expect($line->getTimeInMs())->toBe(15.2);
    expect($line->toArray())->toBe([
        'line' => '64 bytes from 8.8.8.8: icmp_seq=1 ttl=117 time=15.2 ms',
        'time_in_ms' => 15.2,
    ]);
    expect((string) $line)->toBe('64 bytes from 8.8.8.8: icmp_seq=1 ttl=117 time=15.2 ms');
});

it('can parse a successful ping output with statistics', function () {
    $output = [
        'PING 8.8.8.8 (8.8.8.8): 56 data bytes',
        '64 bytes from 8.8.8.8: icmp_seq=0 ttl=117 time=8.5 ms',
        '64 bytes from 8.8.8.8: icmp_seq=1 ttl=117 time=12.3 ms',
        '64 bytes from 8.8.8.8: icmp_seq=2 ttl=117 time=10.2 ms',
        '',
        '--- 8.8.8.8 ping statistics ---',
        '3 packets transmitted, 3 received, 0% packet loss',
        'round-trip min/avg/max/stddev = 8.5/10.2/12.3/1.8 ms',
    ];

    $result = PingResult::fromPingOutput($output, 0, '8.8.8.8', 5);

    expect($result->lines())->toHaveCount(3);
    expect($result->packetsTransmitted())->toBe(3);
    expect($result->packetsReceived())->toBe(3);
    expect($result->minimumTimeInMs())->toBe(8.5);
    expect($result->maximumTimeInMs())->toBe(12.3);
    expect($result->averageTimeInMs())->toBe(10.2);
    expect($result->standardDeviationTimeInMs())->toBe(1.8);
    expect($result->isSuccess())->toBe(true);
    expect($result->packetLossPercentage())->toBe(0);
    expect($result->error())->toBeNull();
    expect($result->hasError())->toBe(false);
});

it('calculates response time from lines when average not available', function () {
    $output = [
        '64 bytes from 8.8.8.8: icmp_seq=0 ttl=117 time=10.0 ms',
        '64 bytes from 8.8.8.8: icmp_seq=1 ttl=117 time=20.0 ms',
        '64 bytes from 8.8.8.8: icmp_seq=2 ttl=117 time=30.0 ms',
    ];

    $result = PingResult::fromPingOutput($output, 0, '8.8.8.8', 5);

    expect($result->averageResponseTimeInMs())->toBe(20.0); // Average of 10, 20, 30
});

it('returns zero response time when no lines and no average', function () {
    $result = PingResult::fromPingOutput([''], 1, 'example.com', 5); // Failed ping with no output

    expect($result->averageResponseTimeInMs())->toBe(0.0);
});

it('prefers average time over calculated time from lines', function () {
    $output = [
        '64 bytes from 8.8.8.8: icmp_seq=0 ttl=117 time=100.0 ms',
        '64 bytes from 8.8.8.8: icmp_seq=1 ttl=117 time=200.0 ms',
        '',
        '--- 8.8.8.8 ping statistics ---',
        '2 packets transmitted, 2 received, 0% packet loss',
        'round-trip min/avg/max/stddev = 100.0/50.0/200.0/50.0 ms',
    ];

    $result = PingResult::fromPingOutput($output, 0, '8.8.8.8', 5);

    expect($result->averageResponseTimeInMs())->toBe(50.0); // Should use parsed average, not calculated 150.0
});

it('can perform ping check with custom interval', function () {
    $checker = new Ping('8.8.8.8', timeoutInSeconds: 5, count: 3, intervalInSeconds: 0.5);

    $result = $checker->run();

    expect($result)->toBeInstanceOf(PingResult::class);
    expect($result->isSuccess())->toBeTrue();
    expect($result->packetLossPercentage())->toBeLessThan(100);
    expect($result->averageResponseTimeInMs())->toBeGreaterThan(0);
    expect($result->rawOutput())->toContain('8.8.8.8');
})->skipOnGitHubActions();

it('builds ping command with interval correctly', function () {
    $checker = new Ping('example.com', timeoutInSeconds: 5, count: 2, intervalInSeconds: 1.5);

    $reflection = new ReflectionClass($checker);
    $method = $reflection->getMethod('buildPingCommand');
    $method->setAccessible(true);

    $command = $method->invoke($checker);

    expect($command)->toContain('-i');
    expect($command)->toContain('1.5');
    expect($command)->toContain('example.com');
});

it('can perform ping check with custom packet size', function () {
    $checker = new Ping('8.8.8.8', timeoutInSeconds: 5, count: 2, packetSizeInBytes: 64);

    $result = $checker->run();

    expect($result)->toBeInstanceOf(PingResult::class);
    expect($result->isSuccess())->toBeTrue();
    expect($result->packetLossPercentage())->toBeLessThan(100);
    expect($result->averageResponseTimeInMs())->toBeGreaterThan(0);
    expect($result->rawOutput())->toContain('8.8.8.8');
})->skipOnGitHubActions();

it('builds ping command with packet size correctly', function () {
    $checker = new Ping('example.com', timeoutInSeconds: 5, count: 2, packetSizeInBytes: 128);

    $reflection = new ReflectionClass($checker);
    $method = $reflection->getMethod('buildPingCommand');
    $method->setAccessible(true);

    $command = $method->invoke($checker);

    expect($command)->toContain('-s');
    expect($command)->toContain('128');
    expect($command)->toContain('example.com');
});

it('builds ping command with both interval and packet size', function () {
    $checker = new Ping('example.com', timeoutInSeconds: 5, count: 2, intervalInSeconds: 0.5, packetSizeInBytes: 256);

    $reflection = new ReflectionClass($checker);
    $method = $reflection->getMethod('buildPingCommand');
    $method->setAccessible(true);

    $command = $method->invoke($checker);

    expect($command)->toContain('-i');
    expect($command)->toContain('0.5');
    expect($command)->toContain('-s');
    expect($command)->toContain('256');
    expect($command)->toContain('example.com');
});


it('builds ping command with TTL correctly', function () {
    $checker = new Ping('example.com', timeoutInSeconds: 5, count: 2, ttl: 32);

    $reflection = new ReflectionClass($checker);
    $method = $reflection->getMethod('buildPingCommand');
    $method->setAccessible(true);

    $command = $method->invoke($checker);

    expect($command)->toContain('-t');
    expect($command)->toContain('32');
    expect($command)->toContain('example.com');
});

it('builds ping command with all options', function () {
    $checker = new Ping('example.com', timeoutInSeconds: 5, count: 3, intervalInSeconds: 0.2, packetSizeInBytes: 512, ttl: 128);

    $reflection = new ReflectionClass($checker);
    $method = $reflection->getMethod('buildPingCommand');
    $method->setAccessible(true);

    $command = $method->invoke($checker);

    expect($command)->toContain('-i');
    expect($command)->toContain('0.2');
    expect($command)->toContain('-s');
    expect($command)->toContain('512');
    expect($command)->toContain('-t');
    expect($command)->toContain('128');
    expect($command)->toContain('example.com');
});

it('can create PingResult from toArray output with real ping data', function () {
    // Execute a real ping to get actual data
    $checker = new Ping('8.8.8.8', timeoutInSeconds: 5, count: 3);
    $originalResult = $checker->run();
    $originalArray = $originalResult->toArray();

    // Create new PingResult from the toArray output
    $reconstructedResult = PingResult::fromArray($originalArray);
    $reconstructedArray = $reconstructedResult->toArray();

    // Verify both arrays are identical
    expect($reconstructedArray['success'])->toBe($originalArray['success']);
    expect($reconstructedArray['error'])->toBe($originalArray['error']);
    expect($reconstructedArray['host'])->toBe($originalArray['host']);
    expect($reconstructedArray['packet_loss_percentage'])->toBe($originalArray['packet_loss_percentage']);
    expect($reconstructedArray['packets_transmitted'])->toBe($originalArray['packets_transmitted']);
    expect($reconstructedArray['packets_received'])->toBe($originalArray['packets_received']);

    // Options should match
    expect($reconstructedArray['options'])->toBe($originalArray['options']);

    // Timings should match
    expect($reconstructedArray['timings'])->toBe($originalArray['timings']);

    // Raw output should match
    expect($reconstructedArray['raw_output'])->toBe($originalArray['raw_output']);

    // Lines should match in count and content
    expect(count($reconstructedArray['lines']))->toBe(count($originalArray['lines']));
    foreach ($reconstructedArray['lines'] as $index => $line) {
        expect($line['line'])->toBe($originalArray['lines'][$index]['line']);
        expect($line['time_in_ms'])->toBe($originalArray['lines'][$index]['time_in_ms']);
    }

    // Response time should be calculated the same way

    // Verify core functionality works on reconstructed object
    expect($reconstructedResult->isSuccess())->toBe($originalResult->isSuccess());
    expect($reconstructedResult->getHost())->toBe($originalResult->getHost());
    expect($reconstructedResult->lines())->toHaveCount(count($originalResult->lines()));
})->skipOnGitHubActions();

it('can create PingResult from toArray output with failed ping (unknown hostname)', function () {
    // Execute a real ping to an unknown hostname to get actual failure data
    $checker = new Ping('non-existent-host-12345.invalid', timeoutInSeconds: 2, count: 1);
    $originalResult = $checker->run();
    $originalArray = $originalResult->toArray();

    // Verify the original result failed as expected
    expect($originalResult->isSuccess())->toBeFalse();
    expect($originalResult->hasError())->toBeTrue();
    expect($originalResult->packetLossPercentage())->toBe(100);

    // Create new PingResult from the toArray output
    $reconstructedResult = PingResult::fromArray($originalArray);
    $reconstructedArray = $reconstructedResult->toArray();

    // Verify both arrays are identical for failed ping
    expect($reconstructedArray['success'])->toBe($originalArray['success']);
    expect($reconstructedArray['success'])->toBeFalse();
    expect($reconstructedArray['error'])->toBe($originalArray['error']);
    expect($reconstructedArray['error'])->not()->toBeNull();
    expect($reconstructedArray['host'])->toBe($originalArray['host']);
    expect($reconstructedArray['packet_loss_percentage'])->toBe($originalArray['packet_loss_percentage']);
    expect($reconstructedArray['packet_loss_percentage'])->toBe(100);
    expect($reconstructedArray['packets_transmitted'])->toBe($originalArray['packets_transmitted']);
    expect($reconstructedArray['packets_received'])->toBe($originalArray['packets_received']);

    // Options should match
    expect($reconstructedArray['options'])->toBe($originalArray['options']);

    // Timings should match (likely all null for failed ping)
    expect($reconstructedArray['timings'])->toBe($originalArray['timings']);

    // Raw output should match
    expect($reconstructedArray['raw_output'])->toBe($originalArray['raw_output']);

    // Lines should match (likely empty for failed ping)
    expect(count($reconstructedArray['lines']))->toBe(count($originalArray['lines']));
    expect($reconstructedArray['lines'])->toBe($originalArray['lines']);

    // Response time should be calculated the same way (likely 0.0 for failed ping)

    // Verify core functionality works on reconstructed object for failed ping
    expect($reconstructedResult->isSuccess())->toBe($originalResult->isSuccess());
    expect($reconstructedResult->isSuccess())->toBeFalse();
    expect($reconstructedResult->hasError())->toBe($originalResult->hasError());
    expect($reconstructedResult->hasError())->toBeTrue();
    expect($reconstructedResult->error())->toBe($originalResult->error());
    expect($reconstructedResult->getHost())->toBe($originalResult->getHost());
    expect($reconstructedResult->packetLossPercentage())->toBe($originalResult->packetLossPercentage());
    expect($reconstructedResult->packetLossPercentage())->toBe(100);
    expect($reconstructedResult->lines())->toHaveCount(count($originalResult->lines()));
})->skipOnGitHubActions();


it('can use showLostPackets setter method', function () {
    $checker = new Ping('8.8.8.8');

    // Use fluent interface to configure showLostPackets
    $checker->showLostPackets(false);

    expect($checker)->toBeInstanceOf(Ping::class);
});

it('builds ping command with showLostPackets option on Linux', function () {
    $checker = new Ping('example.com', showLostPackets: true);

    $reflection = new ReflectionClass($checker);
    $method = $reflection->getMethod('buildPingCommand');
    $method->setAccessible(true);

    $command = $method->invoke($checker);

    if (PHP_OS_FAMILY !== 'Darwin') {
        expect($command)->toContain('-O');
    } else {
        expect($command)->not()->toContain('-O');
    }
    expect($command)->toContain('example.com');
});

it('does not include showLostPackets option when disabled', function () {
    $checker = new Ping('example.com', showLostPackets: false);

    $reflection = new ReflectionClass($checker);
    $method = $reflection->getMethod('buildPingCommand');
    $method->setAccessible(true);

    $command = $method->invoke($checker);

    expect($command)->not()->toContain('-O');
    expect($command)->toContain('example.com');
});

it('showLostPackets is enabled by default', function () {
    $checker = new Ping('example.com');

    $reflection = new ReflectionClass($checker);
    $property = $reflection->getProperty('showLostPackets');
    $property->setAccessible(true);

    expect($property->getValue($checker))->toBeTrue();
});

it('can detect IPv6 support on system', function () {
    $hasIPv6 = hasIPv6Support();

    expect($hasIPv6)->toBeBool();

    // Log the result for debugging
    if ($hasIPv6) {
        echo "IPv6 support detected - IPv6 tests will run\n";
    } else {
        echo "IPv6 not supported - IPv6 tests will be skipped\n";
    }
});

// Helper function to check if system has IPv6 support
function hasIPv6Support(): bool
{
    // Allow explicit disabling via environment variable
    if (getenv('DISABLE_IPV6_TESTS')) {
        return false;
    }

    // Try a quick test ping to see if IPv6 localhost responds
    try {
        $testPing = new Ping('::1', timeoutInSeconds: 1, count: 1);
        $result = $testPing->run();

        // If ping succeeds or fails with anything other than hostname not found, IPv6 is available
        return $result->isSuccess() || $result->error() !== PingError::HostnameNotFound;
    } catch (Exception $e) {
        return false;
    }
}

// Data provider for IPv4 and IPv6 test addresses
dataset('ipAddressProvider', function () {
    $addresses = [
        'IPv4 Google DNS' => ['8.8.8.8', 'IPv4'],
        'IPv4 localhost' => ['127.0.0.1', 'IPv4'],
    ];

    // Only add IPv6 if system supports it
    if (hasIPv6Support()) {
        $addresses['IPv6 Google DNS'] = ['2001:4860:4860::8888', 'IPv6'];
        $addresses['IPv6 localhost'] = ['::1', 'IPv6'];
    }

    return $addresses;
});

// IPv6 and IPv4 Testing with Data Providers
it('can perform successful ping check on both IPv4 and IPv6 addresses', function (string $host, string $ipVersion) {
    $checker = new Ping($host, timeoutInSeconds: 5, count: 3);

    $result = $checker->run();

    expect($result)->toBeInstanceOf(PingResult::class);
    expect($result->isSuccess())->toBeTrue();
    expect($result->packetLossPercentage())->toBeLessThan(100);
    expect($result->averageResponseTimeInMs())->toBeGreaterThan(0);
    expect($result->rawOutput())->toContain($host);
})->with('ipAddressProvider')->skipOnGitHubActions();

it('can parse ping results with multiple packets for both IPv4 and IPv6', function (string $host, string $ipVersion) {
    $checker = new Ping($host, timeoutInSeconds: 5, count: 4);

    $result = $checker->run();

    expect($result)->toBeInstanceOf(PingResult::class);
    expect($result->lines())->toHaveCount(4);

    foreach ($result->lines() as $line) {
        expect($line)->toBeInstanceOf(PingResultLine::class);
        expect($line->getTimeInMs())->toBeGreaterThan(0);
        expect($line->getRawLine())->toContain('time');
    }
})->with('ipAddressProvider')->skipOnGitHubActions();

it('can extract packet statistics for both IPv4 and IPv6', function (string $host, string $ipVersion) {
    $checker = new Ping($host, timeoutInSeconds: 5, count: 3);

    $result = $checker->run();

    expect($result->packetsTransmitted())->toBe(3);
    expect($result->packetsReceived())->toBeGreaterThanOrEqual(0);
    expect($result->packetsReceived())->toBeLessThanOrEqual(3);

    if ($result->minimumTimeInMs() !== null) {
        expect($result->minimumTimeInMs())->toBeGreaterThan(0);
        expect($result->maximumTimeInMs())->toBeGreaterThanOrEqual($result->minimumTimeInMs());
        expect($result->averageTimeInMs())->toBeBetween($result->minimumTimeInMs(), $result->maximumTimeInMs());
    }
})->with('ipAddressProvider')->skipOnGitHubActions();

it('can convert result to array for both IPv4 and IPv6', function (string $host, string $ipVersion) {
    $checker = new Ping($host, timeoutInSeconds: 3, count: 2, intervalInSeconds: 1.5, packetSizeInBytes: 64, ttl: 32);

    $result = $checker->run();
    $array = $result->toArray();

    expect($array)->toHaveKeys([
        'success',
        'error',
        'host',
        'packet_loss_percentage',
        'packets_transmitted',
        'packets_received',
        'options',
        'timings',
        'raw_output',
        'lines',
    ]);

    expect($array['success'])->toBeBool();
    expect($array['packet_loss_percentage'])->toBeInt();
    expect($array['host'])->toBe($host);
    expect($array['lines'])->toBeArray();
    expect(count($array['lines']))->toBe(count($result->lines()));

    expect($array['options'])->toHaveKeys(['timeout_in_seconds', 'interval', 'packet_size_in_bytes', 'ttl']);
    expect($array['options']['timeout_in_seconds'])->toBe(3);
    expect($array['options']['interval'])->toBe(1.5);
    expect($array['options']['packet_size_in_bytes'])->toBe(64);
    expect($array['options']['ttl'])->toBe(32);

    expect($array['timings'])->toHaveKeys(['minimum_time_in_ms', 'maximum_time_in_ms', 'average_time_in_ms', 'standard_deviation_time_in_ms']);
})->with('ipAddressProvider')->skipOnGitHubActions();

it('handles timeout correctly for both IPv4 and IPv6', function (string $host, string $ipVersion) {
    $checker = new Ping($host, timeoutInSeconds: 2, count: 1);

    $result = $checker->run();

    expect($result)->toBeInstanceOf(PingResult::class);
    // For localhost addresses (both IPv4 and IPv6), this should likely succeed
    // For external addresses, we just ensure it returns a valid result
    expect($result->packetLossPercentage())->toBeInt();
    expect($result->packetLossPercentage())->toBeBetween(0, 100);
})->with('ipAddressProvider')->skipOnGitHubActions();

it('can perform ping check with custom interval for both IPv4 and IPv6', function (string $host, string $ipVersion) {
    $checker = new Ping($host, timeoutInSeconds: 5, count: 3, intervalInSeconds: 0.5);

    $result = $checker->run();

    expect($result)->toBeInstanceOf(PingResult::class);
    expect($result->isSuccess())->toBeTrue();
    expect($result->packetLossPercentage())->toBeLessThan(100);
    expect($result->averageResponseTimeInMs())->toBeGreaterThan(0);
    expect($result->rawOutput())->toContain($host);
})->with('ipAddressProvider')->skipOnGitHubActions();

it('can perform ping check with custom packet size for both IPv4 and IPv6', function (string $host, string $ipVersion) {
    $checker = new Ping($host, timeoutInSeconds: 5, count: 2, packetSizeInBytes: 128);

    $result = $checker->run();

    expect($result)->toBeInstanceOf(PingResult::class);
    expect($result->isSuccess())->toBeTrue();
    expect($result->packetLossPercentage())->toBeLessThan(100);
    expect($result->averageResponseTimeInMs())->toBeGreaterThan(0);
    expect($result->rawOutput())->toContain($host);
})->with('ipAddressProvider')->skipOnGitHubActions();

it('can perform ping check with custom TTL for both IPv4 and IPv6', function (string $host, string $ipVersion) {
    $checker = new Ping($host, timeoutInSeconds: 5, count: 2, ttl: 32);

    $result = $checker->run();

    expect($result)->toBeInstanceOf(PingResult::class);
    expect($result->isSuccess())->toBeTrue();
    expect($result->packetLossPercentage())->toBeLessThan(100);
    expect($result->averageResponseTimeInMs())->toBeGreaterThan(0);
    expect($result->rawOutput())->toContain($host);
})->with('ipAddressProvider')->skipOnGitHubActions();

it('can validate IPv6 address format in ping commands', function () {
    if (!hasIPv6Support()) {
        $this->markTestSkipped('IPv6 not supported on this system');
    }

    $checker = new Ping('::1', timeoutInSeconds: 5, count: 1);

    $reflection = new ReflectionClass($checker);
    $method = $reflection->getMethod('buildPingCommand');
    $method->setAccessible(true);

    $command = $method->invoke($checker);

    expect($command)->toContain('::1');
    // Verify that the IPv6 address is properly handled in the command
    $commandString = implode(' ', $command);
    expect($commandString)->toMatch('/ping.*::1/');
});

it('can distinguish between IPv4 and IPv6 address formats', function (string $host, string $expectedType) {
    $checker = new Ping($host, timeoutInSeconds: 2, count: 1);

    // Basic format validation
    if ($expectedType === 'IPv6') {
        expect($host)->toContain(':');
        expect((bool) filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6))->toBeTrue();
        expect((bool) filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))->toBeFalse();
    } else {
        expect($host)->toContain('.');
        expect((bool) filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))->toBeTrue();
        expect((bool) filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6))->toBeFalse();
    }

    $result = $checker->run();
    expect($result)->toBeInstanceOf(PingResult::class);
    expect($result->getHost())->toBe($host);
})->with('ipAddressProvider');

it('can create PingResult from toArray output for both IPv4 and IPv6', function (string $host, string $ipVersion) {
    // Execute a real ping to get actual data
    $checker = new Ping($host, timeoutInSeconds: 5, count: 2);
    $originalResult = $checker->run();

    // Only proceed with reconstruction test if the original ping was successful
    if ($originalResult->isSuccess()) {
        $originalArray = $originalResult->toArray();

        // Create new PingResult from the toArray output
        $reconstructedResult = PingResult::fromArray($originalArray);
        $reconstructedArray = $reconstructedResult->toArray();

        // Verify both arrays are identical
        expect($reconstructedArray['success'])->toBe($originalArray['success']);
        expect($reconstructedArray['host'])->toBe($originalArray['host']);
        expect($reconstructedArray['packet_loss_percentage'])->toBe($originalArray['packet_loss_percentage']);

        // Verify core functionality works on reconstructed object
        expect($reconstructedResult->isSuccess())->toBe($originalResult->isSuccess());
        expect($reconstructedResult->getHost())->toBe($originalResult->getHost());
        expect($reconstructedResult->lines())->toHaveCount(count($originalResult->lines()));
    }
})->with('ipAddressProvider')->skipOnGitHubActions();

it('builds ping commands correctly for both IPv4 and IPv6 addresses', function (string $host, string $ipVersion) {
    $checker = new Ping($host, timeoutInSeconds: 5, count: 3, intervalInSeconds: 0.5, packetSizeInBytes: 128, ttl: 32);

    $reflection = new ReflectionClass($checker);
    $method = $reflection->getMethod('buildPingCommand');
    $method->setAccessible(true);

    $command = $method->invoke($checker);

    // Common ping command elements
    expect($command)->toContain('ping');
    expect($command)->toContain('-c');
    expect($command)->toContain('3');
    expect($command)->toContain('-W');
    expect($command)->toContain('-i');
    expect($command)->toContain('0.5');
    expect($command)->toContain('-s');
    expect($command)->toContain('128');
    expect($command)->toContain('-t');
    expect($command)->toContain('32');
    expect($command)->toContain($host);

    // Verify the address format is preserved
    if ($ipVersion === 'IPv6') {
        expect($host)->toContain(':');
    } else {
        expect($host)->toContain('.');
    }
})->with('ipAddressProvider');

it('can use setter methods to configure ping options for both IPv4 and IPv6', function (string $host, string $ipVersion) {
    $checker = new Ping($host);

    // Use fluent interface to configure all options
    $checker->timeoutInSeconds(10)
        ->count(2)
        ->intervalInSeconds(0.5)
        ->packetSizeInBytes(128)
        ->ttl(32)
        ->showLostPackets(false);

    $result = $checker->run();

    expect($result)->toBeInstanceOf(PingResult::class);
    expect($result->isSuccess())->toBeTrue();

    // Verify the options were applied correctly in the result
    $array = $result->toArray();
    expect($array['options']['timeout_in_seconds'])->toBe(10);
    expect($array['options']['interval'])->toBe(0.5);
    expect($array['options']['packet_size_in_bytes'])->toBe(128);
    expect($array['options']['ttl'])->toBe(32);
})->with('ipAddressProvider')->skipOnGitHubActions();
