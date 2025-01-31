<?php

namespace SlimErrorRenderer\Renderer;

use SlimErrorRenderer\Interfaces\GenericErrorPageRendererInterface;

final readonly class GenericErrorPageRenderer implements GenericErrorPageRendererInterface
{
    /**
     * Render prod error page.
     *
     * @param int $statusCode Http status code
     * @param string|null $safeExceptionMessage Exception message that doesn't contain sensitive information
     * @param string|null $errorReportEmailAddress Email address to report errors to
     *
     * @return string
     */
    public function renderHtmlProdErrorPage(
        int $statusCode,
        ?string $safeExceptionMessage = null,
        ?string $errorReportEmailAddress = null,
    ): string {
        switch ($statusCode) {
            case 404:
                $title = 'Page not found';
                $htmlGenericErrorMessage = "Looks like you've ventured into uncharted territory. 
                Please report the issue!";
                break;
            case 403:
                $title = 'Access forbidden';
                $htmlGenericErrorMessage =
                    'You are not allowed to access this page. Please report the issue if you think this is 
                        an error.';
                break;
            case 400:
                $title = 'The request is invalid';
                $htmlGenericErrorMessage = 'There is something wrong with the request syntax. Please report the issue.';
                break;
            case 422:
                $title = 'Validation failed.';
                $htmlGenericErrorMessage = 'The server could not interpret the data it received. Please try 
                again with valid data and
                        report the issue if it persists.';
                break;
            case 500:
                $title = 'Internal Server Error.';
                $htmlGenericErrorMessage =
                    'It\'s not your fault! The server has an internal error. <br> Please try again and 
                            report the issue if the problem persists.';
                break;
            default:
                $title = 'An error occurred.';
                $htmlGenericErrorMessage =
                    'Please try again and then report the error if the issue persists.';
                break;
        }
        $htmlReportIssueBtn = '';
        if ($errorReportEmailAddress) {
            $emailSubject = $safeExceptionMessage !== null ?
                strip_tags(str_replace('"', '', $safeExceptionMessage)) : $statusCode . ' ' . $title;
            $emailBody = "This is what I did before the error happened:\n";
            $htmlReportIssueBtn = <<<HTML
<a href="mailto:$errorReportEmailAddress?subject=$emailSubject&body=$emailBody" target="_blank" 
class="btn">Report the issue</a>
HTML;
        }

        $htmlExceptionMessage = $safeExceptionMessage !== null ?
            '<p id="server-message">Server message: ' . $this->html($safeExceptionMessage) . '</p>' : '';

        // $htmlGenericErrorMessage and $htmlExceptionMessage should not be escaped as they are created above, so
        // they are safe and have html tags that should be interpreted
        $errorHtmlSection = <<<HTML
<section id="error-inner-section">
    <h1 id="error-status-code">$statusCode</h1>

    <section id="error-description-section">
        <h2 id="error-reason-phrase">OOPS! {$this->html($title)}</h2>
        <p id="error-message">$htmlGenericErrorMessage</p>
        $htmlExceptionMessage
    </section>
    <section id="error-btn-section">
        <button onclick="window.history.go(-1); return false;" class="btn">Go back</button>
        $htmlReportIssueBtn
    </section>
</section>
HTML;

        return $this->renderHtmlInLayout($errorHtmlSection, $title);
    }

    /**
     * Render error section in HTML layout.
     * Initially separated from section to be able to use the section with an own custom layout, but it's too
     * much configuration. An entire custom ProdErrorPageRenderer must be provided to change the layout.
     *
     * @param string $errorSectionHtml
     * @param string $title
     *
     * @return string
     */
    private function renderHtmlInLayout(string $errorSectionHtml, string $title): string
    {
        $css = $this->getCss();

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="content-type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>$css</style>
    <title>$title</title>
</head>
<body>
    <main>
        $errorSectionHtml
    </main>

</body>
</html>
HTML;
    }

    /**
     * Escape error message for HTML output.
     *
     * @param string $text
     *
     * @return string
     */
    private function html(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function getCss(): string
    {
        return <<<CSS
:root {
    --error-body-gradient-color-1: #49d2ff;
    --error-body-gradient-color-2: #ea9bc2;

    --error-inner-section-background: rgba(255, 255, 255, 0.4);
    --error-reason-phrase-color: #535353;

    --error-status-code-gradient-color-1: #00c1ff;
    --error-status-code-gradient-color-2: #ff6bb4;

}

[data-theme="dark"] {
    --error-body-gradient-color-1: rgb(102, 93, 182);
    --error-body-gradient-color-2: rgb(64, 148, 157);
    --error-inner-section-background: rgba(0, 0, 0, 0.4);
    --error-reason-phrase-color: #a9a9a9;
}


@media (min-width: 100px) {
    body {
        background: linear-gradient(to bottom right, var(--error-body-gradient-color-1) 0%, 
        var(--error-body-gradient-color-2) 100%);
        margin: 0;
        font-family: "Trebuchet MS", sans-serif;
    }

    main {
        display: flex;
        align-items: center;
        justify-content: center;
        /*background: lightblue;*/
        margin-left: 0;
        margin-top: 0;
        border-radius: 0 0 0 0;
        background: transparent;
        height: 100dvh;
    }
    
    .btn {
        background: white;
        color: black;
        padding: 13px 25px;
        border: none;
        margin: 15px 0 0;
        border-radius: 14px;
        cursor: pointer;
        font-size: 18px;
        box-shadow: 2px 3px 11px rgba(0, 0, 0, 0.2);
        text-decoration: none; /*For when <a> is used as button*/
        display: inline-block; /*For when <a> is used as button and when icon is used*/
        letter-spacing: 0.5px; /*Increase spacing between letters a bit*/
        transition: filter 250ms;
            font-family: inherit;        
    }

    footer {
        background: var(--error-inner-section-background);
        margin-top: 0;
        /*border-radius: 0;*/
    }

    #error-inner-section {
        width: fit-content;
        max-width: 92%;
        height: fit-content;
        padding: 40px 30px;
        /*border: 1px solid #ccc;*/
        /*margin-left: 50px;*/
        text-align: center;
        border-radius: 30px;
        background: var(--error-inner-section-background);
        /*backdrop-filter: blur(50px);*/
        box-shadow: 0 5px 10px rgba(0, 0, 0, 0.1);
    }

    /*#error-status-code*/
    #error-inner-section h1 {
        font-size: clamp(100px, 18vw, 200px);
        font-family: Verdana, Helvetica, Arial, sans-serif;
        line-height: 1em;
        margin-bottom: 0;
        margin-top: 0px;
        position: relative;
        background: linear-gradient(to bottom right, var(--error-status-code-gradient-color-1) 10%,
        var(--error-status-code-gradient-color-2) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    #error-inner-section h2 {
        text-transform: uppercase;
        /*font-family: Poppins, Helvetica, sans-serif;*/
        color: var(--error-reason-phrase-color);
        /*font-family: SF-Pro Display, Helvetica, sans-serif;*/
    }

    #error-inner-section p {
        /*font-family: Poppins, Helvetica, sans-serif;*/
        font-weight: 400;
        font-size: 1.2em;
    }

    p#server-message {
        font-size: 1em;
    }

    #error-btn-section {
        margin-top: 30px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-evenly;
    }

    #error-btn-section .btn {
        background: rgba(255, 255, 255, 0.4);
        margin: 10px;
    }
}

@media (min-width: 641px) {

    #error-inner-section {
        padding: 40px 50px;
        max-width: 80%;
    }
}

@media (min-width: 961px) {
    #error-inner-section {
        padding: 40px 100px;
    }
}
CSS;
    }
}
