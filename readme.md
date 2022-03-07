alternative to PHP serializing that recursively extracts public properties from objects and reconstructs or hydrates them using reflection

## install
```
composer require leongrdic/seriale
```

## usage
```php
use Le\Seriale\Seriale;
$seriale = new Seriale;

$someObject = new SomeClass();
$someObject->publicProp = 'test';

$extracted = $seriale->extract($someObject);
// ..later..
$restored = $seriale->hydrate(SomeClass::class, $extracted);

$someObject->publicProp === $restored->publicProp // true
```