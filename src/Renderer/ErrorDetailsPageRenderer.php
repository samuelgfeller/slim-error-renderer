<?php

namespace SlimErrorRenderer\Renderer;

use ErrorException;
use SlimErrorRenderer\Interfaces\ErrorDetailsPageRendererInterface;
use Throwable;

final class ErrorDetailsPageRenderer implements ErrorDetailsPageRendererInterface
{
    /**
     * Render error details page.
     *
     * @param Throwable $exception
     * @param int $statusCode
     * @param string $reasonPhrase
     *
     * @return string The error details page in html
     */
    public function renderHtmlDetailsPage(Throwable $exception, int $statusCode, string $reasonPhrase): string
    {
        $lineNumber = $exception->getLine();
        $exceptionMessage = $exception->getMessage();
        $errorFormatter = new ErrorDetailsFormatter($exception);

        // If the exception is ErrorException, the css class is warning, otherwise it's error
        $severityCssClassName = $exception instanceof ErrorException ? 'warning' : 'error';

        [$pathToMainErrorFile, $mainErrorFileName] = $errorFormatter->getShortenedFilePathAndName();

        // Exception class name
        $exceptionClassName = get_class($exception);

        // Stack trace
        $stackTraceArray = $errorFormatter->getFormattedStackTraceArray();
        // Already escaped html
        $stackTraceHtml = $this->getTraceEntriesHtml($stackTraceArray);

        // Css and JS
        $css = $this->getCss();
        $js = $this->getJs();

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$this->html($exceptionMessage)}</title>
    <style>$css</style>
</head>

<body class="{$this->html($severityCssClassName)}">
<div id="title-div" class="{$this->html($severityCssClassName)}">
    <p><span>$statusCode | {$this->html($reasonPhrase)}</span>
        <span id="exception-name">{$this->html($exceptionClassName)}</span>
    </p>
    <h1>{$this->html($exceptionMessage)} in <span id="first-path-chunk">{$this->html($pathToMainErrorFile)}</span>
        {$this->html($mainErrorFileName)}
        on line $lineNumber.
    </h1>
</div>
<div id="trace-div" class="{$this->html($severityCssClassName)}">
    <table aria-hidden="true">
        <tr class="non-vendor">
            <th id="num-th">#</th>
            <th>Function</th>
            <th>Location</th>
        </tr>
        $stackTraceHtml
    </table>
</div>
<script>$js</script>
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

    /**
     * @param array<int, array<string, mixed>> $traceEntries
     *
     * @return string
     */
    private function getTraceEntriesHtml(array $traceEntries): string
    {
        $traceContent = '';
        foreach ($traceEntries as $key => $entry) {
            $traceContent .= '<tr>
        <td class="' . $this->html($entry['nonVendorClass']) . '">' . $key . '</td>
        <td class="function-td ' . $this->html($entry['nonVendorFunctionCallClass']) . '">' .
                $this->html($entry['classAndFunction']) . '(';
            // Function parameters
            foreach ($entry['args'] as $argument) {
                // Parameter is expanded on click
                $traceContent .= '<span class="args-span" data-full-details="' .
                    $this->html($argument['detailed']) . '">' .
                    $this->html($argument['truncated']) . '</span>,';
            }
            // Close trace class and function and add file name and line number
            $traceContent .= ')
        </td>
        <td class="stack-trace-file-name ' . $this->html($entry['nonVendorClass']) . '">' .
                $this->html($entry['fileName']) . (!empty($entry['fileName']) ? ':' : '') .
                '<span class="lineSpan">' . $this->html($entry['line']) . '</span>
        </td>
    </tr>';
        }

        return $traceContent;
    }

    /**
     * Returns the css for the error details page.
     *
     * @return string
     */
    private function getCss(): string
    {
        return <<<CSS
/*mobile first min-width sets base and content is adapted to computers.*/
@media (min-width: 100px) {

    * {
        overflow-wrap: anywhere;
    }

    body {
        margin: 0;
        background: #ffd9d0;
        font-family:  "Trebuchet MS", Tahoma, Arial, sans-serif;
    }

    body.warning {
        background: #ffead0;
    }

    body.error {
        background: #ffd9d0;
    }

    #title-div {
        padding: 5px 10%;
        color: black;
        background: tomato;
        border-radius: 0 35px;
        box-shadow: 0 0 10px rgba(0,0,0,0.25);
        box-sizing: border-box;
        margin: 30px 0;
        font-size: 0.8em;
    }

    #title-div h1 {
        margin-top: 4px;
    }

    #title-div.warning {
        background: orange;
        /*box-shadow: 0 0 7px orange;*/
    }

    #title-div.error {
        background: tomato;
        box-shadow: 0 0 7px tomato;
    }

    #first-path-chunk {
        font-size: 0.7em;
    }

    #trace-div {
        font-size: 0.8em;
        margin: auto auto 40px;
        min-width: 350px;
        padding: 20px;
        background: #ff9e88;
        border-radius: 0 35px;
        /*box-shadow: 0 0 10px #ff856e;*/
        box-shadow: 0 0 10px rgba(0,0,0,0.16);
        width: 90%;
    }

    #trace-div.warning {
        background: #ffc588;
    }

    #trace-div.error {
        background: #ff9e88;
        box-shadow: 0 0 10px #ff856e;
    }

    #trace-div h2 {
        margin-top: 0;
        padding-top: 19px;
        text-align: center;
    }

    #trace-div table {
        border-collapse: collapse;
        width: 100%;
        overflow-x: auto;
    }

    #trace-div table td, #trace-div table th { /*border-top: 6px solid red;*/
        padding: 8px;
        text-align: left;
    }

    #trace-div table tr td:nth-child(3) {
        min-width: 100px;
    }

    #num-th {
        font-size: 1.3em;
        color: #a46856;
        margin-right: 50px;
    }

    .non-vendor {
        font-weight: bold;
        font-size: 1.2em;
    }

    .non-vendor .lineSpan {
        font-weight: bold;
        color: #b00000;
        font-size: 1.1em;
    }

    .is-vendor {
        font-weight: normal;
    }

    .args-span {
        color: #395186;
        cursor: pointer;
    }

    #exception-name {
        float: right
    }

    .function-td {
        font-size: 0.9em;
    }
}

@media (min-width: 641px) {
    #trace-div {
        width: 80%;
    }
}
@media (min-width: 810px) {
    #title-div {
        margin: 30px;
    }
    #trace-div table tr td:first-child, #trace-div table tr th:first-child {
        padding-left: 20px;
    }
    #title-div{
        font-size: 1em;
    }
}
@media (min-width: 1000px) {
    #trace-div {
        font-size: 1em;
    }
}
CSS;
    }

    /**
     * Returns JS used by the error details page.
     *
     * @return string
     */
    private function getJs(): string
    {
        return <<<JS
window.onload = function () {
    // Camel-wrap all stack trace file names
    let elements = document.querySelectorAll('.stack-trace-file-name');
    elements.forEach(function (element) {
        camelWrap(element);
    });

    // Show full details when clicking on an argument
    // Select all spans with the class 'args-span'
    var spans = document.querySelectorAll('.args-span');

    // Add a click event listener to each span
    spans.forEach(function (span) {
        let spanExpanded = false;
        let formatted;
        span.addEventListener('click', function () {
            // Get the full details from the data attribute
            let fullDetails = this.getAttribute('data-full-details');
            // Display the full details and store the formatted text
            if (!spanExpanded) {
                formatted = this.innerText;
                span.innerText = fullDetails;
            } else {
                span.innerText = formatted;
            }
            spanExpanded = !spanExpanded;
        });
    });
}

/**
 * This function is used to apply the camelWrapUnicode function to a given DOM node
 * and then replace all zero-width spaces in the node's innerHTML with <wbr> elements.
 * The <wbr> element represents a word break opportunity—a position within text where
 * the browser may optionally break a line, though its line-breaking rules would not
 * otherwise create a break at that location.
 *
 * @param {Node} node - The DOM node to which the camelWrapUnicode function should
 * be applied and in whose innerHTML the zero-width spaces should be replaced
 * with <wbr> elements.
 */
function camelWrap(node) {
    camelWrapUnicode(node);
    node.innerHTML = node.innerHTML.replace(/\u200B/g, "<wbr>");
}

/**
 * This function is used to insert a zero-width space before each uppercase letter in
 * a camelCase string.
 * It does this by recursively traversing the DOM tree starting from the given node.
 * For each text node it finds, it replaces the node's value with a new string where
 * a zero-width space has been inserted before each uppercase letter.
 *
 * Source: http://heap.ch/blog/2016/01/19/camelwrap/
 * @param {Node} node - The node from where to start the DOM traversal.
 */
function camelWrapUnicode(node) {
    // Start from the first child of the given node and continue to the next sibling until there are no more siblings.
    for (node = node.firstChild; node; node = node.nextSibling) {
        // If the current node is a text node, replace its value.
        if (node.nodeType === Node.TEXT_NODE) {
            // Replace the node's value with a new string where a zero-width space has been inserted before each 
            // uppercase letter.
            // This is done by first matching any word character or colon that is repeated 18 or more times, 
            // and for each match, a new string is returned where a zero-width space has been inserted before 
            // each uppercase letter.
            // The same is done by matching any dot character, but without the repetition requirement.
            node.nodeValue = node.nodeValue.replace(/[\w:]{18,}/g, function (str) {
                return str.replace(/([a-z])([A-Z])/g, "$1\u200B$2");
            });
        } else {
            // If the current node is not a text node, continue the traversal from this node.
            camelWrapUnicode(node);
        }
    }
}
JS;
    }
}
