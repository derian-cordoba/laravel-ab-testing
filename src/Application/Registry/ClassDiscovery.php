<?php

declare(strict_types=1);

namespace ABTests\Application\Registry;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Scans one or more directories for PHP files and extracts the fully-qualified
 * class name from each file using the PHP tokenizer. Used by the ab:cache
 * command to build the auto-discovery manifest without requiring Composer's
 * class-map or loading every file.
 */
final readonly class ClassDiscovery
{
    /**
     * @param  list<string>  $directories  Absolute paths to scan recursively.
     * @return list<string> Fully-qualified class names found.
     */
    public function discover(array $directories): array
    {
        $classNames = [];

        foreach ($directories as $directory) {
            if (! is_dir($directory)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                    continue;
                }

                $className = $this->extractClassName($file->getRealPath());

                if ($className !== null) {
                    $classNames[] = $className;
                }
            }
        }

        return $classNames;
    }

    /**
     * Parse a PHP file with the tokenizer to extract the first class/enum
     * declaration's fully-qualified name, without executing the file.
     */
    private function extractClassName(string $filePath): ?string
    {
        $source = @file_get_contents($filePath);

        if ($source === false) {
            return null;
        }

        $tokens = token_get_all($source);
        $namespace = '';
        $count = count($tokens);

        foreach ($tokens as $i => $iValue) {
            // Collect the namespace declaration.
            if (is_array($iValue) && $iValue[0] === T_NAMESPACE) {
                $i++;
                $parts = [];

                while ($i < $count) {
                    $token = $tokens[$i];

                    if (is_string($token)) {
                        // Hit a '{' or ';' — end of the namespace declaration.
                        if ($token === '{' || $token === ';') {
                            break;
                        }
                    } elseif (is_array($token)) {
                        if (in_array($token[0], [T_STRING, T_NS_SEPARATOR, T_NAME_QUALIFIED], true)) {
                            $parts[] = $token[1];
                        } elseif ($token[0] !== T_WHITESPACE) {
                            break;
                        }
                    }

                    $i++;
                }

                $namespace = implode('', $parts);

                continue;
            }

            // Find the first class or enum declaration.
            if (is_array($iValue) && in_array($iValue[0], [T_CLASS, T_ENUM], true)) {
                // Skip whitespace tokens to reach the name.
                $j = $i + 1;
                while ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                    $j++;
                }

                if ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                    $shortName = $tokens[$j][1];

                    return $namespace !== '' ? "$namespace\\$shortName" : $shortName;
                }
            }
        }

        return null;
    }
}
