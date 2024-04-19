<?php

namespace SlimErrorRenderer\Renderer;

use SlimErrorRenderer\Interfaces\ErrorDetailsPageRendererInterface;
use Throwable;

final class ErrorDetailsPageRenderer implements ErrorDetailsPageRendererInterface
{
    // The filesystem path below will be removed from the file path in the error message
    private string $fileSystemPath = 'C:\xampp\htdocs\\';

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
        $file = $exception->getFile();
        $lineNumber = $exception->getLine();
        $exceptionMessage = $exception->getMessage();

        // If the exception is ErrorException, the css class is warning, otherwise it's error
        $severityCssClassName = $exception instanceof \ErrorException ? 'warning' : 'error';

        // Remove the filesystem path and make the path to the file that had the error smaller to increase readability
        $lastBackslash = strrpos($file, '\\');
        $mainErrorFile = substr($file, $lastBackslash + 1);
        $firstChunkFullPath = substr($file, 0, $lastBackslash + 1);
        // remove C:\xampp\htdocs\ and project name to keep only part starting with src\
        $firstChunkMinusFilesystem = str_replace($this->fileSystemPath, '', $firstChunkFullPath);
        // locate project name because it is right before the first backslash (after removing filesystem)
        $projectName = substr($firstChunkMinusFilesystem, 0, strpos($firstChunkMinusFilesystem, '\\') + 1);
        // remove project name from first chunk
        $pathToMainErrorFile = str_replace($projectName, '', $firstChunkMinusFilesystem);
        // Exception class name
        $exceptionClassName = get_class($exception);

        // Stack trace
        $stackTraceArray = $this->getFormattedStackTraceArray($exception);
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
        {$this->html($mainErrorFile)}
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
     * @param Throwable $exception
     *
     * @return array<int, array<string, mixed>> Trace entries
     */
    private function getFormattedStackTraceArray(Throwable $exception): array
    {
        $traceEntries = [];
        $trace = $exception->getTrace();

        foreach ($trace as $key => $t) {
            // Sometimes class, type, file and line not set e.g. pdfRenderer when var undefined in template
            $t['class'] = $t['class'] ?? '';
            $t['type'] = $t['type'] ?? '';
            $t['file'] = $t['file'] ?? '';
            $t['line'] = $t['line'] ?? '';
            // remove everything from file path before the last \
            $fileWithoutPath = $this->removeEverythingBeforeLastBackslash($t['file']);
            // remove everything from class before last \
            $classWithoutPath = $this->removeEverythingBeforeLastBackslash($t['class']);
            // if the file path doesn't contain "vendor", a css class is added to highlight it
            $nonVendorFileClass = !str_contains($t['file'], 'vendor') ? 'non-vendor' : '';
            // if file and class path don't contain vendor, add "non-vendor" css class to add highlight on class
            $classIsVendor = str_contains($t['class'], 'vendor');
            $nonVendorFunctionCallClass = !empty($nonVendorFileClass) && !$classIsVendor ? 'non-vendor' : '';
            // Get function arguments
            $args = [];
            foreach ($t['args'] ?? [] as $argKey => $argument) {
                // Get argument as string not longer than 15 characters
                $args[$argKey]['truncated'] = $this->getTraceArgumentAsTruncatedString($argument);
                // Get full length of argument as string
                $fullArgument = $this->getTraceArgumentAsString($argument);
                // Replace double backslash with single backslash
                $args[$argKey]['detailed'] = str_replace('\\\\', '\\', $fullArgument);
            }
            $traceEntries[$key]['args'] = $args;
            // If the file is outside vendor class, add "non-vendor" css class to highlight it
            $traceEntries[$key]['nonVendorClass'] = $nonVendorFileClass;
            // Function call happens in a class outside the vendor folder
            // File may be non-vendor, but function call of the same trace entry is in a vendor class
            $traceEntries[$key]['nonVendorFunctionCallClass'] = $nonVendorFunctionCallClass;
            $traceEntries[$key]['classAndFunction'] = $classWithoutPath . $t['type'] . $t['function'];
            $traceEntries[$key]['fileName'] = $fileWithoutPath;
            $traceEntries[$key]['line'] = $t['line'];
        }

        return $traceEntries;
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
        <td class="function-td ' . $this->html($entry['nonVendorFunctionCallClass']) . '">' . $this->html($entry['classAndFunction']) . '(';
            // Function parameters
            foreach ($entry['args'] as $argument) {
                // Parameter is expanded on click
                $traceContent .= '<span class="args-span" data-full-details="' . $this->html($argument['detailed']) . '">' .
                    $this->html($argument['truncated']) . '</span>,';
            }
            // Close trace class and function and add file name and line number
            $traceContent .= ')
        </td>
        <td class="stack-trace-file-name ' . $this->html($entry['nonVendorClass']) . '">' . $this->html($entry['fileName']) .
                ':<span class="lineSpan">' . $this->html($entry['line']) . '</span>
        </td>
    </tr>';
        }

        return $traceContent;
    }

    /**
     * The stack trace contains the functions that are called during script execution with
     * function arguments that can be any type (objects, arrays, strings or null).
     * This function returns the argument as a string.
     *
     * @param mixed $argument
     *
     * @return string
     */
    private function getTraceArgumentAsString(mixed $argument): string
    {
        // If the variable is an object, return its class name.
        if (is_object($argument)) {
            return get_class($argument);
        }

        // If the variable is an array, iterate over its elements
        if (is_array($argument)) {
            $result = [];
            foreach ($argument as $key => $value) {
                // if it's an object, get its class name if it's an array represent it as 'Array'
                // otherwise, keep the original value.
                if (is_object($value)) {
                    $result[$key] = get_class($value);
                } elseif (is_array($value)) {
                    $result[$key] = 'Array';
                } else {
                    $result[$key] = $value;
                }
            }

            // Return the array converted to a string using var_export
            return var_export($result, true);
        }

        // If the variable is not an object or an array, convert it to a string using var_export.
        return var_export($argument, true);
    }

    /**
     * Convert the given argument to a string not longer than 15 chars
     * except if it's a file or a class name.
     *
     * @param mixed $argument the variable to be converted to a string
     *
     * @return string the string representation of the variable
     */
    private function getTraceArgumentAsTruncatedString(mixed $argument): string
    {
        if ($argument === null) {
            $formatted = 'NULL';
        } elseif (is_string($argument)) {
            // If string contains backslashes keep part after the last backslash, otherwise keep the first 15 chars
            if (str_contains($argument, '\\')) {
                $argument = $this->removeEverythingBeforeLastBackslash($argument);
            } elseif (strlen($argument) > 15) {
                $argument = substr($argument, 0, 15) . '...';
            }
            $formatted = '"' . $argument . '"';
        } elseif (is_object($argument)) {
            $formatted = get_class($argument);
            // Only keep the last part of class string
            if (strlen($formatted) > 15 && str_contains($formatted, '\\')) {
                $formatted = $this->removeEverythingBeforeLastBackslash($formatted);
            }
        } elseif (is_array($argument)) {
            // Convert each array element to string recursively
            $elements = array_map(function ($element) {
                return $this->getTraceArgumentAsTruncatedString($element);
            }, $argument);

            return '[' . implode(', ', $elements) . ']';
        } else {
            $formatted = (string)$argument;
        }

        return $formatted;
    }

    /**
     * If a string is 'App\Domain\Example\Class', this function returns 'Class'.
     *
     * @param string $string
     *
     * @return string
     */
    private function removeEverythingBeforeLastBackslash(string $string): string
    {
        return trim(substr($string, strrpos($string, '\\') + 1));
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
            // Replace the node's value with a new string where a zero-width space has been inserted before each uppercase letter.
            // This is done by first matching any word character or colon that is repeated 18 or more times, and for each match,
            // a new string is returned where a zero-width space has been inserted before each uppercase letter.
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
