<?php

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

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
 * @subpackage UnitTests
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */

require_once 'Zend/Registry.php';
require_once 'Zend/View/Twig.php';

/**
 * @category   Zend
 * @package    Zend_View
 * @subpackage UnitTests
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @group      Zend_View
 */
class Zend_View_TwigTest extends TestCase
{
    /**
     * @var Zend_View_Twig
     */
    protected $view;

    protected function set_up()
    {
        if (!class_exists('Twig\Environment')) {
            $this->markTestSkipped('twig/twig is not installed');
        }

        // form helpers depend on the doctype kept in the registry
        Zend_Registry::_unsetInstance();

        $this->view = new Zend_View_Twig();
        $this->view->setScriptPath(__DIR__ . '/_files/twig');
    }

    public function testRendersTwigTemplateWithViewVariables()
    {
        $this->view->title = 'Hello';
        $this->assertSame("<h1>Hello</h1>\n", $this->view->render('hello.twig'));
    }

    public function testRendersPhtmlScripts()
    {
        $this->view->title = '<Hello>';
        $this->assertSame('&lt;Hello&gt;', $this->view->render('php.phtml'));
    }

    public function testFallsBackToTwigTemplateWhenPhtmlScriptIsMissing()
    {
        $this->view->title = 'Hello';
        $this->assertSame("<h1>Hello</h1>\n", $this->view->render('hello.phtml'));
    }

    public function testPrefersExistingPhtmlScript()
    {
        $this->view->title = 'Hello';
        $this->assertSame("<h1>Hello</h1>\n", $this->view->render('mixed.phtml'));
    }

    public function testMissingScriptReportsRequestedName()
    {
        $this->expectException(Zend_View_Exception::class);
        $this->expectExceptionMessage("script 'missing.phtml' not found");
        $this->view->render('missing.phtml');
    }

    public function testAutoescapesVariables()
    {
        $this->view->html = '<b>';
        $this->assertSame("<p>&lt;b&gt;</p><p><b></p>\n", $this->view->render('escape.twig'));
    }

    public function testExtendsAndIncludesResolveAgainstScriptPaths()
    {
        $this->view->title = 'T';
        $this->assertSame("child:T|item:T\n", $this->view->render('child.twig'));
    }

    public function testLaterScriptPathsTakePrecedence()
    {
        $this->view->addScriptPath(__DIR__ . '/_files/twig/partials');
        $this->view->title = 'T';
        $this->assertSame('item:T', $this->view->render('item.twig'));
    }

    public function testViewHelpersAreTwigFunctions()
    {
        $this->view->title = '<t>';
        $this->assertSame(
            '<input type="text" name="n" id="n" value="&lt;b&gt;">|&lt;t&gt;',
            $this->view->render('helpers.twig')
        );
    }

    public function testUnknownFunctionIsASyntaxError()
    {
        $this->view->setScriptPath($this->makeTemplateDir(['bad.twig' => '{{ noSuchHelper() }}']));
        $this->expectException(\Twig\Error\SyntaxError::class);
        $level = ob_get_level();
        try {
            $this->view->render('bad.twig');
        } finally {
            $this->assertSame($level, ob_get_level());
        }
    }

    public function testPartialHelperRendersTwigTemplateWithOwnVariables()
    {
        $this->view->title = 'outer';
        $this->assertSame('item:from partial', $this->view->render('partial.twig'));
    }

    public function testTwigTemplateCanRenderOtherScripts()
    {
        $this->view->title = 'T';
        $this->assertSame('item:T', $this->view->render('mixed.twig'));
    }

    public function testCustomTwigSuffix()
    {
        $this->view->setScriptPath($this->makeTemplateDir(['page.html.tpl' => '{{ title }}']));
        $this->view->setTwigSuffix('.tpl');
        $this->view->title = '<T>';
        $this->assertSame('&lt;T&gt;', $this->view->render('page.html.tpl'));
    }

    public function testTwigOptionsFromConfig()
    {
        $view = new Zend_View_Twig([
            'scriptPath'  => $this->makeTemplateDir(['raw.twig' => '{{ html }}']),
            'twigOptions' => ['autoescape' => false],
        ]);
        $view->html = '<b>';
        $this->assertSame('<b>', $view->render('raw.twig'));
    }

    public function testCustomEnvironmentGetsViewHelpers()
    {
        $twig = new \Twig\Environment(new \Twig\Loader\ArrayLoader([
            'page.twig' => '{{ formHidden("n", title) }}',
        ]));
        $view = new Zend_View_Twig(['twig' => $twig]);
        $view->setScriptPath($this->makeTemplateDir(['page.twig' => '']));
        $view->title = '<T>';
        $this->assertSame($twig, $view->getTwig());
        $this->assertSame('<input type="hidden" name="n" value="&lt;T&gt;" id="n">', $view->render('page.twig'));
    }

    /**
     * @param  array $templates
     * @return string
     */
    protected function makeTemplateDir(array $templates)
    {
        $dir = sys_get_temp_dir() . '/zf-view-twig-' . uniqid();
        mkdir($dir);
        foreach ($templates as $name => $content) {
            file_put_contents($dir . '/' . $name, $content);
        }
        register_shutdown_function(function () use ($dir, $templates) {
            foreach (array_keys($templates) as $name) {
                @unlink($dir . '/' . $name);
            }
            @rmdir($dir);
        });
        return $dir;
    }
}
