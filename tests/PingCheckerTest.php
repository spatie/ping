<?php

namespace Tests\Feature\PingCheck;

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

    $command = $method->invoke($checker);

    expect($command)->toContain('-s');
    expect($command)->toContain('128');
    expect($command)->toContain('example.com');
});

it('builds ping command with both interval and packet size', function () {
    $checker = new Ping('example.com', timeoutInSeconds: 5, count: 2, intervalInSeconds: 0.5, packetSizeInBytes: 256);

    $reflection = new ReflectionClass($checker);
    $method = $reflection->getMethod('buildPingCommand');

    $command = $method->invoke($checker);

    expect($command)->toContain('-i');
    expect($command)->toContain('0.5');
    expect($command)->toContain('-s');
    expect($command)->toContain('256');
    expect($command)->toContain('example.com');
});

it('can perform ping check with custom TTL', function () {
    $checker = new Ping('8.8.8.8', timeoutInSeconds: 5, count: 2, ttl: 64);

    $result = $checker->run();

    expect($result)->toBeInstanceOf(PingResult::class);
    expect($result->isSuccess())->toBeTrue();
    expect($result->packetLossPercentage())->toBeLessThan(100);
    expect($result->averageResponseTimeInMs())->toBeGreaterThan(0);
    expect($result->rawOutput())->toContain('8.8.8.8');
})->skipOnGitHubActions();

it('builds ping command with TTL correctly', function () {
    $checker = new Ping('example.com', timeoutInSeconds: 5, count: 2, ttl: 32);

    $reflection = new ReflectionClass($checker);
    $method = $reflection->getMethod('buildPingCommand');

    $command = $method->invoke($checker);

    expect($command)->toContain('-t');
    expect($command)->toContain('32');
    expect($command)->toContain('example.com');
});

it('builds ping command with all options', function () {
    $checker = new Ping('example.com', timeoutInSeconds: 5, count: 3, intervalInSeconds: 0.2, packetSizeInBytes: 512, ttl: 128);

    $reflection = new ReflectionClass($checker);
    $method = $reflection->getMethod('buildPingCommand');

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

it('can use setter methods to configure ping options', function () {
    $checker = new Ping('8.8.8.8');

    // Use fluent interface to configure all options
    $checker->timeoutInSeconds(10)
        ->count(2)
        ->intervalInSeconds(0.5)
        ->packetSizeInBytes(128)
        ->ttl(32);

    $result = $checker->run();

    expect($result)->toBeInstanceOf(PingResult::class);
    expect($result->isSuccess())->toBeTrue();

    // Verify the options were applied correctly in the result
    $array = $result->toArray();
    expect($array['options']['timeout_in_seconds'])->toBe(10);
    expect($array['options']['interval'])->toBe(0.5);
    expect($array['options']['packet_size_in_bytes'])->toBe(128);
    expect($array['options']['ttl'])->toBe(32);
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

    $command = $method->invoke($checker);

    expect($command)->not()->toContain('-O');
    expect($command)->toContain('example.com');
});

it('showLostPackets is enabled by default', function () {
    $checker = new Ping('example.com');

    $reflection = new ReflectionClass($checker);
    $property = $reflection->getProperty('showLostPackets');

    expect($property->getValue($checker))->toBeTrue();
});

it('can ping a ipv6 address', function () {
    $checker = new Ping('2001:4860:4860::8888');

    $result = $checker->run();

    expect($result)->toBeInstanceOf(PingResult::class);
    expect($result->isSuccess())->toBeTrue();
    expect($result->packetLossPercentage())->toBeLessThan(100);
    expect($result->averageResponseTimeInMs())->toBeGreaterThan(0);
    expect($result->rawOutput())->toContain('2001:4860:4860::8888');
})->whenIpv6Available();

it('can force IPv4', function () {
    $checker = (new Ping('google.com'))
        ->ipVersion(\Spatie\Ping\Enums\IpVersion::IPv4);

    $result = $checker->run();

    expect($result)->toBeInstanceOf(PingResult::class);
    expect($result->isSuccess())->toBeTrue();
    expect($result->ipVersion())->toBe(\Spatie\Ping\Enums\IpVersion::IPv4);
})->skipOnGitHubActions();

it('can force IPv6', function () {
    $checker = (new Ping('google.com'))
        ->ipVersion(\Spatie\Ping\Enums\IpVersion::IPv6);

    $result = $checker->run();

    expect($result)->toBeInstanceOf(PingResult::class);
    expect($result->isSuccess())->toBeTrue();
    expect($result->ipVersion())->toBe(\Spatie\Ping\Enums\IpVersion::IPv6);
})->whenIpv6Available();

it('uses IPv4 by default', function () {
    $checker = new Ping('example.com');

    expect($checker->run()->ipVersion())->toBe(\Spatie\Ping\Enums\IpVersion::IPv4);
})->skipOnGitHubActions();

it('includes ip_version in toArray output', function () {
    $checker = new Ping('8.8.8.8');

    $result = $checker->run();
    $array = $result->toArray();

    expect($array['options'])->toHaveKey('ip_version');
    expect($array['options']['ip_version'])->toBe('ipv4');
})->skipOnGitHubActions();

it('can create PingResult from toArray with ip_version', function () {
    $originalData = [
        'success' => true,
        'error' => null,
        'host' => '8.8.8.8',
        'packet_loss_percentage' => 0,
        'packets_transmitted' => 3,
        'packets_received' => 3,
        'options' => [
            'timeout_in_seconds' => 5,
            'interval' => 1.0,
            'packet_size_in_bytes' => 56,
            'ttl' => 64,
            'ip_version' => 'ipv6',
        ],
        'timings' => [
            'minimum_time_in_ms' => 10.0,
            'maximum_time_in_ms' => 20.0,
            'average_time_in_ms' => 15.0,
            'standard_deviation_time_in_ms' => 5.0,
        ],
        'raw_output' => 'test output',
        'lines' => [],
    ];

    $result = PingResult::fromArray($originalData);

    expect($result->ipVersion())->toBe(\Spatie\Ping\Enums\IpVersion::IPv6);
    expect($result->toArray()['options']['ip_version'])->toBe('ipv6');
});
