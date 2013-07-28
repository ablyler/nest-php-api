# Nest PHP API (unofficial)

PHP API to the Nest Thermostat

## Usage

```php
// include the nest api library
include_once('./nest-php-api.php');

// create new nest object w/ login information
$nest = new Nest('john@doe.com', 'password');

// print the current house state
echo $nest->house_state_get();

// set the house to away
$nest->house_state_set('away');

// set the temp to 72 degrees fahrenheit
$nest->temperature_set(72);

// obtain all the status information from nest
var_dump($nest->status_get());
```