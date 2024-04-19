# Slim Error Renderer

[![Latest Version on Packagist](https://img.shields.io/github/release/samuelgfeller/slim-error-renderer.svg)](https://packagist.org/packages/samuelgfeller/slim-error-renderer)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg)](LICENSE)
[![Build Status](https://github.com/samuelgfeller/slim-error-renderer/actions/workflows/build.yml/badge.svg)](https://github.com/samuelgfeller/slim-error-renderer/actions)
[![Total Downloads](https://img.shields.io/packagist/dt/samuelgfeller/slim-error-renderer.svg)](https://packagist.org/packages/samuelgfeller/slim-error-renderer/stats)

This package provides an alternative to the default Slim error handler and renderer.  
It renders a styled [error details page](#error-details-design) with the stack trace and the error message
or a [generic error page](#generic-error-page-design) for production. 

Custom error page renderers can be created to change the design of the error pages 
by implementing the `ErrorDetailsPageRendererInterface`
or `GenericErrorPageRendererInterface`.

It also provides a [middleware](#exception-heavy-middleware) to make the project "exception-heavy",
which means that it will throw exceptions with a stack trace for notices and warnings during
development and testing like other frameworks such as Laravel or Symfony.

## Why use this package?
A reason this small library exists instead of using the default Slim error handler and a [custom 
error renderer](https://www.slimframework.com/docs/v4/middleware/error-handling.html#error-handlingrendering), 
is to provide the "exception-heavy" feature and better-looking error pages.  
But these things can be achieved with a custom error renderer and middleware located in the project as well. 

The issue with the default `Slim\Handlers\ErrorHandler` is that while testing, the 
`$contentType` in the error handler is `null` and instead of using any custom error renderer
its hardcoded to use the `Slim\Error\Renderers\HtmlErrorRenderer`. This has two consequences:
1. The error is not thrown while integration testing, which means debugging is harder.
2. Tests where an exception is expected, fail with the 
[PHPUnit 11 warning](tps://github.com/sebastianbergmann/phpunit/pull/5619) 
`Test code or tested code did not remove its own error handlers`.
A fix for this message is calling `restore_error_handler()` but this can't be done as the error handler doesn't
allow for custom error renderers when testing. 

So a custom handler is required anyway, and with the custom renderers and the handling of 
non-fatal errors, it made sense to put that in a separate small library.  


## Requirements

* PHP 8.2+
* Composer
* A Slim 4 application

## Installation

### Install the package with composer

Open a terminal in your project's root directory and run the following command:

```bash
composer require samuelgfeller/slim-error-renderer
```

### Add the error handling middleware to the Slim app

#### Container instantiation

If you're using
[Dependency Injection](https://github.com/samuelgfeller/slim-example-project/wiki/Dependency-Injection),
add the error handling middleware to
the container definitions (e.g. in the `config/container.php`) file.
The `ExceptionHandlingMiddleware` constructor accepts the following parameters:

1. Required: instance of a response factory object implementing the `ResponseFactoryInterface`
  (see [here](https://github.com/samuelgfeller/slim-starter/blob/master/config/container.php) for a 
  default implementation of the response factory)
1. Optional: instance of a PSR 3 [logger](https://github.com/samuelgfeller/slim-example-project/wiki/Logging)
  to log the error
1. Optional: boolean to display error details
  (documentation: [Error Handling](https://github.com/samuelgfeller/slim-example-project/wiki/Error-Handling))
1. Optional: contact email for the "report error" button on the error page
1. Optional: A custom generic error page renderer that
  implements `SlimErrorRenderer\Interfaces\ProdErrorPageRendererInterface`
1. Optional: A custom error details page renderer that
  implements `SlimErrorRenderer\Interfaces\ErrorDetailsPageRendererInterface`

```php
<?php

use SlimErrorRenderer\Middleware\ExceptionHandlingMiddleware;

return [
    // ...
    
    ExceptionHandlingMiddleware::class => function (ContainerInterface $container) {
        $settings = $container->get('settings');
        $app = $container->get(App::class);
        
        return new ExceptionHandlingMiddleware(
            $app->getResponseFactory(),
            $container->get(LoggerInterface::class),
            (bool)$settings['error']['display_error_details'],
            $settings['public']['main_contact_email'] ?? null
        );
    },
    
    // ...
];
```

#### Middleware stack

The middleware can now be added to the middleware stack in the `config/middleware.php` file.  
It should be the very last middleware in the stack to catch all exceptions (Slim middlewares
are executed in the
[reverse order](https://github.com/samuelgfeller/slim-example-project/wiki/Middleware#order-of-execution)
they are added).  
This replaces the default slim error middleware.

```php
<?php

use Slim\App;

return function (App $app) {
    // ...

    // Handle exceptions and display error page
    $app->add(ExceptionHandlingMiddleware::class);
}
```

### "Exception-heavy" middleware
The `NonFatalErrorHandlingMiddleware` promotes warnings and notices to exceptions 
when the `display_error_details` setting is set to `true` in the 
[configuration](https://github.com/samuelgfeller/slim-example-project/wiki/Configuration).  
This means that the error details for notices and warnings will [be displayed](#warning--notice) 
with the stack trace and error message.

#### Container instantiation
The `NonFatalErrorHandlingMiddleware` also needs to be instantiated in the container.

The constructor takes three parameters:

1. Required: bool to display error details
1. Required: bool to log the warning / notice
1. Optional: instance of a PSR 3 logger to log the warning / notice

```php
<?php

use SlimErrorRenderer\Middleware\NonFatalErrorHandlingMiddleware;

return [
    // ...
    
    NonFatalErrorHandlingMiddleware::class => function (ContainerInterface $container) {
        $settings = $container->get('settings');
        
        return new NonFatalErrorHandlingMiddleware(
            (bool)$settings['error']['display_error_details'],
            (bool)$settings['error']['log_warning_notice'],
            $container->get(LoggerInterface::class)
        );
    },
    
    // ...
];
```

#### Add to middleware stack

The middleware should be added right above the `ExceptionHandlingMiddleware` in 
the stack.

File: `config/middleware.php`
```php

use Slim\App;

return function (App $app) {
    // ...

    // Promote warnings and notices to exceptions
    $app->add(NonFatalErrorHandlingMiddleware::class); // <- Add here
    // Handle exceptions and display error page
    $app->add(ExceptionHandlingMiddleware::class);
}
```

### Conclusion

Have a look a the [`slim-starter`](https://github.com/samuelgfeller/slim-starter) for a default 
implementation of this package and the
[`slim-example-project`](https://github.com/samuelgfeller/slim-example-project) for a custom 
generic error page rendering with layout.

## Error details design
### Fatal error
<img src="https://github.com/samuelgfeller/slim-example-project/assets/31797204/fea0abee-17f6-46dd-9efa-c5928244f7b6" width="600">

### Warning / Notice

<img src="https://github.com/samuelgfeller/slim-example-project/assets/31797204/9c2e3d7c-6752-4854-b535-5e54d25fd11e" width="600">

## Generic error page design
<img src="https://github.com/samuelgfeller/slim-example-project/assets/31797204/d1fd052e-a16f-4a76-895a-2eac456c4a79" width="600">