<?php

namespace PostCSS\Path;

/**
 * Bare port of functions from NodeJS project.
 *
 * @link https://github.com/nodejs/node/blob/master/lib/fs.js
 * @link https://github.com/nodejs/node/blob/master/lib/path.js
 */
class NodeJS
{
    /**
     * Bare port of path.resolve.
     *
     * @param string $path
     *
     * @return string
     */
    public static function resolve($path/*, ...*/)
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return static::win32_resolve(func_get_args());
        } else {
            return static::posix_resolve(func_get_args());
        }
    }

    /**
     * Bare port of path.posix.resolve.
     *
     * @param string[] $paths
     *
     * @throws \Exception
     *
     * @return string
     */
    protected static function posix_resolve(array $paths)
    {
        $resolvedPath = '';
        $resolvedAbsolute = false;
        $cwd = null;
        for ($i = count($paths) - 1; $i >= -1 && !$resolvedAbsolute; --$i) {
            if ($i >= 0) {
                $path = $paths[$i];
            } else {
                if ($cwd === null) {
                    $cwd = function_exists('getcwd') ? @getcwd() : false;
                }
                $path = $cwd;
            }
            if (!is_string($path)) {
                throw new \Exception('Path must be a string. Received '.json_encode($path));
            }

            // Skip empty entries
            if ($path === '') {
                continue;
            }

            $resolvedPath = $path.'/'.$resolvedPath;
            $resolvedAbsolute = ord($path[0]) === 47/*/*/;
        }
        // At this point the path should be resolved to a full absolute path, but
        // handle relative paths to be safe (might happen when process.cwd() fails)

        // Normalize the path
        $resolvedPath = static::normalizeStringPosix($resolvedPath, !$resolvedAbsolute);

        if ($resolvedAbsolute) {
            if ($resolvedPath !== '') {
                return '/'.$resolvedPath;
            } else {
                return '/';
            }
        } elseif ($resolvedPath !== '') {
            return $resolvedPath;
        } else {
            return '.';
        }
    }

    /**
     * Bare port of path.win32.resolve.
     *
     * @param string[] $paths
     *
     * @throws \Exception
     *
     * @return string
     */
    protected static function win32_resolve(array $paths)
    {
        $resolvedDevice = '';
        $resolvedTail = '';
        $resolvedAbsolute = false;
        for ($i = count($paths) - 1; $i >= -1; --$i) {
            if ($i >= 0) {
                $path = $paths[$i];
            } elseif (!$resolvedDevice) {
                $path = function_exists('getcwd') ? @getcwd() : false;
            } else {
                // Windows has the concept of drive-specific current working
                // directories. If we've resolved a drive letter but not yet an
                // absolute path, get cwd for that drive. We're sure the device is not
                // a UNC path at this points, because UNC paths are always absolute.
                $path = function_exists('getenv') ? @getenv('='.$resolvedDevice) : false;
                // Verify that a drive-local cwd was found and that it actually points
                // to our drive. If not, default to the drive's root.
                if ($path === false || strcasecmp($substr($path, 0, 3), $resolvedDevice.'\\') !== 0) {
                    $path = $resolvedDevice.'\\';
                }
            }
            if (!is_string($path)) {
                throw new \Exception('Path must be a string. Received '.json_encode($path));
            }

            $len = strlen($path);

            // Skip empty entries
            if ($len === 0) {
                continue;
            }

            $rootEnd = 0;
            $code = ord($path[0]);
            $device = '';
            $isAbsolute = false;

            // Try to match a root
            if ($len > 1) {
                if ($code === 47/*/*/ || $code === 92/*\*/) {
                    // Possible UNC root

                    // If we started with a separator, we know we at least have an
                    // absolute path of some kind (UNC or otherwise)
                    $isAbsolute = true;

                    $code = ord($path[1]);
                    if ($code === 47/*/*/ || $code === 92/*\*/) {
                        // Matched double path separator at beginning
                        $j = 2;
                        $last = $j;
                        // Match 1 or more non-path separators
                        for (; $j < $len; ++$j) {
                            $code = ord($path[$j]);
                            if ($code === 47/*/*/ || $code === 92/*\*/) {
                                break;
                            }
                        }
                        if ($j < len && $j !== $last) {
                            $firstPart = substr($path, $last, $j - $last);
                            // Matched!
                            $last = $j;
                            // Match 1 or more path separators
                            for (; $j < $len; ++$j) {
                                $code = ord($path[$j]);
                                if ($code !== 47/*/*/ && $code !== 92/*\*/) {
                                    break;
                                }
                            }
                            if ($j < $len && $j !== $last) {
                                // Matched!
                                $last = $j;
                                // Match 1 or more non-path separators
                                for (; $j < $len; ++$j) {
                                    $code = ord($path[$j]);
                                    if ($code === 47/*/*/ || $code === 92/*\*/) {
                                        break;
                                    }
                                }
                                if ($j === $len) {
                                    // We matched a UNC root only
                                    $device = '\\\\'.$firstPart.'\\'.substr($path, $last);
                                    $rootEnd = $j;
                                } elseif ($j !== $last) {
                                    // We matched a UNC root with leftovers
                                    $device = '\\\\'.$firstPart.'\\'.substr($path, $last, $j - $last);
                                    $rootEnd = $j;
                                }
                            }
                        }
                    } else {
                        $rootEnd = 1;
                    }
                } elseif (($code >= 65/*A*/ && $code <= 90/*Z*/) || ($code >= 97/*a*/ && $code <= 122/*z*/)) {
                    // Possible device root
                    $code = ord($path[1]);
                    if ($code === 58/*:*/) {
                        $device = substr($path, 0, 2);
                        $rootEnd = 2;
                        if ($len > 2) {
                            $code = ord($path[2]);
                            if ($code === 47/*/*/ || $code === 92/*\*/) {
                                // Treat separator following drive name as an absolute path
                                // indicator
                                $isAbsolute = true;
                                $rootEnd = 3;
                            }
                        }
                    }
                }
            } elseif ($code === 47/*/*/ || $code === 92/*\*/) {
                // `path` contains just a path separator
                $rootEnd = 1;
                $isAbsolute = true;
            }

            if ($device !== '' && $resolvedDevice !== '' && strcasecmp($device, $resolvedDevice) !== 0) {
                // This path points to another device so it is not applicable
                continue;
            }

            if ($resolvedDevice === '' && $device !== '') {
                $resolvedDevice = $device;
            }
            if (!$resolvedAbsolute) {
                $resolvedTail = substr($path, $rootEnd).'\\'.$resolvedTail;
                $resolvedAbsolute = $isAbsolute;
            }

            if ($resolvedDevice !== '' && $resolvedAbsolute) {
                break;
            }
        }

        // At this point the path should be resolved to a full absolute path,
        // but handle relative paths to be safe (might happen when process.cwd()
        // fails)

        // Normalize the tail path
        $resolvedTail = static::normalizeStringWin32($resolvedTail, !$resolvedAbsolute);

        $result = $resolvedDevice.($resolvedAbsolute ? '\\' : '').$resolvedTail;

        return ($result === '') ? '.' : $result;
    }

    /**
     * Bare port of path.normalizeStringWin32.
     *
     * Resolves . and .. elements in a path with directory names.
     *
     * @param string $path
     * @param bool $allowAboveRoot
     *
     * @return string
     */
    protected static function normalizeStringWin32($path, $allowAboveRoot)
    {
        $res = '';
        $lastSlash = -1;
        $dots = 0;
        $pathLength = strlen($path);
        for ($i = 0; $i <= $pathLength; ++$i) {
            if ($i < $pathLength) {
                $code = ord($path[$i]);
            } elseif ($code === 47/*/*/ || $code === 92/*\*/) {
                break;
            } else {
                $code = 47/*/*/;
            }
            if ($code === 47/*/*/ || $code === 92/*\*/) {
                if ($lastSlash === $i - 1 || $dots === 1) {
                    // NOOP
                } elseif ($lastSlash !== $i - 1 && $dots === 2) {
                    $resLength = strlen($res);
                    if ($resLength < 2 || ord($res[$resLength - 1]) !== 46/*.*/ || ord($res[$resLength - 2]) !== 46/*.*/) {
                        if ($resLength > 2) {
                            $start = $resLength - 1;
                            $j = $start;
                            for (; $j >= 0; --$j) {
                                if (ord($res[$j]) === 92/*\*/) {
                                    break;
                                }
                            }
                            if ($j !== $start) {
                                if ($j === -1) {
                                    $res = '';
                                } else {
                                    $res = substr($res, 0, $j);
                                }
                                $lastSlash = $i;
                                $dots = 0;
                                continue;
                            }
                        } elseif ($resLength === 2 || $resLength === 1) {
                            $res = '';
                            $lastSlash = $i;
                            $dots = 0;
                            continue;
                        }
                    }
                    if ($allowAboveRoot) {
                        if ($resLength > 0) {
                            $res .= '\\..';
                        } else {
                            $res = '..';
                        }
                    }
                } else {
                    if ($res !== '') {
                        $res .= '\\'.substr($path, $lastSlash + 1, $i - $lastSlash - 1);
                    } else {
                        $res = substr($path, $lastSlash + 1, $i - $lastSlash - 1);
                    }
                }
                $lastSlash = $i;
                $dots = 0;
            } elseif ($code === 46/*.*/ && $dots !== -1) {
                ++$dots;
            } else {
                $dots = -1;
            }
        }

        return $res;
    }

    /**
     * Bare port of path.normalizeStringPosix.
     *
     * Resolves . and .. elements in a path with directory names.
     *
     * @param string $path
     * @param bool $allowAboveRoot
     *
     * @return string
     */
    protected static function normalizeStringPosix($path, $allowAboveRoot)
    {
        $res = '';
        $lastSlash = -1;
        $dots = 0;
        $pathLength = strlen($path);
        for ($i = 0; $i <= $pathLength; ++$i) {
            if ($i < $pathLength) {
                $code = ord($path[$i]);
            } elseif ($code === 47/*/*/) {
                break;
            } else {
                $code = 47/*/*/;
            }
            if ($code === 47/*/*/) {
                if ($lastSlash === $i - 1 || $dots === 1) {
                    // NOOP
                } elseif ($lastSlash !== $i - 1 && $dots === 2) {
                    $resLength = strlen($res);
                    if ($resLength < 2 || ord($res[$resLength - 1]) !== 46/*.*/ || ord($res[$resLength - 2]) !== 46/*.*/) {
                        if ($resLength > 2) {
                            $start = $resLength - 1;
                            $j = $start;
                            for (; $j >= 0; --$j) {
                                if (ord($res[$j]) === 47/*/*/) {
                                    break;
                                }
                            }
                            if ($j !== $start) {
                                if ($j === -1) {
                                    $res = '';
                                } else {
                                    $res = substr($res, 0, $j);
                                }
                                $lastSlash = $i;
                                $dots = 0;
                                continue;
                            }
                        } elseif ($resLength === 2 || $resLength === 1) {
                            $res = '';
                            $lastSlash = $i;
                            $dots = 0;
                            continue;
                        }
                    }
                    if ($allowAboveRoot) {
                        if ($res !== '') {
                            $res .= '/..';
                        } else {
                            $res = '..';
                        }
                    }
                } else {
                    if (strlen($res) > 0) {
                        $res .= '/'.substr($path, $lastSlash + 1, $i - $lastSlash - 1);
                    } else {
                        $res = substr($path, $lastSlash + 1, $i - $lastSlash - 1);
                    }
                }
                $lastSlash = $i;
                $dots = 0;
            } elseif ($code === 46/*.*/ && $dots !== -1) {
                ++$dots;
            } else {
                $dots = -1;
            }
        }

        return $res;
    }

    /**
     * Bare port of path.relative.
     *
     * It will solve the relative path from `from` to `to`.
     *
     * @param string $from
     * @param string $to
     *
     * @return string
     */
    public static function relative($from, $to)
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return static::win32_relative($from, $to);
        } else {
            return static::posix_relative($from, $to);
        }
    }

    /**
     * Bare port of path.win32.relative.
     *
     * It will solve the relative path from `from` to `to`, for instance:
     * $from = 'C:\\orandea\\test\\aaa'
     * $to = 'C:\\orandea\\impl\\bbb'
     * The output of the function should be: '..\\..\\impl\\bbb'.
     *
     * @param string $from
     * @param string $to
     *
     * @return string
     */
    protected static function win32_relative($from, $to)
    {
        if (!is_string($from)) {
            throw new \Exception('Path must be a string. Received '.json_encode($from));
        }
        if (!is_string($to)) {
            throw new \Exception('Path must be a string. Received '.json_encode($to));
        }
        if ($from === $to) {
            return '';
        }
        $fromOrig = static::win32_resolve([$from]);
        $toOrig = static::win32_resolve([$to]);
        if ($fromOrig === $toOrig) {
            return '';
        }
        $from = strtolower($fromOrig);
        $to = strtolower($toOrig);
        if ($from === $to) {
            return '';
        }
        // Trim any leading backslashes
        $fromStart = 0;
        $fl = strlen($from);
        for (; $fromStart < $fl; ++$fromStart) {
            if (ord($from[$fromStart]) !== 92/*\*/) {
                break;
            }
        }
        // Trim trailing backslashes (applicable $to UNC paths only)
        $fromEnd = $fl;
        for (; $fromEnd - 1 > $fromStart; --$fromEnd) {
            if (ord($from[$fromEnd - 1]) !== 92/*\*/) {
                break;
            }
        }
        $fromLen = ($fromEnd - $fromStart);
        // Trim any leading backslashes
        $tl = strlen($to);
        $toStart = 0;
        for (; $toStart < $tl; ++$toStart) {
            if (ord($to[$toStart]) !== 92/*\*/) {
                break;
            }
        }
        // Trim trailing backslashes (applicable $to UNC paths only)
        $toEnd = $tl;
        for (; $toEnd - 1 > $toStart; --$toEnd) {
            if (ord($to[$toEnd - 1]) !== 92/*\*/) {
                break;
            }
        }
        $toLen = ($toEnd - $toStart);
        // Compare paths $to find the longest common path $from root
        $length = ($fromLen < $toLen ? $fromLen : $toLen);
        $lastCommonSep = -1;
        $i = 0;
        for (; $i <= $length; ++$i) {
            if ($i === $length) {
                if ($toLen > $length) {
                    if (ord($to[$toStart + $i]) === 92/*\*/) {
                        // We get here if `$from` is the exact base path for `$to`.
                        // For example: $from='C:\\foo\\bar'; $to='C:\\foo\\bar\\baz'
                        return substr($toOrig, $toStart + $i + 1);
                    } elseif ($i === 2) {
                        // We get here if `$from` is the device root.
                        // For example: $from='C:\\'; $to='C:\\foo'
                        return substr($toOrig, $toStart + $i);
                    }
                }
                if ($fromLen > $length) {
                    if (ord($from[$fromStart + $i]) === 92/*\*/) {
                        // We get here if `$to` is the exact base path for `$from`.
                        // For example: $from='C:\\foo\\bar'; $to='C:\\foo'
                        $lastCommonSep = $i;
                    } elseif ($i === 2) {
                        // We get here if `$to` is the device root.
                        // For example: $from='C:\\foo\\bar'; $to='C:\\'
                        $lastCommonSep = 3;
                    }
                }
                break;
            }
            $fromCode = ord($from[$fromStart + $i]);
            $toCode = ord($to[$toStart + $i]);
            if ($fromCode !== $toCode) {
                break;
            } elseif ($fromCode === 92/*\*/) {
                $lastCommonSep = $i;
            }
        }
        // We found a mismatch before the first common path separator was seen, so
        // return the original `$to`.
        // TODO: do this just for device roots (and not UNC paths)?
        if ($i !== $length && $lastCommonSep === -1) {
            if ($toStart > 0) {
                return substr($toOrig, $toStart);
            } else {
                return $toOrig;
            }
        }
        $out = '';
        if ($lastCommonSep === -1) {
            $lastCommonSep = 0;
        }
        // Generate the relative path based on the path difference between `$to` and `$from`
        for ($i = $fromStart + $lastCommonSep + 1; $i <= $fromEnd; ++$i) {
            if ($i === $fromEnd || ord($from[$i]) === 92/*\*/) {
                if ($out === '') {
                    $out = '..';
                } else {
                    $out .= '\\..';
                }
            }
        }
        // Lastly, append the rest of the destination (`$to`) path that comes after
        // the common path parts
        if ($out !== '') {
            return $out.substr($toOrig, $toStart + $lastCommonSep, $toEnd - $toStart + $lastCommonSep);
        } else {
            $toStart .= $lastCommonSep;
            if (ord($toOrig[$toStart]) === 92/*\*/) {
                ++$toStart;
            }

            return substr($toOrig, $toStart, $toEnd - $toStart);
        }
    }

    /**
     * Bare port of path.posix.relative.
     *
     * @param string $from
     * @param string $to
     *
     * @return string
     */
    protected static function posix_relative($from, $to)
    {
        if (!is_string($from)) {
            throw new \Exception('Path must be a string. Received '.json_encode($from));
        }
        if (!is_string($to)) {
            throw new \Exception('Path must be a string. Received '.json_encode($to));
        }
        if ($from === $to) {
            return '';
        }
        $from = static::posix_resolve([$from]);
        $to = static::posix_resolve([$to]);

        if ($from === $to) {
            return '';
        }

        // Trim any leading backslashes
        $fromStart = 1;
        $fl = strlen($from);
        for (; $fromStart < $fl; ++$fromStart) {
            if (ord($from[$fromStart]) !== 47/*/*/) {
                break;
            }
        }
        $fromEnd = $fl;
        $fromLen = ($fromEnd - $fromStart);
        // Trim any leading backslashes
        $toStart = 1;
        $tl = strlen($to);
        for (; $toStart < $tl; ++$toStart) {
            if (ord($to[$toStart]) !== 47/*/*/) {
                break;
            }
        }
        $toEnd = $tl;
        $toLen = ($toEnd - $toStart);
        // Compare paths to find the longest common path from root
        $length = ($fromLen < $toLen ? $fromLen : $toLen);
        $lastCommonSep = -1;
        $i = 0;
        for (; $i <= $length; ++$i) {
            if ($i === $length) {
                if ($toLen > $length) {
                    if (ord($to[$toStart + $i]) === 47/*/*/) {
                        // We get here if `$from` is the exact base path for `$to`.
                        // For example: $from='/foo/bar'; $to='/foo/bar/baz'
                        return substr($to, $toStart + $i + 1);
                    } elseif ($i === 0) {
                        // We get here if `$from` is the root
                        // For example: $from='/'; $to='/foo'
                        return substr($to, $toStart + $i);
                    }
                } elseif ($fromLen > $length) {
                    if (ord($from[$fromStart + $i]) === 47/*/*/) {
                        // We get here if `$to` is the exact base path for `$from`.
                        // For example: $from='/foo/bar/baz'; $to='/foo/bar'
                        $lastCommonSep = $i;
                    } elseif ($i === 0) {
                        // We get here if `$to` is the root.
                        // For example: $from='/foo'; $to='/'
                        $lastCommonSep = 0;
                    }
                }
                break;
            }
            $fromCode = ord($from[$fromStart + $i]);
            $toCode = ord($to[$toStart + $i]);
            if ($fromCode !== $toCode) {
                break;
            } elseif ($fromCode === 47/*/*/) {
                $lastCommonSep = $i;
            }
        }
        $out = '';
        // Generate the relative path based on the path difference between `$to` and `$from`
        for ($i = $fromStart + $lastCommonSep + 1; $i <= $fromEnd; ++$i) {
            if ($i === $fromEnd || ord($from[$i]) === 47/*/*/) {
                if ($out === '') {
                    $out = '..';
                } else {
                    $out .= '/..';
                }
            }
        }
        // Lastly, append the rest of the destination (`$to`) path that comes after the common path parts
        if ($out !== '') {
            return $out.substr($to, $toStart + $lastCommonSep);
        } else {
            $toStart += $lastCommonSep;
            if (ord($to[$toStart]) === 47/*/*/) {
                ++$toStart;
            }

            return substr($to, $toStart);
        }
    }

    /**
     * Bare port of path.dirname.
     *
     * @param string $path
     *
     * @throws \Exception
     *
     * @return string
     */
    public static function dirname($path)
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return static::win32_dirname($path);
        } else {
            return static::posix_dirname($path);
        }
    }

    /**
     * Bare port of path.win32.dirname.
     *
     * @param string $path
     *
     * @throws \Exception
     *
     * @return string
     */
    protected static function win32_dirname($path)
    {
        if (!is_string($path)) {
            throw new \Exception('Path must be a string. Received '.json_encode($path));
        }
        $len = strlen($path);
        if ($len === 0) {
            return '.';
        }
        $rootEnd = -1;
        $end = -1;
        $matchedSlash = true;
        $offset = 0;
        $code = ord($path[0]);

        // Try to match a root
        if ($len > 1) {
            if ($code === 47/*/*/ || $code === 92/*\*/) {
                // Possible UNC root
                $rootEnd = $offset = 1;
                $code = ord($path[1]);
                if ($code === 47/*/*/ || $code === 92/*\*/) {
                    // Matched double path separator at beginning
                    $j = 2;
                    $last = $j;
                    // Match 1 or more non-path separators
                    for (; $j < $len; ++$j) {
                        $code = ord($path[$j]);
                        if ($code === 47/*/*/ || $code === 92/*\*/) {
                            break;
                        }
                    }
                    if ($j < $len && $j !== $last) {
                        // Matched!
                        $last = $j;
                        // Match 1 or more path separators
                        for (; $j < $len; ++$j) {
                            $code = ord($path[$j]);
                            if ($code !== 47/*/*/ && $code !== 92/*\*/) {
                                break;
                            }
                        }
                        if ($j < $len && $j !== $last) {
                            // Matched!
                            $last = $j;
                            // Match 1 or more non-path separators
                            for (; $j < $len; ++$j) {
                                $code = ord($path[$j]);
                                if ($code === 47/*/*/ || $code === 92/*\*/) {
                                    break;
                                }
                            }
                            if ($j === $len) {
                                // We matched a UNC root only
                                return $path;
                            }
                            if ($j !== $last) {
                                // We matched a UNC root with leftovers
                                // Offset by 1 to include the separator after the UNC root to
                                // treat it as a "normal root" on top of a (UNC) root
                                $rootEnd = $offset = $j + 1;
                            }
                        }
                    }
                }
            } elseif (($code >= 65/*A*/ && $code <= 90/*Z*/) || ($code >= 97/*a*/ && $code <= 122/*z*/)) {
                // Possible device root
                $code = ord($path[1]);
                if (ord($path[1]) === 58/*:*/) {
                    $rootEnd = $offset = 2;
                    if ($len > 2) {
                        $code = ord($path[2]);
                        if ($code === 47/*/*/ || $code === 92/*\*/) {
                            $rootEnd = $offset = 3;
                        }
                    }
                }
            }
        } elseif ($code === 47/*/*/ || $code === 92/*\*/) {
            return $path[0];
        }

        for ($i = $len - 1; $i >= $offset; --$i) {
            $code = ord($path[$i]);
            if ($code === 47/*/*/ || $code === 92/*\*/) {
                if (!$matchedSlash) {
                    $end = $i;
                    break;
                }
            } else {
                // We saw the first non-path separator
                $matchedSlash = false;
            }
        }

        if ($end === -1) {
            if ($rootEnd === -1) {
                return '.';
            } else {
                $end = $rootEnd;
            }
        }

        return substr($path, 0, $end);
    }

    /**
     * Bare port of path.posix.dirname.
     *
     * @param string $path
     *
     * @throws \Exception
     *
     * @return string
     */
    protected static function posix_dirname($path)
    {
        if (!is_string($path)) {
            throw new \Exception('Path must be a string. Received '.json_encode($path));
        }
        $len = strlen($path);
        if ($len === 0) {
            return '.';
        }
        $code = ord($path[0]);
        $hasRoot = ($code === 47/*/*/);
        $end = -1;
        $matchedSlash = true;
        for ($i = $len - 1; $i >= 1; --$i) {
            $code = ord($path[$i]);
            if ($code === 47/*/*/) {
                if (!$matchedSlash) {
                    $end = $i;
                    break;
                }
            } else {
                // We saw the first non-path separator
                $matchedSlash = false;
            }
        }

        if ($end === -1) {
            return $hasRoot ? '/' : '.';
        }
        if ($hasRoot && $end === 1) {
            return '//';
        }

        return substr($path, 0, $end);
    }

    /**
     * Bare port of path.join.
     */
    public static function join(/*$path1, $path2, ..., $pathN*/)
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return static::win32_join(func_get_args());
        } else {
            return static::posix_join(func_get_args());
        }
    }

    /**
     * Bare port of path.win32.join.
     */
    protected static function win32_join(array $arguments)
    {
        $argumentsLength = count($arguments);
        if ($argumentsLength === 0) {
            return '.';
        }
        $joined = null;
        $firstPart = null;
        for ($i = 0; $i < $argumentsLength; ++$i) {
            $arg = $arguments[$i];
            if (!is_string($arg)) {
                throw new \Exception('Path must be a string. Received '.json_encode($arg));
            }
            if ($arg !== '') {
                if ($joined === null) {
                    $joined = $firstPart = $arg;
                } else {
                    $joined .= '\\'.$arg;
                }
            }
        }
        if ($joined === null) {
            return '.';
        }
        // Make sure that the joined path doesn't start with two slashes, because normalize() will mistake it for an UNC path then.

        // This step is skipped when it is very clear that the user actually intended to point at an UNC path.
        // This is assumed when the first non-empty string arguments starts with exactly two slashes followed by at least one more non-slash character.

        // Note that for normalize() to treat a path as an UNC path it needs to have at least 2 components, so we don't filter for that here.
        // This means that the user can use join to construct UNC paths from a server name and a share name; for example:
        //   path.join('//server', 'share') -> '\\\\server\\share\\')
        // var firstPart = paths[0];
        $needsReplace = true;
        $slashCount = 0;
        $code = ord($firstPart[0]);
        if ($code === 47/*/*/ || $code === 92/*\*/) {
            ++$slashCount;
            $firstLen = strlen($firstPart);
            if ($firstLen > 1) {
                $code = ord($firstPart[1]);
                if ($code === 47/*/*/ || $code === 92/*\*/) {
                    ++$slashCount;
                    if ($firstLen > 2) {
                        $code = ord($firstPart[2]);
                        if ($code === 47/*/*/ || $code === 92/*\*/) {
                            ++$slashCount;
                        } else {
                            // We matched a UNC path in the first part
                            $needsReplace = false;
                        }
                    }
                }
            }
        }
        if ($needsReplace) {
            // Find any more consecutive slashes we need to replace
            for (; $slashCount < strlen($joined); ++$slashCount) {
                $code = ord($joined[$slashCount]);
                if ($code !== 47/*/*/ && $code !== 92/*\*/) {
                    break;
                }
            }
            // Replace the slashes if needed
            if ($slashCount >= 2) {
                $joined = '\\'.substr($joined, $slashCount);
            }
        }

        return static::win32_normalize($joined);
    }

    /**
     * Bare port of path.posix.join.
     */
    protected static function posix_join(array $arguments)
    {
        $argumentsLength = count($arguments);
        if ($argumentsLength === 0) {
            return '.';
        }
        $joined = null;
        for ($i = 0; $i < $argumentsLength; ++$i) {
            $arg = $arguments[$i];
            if (!is_string($arg)) {
                throw new \Exception('Path must be a string. Received '.json_encode($arg));
            }
            if ($arg !== '') {
                if ($joined === null) {
                    $joined = $arg;
                } else {
                    $joined .= '/'.$arg;
                }
            }
        }
        if ($joined === null) {
            return '.';
        }

        return static::posix_normalize($joined);
    }

    /**
     * Bare port of path.normalize.
     *
     * @param string $path
     *
     * @return string
     *
     * throws \Exception
     */
    public static function normalize($path)
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return static::win32_normalize($path);
        } else {
            return static::posix_normalize($path);
        }
    }

    /**
     * Bare port of path.win32.normalize.
     *
     * @param string $path
     *
     * @return string
     *
     * throws \Exception
     */
    protected static function win32_normalize($path)
    {
        if (!is_string($path)) {
            throw new \Exception('Path must be a string. Received '.json_encode($path));
        }

        $len = strlen($path);
        if ($len === 0) {
            return '.';
        }
        $rootEnd = 0;
        $code = ord($path[0]);
        $device = null;
        $isAbsolute = false;
        // Try to match a root
        if ($len > 1) {
            if ($code === 47/*/*/ || $code === 92/*\*/) {
                // Possible UNC root

                // If we started with a separator, we know we at least have an absolute path of some kind (UNC or otherwise)
                $isAbsolute = true;

                $code = ord($path[1]);
                if ($code === 47/*/*/ || $code === 92/*\*/) {
                    // Matched double path separator at beginning
                    $j = 2;
                    $last = $j;
                    // Match 1 or more non-path separators
                    for (; $j < $len; ++$j) {
                        $code = ord($path[$j]);
                        if ($code === 47/*/*/ || $code === 92/*\*/) {
                            break;
                        }
                    }
                    if ($j < $len && $j !== $last) {
                        $firstPart = substr($path, $last, $j - $last);
                        // Matched!
                        $last = $j;
                        // Match 1 or more path separators
                        for (; $j < $len; ++$j) {
                            $code = ord($path[$j]);
                            if ($code !== 47/*/*/ && $code !== 92/*\*/) {
                                break;
                            }
                        }
                        if ($j < $len && $j !== $last) {
                            // Matched!
                            $last = $j;
                            // Match 1 or more non-path separators
                            for (; $j < $len; ++$j) {
                                $code = ord($path[$j]);
                                if ($code === 47/*/*/ || $code === 92/*\*/) {
                                    break;
                                }
                            }
                            if ($j === $len) {
                                // We matched a UNC root only
                                // Return the normalized version of the UNC root since there is nothing left to process

                                return '\\\\'.$firstPart.'\\'.substr($path, $last).'\\';
                            } elseif ($j !== $last) {
                                // We matched a UNC root with leftovers

                                $device = '\\\\'.$firstPart.'\\'.substr($path, $last, $j - $last);
                                $rootEnd = $j;
                            }
                        }
                    }
                } else {
                    $rootEnd = 1;
                }
            } elseif (($code >= 65/*A*/ && $code <= 90/*Z*/) || ($code >= 97/*a*/ && $code <= 122/*z*/)) {
                // Possible device root

                $code = ord($path[1]);
                if (ord($path[1]) === 58/*:*/) {
                    $device = substr($path, 0, 2);
                    $rootEnd = 2;
                    if ($len > 2) {
                        $code = ord($path[2]);
                        if ($code === 47/*/*/ || $code === 92/*\*/) {
                            // Treat separator following drive name as an absolute path indicator
                            $isAbsolute = true;
                            $rootEnd = 3;
                        }
                    }
                }
            }
        } elseif ($code === 47/*/*/ || $code === 92/*\*/) {
            // `path` contains just a path separator, exit early to avoid unnecessary work
            return '\\';
        }

        $code = ord($path[$len - 1]);
        $trailingSeparator = ($code === 47/*/*/ || $code === 92/*\*/);
        if ($rootEnd < $len) {
            $tail = static::normalizeStringWin32(substr($path, $rootEnd), !$isAbsolute);
        } else {
            $tail = '';
        }
        if ($tail === '' && !$isAbsolute) {
            $tail = '.';
        }
        if ($tail !== '' && $trailingSeparator) {
            $tail .= '\\';
        }
        if ($device === null) {
            if ($isAbsolute) {
                if ($tail !== '') {
                    return '\\'.$tail;
                } else {
                    return '\\';
                }
            } elseif ($tail !== '') {
                return $tail;
            } else {
                return '';
            }
        } else {
            if ($isAbsolute) {
                if ($tail !== '') {
                    return $device.'\\'.$tail;
                } else {
                    return $device.'\\';
                }
            } elseif ($tail !== '') {
                return $device.$tail;
            } else {
                return $device;
            }
        }
    }

    /**
     * Bare port of path.posix.normalize.
     *
     * @param string $path
     *
     * @return string
     *
     * throws \Exception
     */
    protected static function posix_normalize($path)
    {
        if (!is_string($path)) {
            throw new \Exception('Path must be a string. Received '.json_encode($path));
        }
        if ($path === '') {
            return '.';
        }
        $isAbsolute = ord($path[0]) === 47/*/*/;
        $trailingSeparator = ord($path[strlen($path) - 1]) === 47/*/*/;

        // Normalize the path
        $path = static::normalizeStringPosix($path, !$isAbsolute);

        if ($path === '' && !$isAbsolute) {
            $path = '.';
        }
        if ($path !== '' && $trailingSeparator) {
            $path .= '/';
        }
        if ($isAbsolute) {
            return '/'.$path;
        }

        return $path;
    }
}
