<?php

namespace SlimErrorRenderer\Interfaces;

interface GenericErrorPageRendererInterface
{
    /**
     * Renders the error page for production (without sensitive infos) in html.
     *
     * @param int $statusCode
     * @param string|null $safeExceptionMessage
     * @param string|null $errorReportEmailAddress
     *
     * @return string The error page in html
     */
    public function renderHtmlProdErrorPage(
        int $statusCode,
        ?string $safeExceptionMessage,
        ?string $errorReportEmailAddress
    ): string;
}
