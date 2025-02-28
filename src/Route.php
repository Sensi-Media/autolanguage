<?php

namespace Sensi\Autolanguage;

use DomainException;
use Laminas\Diactoros\Response\RedirectResponse;

/**
 * Autolanguage router.
 *
 * Designed to work with Monolyth\Reroute, e.g.:
 *
 * <code>
 * <?php
 *
 * $router->when('/', new Route($config));
 *
 * </code>
 *
 * Can also be used with other routing systems, as long as you `__invoke` at the
 * correct moment and properly handle the response.
 */
class Route
{
    /**
     * Hash of default options.
     *
     * @var array
     */
    private $defaults = [
        /**
         * If set, the language for the currently logged in user.
         *
         * @var string
         */
        'user' => null,

        /**
         * An array of allowed languages.
         *
         * @var array
         */
        'allowed' => ['en'],

        /**
         * If set, the fallback language if no allowed language could be set.
         * Defaults to English, set to null to use the first option in $allowed
         * instead. Use a callable to forward processing to a custom function
         * (e.g. a "please pick a language" page).
         *
         * @var string|callable
         */
        'fallback' => 'en',

        /**
         * The URL template to inject the found language into. Uses :language as
         * a placeholder (simple str_replace).
         *
         * @var string
         */
        'template' => '/:language/',

        /**
         * Optional name of cookie containing previously selected language.
         *
         * @var string
         */
        'cookie' => null,

        /**
         * Optional name of session variable containing previously selected
         * language.
         *
         * @var string
         */
        'session' => null,
    ];

    /** @var array */
    private static $config = [];

    /**
     * Constructor. Optionally pass a hash of options. If an option is missing,
     * the corresponding value from the defaults is used.
     *
     * @param array $config Optional configuration, see defaults.
     * @return void
     */
    public function __construct(array $config = [])
    {
        self::$config = $config + $this->defaults;
    }

    /**
     * Invoker. This is designed to work with Monolyth\Reroute, but could also
     * be called manually for other routing systems.
     *
     * @return Laminas\Diactoros\Response\RedirectResponse
     * @throws DomainException if not valid language could be determined.
     */
    public function __invoke() : RedirectResponse
    {
        if (self::$config['user']) {
            $language = self::$config['user'];
        } elseif (self::$config['cookie']
            && isset($_COOKIE[self::$config['cookie']])
            && in_array($_COOKIE[self::$config['cookie']], self::$config['allowed'])
        ) {
            $language = $_COOKIE[self::$config['cookie']];
        } elseif (self::$config['session'] && isset($_SESSION[self::$config['session']])) {
            $language = $_SESSION[self::$config['session']];
        } else {
            $language = self::getPreferredLanguage();
        }
        if (!isset($language)) {
            if (isset(self::$config['fallback'])) {
                if (is_callable(self::$config['fallback'])) {
                    return self::$config['fallback'];
                } else {
                    $language = self::$config['fallback'];
                }
            } else {
                throw new DomainException("Sorry, couldn't find a valid language to redirect to.");
            }
        }
        $url = str_replace(':language', $language, self::$config['template']);
        return new RedirectResponse($url);
    }

    /**
     * Guesstimate preferred language based on browser info.
     *
     * @return string|null Language string, or null if nothing could be found.
     */
    public static function getPreferredLanguage() :? string
    {
        $options = [];
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $parts = preg_split('@,\s*@', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
            foreach ($parts as $part) {
                $parts = explode(';', $part);
                $code = strtolower(array_shift($parts));
                $code = preg_replace('/-[a-z]{2,}$/', '', $code);
                if (preg_match('@;q=(.*?)$@', $part, $match)) {
                    $weight = $match[1];
                } else {
                    $weight = 1;
                }
                $options[$code] = $options[$code] ?? 0;
                $options[$code] += $weight;
            }
            asort($options);
        }
        foreach (array_reverse($options) as $lan => $weight) {
            if (in_array($lan, self::$config['allowed'])) {
                return $lan;
            }
        }
        return null;
    }
}

