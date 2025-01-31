<?php

namespace SlimErrorRenderer\Renderer;

use Psr\Http\Message\ResponseInterface;
use Slim\Exception\HttpException;
use Throwable;

class JsonErrorRenderer
{
    public function renderJsonErrorResponse(
        Throwable $exception,
        ResponseInterface $response,
        bool $displayErrorDetails = false,
        int $statusCode = 500,
        string $reasonPhrase = 'Internal Server Error',
    ): ResponseInterface {
        $response = $response->withHeader('Content-Type', 'application/json');

        $jsonErrorResponse = [
            'status' => $statusCode,
            // If it's a HttpException it's safe to show the error message to the user otherwise show reason phrase
            'message' => $exception instanceof HttpException ? $exception->getMessage() : $reasonPhrase,
        ];

        // If $displayErrorDetails is true, add exception details to json response
        if ($displayErrorDetails === true) {
            $errorFormatter = new ErrorDetailsFormatter($exception);
            [$pathToMainErrorFile, $mainErrorFileName] = $errorFormatter->getShortenedFilePathAndName();
            $jsonErrorResponse['error'] = $exception->getMessage();
            $jsonErrorResponse['file'] = $pathToMainErrorFile . $mainErrorFileName;
            $jsonErrorResponse['line'] = $exception->getLine();
            $jsonErrorResponse['trace'] =
                $this->getTraceEntriesJsonArray($errorFormatter->getFormattedStackTraceArray());
        }

        // Encode and add to response
        $response->getBody()->write(
            (string)json_encode($jsonErrorResponse, JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR)
        );

        return $response;
    }

    /**
     * Returns trace entries for JSON response.
     *
     * @param array $traceEntries
     *
     * @return array
     */
    private function getTraceEntriesJsonArray(array $traceEntries): array
    {
        $traceJson = [];
        foreach ($traceEntries as $key => $entry) {
            // Extract truncated arguments
            $truncatedArgs = array_map(function ($argument) {
                return $argument['truncated'];
            }, $entry['args']);
            $truncatedArgsString = implode(', ', $truncatedArgs);
            $isNotVendor = $entry['nonVendorClass'] === 'non-vendor';
            $traceJson[$key] = "#$key " . ($isNotVendor ? '(src) ' : '(vendor) ') . $entry['classAndFunction'] .
                '(' . $truncatedArgsString . ')' .
                (!empty($entry['fileName']) ? ' called in (file)' . $entry['fileName'] . ':' . $entry['line'] : '');
        }

        return $traceJson;
    }
}
