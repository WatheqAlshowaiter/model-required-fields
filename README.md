[![Package cover](./arts/package-cover.png)

# Model Required Fields

[![Latest Version on Packagist](https://img.shields.io/packagist/v/watheqalshowaiter/model-required-fields.svg?style=flat-square)](https://packagist.org/packages/watheqalshowaiter/model-required-fields)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/watheqalshowaiter/model-required-fields/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/watheqalshowaiter/model-required-fields/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/watheqalshowaiter/model-required-fields/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/watheqalshowaiter/model-required-fields/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/watheqalshowaiter/model-required-fields.svg?style=flat-square)](https://packagist.org/packages/watheqalshowaiter/model-required-fields)

Get the **required** model fields, excluding **primary keys**, **nullable** fields, and fields with **defaults**.

## Installation

You can install the package via composer:

```bash
composer require watheqalshowaiter/model-required-fields
```

## Usage

- Add the `RequiredFields` trait to your model

```php
use WatheqAlshowaiter\ModelRequiredFields\RequiredFields;

class User extends Model
{
   use RequiredFields;
}
```

- Now use the trait as follows

```php
    User::requiredFields(); // returns ['name', 'email', 'password']
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

This package is simple yet completed in its focused scope, but if you have any ideas or suggestions to improve it or fix
bugs, your contribution is welcome. I encourage you to submit an issue first, then do pull request.

## Security Vulnerabilities

If you find any security vulnerabilities don't hesitate to contact me at `watheqalshowaiter[at]gmail.com` to fix it.

## Credits

- [Watheq Alshowaiter](https://github.com/WatheqAlshowaiter)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
