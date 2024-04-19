<?php

namespace SlimErrorRenderer\Middleware;

use DomainException;
use InvalidArgumentException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpException;
use SlimErrorRenderer\Interfaces\ErrorDetailsPageRendererInterface;
use SlimErrorRenderer\Interfaces\GenericErrorPageRendererInterface;
use SlimErrorRenderer\Renderer\ErrorDetailsPageRenderer;
use SlimErrorRenderer\Renderer\GenericErrorPageRenderer;
use Throwable;

/**
 * Documentation: https://github.com/samuelgfeller/slim-example-project/wiki/Error-Handling.
 */
final class ExceptionHandlingMiddleware implements MiddlewareInterface
{
    private int $statusCode = 500;
    private string $reasonPhrase = 'Internal Server Error';

    /**
     * @param ResponseFactoryInterface $responseFactory
     * @param LoggerInterface|null $logger
     * @param bool $displayErrorDetails
     * @param GenericErrorPageRendererInterface $prodPageRenderer
     * @param string|null $errorReportEmailAddress
     * @param ErrorDetailsPageRendererInterface $errorDetailsPageRenderer
     */
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly ?LoggerInterface $logger = null,
        private readonly bool $displayErrorDetails = false,
        private readonly ?string $errorReportEmailAddress = null,
        private readonly GenericErrorPageRendererInterface $prodPageRenderer = new GenericErrorPageRenderer(),
        private readonly ErrorDetailsPageRendererInterface $errorDetailsPageRenderer = new ErrorDetailsPageRenderer(),
    ) {
    }

    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     *
     * @throws Throwable
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (Throwable $exception) {
            return $this->handleException($request, $exception);
        }
    }

    /**
     * @param ServerRequestInterface $request
     * @param Throwable $exception
     *
     * @throws Throwable
     */
    private function handleException(ServerRequestInterface $request, Throwable $exception): ResponseInterface
    {
        $httpStatusCode = $this->getHttpStatusCode($exception);
        $response = $this->responseFactory->createResponse($httpStatusCode);

        // Log error
        // If exception is an instance of ErrorException it means that the NonFatalErrorHandlerMiddleware
        // threw the exception for a warning or notice.
        // That middleware already logged the message, so it doesn't have to be done here.
        // The reason it is logged there is that if displayErrorDetails is false, ErrorException is not
        // thrown and the warnings and notices still have to be logged in prod.
        if (isset($this->logger) && !$exception instanceof \ErrorException) {
            // Error with no stack trace https://stackoverflow.com/a/2520056/9013718
            $this->logger->error(
                sprintf(
                    '%s File %s:%s , Method: %s, Path: %s',
                    $exception->getMessage(),
                    $exception->getFile(),
                    $exception->getLine(),
                    $request->getMethod(),
                    $request->getUri()->getPath()
                )
            );
        }

        // If script is called via cli (e.g. testing) throw the exception to have the standard behaviour
        if (PHP_SAPI === 'cli') {
            // If the column is not found and the request is coming from the command line, it probably means
            // that the database schema.sql was not updated after a change.
            if ($exception instanceof \PDOException && str_contains($exception->getMessage(), 'Column not found')) {
                echo "Column not existing. If you're using samuelgfeller/test-traits, try running 
                `composer schema:generate` in the console and run tests again. \n";
            }

            // Restore previous error handler when the exception has been thrown to satisfy PHPUnit v11
            // It is restored in the post-processing of the NonFatalErrorHandlerMiddleware, but the code doesn't
            // reach it when there's an exception (especially needed for tests expecting an exception).
            // Related PR: https://github.com/sebastianbergmann/phpunit/pull/5619
            restore_error_handler();

            // The exception is thrown to have the standard behaviour (important for testing).
            throw $exception;
        }

        // Detect status code
        $this->statusCode = $this->getHttpStatusCode($exception);
        $response = $response->withStatus($this->statusCode);
        // Reason phrase is the text that describes the status code e.g. 404 => Not found
        $this->reasonPhrase = $response->getReasonPhrase();

        // If the request is JSON, return a JSON response with the error details
        if (str_contains($request->getHeaderLine('Accept'), 'application/json')) {
            return $this->renderJson($exception, $response);
        }

        // Render html details page
        if ($this->displayErrorDetails === true) {
            $errorPageHtml = $this->errorDetailsPageRenderer->renderHtmlDetailsPage($exception, $this->statusCode, $this->reasonPhrase);

            $response->getBody()->write($errorPageHtml);

            return $response;
        }

        // If it's a HttpException it's safe to show the error message to the user otherwise let renderer decide
        $safeExceptionMessage = $exception instanceof HttpException ? $exception->getMessage() : null;

        // Render error section without layout

        $errorPageHtml = $this->prodPageRenderer->renderHtmlProdErrorPage(
            $this->statusCode,
            $safeExceptionMessage,
            $this->errorReportEmailAddress
        );

        $response->getBody()->write($errorPageHtml);

        return $response;
    }

    public function renderJson(Throwable $exception, ResponseInterface $response): ResponseInterface
    {
        $response = $response->withHeader('Content-Type', 'application/json');

        $jsonErrorResponse = [
            'status' => $this->statusCode,
            // If it's a HttpException it's safe to show the error message to the user otherwise show reason phrase
            'message' => $exception instanceof HttpException ? $exception->getMessage() : $this->reasonPhrase,
        ];

        // If $displayErrorDetails is true, add exception details to json response
        if ($this->displayErrorDetails === true) {
            $jsonErrorResponse['error'] = $exception->getMessage();
            /*$jsonErrorResponse['details'] = [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTrace(),
            ];*/
        }

        // Encode and add to response
        $response->getBody()->write(
            (string)json_encode($jsonErrorResponse, JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR)
        );

        return $response;
    }

    private function getHttpStatusCode(Throwable $exception): int
    {
        // HttpExceptions have a status code
        if ($exception instanceof HttpException) {
            return (int)$exception->getCode();
        }
        // Validation error
        if (str_contains($exception::class, strtolower('validation'))) {
            return 422; // Unprocessable Entity
        }

        if ($exception instanceof DomainException || $exception instanceof InvalidArgumentException) {
            return 400; // Bad Request
        }

        $file = basename($exception->getFile());
        if ($file === 'CallableResolver.php') {
            return 404; // Not Found
        }

        // Return default "Internal Server Error"
        return 500;
    }
}