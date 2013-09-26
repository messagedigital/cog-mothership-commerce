Forex
=====

Configuration
-------------

```php
// app/my_project/commerce/src/MyProject/Commerce/Bootstrap/Services.php

$services['forex'] = $services->share(function($c) {
	return new Commerce\Forex\Forex(
		$c['db.query'],                   // DB query
		'GBP',                            // Base currency
		array('GBP', 'USD', 'EUR', 'JPY') // Available currencies
	);
});

$services['forex.feed'] = $services->share(function($c) {
	return new Commerce\Forex\Feeds\ECB;
});
```

Usage
-----

**Convert prices one at a time**

```php
$priceGBP = 12.50;

// Convert from the base currency
$priceUSD = $this->get('forex')->convert($priceGBP, 'USD');

// Convert from a specified currency
$priceEUR = $this->get('forex')->convert($priceUSD, 'EUR', 'USD');
```

**Convert multiple prices**

```php
$priceGBP = 12.50;
$currencies = array('GBP', 'USD', 'EUR', 'JPY');

// Create a converter on the base currency
$converter = $this->get('forex')->amount($priceGBP);

// Loop currencies and output the converter value for each one
foreach ($currencies as $currency) {
	echo $currency . ': ' .$converter->to($currency)->convert();
}

// Change the currency to convert from
$converter->from('USD');

// Loop currencies and output the converter value for each one against the new from currency
foreach ($currencies as $currency) {
	echo $currency . ': ' .$converter->to($currency)->convert();
}
```

**Fetch the latest rates**

This should be run regularly to keep the exchange rates up to date.

```php
$this->get('forex.feed')->fetch();
```