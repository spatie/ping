# Run an ICMP ping and get structured results

[![Latest Version on Packagist](https://img.shields.io/packagist/v/spatie/ping.svg?style=flat-square)](https://packagist.org/packages/spatie/ping)
[![Tests](https://img.shields.io/github/actions/workflow/status/spatie/ping/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/spatie/ping/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/spatie/ping.svg?style=flat-square)](https://packagist.org/packages/spatie/ping)

This package provides a simple way to execute ICMP ping commands and parse the results into structured data. It wraps the system's ping command and returns detailed information about packet loss, response times, and connectivity status.

```php
use Spatie\Ping\Ping;

$result = (new Ping('8.8.8.8'))->run(); // returns an instance of \Spatie\Ping\PingResult

// Basic status
echo $result->isSuccess() ? 'Success' : 'Failed';
echo $result->hasError() ? "Error: {$result->error()?->value}" : 'No errors';

// Packet statistics
echo "Packets transmitted: {$result->packetsTransmitted()}";
echo "Packets received: {$result->packetsReceived()}";
echo "Packet loss: {$result->packetLossPercentage()}%";

// Timing information
echo "Min time: {$result->minimumTime()}ms";
echo "Max time: {$result->maximumTime()}ms";  
echo "Average time: {$result->averageTime()}ms";
echo "Standard deviation: {$result->standardDeviationTime()}ms";

// Individual ping lines
foreach ($result->lines() as $line) {
    echo "Response: {$line->getRawLine()} ({$line->getTimeInMs()}ms)";
}
```

## Support us

[<img src="https://github-ads.s3.eu-central-1.amazonaws.com/ping.jpg?t=1" width="419px" />](https://spatie.be/github-ad-click/ping)

We invest a lot of resources into creating [best in class open source packages](https://spatie.be/open-source). You can support us by [buying one of our paid products](https://spatie.be/open-source/support-us).

We highly appreciate you sending us a postcard from your hometown, mentioning which of our package(s) you are using. You'll find our address on [our contact page](https://spatie.be/about-us). We publish all received postcards on [our virtual postcard wall](https://spatie.be/open-source/postcards).

## Installation

You can install the package via Composer:

```bash
composer require spatie/ping
```

## Usage

The simplest way to ping a host:

```php
use Spatie\Ping\Ping;

$result = (new Ping('8.8.8.8'))->run();

if ($result->isSuccess()) {
    echo "Ping successful! Average response time: {$result->averageResponseTimeInMs()}ms";
} else {
    echo "Ping failed: {$result->error()?->value}";
}
```

### Configuring ping options

You can customize the ping behavior using constructor parameters:

```php
$result = (new Ping(
    hostname: '8.8.8.8',
    timeout: 5,      // seconds
    count: 3,        // number of packets
    interval: 1.0,   // seconds between packets
    packetSize: 64,  // bytes
    ttl: 64          // time to live
))->run();
```

Or use the fluent interface:

```php
$result = (new Ping('8.8.8.8'))
    ->timeout(10)
    ->count(5)
    ->interval(0.5)
    ->packetSize(128)
    ->ttl(32)
    ->run();
```

### Working with results

The `PingResult` object provides detailed information about the ping operation:

```php
$result = (new Ping('8.8.8.8', count: 3))->run();

// Basic status
echo $result->isSuccess() ? 'Success' : 'Failed';
echo $result->hasError() ? "Error: {$result->error()?->value}" : 'No errors';

// Packet statistics
echo "Packets transmitted: {$result->packetsTransmitted()}";
echo "Packets received: {$result->packetsReceived()}";
echo "Packet loss: {$result->packetLossPercentage()}%";

// Timing information
echo "Min time: {$result->minimumTime()}ms";
echo "Max time: {$result->maximumTime()}ms";  
echo "Average time: {$result->averageTime()}ms";
echo "Standard deviation: {$result->standardDeviationTime()}ms";

// Individual ping lines
foreach ($result->lines() as $line) {
    echo "Response: {$line->getRawLine()} ({$line->getTimeInMs()}ms)";
}

// Raw ping output
echo $result->raw;
```

### Converting to array

You can convert the result to an array for easy serialization:

```php
$result = (new Ping('8.8.8.8'))->run();
$array = $result->toArray();

// The array contains all ping data including:
// - success status and error information
// - packet statistics and timing data  
// - configuration options used
// - raw output and parsed lines
```

You can also reconstruct a `PingResult` from an array:

```php
$newResult = PingResult::fromArray($array);
```

## Error handling

The `error()` method on a `PingResult` will return a case of the `Spatie\Ping\PingError` enum. 

```php
use Spatie\Ping\Ping;
use Spatie\Ping\PingError;

$result = (new Ping('non-existent-host.invalid'))->run();

if (! $result->isSuccess()) {
    return $result->error() // returns the enum case Spatie\Ping\PingError::HostnameNotFound
}
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](https://github.com/spatie/.github/blob/main/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Freek Van der Herten](https://github.com/freekmurze)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
