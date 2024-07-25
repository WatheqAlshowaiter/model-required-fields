![Package cover](./arts/package-cover.png)

# Model Required Fields

[![Latest Version on Packagist](https://img.shields.io/packagist/v/watheqalshowaiter/model-required-fields.svg?style=flat-square)](https://packagist.org/packages/watheqalshowaiter/model-required-fields)
[![Total Downloads](https://img.shields.io/packagist/dt/watheqalshowaiter/model-required-fields.svg?style=flat-square)](https://packagist.org/packages/watheqalshowaiter/model-required-fields)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/watheqalshowaiter/model-required-fields/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/watheqalshowaiter/model-required-fields/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![GitHub Tests For Laravel Versions Action Status](https://img.shields.io/github/actions/workflow/status/watheqalshowaiter/model-required-fields/tests-for-laravel-versions.yml?branch=main&label=tests-for-laravel-versions&style=flat-square)](https://github.com/watheqalshowaiter/model-required-fields/actions?query=workflow%3A"tests-for-laravel-versions"+branch%3Amain)
[![GitHub Tests For Databases Action Status](https://img.shields.io/github/actions/workflow/status/watheqalshowaiter/model-required-fields/tests-for-databases.yml?branch=main&label=tests-for-databases&style=flat-square)](https://github.com/watheqalshowaiter/model-required-fields/actions?query=workflow%3Atests-for-databases+branch%3Amain)

Get the **required** model fields, excluding **primary keys**, **nullable** fields, and fields with **defaults**.

## Installation

You can install the package via Composer:

```bash
composer require watheqalshowaiter/model-required-fields --dev
```

We prefer `--dev` because usually you will use it development, not in production.

## Usage

We Assume that the `User` model has this schema as the defaults

```php
Schema::create('users', function (Blueprint $table) {
    $table->id(); // primary key
    $table->string('name'); // required
    $table->string('email')->unique(); // required
    $table->timestamp('email_verified_at')->nullable(); // nullable
    $table->string('password'); // required
    $table->rememberToken(); // nullable
    $table->timestamps(); // nullable
});
```

-   Add the `RequiredFields` trait to your model

```php
use WatheqAlshowaiter\ModelRequiredFields\RequiredFields;

class User extends Model
{
   use RequiredFields;
}
```

-   Now use the trait as follows

```php
User::requiredFields(); // returns ['name', 'email', 'password']
```

That's it!

### Another Complex Table

let's say the `Post` model has these fields

```php
Schema::create('posts', function (Blueprint $table) {
    $table->uuid('id')->primary(); // primary key
    $table->foreignId('user_id')->constrained(); // required
    $table->foreignId('category_id')->nullable(); // nullable
    $table->uuid(); // required (but will be changed later) 👇
    $table->ulid('ulid')->nullable(); // nullable (but will be changed later) 👇
    $table->boolean('active')->default(false); // default
    $table->string('title'); // required
    $table->json('description')->nullable(); // nullable (but will be changed later) 👇
    $table->string('slug')->nullable()->unique(); // nullable
    $table->timestamps(); // nullable
    $table->softDeletes(); // nullable
});

// later migration..
Schema::table('posts', function(Blueprint $table){
    $table->json('description')->nullable(false)->change(); // required
    $table->ulid('ulid')->nullable(false)->change(); // required
    $table->uuid()->nullable()->change(); // nullable
});
```

-   We can add the `RequiredFields` trait to the `Post` Model

```php
use WatheqAlshowaiter\ModelRequiredFields\RequiredFields;

class Post extends Model
{
   use RequiredFields;
}
```

-   Now use the trait as follows

```php
Post::requiredFields(); // returns ['user_id', 'ulid', 'title', 'description']
```

### And more

We have the flexibility to get required fields with nullables, defaults, primary keys, and a mix of them or return all fields. You can use these methods with these results:

```php
// The default parameters, only required fields
Post::getRequiredFields(
    $withNullables = false,
    $withDefaults = false,
    $withPrimaryKey = false
);
// or
Post::getRequiredFields();
// returns ['user_id', 'ulid', 'title', 'description']
```

```php
// get required fields with nullables
Post::getRequiredFields(
    $withNullables = true,
    $withDefaults = false,
    $withPrimaryKey = false
);
// or
Post::getRequiredFields(
    $withNullables = true,
);
// or
Post::getRequiredFields(true);
// or
Post::getRequiredFieldsWithNullables();
// returns ['user_id', 'category_id', 'uuid', 'ulid', 'title', 'description', 'slug', 'created_at', 'updated_at', 'deleted_at']
```

```php
// get required fields with defaults
Post::getRequiredFields(
    $withNullables = false,
    $withDefaults = true,
    $withPrimaryKey = false
);
// or
Post::getRequiredFieldsWithDefaults();
// returns ['user_id', 'ulid', 'active', 'title', 'description']
```

```php
// get required fields with primary key
Post::getRequiredFields(
    $withNullables = false,
    $withDefaults = false,
    $withPrimaryKey = true
);
// or
Post::getRequiredFieldsWithPrimaryKey();
// returns ['id', 'user_id', 'ulid', 'title', 'description']
```

```php
// get required fields with nullables and defaults
Post::getRequiredFields(
    $withNullables = true,
    $withDefaults = true,
    $withPrimaryKey = false
);
// or
Post::getRequiredFieldsWithNullablesAndDefaults();
// returns ['user_id', 'category_id', 'uuid', 'ulid', 'active', 'title', 'description', 'slug', 'created_at', 'updated_at', 'deleted_at']
```

```php
// get required fields with nullables and primary key
Post::getRequiredFields(
    $withNullables = true,
    $withDefaults = false,
    $withPrimaryKey = true
);
// or
Post::getRequiredFieldsWithNullablesAndPrimaryKey();
// returns ['id', 'user_id', 'category_id', 'uuid', 'ulid', 'title', 'description', 'slug', 'created_at', 'updated_at', 'deleted_at']
```

```php
// get required fields with defaults and primary key
Post::getRequiredFields(
    $withNullables = false,
    $withDefaults = true,
    $withPrimaryKey = true
);
// or
Post::getRequiredFieldsWithDefaultsAndPrimaryKey();
// returns ['id', 'user_id', 'ulid', 'active', 'title', 'description']
```

```php
// get required fields with defaults and primary key
Post::getRequiredFields(
    $withNullables = true,
    $withDefaults = true,
    $withPrimaryKey = true
);
// or
Post::getAllFields();
// returns ['id', 'user_id', 'category_id', 'uuid', 'ulid', 'active', 'title', 'description', 'slug', 'created_at', 'updated_at', 'deleted_at']
```

## Why?

### The problem

I wanted to add tests to a legacy project that didn't have any. I wanted to add tests but couldn't find a factory, so I tried building them. However, it was hard to figure out the required fields for testing the basic functionality since some tables have too many fields.

### The Solution

To solve this, I created a simple trait that retrieves the required fields easily. Later, I added support for older Laravel versions, as that was where most of the use cases occurred. Eventually, I extracted it into this package.

So Briefly, This package is useful if:

-   you want to build factories or tests for projects you didn't start from scratch.
-   you are working with a legacy project and don't want to be faced with SQL errors when creating tables.
-   you have so many fields in your table and want to get the required fields fast.
-   or any use case you find it useful.

## Features

✅ Supports Laravel versions: 11, 10, 9, 8, 7, and 6.

✅ Supports PHP versions: 8.2, 8.1, 8.0, and 7.4.

✅ Supports SQL databases: SQLite, MySQL/MariaDB, PostgreSQL, and SQL Server.

✅ Fully automated tested with PHPUnit.

✅ Full GitHub Action CI pipeline to format code and test against all Laravel and PHP versions.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

If you have any ideas or suggestions to improve it or fix bugs, your contribution is welcome. I encourage you to look at [todos](./todos.md) which are the most important features need to be added. If you have something different, submit an issue first to discus or report a bug, then do a pull request.

## Security Vulnerabilities

If you find any security vulnerabilities don't hesitate to contact me at `watheqalshowaiter[at]gmail[dot]com` to fix
them.

## Credits

-   [Watheq Alshowaiter](https://github.com/WatheqAlshowaiter)
-   [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
