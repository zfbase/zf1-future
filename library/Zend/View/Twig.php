<?php
/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_View
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id$
 */

/**
 * @see Zend_View
 */
require_once 'Zend/View.php';

/**
 * View that renders Twig templates in addition to PHP (.phtml) view scripts.
 *
 * Scripts ending with the Twig suffix (".twig" by default) are rendered with
 * Twig; all other scripts are included as regular PHP view scripts. When a
 * ".phtml" script is requested but does not exist, a ".twig" script with the
 * same name is used instead, so the ViewRenderer action helper and
 * Zend_Layout pick up Twig templates without changing their view suffix.
 *
 * Inside Twig templates:
 * - view variables are available as top-level variables;
 * - the view object itself is available as "view";
 * - every view helper can be called as a function, e.g. {{ url({...}) }} or
 *   {{ headTitle() }}; helper output is not escaped again;
 * - templates are looked up in the view script paths, so {% extends %} and
 *   {% include %} take names relative to a script path.
 *
 * Requires twig/twig (^2.7 || ^3.0).
 *
 * @category  Zend
 * @package   Zend_View
 * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd     New BSD License
 */
class Zend_View_Twig extends Zend_View
{
    /**
     * Twig environment
     * @var \Twig\Environment|null
     */
    protected $_twig = null;

    /**
     * Options for the Twig environment created by {@link getTwig()}
     * @var array
     */
    protected $_twigOptions = [];

    /**
     * Loader created by this view, kept in sync with the script paths
     * @var \Twig\Loader\FilesystemLoader|null
     */
    protected $_twigLoader = null;

    /**
     * Filename suffix of Twig templates
     * @var string
     */
    protected $_twigSuffix = 'twig';

    /**
     * Constructor
     *
     * Additional config keys:
     * - twig: a \Twig\Environment instance to use
     * - twigOptions: options for the \Twig\Environment created by the view
     * - twigSuffix: filename suffix of Twig templates
     *
     * @param  array $config
     * @return void
     */
    public function __construct($config = [])
    {
        if (array_key_exists('twig', $config)) {
            $this->setTwig($config['twig']);
        }

        if (array_key_exists('twigOptions', $config)) {
            $this->setTwigOptions($config['twigOptions']);
        }

        if (array_key_exists('twigSuffix', $config)) {
            $this->setTwigSuffix($config['twigSuffix']);
        }

        parent::__construct($config);
    }

    /**
     * Set the Twig environment
     *
     * View helpers are registered as Twig functions on the environment. If
     * its loader is not a \Twig\Loader\FilesystemLoader created by this view,
     * the loader is responsible for resolving template names relative to the
     * view script paths.
     *
     * @param  \Twig\Environment $twig
     * @return Zend_View_Twig
     */
    public function setTwig(\Twig\Environment $twig)
    {
        $this->_twig = $twig;
        $this->_registerHelpers($twig);
        return $this;
    }

    /**
     * Retrieve the Twig environment, creating it if necessary
     *
     * @return \Twig\Environment
     */
    public function getTwig()
    {
        if (null === $this->_twig) {
            if (!class_exists('Twig\Environment')) {
                require_once 'Zend/View/Exception.php';
                $e = new Zend_View_Exception('Twig is not installed; install twig/twig to render Twig templates');
                $e->setView($this);
                throw $e;
            }

            $this->_twigLoader = new \Twig\Loader\FilesystemLoader();
            $options = array_merge(
                ['charset' => $this->getEncoding()],
                $this->_twigOptions
            );
            $this->setTwig(new \Twig\Environment($this->_twigLoader, $options));
        }

        return $this->_twig;
    }

    /**
     * Set options for the Twig environment created by {@link getTwig()}
     *
     * Has no effect once the environment has been created.
     *
     * @param  array $options
     * @return Zend_View_Twig
     */
    public function setTwigOptions(array $options)
    {
        $this->_twigOptions = $options;
        return $this;
    }

    /**
     * Get options for the Twig environment
     *
     * @return array
     */
    public function getTwigOptions()
    {
        return $this->_twigOptions;
    }

    /**
     * Set filename suffix of Twig templates
     *
     * @param  string $suffix
     * @return Zend_View_Twig
     */
    public function setTwigSuffix($suffix)
    {
        $this->_twigSuffix = ltrim((string) $suffix, '.');
        return $this;
    }

    /**
     * Get filename suffix of Twig templates
     *
     * @return string
     */
    public function getTwigSuffix()
    {
        return $this->_twigSuffix;
    }

    /**
     * Whether the given script is a Twig template
     *
     * @param  string $name
     * @return bool
     */
    public function isTwigScript($name)
    {
        $suffix = '.' . $this->_twigSuffix;
        return substr($name, -strlen($suffix)) === $suffix;
    }

    /**
     * Processes a view script and returns the output
     *
     * Discards output buffers left open when rendering fails.
     *
     * @param  string $name The script name to process.
     * @return string The script output.
     */
    public function render($name)
    {
        $level = ob_get_level();
        try {
            return parent::render($name);
        } catch (Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw $e;
        }
    }

    /**
     * Finds a view script, falling back from ".phtml" to the Twig suffix
     *
     * @param  string $name
     * @return string
     */
    protected function _script($name)
    {
        try {
            return parent::_script($name);
        } catch (Zend_View_Exception $e) {
            if ('.phtml' !== substr($name, -6)) {
                throw $e;
            }

            try {
                return parent::_script(substr($name, 0, -5) . $this->_twigSuffix);
            } catch (Zend_View_Exception $twigException) {
                throw $e;
            }
        }
    }

    /**
     * Renders a Twig template, or includes a PHP view script
     *
     * Takes the view script file name as its only argument.
     */
    protected function _run()
    {
        $file = func_get_arg(0);
        if (!$this->isTwigScript($file)) {
            parent::_run($file);
            return;
        }

        $twig = $this->getTwig();
        $paths = $this->getScriptPaths();
        if (null !== $this->_twigLoader && $twig->getLoader() === $this->_twigLoader) {
            $this->_twigLoader->setPaths(array_map(function ($path) {
                return rtrim($path, '/\\');
            }, $paths));
        }

        $context = $this->getVars();
        $context['view'] = $this;

        echo $twig->render($this->_templateName($file, $paths), $context);
    }

    /**
     * Get template name relative to the script path containing it
     *
     * @param  string $file
     * @param  array  $paths
     * @return string
     */
    protected function _templateName($file, array $paths)
    {
        foreach ($paths as $path) {
            if (0 === strpos($file, $path)) {
                return substr($file, strlen($path));
            }
        }

        return $file;
    }

    /**
     * Expose view helpers as Twig functions
     *
     * Functions are resolved lazily, so helpers added later via helper paths
     * or {@link registerHelper()} are available too. Helpers are called on
     * the view rendering the template (which differs from this view inside
     * partials, since those render a clone).
     *
     * @param  \Twig\Environment $twig
     * @return void
     */
    protected function _registerHelpers(\Twig\Environment $twig)
    {
        $view = $this;
        $twig->registerUndefinedFunctionCallback(function ($name) use ($view) {
            try {
                $view->getHelper($name);
            } catch (Zend_Loader_PluginLoader_Exception $e) {
                return false;
            }

            return new \Twig\TwigFunction(
                $name,
                function (array $context, ...$args) use ($view, $name) {
                    $current = (isset($context['view']) && $context['view'] instanceof Zend_View_Abstract)
                        ? $context['view']
                        : $view;
                    return $current->__call($name, $args);
                },
                ['needs_context' => true, 'is_safe' => ['html']]
            );
        });
    }
}
