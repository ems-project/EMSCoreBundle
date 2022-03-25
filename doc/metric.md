# Metric

## Usage

The Metric are implemented using the prometheus library.

Prometheus collects and stores its metrics as time series data, i.e. metrics information is stored with the timestamp at which it was recorded, alongside optional key-value pairs called labels.

The metrics are available in the admin via the '/metrics' url.

`http://demo-admin-dev.localhost/metrics`

## Type of metric

### Counter

A counter is a cumulative metric that represents a single monotonically increasing counter whose value can only increase or be reset to zero on restart.

```php
$counter = $registry->getOrRegisterCounter('test', 'some_counter', 'it increases', ['type']);
$counter->incBy(3, ['blue']);
```

### Gauge

A gauge is a metric that represents a single numerical value that can arbitrarily go up and down.

```php
$gauge = $registry->getOrRegisterGauge('test', 'some_gauge', 'it sets', ['type']);
$gauge->set(2.5, ['blue']);
```

### Histogram

A histogram samples observations (usually things like request durations or response sizes) and counts them in configurable buckets. It also provides a sum of all observed values.

```php
$histogram = $registry->getOrRegisterHistogram('test', 'some_histogram', 'it observes', ['type'], [0.1, 1, 2, 3.5, 4, 5, 6, 7, 8, 9]);
$histogram->observe(3.5, ['blue']);
```

### Summary

Similar to a histogram, a summary samples observations (usually things like request durations and response sizes).

```php
$summary = $registry->getOrRegisterSummary('test', 'some_summary', 'it observes a sliding window', ['type'], 84600, [0.01, 0.05, 0.5, 0.95, 0.99]);
$histogram->observe(5, ['blue']);
```

## Example

Build the metric registerer that you want to collect.

```php

$documentCounter = $registry->getOrRegisterGauge(
            'emsco',
            'revisions_total',
            'Number of document by Content Type',
            ['contenttype']
        );
```

Collect the data that you want to display.

```php
        $allContentTypes = $this->contentTypeRepository->findAll();
```

Give those data to your counter and the metric will display them.
```php
    foreach ($allContentTypes as $cT) {
        $revision = $this->revisionRepository->findByContentType($cT);
        $documentCounter->set(\count($revision), [$cT->getName()]);
    }
```

