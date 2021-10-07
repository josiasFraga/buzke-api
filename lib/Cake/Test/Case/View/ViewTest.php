<?php
/**
 * ViewTest file
 *
 * CakePHP(tm) Tests <https://book.cakephp.org/2.0/en/development/testing.html>
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://book.cakephp.org/2.0/en/development/testing.html CakePHP(tm) Tests
 * @package       Cake.Test.Case.View
 * @since         CakePHP(tm) v 1.2.0.4206
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */

App::uses('View', 'View');
App::uses('Helper', 'View');
App::uses('Controller', 'Controller');
App::uses('CacheHelper', 'View/Helper');
App::uses('HtmlHelper', 'View/Helper');
App::uses('ErrorHandler', 'Error');
App::uses('CakeEventManager', 'Event');
App::uses('CakeEventListener', 'Event');

/**
 * ViewPostsController class
 *
 * @package       Cake.Test.Case.View
 */
class ViewPostsController extends Controller {

/**
 * name property
 *
 * @var string
 */
	public $name = 'Posts';

/**
 * uses property
 *
 * @var mixed
 */
	public $uses = null;

/**
 * index method
 *
 * @return void
 */
	public function index() {
		$this->set(array(
			'testData' => 'Some test data',
			'test2' => 'more data',
			'test3' => 'even more data',
		));
	}

/**
 * nocache_tags_with_element method
 *
 * @return void
 */
	public function nocache_multiple_element() {
		$this->set('foo', 'this is foo var');
		$this->set('bar', 'this is bar var');
	}

}

/**
 * ThemePostsController class
 *
 * @package       Cake.Test.Case.View
 */
class ThemePostsController extends Controller {

	public $theme = null;

/**
 * index method
 *
 * @return void
 */
	public function index() {
		$this->set('testData', 'Some test data');
		$test2 = 'more data';
		$test3 = 'even more data';
		$this->set(compact('test2', 'test3'));
	}

}

/**
 * TestThemeView class
 *
 * @package       Cake.Test.Case.View
 */
class TestThemeView extends View {

/**
 * renderElement method
 *
 * @param string $name
 * @param array $params
 * @return string The given name
 */
	public function renderElement($name, $params = array()) {
		return $name;
	}

/**
 * getViewFileName method
 *
 * @param string $name Controller action to find template filename for
 * @return string Template filename
 */
	public function getViewFileName($name = null) {
		return $this->_getViewFileName($name);
	}

/**
 * getLayoutFileName method
 *
 * @param string $name The name of the layout to find.
 * @return string Filename for layout file (.ctp).
 */
	public function getLayoutFileName($name = null) {
		return $this->_getLayoutFileName($name);
	}

}

/**
 * TestView class
 *
 * @package       Cake.Test.Case.View
 */
class TestView extends View {

/**
 * getViewFileName method
 *
 * @param string $name Controller action to find template filename for
 * @return string Template filename
 */
	public function getViewFileName($name = null) {
		return $this->_getViewFileName($name);
	}

/**
 * getLayoutFileName method
 *
 * @param string $name The name of the layout to find.
 * @return string Filename for layout file (.ctp).
 */
	public function getLayoutFileName($name = null) {
		return $this->_getLayoutFileName($name);
	}

/**
 * paths method
 *
 * @param string $plugin Optional plugin name to scan for view files.
 * @param bool $cached Set to true to force a refresh of view paths.
 * @return array paths
 */
	public function paths($plugin = null, $cached = true) {
		return $this->_paths($plugin, $cached);
	}

/**
 * Test only function to return instance scripts.
 *
 * @return array Scripts
 */
	public function scripts() {
		return $this->_scripts;
	}

}

/**
 * TestBeforeAfterHelper class
 *
 * @package       Cake.Test.Case.View
 */
class TestBeforeAfterHelper extends Helper {

/**
 * property property
 *
 * @var string
 */
	public $property = '';

/**
 * beforeLayout method
 *
 * @param string $viewFile
 * @return void
 */
	public function beforeLayout($viewFile) {
		$this->property = 'Valuation';
	}

/**
 * afterLayout method
 *
 * @param string $layoutFile
 * @return void
 */
	public function afterLayout($layoutFile) {
		$this->_View->output .= 'modified in the afterlife';
	}

}

/**
 * TestObjectWithToString
 *
 * An object with the magic method __toString() for testing with view blocks.
 */
class TestObjectWithToString {

	public function __toString() {
		return "I'm ObjectWithToString";
	}

}

/**
 * TestObjectWithoutToString
 *
 * An object without the magic method __toString() for testing with view blocks.
 */
class TestObjectWithoutToString {
}

/**
 * TestViewEventListener
 *
 * An event listener to test cakePHP events
 */
class TestViewEventListener implements CakeEventListener {

/**
 * type of view before rendering has occurred
 *
 * @var string
 */
	public $beforeRenderViewType;

/**
 * type of view after rendering has occurred
 *
 * @var string
 */
	public $afterRenderViewType;

/**
 * implementedEvents method
 *
 * @return array
 */
	public function implementedEvents() {
		return array(
				'View.beforeRender' => 'beforeRender',
				'View.afterRender' => 'afterRender'
				);
	}

/**
 * beforeRender method
 *
 * @param CakeEvent $event the event being sent
 * @return void
 */
	public function beforeRender($event) {
		$this->beforeRenderViewType = $event->subject()->getCurrentType();
	}

/**
 * afterRender method
 *
 * @param CakeEvent $event the event being sent
 * @return void
 */
	public function afterRender($event) {
		$this->afterRenderViewType = $event->subject()->getCurrentType();
	}

}

/**
 * ViewTest class
 *
 * @package       Cake.Test.Case.View
 */
class ViewTest extends CakeTestCase {

/**
 * Fixtures used in this test.
 *
 * @var array
 */
	public $fixtures = array('core.user', 'core.post');

/**
 * setUp method
 *
 * @return void
 */
	public function setUp() {
		parent::setUp();

		$request = $this->getMock('CakeRequest');
		$this->Controller = new Controller($request);
		$this->PostsController = new ViewPostsController($request);
		$this->PostsController->viewPath = 'Posts';
		$this->PostsController->index();
		$this->View = new View($this->PostsController);

		$themeRequest = new CakeRequest('posts/index');
		$this->ThemeController = new Controller($themeRequest);
		$this->ThemePostsController = new ThemePostsController($themeRequest);
		$this->ThemePostsController->viewPath = 'posts';
		$this->ThemePostsController->index();
		$this->ThemeView = new View($this->ThemePostsController);

		App::build(array(
			'Plugin' => array(CAKE . 'Test' . DS . 'test_app' . DS . 'Plugin' . DS),
			'View' => array(CAKE . 'Test' . DS . 'test_app' . DS . 'View' . DS)
		), App::RESET);
		App::objects('plugins', null, false);

		CakePlugin::load(array('TestPlugin', 'TestPlugin', 'PluginJs'));
		Configure::write('debug', 2);
	}

/**
 * tearDown method
 *
 * @return void
 */
	public function tearDown() {
		parent::tearDown();
		CakePlugin::unload();
		unset($this->View);
		unset($this->PostsController);
		unset($this->Controller);
		unset($this->ThemeView);
		unset($this->ThemePostsController);
		unset($this->ThemeController);
	}

/**
 * Test getViewFileName method
 *
 * @return void
 */
	public function testGetTemplate() {
		$this->Controller->plugin = null;
		$this->Controller->name = 'Pages';
		$this->Controller->viewPath = 'Pages';
		$this->Controller->action = 'display';
		$this->Controller->params['pass'] = array('home');

		$ThemeView = new TestThemeView($this->Controller);
		$ThemeView->theme = 'test_theme';
		$expected = CAKE . 'Test' . DS . 'test_app' . DS . 'View' . DS . 'Pages' . DS . 'home.ctp';
		$result = $ThemeView->getViewFileName('home');
		$this->assertEquals($expected, $result);

		$expected = CAKE . 'Test' . DS . 'test_app' . DS . 'View' . DS . 'Themed' . DS . 'TestTheme' . DS . 'Posts' . DS . 'index.ctp';
		$result = $ThemeView->getViewFileName('/Posts/index');
		$this->assertEquals($expected, $result);

		$ThemeView->theme = 'TestTheme';
		$expected = CAKE . 'Test' . DS . 'test_app' . DS . 'View' . DS . 'Themed' . DS . 'TestTheme' . DS . 'Layouts' . DS . 'default.ctp';
		$result = $ThemeView->getLayoutFileName();
		$this->assertEquals($expected, $result);

		$ThemeView->layoutPath = 'rss';
		$expected = CAKE . 'Test' . DS . 'test_app' . DS . 'View' . DS . 'Layouts' . DS . 'rss' . DS . 'default.ctp';
		$result = $ThemeView->getLayoutFileName();
		$this->assertEquals($expected, $result);

		$ThemeView->layoutPath = 'Emails' . DS . 'html';
		$expected = CAKE . 'Test' . DS . 'test_app' . DS . 'View' . DS . 'Layouts' . DS . 'Emails' . DS . 'html' . DS . 'default.ctp';
		$result = $ThemeView->getLayoutFileName();
		$this->assertEquals($expected, $result);
	}

/**
 * Test that plugin files with absolute file paths are scoped
 * to the plugin and do now allow any file path.
 *
 * @expectedException MissingViewException
 * @return void
 */
	public function testPluginGetTemplateAbsoluteFail() {
		$this->Controller->viewPath = 'Pages';
		$this->Controller->action = 'display';
		$this->Controller->params['pass'] = array('home');

		$view = new TestThemeView($this->Controller);
		$expected = CAKE . 'Test' . DS . 'test_app' . DS . 'Plugin' . DS . 'Company' . DS . 'TestPluginThree' . DS . 'View' . DS . 'Pages' . DS . 'index.ctp';
		$result = $view->getViewFileName('Company/TestPluginThree./Pages/index');
		$this->assertPathEquals($expected, $result);

		$view->getViewFileName('Company/TestPluginThree./etc/passwd');
	}

/**
 * Test getLayoutFileName method on plugin
 *
 * @return void
 */
	public function testPluginGetTemplate() {
		$this->Controller->plugin = 'TestPlugin';
		$this->Controller->name = 'TestPlugin';
		$this->Controller->viewPath = 'Tests';
		$this->Controller->action = 'index';

		$View = new TestView($this->Controller);

		$expected = CakePlugin::path('TestPlugin') . 'View' . DS . 'Tests' . DS . 'index.ctp';
		$result = $View->getViewFileName('index');
		$this->assertEquals($expected, $result);

		$expected = CakePlugin::path('TestPlugin') . 'View' . DS . 'Layouts' . DS . 'default.ctp';
		$result = $View->getLayoutFileName();
		$this->assertEquals($expected, $result);
	}

/**
 * Test getViewFileName method on plugin
 *
 * @return void
 */
	public function testPluginThemedGetTemplate() {
		$this->Controller->plugin = 'TestPlugin';
		$this->Controller->name = 'TestPlugin';
		$this->Controller->viewPath = 'Tests';
		$this->Controller->action = 'index';
		$this->Controller->theme = 'TestTheme';

		$ThemeView = new TestThemeView($this->Controller);
		$expected = CAKE . 'Test' . DS . 'test_app' . DS . 'View' . DS . 'Themed' . DS . 'TestTheme' . DS . 'Plugin' . DS . 'TestPlugin' . DS . 'Tests' . DS . 'index.ctp';
		$result = $ThemeView->getViewFileName('index');
		$this->assertEquals($expected, $result);

		$expected = CAKE . 'Test' . DS . 'test_app' . DS . 'View' . DS . 'Themed' . DS . 'TestTheme' . DS . 'Plugin' . DS . 'TestPlugin' . DS . 'Layouts' . DS . 'plugin_default.ctp';
		$result = $ThemeView->getLayoutFileName('plugin_default');
		$this->assertEquals($expected, $result);

		$expected = CAKE . 'Test' . DS . 'test_app' . DS . 'View' . DS . 'Themed' . DS . 'TestTheme' . DS . 'Layouts' . DS . 'default.ctp';
		$result = $ThemeView->getLayoutFileName('default');
		$this->assertEquals($expected, $result);
	}

/**
 * Test that plugin/$plugin_name is only appended to the paths it should be.
 *
 * @return void
 */
	public function testPluginPathGeneration() {
		$this->Controller->plugin = 'TestPlugin';
		$this->Controller->name = 'TestPlugin';
		$this->Controller->viewPath = 'Tests';
		$this->Controller->action = 'index';

		$View = new TestView($this->Controller);
		$paths = $View->paths();
		$expected = array_merge(App::path('View'), App::core('View'), App::core('Console/Templates/skel/View'));
		$this->assertEquals($expected, $paths);

		$paths = $View->paths('TestPlugin');
		$pluginPath = CakePlugin::path('TestPlugin');
		$expected = array(
			CAKE . 'Test' . DS . 'test_app' . DS . 'View' . DS . 'Plugin' . DS . 'TestPlugin' . DS,
			$pluginPath . 'View' . DS,
			CAKE . 'Test' . DS . 'test_app' . DS . 'View' . DS,
			CAKE . 'View' . DS,
			CAKE . 'Console' . DS . 'Templates' . DS . 'skel' . DS . 'View' . DS
		);
		$this->assertEquals($expected, $paths);
	}

/**
 * Test that CamelCase'd plugins still find their view files.
 *
 * @return void
 */
	public function testCamelCasePluginGetTemplate() {
		$this->Controller->plugin = 'TestPlugin';
		$this->Controller->name = 'TestPlugin';
		$this->Controller->viewPath = 'Tests';
		$this->Controller->action = 'index';

		$View = new TestView($this->Controller);
		App::build(array(
			'Plugin' => array(CAKE . 'Test' . DS . 'test_app' . DS . 'Plugin' . DS),
			'View' => array(CAKE . 'Test' . DS . 'test_app' . DS . 'View' . DS)
		));

		$pluginPath = CakePlugin::path('TestPlugin');
		$expected = CAKE . 'Test' . DS . 'test_app' . DS . 'Plugin' . DS . 'TestPlugin' . DS . 'View' . DS . 'Tests' . DS . 'index.ctp';
		$result = $View->getViewFileName('index');
		$this->assertEquals($expected, $result);

		$expected = $pluginPath . 'View' . DS . 'Layouts' . DS . 'default.ctp';
		$result = $View->getLayoutFileName();
		$this->assertEquals($expected, $result);
	}

/**
 * Test getViewFileName method
 *
 * @return void
 */
	public function testGetViewFileNames() {
		$this->Controller->plugin = null;
		$this->Controller->name = 'Pages';
		$this->Controller->viewPath = 'Pages';
		$this->Controller->action = 'display';
		$this->Controller->params['pass'] = array('home');

		$View = new TestView($this->Controller);

		$expected = CAKE . 'Test' . DS . 'test_app' . DS . 'View' . DS . 'Pages' . DS . 'home.ctp';
		$result = $View->getViewFileName('home');
		$this->assertEquals($expected, $result);

		$expected = CAKE . 'Test' . DS . 'test_app' . DS . 'View' . DS . 'Posts' . DS . 'index.ctp';
		$result = $View->getViewFileName('/Posts/index');
		$this->assertEquals($expected, $result);

		$expected = CAKE . 'Test' . DS . 'test_app' . DS . 'View' . DS . 'Posts' . DS . 'index.ctp';
		$result = $View->getViewFileName('../Posts/index');
		$this->assertEquals($expected, $result);

		$expected = CAKE . 'Test' . DS . 'test_app' . DS . 'View' . DS . 'Pages' . DS . 'page.home.ctp';
		$result = $View->getViewFileName('page.home');
		$this->assertEquals($expected, $result, 'Should not ruin files with dots.');

		CakePlugin::load('TestPlugin');
		$expected = CAKE . 'Test' . DS . 'test_app' . DS . 'View' . DS . 'Pages' . DS . 'home.ctp';
		$result = $View->getViewFileName('TestPlugin.home');
		$this->assertEquals($expected, $result, 'Plugin is missing the view, cascade to app.');

		$View->viewPath = 'Tests';
		$expected = CAKE . 'Test' . DS . 'test_app' . DS . 'Plugin' . DS . 'TestPlugin' . DS . 'View' . DS . 'Tests' . DS . 'index.ctp';
		$result = $View->getViewFileName('TestPlugin.index');
		$this->assertEquals($expected, $result);
	}

/**
 * Test getting layout filenames
 *
 * @return void
 */
	public function testGetLayoutFileName() {
		$this->Controller->plugin = null;
		$this->Controller->name = 'Pages';
		$this->Controller->viewPath = 'Pages';
		$this->Controller->action = 'display';

		$View = new TestView($this->Controller);

		$expected = CAKE . 'Test' . DS . 'test_app' . DS . 'View' . DS . 'Layouts' . DS . 'default.ctp';
		$result = $View->getLayoutFileName();
		$this->assertEquals($expected, $result);

		$View->layoutPath = 'rss';
		$expected = CAKE . 'Test' . DS . 'test_app' . DS . 'View' . DS . 'Layouts' . DS . 'rss' . DS . 'default.ctp';
		$result = $View->getLayoutFileName();
		$this->assertEquals($expected, $result);

		$View->layoutPath = 'Emails' . DS . 'html';
		$expected = CAKE . 'Test' . DS . 'test_app' . DS . 'View' . DS . 'Layouts' . DS . 'Emails' . DS . 'html' . DS . 'default.ctp';
		$result = $View->getLayoutFileName();
		$this->assertEquals($expected, $result);
	}

/**
 * Test getting layout filenames for plugins.
 *
 * @return void
 */
	public function testGetLayoutFileNamePlugin() {
		$this->Controller->plugin = null;
		$this->Controller->name = 'Pages';
		$this->Controller->viewPath = 'Pages';
		$this->Controller->action = 'display';

		$View = new TestView($this->Controller);
		CakePlugin::load('TestPlugin');

		$expected = CAKE . 'Test' . DS . 'test_app' . DS . 'Plugin' . DS . 'TestPlugin' . DS . 'View' . DS . 'Layouts' . DS . 'default.ctp';
		$result = $View->getLayoutFileName('TestPlugin.default');
		$this->assertEquals($expected, $result);

		$View->plugin = 'TestPlugin';
		$expected = CAKE . 'Test' . DS . 'test_app' . DS . 'Plugin' . DS . 'TestPlugin' . DS . 'View' . DS . 'Layouts' . DS . 'default.ctp';
		$result = $View->getLayoutFileName('default');
		$this->assertEquals($expected, $result);
	}

/**
 * Test for missing views
 *
 * @expectedException MissingViewException
 * @return void
 */
	public function testMissingView() {
		$this->Controller->plugin = null;
		$this->Controller->name = 'Pages';
		$this->Controller->viewPath = 'Pages';
		$this->Controller->action = 'display';
		$this->Controller->params['pass'] = array('home');

		$View = new TestView($this->Controller);
		$View->getViewFileName('does_not_exist');
	}

/**
 * Test for missing theme views
 *
 * @expectedException MissingViewException
 * @return void
 */
	public function testMissingThemeView() {
		$this->ThemeController->plugin = null;
		$this->ThemeController->name = 'Pages';
		$this->ThemeController->viewPath = 'Pages';
		$this->ThemeController->action = 'display';
		$this->ThemeController->theme = 'my_theme';

		$this->ThemeController->params['pass'] = array('home');

		$View = new TestThemeView($this->ThemeController);
		$View->getViewFileName('does_not_exist');
	}

/**
 * Test for missing layouts
 *
 * @expectedException MissingLayoutException
 * @return void
 */
	public function testMissingLayout() {
		$this->Controller->plugin = null;
		$this->Controller->name = 'Posts';
		$this->Controller->viewPath = 'Posts';
		$this->Controller->layout = 'whatever';

		$View = new TestView($this->Controller);
		$View->getLayoutFileName();
	}

/**
 * Test for missing theme layouts
 *
 * @expectedException MissingLayoutException
 * @return void
 */
	public function testMissingThemeLayout() {
		$this->ThemeController->plugin = null;
		$this->ThemeController->name = 'Posts';
		$this->ThemeController->viewPath = 'posts';
		$this->ThemeController->layout = 'whatever';
		$this->ThemeController->theme = 'my_theme';

		$View = new TestThemeView($this->ThemeController);
		$View->getLayoutFileName();
	}

/**
 * Test viewVars method
 *
 * @return void
 */
	public function testViewVars() {
		$this->assertEquals(array('testData' => 'Some test data', 'test2' => 'more data', 'test3' => 'even more data'), $this->View->viewVars);
	}

/**
 * Test generation of UUIDs method
 *
 * @return void
 */
	public function testUUIDGeneration() {
		$result = $this->View->uuid('form', array('controller' => 'posts', 'action' => 'index'));
		$this->assertEquals('form5988016017', $result);
		$result = $this->View->uuid('form', array('controller' => 'posts', 'action' => 'index'));
		$this->assertEquals('formc3dc6be854', $result);
		$result = $this->View->uuid('form', array('controller' => 'posts', 'action' => 'index'));
		$this->assertEquals('form28f92cc87f', $result);
	}

/**
 * Test addInlineScripts method
 *
 * @return void
 */
	public function testAddInlineScripts() {
		$View = new TestView($this->Controller);
		$View->addScript('prototype.js');
		$View->addScript('prototype.js');
		$this->assertEquals(array('prototype.js'), $View->scripts());

		$View->addScript('mainEvent', 'Event.observe(window, "load", function() { doSomething(); }, true);');
		$this->assertEquals(array('prototype.js', 'mainEvent' => 'Event.observe(window, "load", function() { doSomething(); }, true);'), $View->scripts());
	}

/**
 * Test elementExists method
 *
 * @return void
 */
	public function testElementExists() {
		$result = $this->View->elementExists('test_element');
		$this->assertTrue($result);

		$result = $this->View->elementExists('TestPlugin.plugin_element');
		$this->assertTrue($result);

		$result = $this->View->elementExists('non_existent_element');
		$this->assertFalse($result);

		$result = $this->View->elementExists('TestPlugin.element');
		$this->assertFalse($result);

		$this->View->plugin = 'TestPlugin';
		$result = $this->View->elementExists('test_plugin_element');
		$this->assertTrue($result);
	}

/**
 * Test element method
 *
 * @return void
 */
	public function testElement() {
		$result = $this->View->element('test_element');
		$this->assertEquals('this is the test element', $result);

		$result = $this->View->element('plugin_element', array(), array('plugin' => 'TestPlugin'));
		$this->assertEquals('this is the plugin element using params[plugin]', $result);

		$result = $this->View->element('plugin_element', array(), array('plugin' => 'test_plugin'));
		$this->assertEquals('this is the plugin element using params[plugin]', $result);

		$result = $this->View->element('TestPlugin.plugin_element');
		$this->assertEquals('this is the plugin element using params[plugin]', $result);

		$this->View->plugin = 'TestPlugin';
		$result = $this->View->element('test_plugin_element');
		$this->assertEquals('this is the test set using View::$plugin plugin element', $result);
	}

/**
 * Test elementInexistent method
 *
 * @expectedException PHPUnit_Framework_Error_Notice
 * @return void
 */
	public function testElementInexistent() {
		$this->View->element('non_existent_element');
	}

/**
 * Test elementInexistent2 method
 *
 * @expectedException PHPUnit_Framework_Error_Notice
 * @return void
 */
	public function testElementInexistent2() {
		$this->View->element('TestPlugin.plugin_element', array(), array('plugin' => 'test_plugin'));
	}

/**
 * Test elementInexistent3 method
 *
 * @expectedException PHPUnit_Framework_Error_Notice
 * @return void
 */
	public function testElementInexistent3() {
		$this->View->element('test_plugin.plugin_element');
	}

/**
 * Test that elements can have callbacks
 *
 * @return void
 */
	public function testElementCallbacks() {
		$Helper = $this->getMock('Helper', array(), array($this->View), 'ElementCallbackMockHtmlHelper');
		$this->View->helpers = array('ElementCallbackMockHtml');
		$this->View->loadHelpers();

		$this->View->Helpers->set('ElementCallbackMockHtml', $Helper);
		$this->View->ElementCallbackMockHtml = $Helper;

		$this->View->ElementCallbackMockHtml->expects($this->at(0))->method('beforeRender');
		$this->View->ElementCallbackMockHtml->expects($this->at(1))->method('afterRender');

		$this->View->element('test_element', array(), array('callbacks' => true));
	}

/**
 * Test that additional element viewVars don't get overwritten with helpers.
 *
 * @return void
 */
	public function testElementParamsDontOverwriteHelpers() {
		$Controller = new ViewPostsController();
		$Controller->helpers = array('Form');

		$View = new View($Controller);
		$result = $View->element('type_check', array('form' => 'string'), array('callbacks' => true));
		$this->assertEquals('string', $result);

		$View->set('form', 'string');
		$result = $View->element('type_check', array(), array('callbacks' => true));
		$this->assertEquals('string', $result);
	}

/**
 * Test elementCacheHelperNoCache method
 *
 * @return void
 */
	public function testElementCacheHelperNoCache() {
		$Controller = new ViewPostsController();
		$View = new TestView($Controller);
		$View->loadHelpers();
		$result = $View->element('test_element', array('ram' => 'val', 'test' => array('foo', 'bar')));
		$this->assertEquals('this is the test element', $result);
	}

/**
 * Test elementCache method
 *
 * @return void
 */
	public function testElementCache() {
		Cache::drop('test_view');
		Cache::config('test_view', array(
			'engine' => 'File',
			'duration' => '+1 day',
			'path' => CACHE . 'views' . DS,
			'prefix' => ''
		));
		Cache::clear(true, 'test_view');

		$View = new TestView($this->PostsController);
		$View->elementCache = 'test_view';

		$result = $View->element('test_element', array(), array('cache' => true));
		$expected = 'this is the test element';
		$this->assertEquals($expected, $result);

		$result = Cache::read('element__test_element_cache_callbacks', 'test_view');
		$this->assertEquals($expected, $result);

		$result = $View->element('test_element', array('param' => 'one', 'foo' => 'two'), array('cache' => true));
		$this->assertEquals($expected, $result);

		$result = Cache::read('element__test_element_cache_callbacks_param_foo', 'test_view');
		$this->assertEquals($expected, $result);

		$View->element('test_element', array(
			'param' => 'one',
			'foo' => 'two'
		), array(
			'cache' => array('key' => 'custom_key')
		));
		$result = Cache::read('element_custom_key', 'test_view');
		$this->assertEquals($expected, $result);

		$View->elementCache = 'default';
		$View->element('test_element', array(
			'param' => 'one',
			'foo' => 'two'
		), array(
			'cache' => array('config' => 'test_view'),
		));
		$result = Cache::read('element__test_element_cache_callbacks_param_foo', 'test_view');
		$this->assertEquals($expected, $result);

		Cache::clear(true, 'test_view');
		Cache::drop('test_view');
	}

/**
 * Test element events
 *
 * @return void
 */
	public function testViewEvent() {
		$View = new View($this->PostsController);
		$View->autoLayout = false;
		$listener = new TestViewEventListener();

		$View->getEventManager()->attach($listener);

		$View->render('index');
		$this->assertEquals(View::TYPE_VIEW, $listener->beforeRenderViewType);
		$this->assertEquals(View::TYPE_VIEW, $listener->afterRenderViewType);

		$this->assertEquals($View->getCurrentType(), View::TYPE_VIEW);
		$View->element('test_element', array(), array('callbacks' => true));
		$this->assertEquals($View->getCurrentType(), View::TYPE_VIEW);

		$this->assertEquals(View::TYPE_ELEMENT, $listener->beforeRenderViewType);
		$this->assertEquals(View::TYPE_ELEMENT, $listener->afterRenderViewType);
	}

/**
 * Test __get allowing access to helpers.
 *
 * @return void
 */
	public function testMagicGet() {
		$View = new View($this->PostsController);
		$View->loadHelper('Html');
		$this->assertInstanceOf('HtmlHelper', $View->Html);
	}

/**
 * Test that ctp is used as a fallback file extension for elements
 *
 * @return void
 */
	public function testElementCtpFallback() {
		$View = new TestView($this->PostsController);
		$View->ext = '.missing';
		$element = 'test_element';
		$expected = 'this is the test element';
		$result = $View->element($element);

		$this->assertEquals($expected, $result);
	}

/**
 * Test loadHelpers method
 *
 * @return void
 */
	public function testLoadHelpers() {
		$View = new View($this->PostsController);

		$View->helpers = array('Html', 'Form');
		$View->loadHelpers();

		$this->assertInstanceOf('HtmlHelper', $View->Html, 'Object type is wrong.');
		$this->assertInstanceOf('FormHelper', $View->Form, 'Object type is wrong.');
	}

/**
 * Test lazy loading helpers
 *
 * @return void
 */
	public function testLazyLoadHelpers() {
		$View = new View($this->PostsController);

		$View->helpers = array();
		$this->assertInstanceOf('HtmlHelper', $View->Html, 'Object type is wrong.');
		$this->assertInstanceOf('FormHelper', $View->Form, 'Object type is wrong.');
	}

/**
 * Test the correct triggering of helper callbacks
 *
 * @return void
 */
	public function testHelperCallbackTriggering() {
		$View = new View($this->PostsController);
		$View->helpers = array();
		$View->Helpers = $this->getMock('HelperCollection', array('trigger'), array($View));

		$View->Helpers->expects($this->at(0))->method('trigger')
			->with(
				$this->logicalAnd(
					$this->isInstanceOf('CakeEvent'),
					$this->attributeEqualTo('_name', 'View.beforeRender'),
					$this->attributeEqualTo('_subject', $View)
				)
			);
		$View->Helpers->expects($this->at(1))->method('trigger')
			->with(
				$this->logicalAnd(
					$this->isInstanceOf('CakeEvent'),
					$this->attributeEqualTo('_name', 'View.beforeRenderFile'),
					$this->attributeEqualTo('_subject', $View)
				)
			);

		$View->Helpers->expects($this->at(2))->method('trigger')
			->with(
				$this->logicalAnd(
					$this->isInstanceOf('CakeEvent'),
					$this->attributeEqualTo('_name', 'View.afterRenderFile'),
					$this->attributeEqualTo('_subject', $View)
				)
			);
		$View->Helpers->expects($this->at(3))->method('trigger')
			->with(
				$this->logicalAnd(
					$this->isInstanceOf('CakeEvent'),
					$this->attributeEqualTo('_name', 'View.afterRender'),
					$this->attributeEqualTo('_subject', $View)
				)
			);

		$View->Helpers->expects($this->at(4))->method('trigger')
			->with(
				$this->logicalAnd(
					$this->isInstanceOf('CakeEvent'),
					$this->attributeEqualTo('_name', 'View.beforeLayout'),
					$this->attributeEqualTo('_subject', $View)
				)
			);

		$View->Helpers->expects($this->at(5))->method('trigger')
			->with(
				$this->logicalAnd(
					$this->isInstanceOf('CakeEvent'),
					$this->attributeEqualTo('_name', 'View.beforeRenderFile'),
					$this->attributeEqualTo('_subject', $View)
				)
			);

		$View->Helpers->expects($this->at(6))->method('trigger')
			->with(
				$this->logicalAnd(
					$this->isInstanceOf('CakeEvent'),
					$this->attributeEqualTo('_name', 'View.afterRenderFile'),
					$this->attributeEqualTo('_subject', $View)
				)
			);

		$View->Helpers->expects($this->at(7))->method('trigger')
			->with(
				$this->logicalAnd(
					$this->isInstanceOf('CakeEvent'),
					$this->attributeEqualTo('_name', 'View.afterLayout'),
					$this->attributeEqualTo('_subject', $View)
				)
			);

		$View->render('index');
	}

/**
 * Test beforeLayout method
 *
 * @return void
 */
	public function testBeforeLayout() {
		$this->PostsController->helpers = array('Session', 'TestBeforeAfter', 'Html');
		$View = new View($this->PostsController);
		$View->render('index');
		$this->assertEquals('Valuation', $View->Helpers->TestBeforeAfter->property);
	}

/**
 * Test afterLayout method
 *
 * @return void
 */
	public function testAfterLayout() {
		$this->PostsController->helpers = array('Session', 'TestBeforeAfter', 'Html');
		$this->PostsController->set('variable', 'values');

		$View = new View($this->PostsController);
		ClassRegistry::addObject('afterView', $View);

		$content = 'This is my view output';
		$result = $View->renderLayout($content, 'default');
		$this->assertRegExp('/modified in the afterlife/', $result);
		$this->assertRegExp('/This is my view output/', $result);
	}

/**
 * Test renderLoadHelper method
 *
 * @return void
 */
	public function testRenderLoadHelper() {
		$this->PostsController->helpers = array('Session', 'Html', 'Form', 'Number');
		$View = new TestView($this->PostsController);

		$result = $View->render('index', false);
		$this->assertEquals('posts index', $result);

		$attached = $View->Helpers->loaded();
		$this->assertEquals(array('Session', 'Html', 'Form', 'Number'), $attached);

		$this->PostsController->helpers = array('Html', 'Form', 'Number', 'TestPlugin.PluggedHelper');
		$View = new TestView($this->PostsController);

		$result = $View->render('index', false);
		$this->assertEquals('posts index', $result);

		$attached = $View->Helpers->loaded();
		$expected = array('Html', 'Form', 'Number', 'PluggedHelper');
		$this->assertEquals($expected, $attached, 'Attached helpers are wrong.');
	}

/**
 * Test render method
 *
 * @return void
 */
	public function testRender() {
		$View = new TestView($this->PostsController);
		$result = $View->render('index');

		$this->assertRegExp("/<meta http-equiv=\"Content-Type\" content=\"text\/html; charset=utf-8\" \/>\s*<title>/", $result);
		$this->assertRegExp("/<div id=\"content\">\s*posts index\s*<\/div>/", $result);
		$this->assertRegExp("/<div id=\"content\">\s*posts index\s*<\/div>/", $result);

		$this->assertTrue(isset($View->viewVars['content_for_layout']), 'content_for_layout should be a view var');
		$this->assertTrue(isset($View->viewVars['scripts_for_layout']), 'scripts_for_layout should be a view var');

		$this->PostsController->set('url', 'flash');
		$this->PostsController->set('message', 'yo what up');
		$this->PostsController->set('pause', 3);
		$this->PostsController->set('pageTitle', 'yo what up');

		$View = new TestView($this->PostsController);
		$result = $View->render(false, 'flash');

		$this->assertRegExp("/<title>yo what up<\/title>/", $result);
		$this->assertRegExp("/<p><a href=\"flash\">yo what up<\/a><\/p>/", $result);

		$this->assertNull($View->render(false, 'flash'));

		$this->PostsController->helpers = array('Session', 'Cache', 'Html');
		$this->PostsController->constructClasses();
		$this->PostsController->cacheAction = array('index' => 3600);
		$this->PostsController->request->params['action'] = 'index';
		Configure::write('Cache.check', true);

		$View = new TestView($this->PostsController);
		$result = $View->render('index');

		$this->assertRegExp("/<meta http-equiv=\"Content-Type\" content=\"text\/html; charset=utf-8\" \/>\s*<title>/", $result);
		$this->assertRegExp("/<div id=\"content\">\s*posts index\s*<\/div>/", $result);
		$this->assertRegExp("/<div id=\"content\">\s*posts index\s*<\/div>/", $result);
	}

/**
 * Test that View::$view works
 *
 * @return void
 */
	public function testRenderUsingViewProperty() {
		$this->PostsController->view = 'cache_form';
		$View = new TestView($this->PostsController);

		$this->assertEquals('cache_form', $View->view);
		$result = $View->render();
		$this->assertRegExp('/Add User/', $result);
	}

/**
 * Test render()ing a file in a subdir from a custom viewPath
 * in a plugin.
 *
 * @return void
 */
	public function testGetViewFileNameSubdirWithPluginAndViewPath() {
		$this->PostsController->plugin = 'TestPlugin';
		$this->PostsController->viewPath = 'Elements';
		$this->PostsController->name = 'Posts';
		$View = new TestView($this->PostsController);

		$expected = CAKE . 'Test' . DS . 'test_app' . DS . 'Plugin' . DS . 'TestPlugin' .
			DS . 'View' . DS . 'Elements' . DS . 'sub_dir' . DS . 'sub_element.ctp';
		$this->assertEquals($expected, $View->getViewFileName('sub_dir/sub_element'));
	}

/**
 * Test that view vars can replace the local helper variables
 * and not overwrite the $this->Helper references
 *
 * @return void
 */
	public function testViewVarOverwritingLocalHelperVar() {
		$Controller = new ViewPostsController();
		$Controller->helpers = array('Session', 'Html');
		$Controller->set('html', 'I am some test html');
		$View = new View($Controller);
		$result = $View->render('helper_overwrite', false);

		$this->assertRegExp('/I am some test html/', $result);
		$this->assertRegExp('/Test link/', $result);
	}

/**
 * Test getViewFileName method
 *
 * @return void
 */
	public function testViewFileName() {
		$View = new TestView($this->PostsController);

		$result = $View->getViewFileName('index');
		$this->assertRegExp('/Posts(\/|\\\)index.ctp/', $result);

		$result = $View->getViewFileName('TestPlugin.index');
		$this->assertRegExp('/Posts(\/|\\\)index.ctp/', $result);

		$result = $View->getViewFileName('/Pages/home');
		$this->assertRegExp('/Pages(\/|\\\)home.ctp/', $result);

		$result = $View->getViewFileName('../Elements/test_element');
		$this->assertRegExp('/Elements(\/|\\\)test_element.ctp/', $result);

		$result = $View->getViewFileName('../Themed/TestTheme/Posts/index');
		$this->assertRegExp('/Themed(\/|\\\)TestTheme(\/|\\\)Posts(\/|\\\)index.ctp/', $result);

		$expected = CAKE . 'Test' . DS . 'test_app' . DS . 'View' . DS . 'Posts' . DS . 'index.ctp';
		$result = $View->getViewFileName('../Posts/index');
		$this->assertEquals($expected, $result);
	}

/**
 * Test renderCache method
 *
 * @return void
 */
	public function testRenderCache() {
		$this->skipIf(!is_writable(CACHE . 'views' . DS), 'CACHE/views dir is not writable, cannot test renderCache.');

		$view = 'test_view';
		$View = new View($this->PostsController);
		$path = CACHE . 'views' . DS . 'view_cache_' . $view;

		$cacheText = '<!--cachetime:' . time() . '-->some cacheText';
		$f = fopen($path, 'w+');
		fwrite($f, $cacheText);
		fclose($f);

		$result = $View->renderCache($path, '+1 second');
		$this->assertFalse($result);
		if (file_exists($path)) {
			unlink($path);
		}

		$cacheText = '<!--cachetime:' . (time() + 10) . '-->some cacheText';
		$f = fopen($path, 'w+');
		fwrite($f, $cacheText);
		fclose($f);
		$result = $View->renderCache($path, '+1 second');

		$this->assertRegExp('/^some cacheText/', $result);

		if (file_exists($path)) {
			unlink($path);
		}
	}

/**
 * Test that render() will remove the cake:nocache tags when only the cachehelper is present.
 *
 * @return void
 */
	public function testRend