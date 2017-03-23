<?php

namespace Sensi\Autolanguage;

use DomainException;
use Zend\Diactoros\Response\RedirectResponse;

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
     * @var array Hash of default options.
     */
    private $defaults = [
        /**
         * @var string
         * If set, the language for the currently logged in user.
         */
        'user' => null,

        /**
         * @var array
         * An array of allowed languages.
         */
        'allowed' => ['en'],

        /**
         * @var string|callable
         * If set, the fallback language if no allowed language could be set.
         * Defaults to English, set to null to use the first option in $allowed
         * instead. Use a callable to forward processing to a custom function
         * (e.g. a "please pick a language" page).
         */
        'fallback' => 'en',

        /**
         * @var string
         * The URL template to inject the found language into. Uses :language as
         * a placeholder (simple str_replace).
         */
        'template' => '/:language/',

        /**
         * @var string
         * Optional name of cookie containing previously selected language.
         */
        'cookie' => null,

        /**
         * @var string
         * Optional name of session variable containing previously selected
         * language.
         */
        'session' => null,
    ];

    private static $config = [];

    /**
     * Constructor. Optionally pass a hash of options. If an option is missing,
     * the corresponding value from the defaults is used.
     */
    public function __construct(array $config = [])
    {
        self::$config = $config + $this->defaults;
    }

    /**
     * Invoker. This is designed to work with Monolyth\Reroute, but could also
     * be called manually for other routing systems.
     *
     * @return Zend\Diactoros\Response\RedirectResponse
     * @throws DomainException if not valid language could be determined.
     */
    public function __invoke()
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

    public static function getPreferredLanguage()
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

