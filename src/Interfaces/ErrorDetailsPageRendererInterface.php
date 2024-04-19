<?php

namespace SlimErrorRenderer\Interfaces;

use Throwable;

interface ErrorDetailsPageRendererInterface
{
    /**
     * Renders the error details page in html.
     *
     * @param Throwable $exception
     * @param int $statusCode
     * @param string $reasonPhrase
     *
     * @return string The error details page in html
     */
    public function renderHtmlDetailsPage(Throwable $exception, int $statusCode, string $reasonPhrase): string;
}
