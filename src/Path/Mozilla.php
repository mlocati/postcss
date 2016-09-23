<?php

namespace PostCSS\Path;

/**
 * Bare port of functions from Mozilla source-map project.
 *
 * @link https://github.com/mozilla/source-map/blob/master/lib/util.js
 */
class Mozilla
{
    /**
     * Joins two paths/URLs.
     *
     * @param string $aRoot The root path or URL
     * @param string $ aPath The path or URL to be joined with the root
     *
     * @return string
     *
     * - If aPath is a URL or a data URI, aPath is returned, unless aPath is a
     *   scheme-relative URL: Then the scheme of aRoot, if any, is prepended
     *   first.
     * - Otherwise aPath is a path. If aRoot is a URL, then its path portion
     *   is updated with the result and aRoot is returned. Otherwise the result
     *   is returned.
     *   - If aPath is absolute, the result is aPath.
     *   - Otherwise the two paths are joined with a slash.
     * - Joining for example 'http://' and 'www.example.com' is also supported
     */
    public static function join($aRoot, $aPath/*, ...*/)
    {
        if (((string) $aRoot) === '') {
            $aRoot = '.';
        }
        if (((string) $aPath) === '') {
            $aPath = '.';
        }
        $aPathUrl = static::urlParse($aPath);
        $aRootUrl = static::urlParse($aRoot);
        if ($aRootUrl !== null) {
            $aRoot = $aRootUrl['path'] ?: '/';
        }

        // `join(foo, '//www.example.org')`
        if ($aPathUrl !== null && !$aPathUrl['scheme']) {
            if ($aRootUrl !== null) {
                $aPathUrl['scheme'] = $aRootUrl['scheme'];
            }

            return static::urlGenerate($aPathUrl);
        }

        if ($aPathUrl !== null || preg_match('/^data:.+\,.+$/', $aPath)) {
            return $aPath;
        }

        // `join('http://', 'www.example.com')`
        if ($aRootUrl !== null && !$aRootUrl['host'] && !$aRootUrl['path']) {
            $aRootUrl['host'] = $aPath;

            return static::urlGenerate($aRootUrl);
        }

        $joined = ($aPath[0] === '/')
        ? $aPath
        : static::normalize(preg_replace('/\/+$/', '', $aRoot).'/'.$aPath);

        if ($aRootUrl !== null) {
            $aRootUrl['path'] = $joined;

            return static::urlGenerate($aRootUrl);
        }

        return $joined;
    }

    /**
     * Make a path relative to a URL or another path.
     *
     * @param string $aRoot The root path or URL
     * @param string $aPath The path or URL to be made relative to aRoot
     *
     * @return string
     */
    public static function relative($aRoot, $aPath)
    {
        if (((string) $aRoot) === '') {
            $aRoot = '.';
        }

        $aRoot = preg_replace('/\/$/', '', $aRoot);

        // It is possible for the path to be above the root. In this case, simply
        // checking whether the root is a prefix of the path won't work. Instead, we
        // need to remove components from the root one by one, until either we find
        // a prefix that fits, or we run out of components to remove.
        $level = 0;

        while (strpos($aPath, $aRoot.'/') !== 0) {
            $index = strrpos($aRoot, '/');
            if ($index === false) {
                return $aPath;
            }
            // If the only part of the root that is left is the scheme (i.e. http://,
            // file:///, etc.), one or more slashes (/), or simply nothing at all, we
            // have exhausted all components, so the path is not relative to the root.
            $aRoot = substr($aRoot, 0, $index);
            if (preg_match('/^([^\/]+:\/)?\/*$/', $aRoot)) {
                return $aPath;
            }

            ++$level;
        }

        return str_repeat('../', $level + 1).substr($aPath, strlen($aRoot + 1));
    }

    /**
     * @param string $aUrl
     *
     * @return null|array
     */
    public static function urlParse($aUrl)
    {
        if (!preg_match('/^(?:([\w+\-.]+):)?\/\/(?:(\w+:\w+)@)?([\w.]*)(?::(\d+))?(\S*)$/', $aUrl, $match)) {
            return null;
        }

        return [
            'scheme' => $match[1],
            'auth' => $match[2],
            'host' => $match[3],
            'port' => $match[4],
            'path' => $match[5],
        ];
    }

    /**
     * @param array $aParsedUrl
     *
     * @return string
     */
    public static function urlGenerate(array $aParsedUrl)
    {
        $url = '';
        if ($aParsedUrl['scheme'] !== '') {
            $url .= $aParsedUrl['scheme'].':';
        }
        $url .= '//';
        if ($aParsedUrl['auth'] !== '') {
            $url .= $aParsedUrl['auth'].'@';
        }
        if ($aParsedUrl['host'] !== '') {
            $url .= $aParsedUrl['host'];
        }
        if ($aParsedUrl['port'] !== '') {
            $url .= ':'.$aParsedUrl['port'];
        }
        if ($aParsedUrl['path'] !== '') {
            $url .= $aParsedUrl['path'];
        }

        return $url;
    }

    /**
     * Normalizes a path, or the path portion of a URL:.
     *
     * - Replaces consecutive slashes with one slash.
     * - Removes unnecessary '.' parts.
     * - Removes unnecessary '<dir>/..' parts.
     *
     * @param string $aPath The path or url to normalize
     *
     * @return string
     */
    public static function normalize($aPath)
    {
        $path = $aPath;
        $url = static::urlParse($aPath);
        if ($url !== null) {
            if ($url['path'] === '') {
                return $aPath;
            }
            $path = $url['path'];
        }
        $isAbsolute = static::isAbsolute($path);

        $parts = preg_split('/\/+/', $path);
        for ($up = 0, $i = count($parts) - 1; $i >= 0; --$i) {
            $part = $parts[$i];
            if ($part === '.') {
                array_splice($parts, $i, 1);
            } elseif ($part === '..') {
                ++$up;
            } elseif ($up > 0) {
                if ($part === '') {
                    // The first part is blank if the path is absolute. Trying to go
                    // above the root is a no-op. Therefore we can remove all '..' parts
                    // directly after the root.
                    array_splice($parts, $i + 1, $up);
                    $up = 0;
                } else {
                    array_splice($parts, $i, 2);
                    --$up;
                }
            }
        }
        $path = implode('/', $parts);

        if ($path === '') {
            $path = $isAbsolute ? '/' : '.';
        }

        if ($url !== null) {
            $url['path'] = $path;

            return static::urlGenerate($url);
        }

        return $path;
    }

    public static function isAbsolute($aPath)
    {
        return (isset($aPath[0]) && ($aPath[0] === '/' || preg_match('/^(?:([\w+\-.]+):)?\/\/(?:(\w+:\w+)@)?([\w.]*)(?::(\d+))?(\S*)$/', $aPath))) ? true : false;
    }
}
