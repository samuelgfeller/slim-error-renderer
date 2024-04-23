<?php

namespace SlimErrorRenderer\Renderer;

use Throwable;

final class ErrorDetailsFormatter
{
    // The filesystem path to the project root folder is removed in the error details page
    private string $fileSystemPath = 'C:\xampp\htdocs\\';

    public function __construct(private readonly Throwable $exception)
    {
    }

    /**
     * @return array<string> The shortened path and file name
     */
    public function getShortenedFilePathAndName(): array
    {
        $file = $this->exception->getFile();
        // Remove the filesystem path and make the path to the file that had the error smaller to increase readability
        $lastBackslash = strrpos($file, '\\');
        $mainErrorFileName = substr($file, $lastBackslash + 1);
        $firstChunkFullPath = substr($file, 0, $lastBackslash + 1);
        // remove C:\xampp\htdocs\ and project name to keep only part starting with src\
        $firstChunkMinusFilesystem = str_replace($this->fileSystemPath, '', $firstChunkFullPath);
        // locate project name because it is right before the first backslash (after removing filesystem)
        $projectName = substr($firstChunkMinusFilesystem, 0, strpos($firstChunkMinusFilesystem, '\\') + 1);
        // remove project name from first chunk
        $pathToMainErrorFile = str_replace($projectName, '', $firstChunkMinusFilesystem);

        return [$pathToMainErrorFile, $mainErrorFileName];
    }

    /**
     * Returns the stack trace entries with the shortened class and function name,
     * only file name without path, line number and function arguments.
     *
     * @return array<int, array<string, mixed>> Trace entries
     */
    public function getFormattedStackTraceArray(): array
    {
        $traceEntries = [];
        $trace = $this->exception->getTrace();

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
            $nonVendorFileClass = !empty($t['file']) && !str_contains($t['file'], 'vendor') ? 'non-vendor' : '';
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
     * @param mixed $argument The variable to be converted to a string
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
}
