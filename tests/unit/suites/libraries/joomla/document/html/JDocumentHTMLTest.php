<?php
/**
 * @package    Joomla.UnitTest
 *
 * @copyright  Copyright (C) 2005 - 2014 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

include_once JPATH_PLATFORM . '/joomla/document/html/html.php';

/**
 * Test class for JDocumentHTML.
 *
 * @package     Joomla.UnitTest
 * @subpackage  Document
 * @since       11.1
 */
class JDocumentHTMLTest extends PHPUnit_Framework_TestCase
{
	/**
	 * @var JDocumentHTML
	 */
	protected $object;

	/**
	 * Sets up the fixture, for example, opens a network connection.
	 * This method is called before a test is executed.
	 *
	 * @return void
	 */
	protected function setUp()
	{
		parent::setUp();

		$this->object = new JDocumentHTML;
	}

	/**
	 * Test construct
	 *
	 * @covers  JDocumentHtml::__construct
	 *
	 * @return  void
	 */
	public function test__construct()
	{
		$documentHtml = new JDocumentHtml;

		$this->assertThat(
			$documentHtml->_mime,
			$this->equalTo('text/html'),
			'JDocumentHtml::__construct: Default Mime does not match'
		);

		$this->assertThat(
			$documentHtml->_type,
			$this->equalTo('html'),
			'JDocumentHtml::__construct: Default Type does not match'
		);
	}

	/**
	 * Test getHeadData
	 *
	 * @return void
	 */
	public function testSetAndGetHeadData()
	{
		// Get default values
		$default = $this->object->getHeadData();

		// Test invalid data
		$return = $this->object->setHeadData('invalid');

		$this->assertThat(
			$this->object->getHeadData(),
			$this->equalTo($default),
			'JDocumentHtml::setHeadData invalid data allowed to be set'
		);

		// Test return value
		$this->assertThat(
			$return,
			$this->isNull(),
			'JDocumentHtml::setHeadData did not return null'
		);

		// Test setting/ getting values
		$test_data = array(
			'title' => 'My Custom Title',
			'description' => 'My Description',
			'link' => 'http://joomla.org',
			'metaTags' => array(
				'myMetaTag' => 'myMetaContent'
			),
			'links' => array(
				'index.php' => array(
					'relation' => 'Start',
					'relType' => 'rel',
					'attribs' => array()
				)
			),
			'styleSheets' => array(
				'test.css' => array(
					'mime' => 'text/css',
					'media' => null,
					'attribs' => array()
				)
			),
			'style' => array(
				'text/css' => 'body { background: white; }'
			),
			'scripts' => array(
				'test.js' => array(
					'mime' => 'text/javascript',
					'defer' => false,
					'async' => false
				)
			),
			'script' => array(
				'text/javascript' => "window.addEvent('load', function() { new JCaption('img.caption'); });"
			),
			'custom' => array(
				"<script>var html5 = true;</script>"
			)
		);

		foreach ($test_data as $dataKey => $dataValue)
		{
			// Set
			$return = $this->object->setHeadData(array($dataKey => $dataValue));

			// Get
			$compareTo = $this->object->getHeadData();

			// Assert
			$this->assertThat(
				$compareTo[$dataKey],
				$this->equalTo($dataValue),
				'JDocumentHtml::setHeadData did not return ' . $dataKey . ' properly or setHeadData with ' . $dataKey . ' did not work'
			);

			// Test return value
			$this->assertThat(
				$return,
				$this->equalTo($this->object),
				'JDocumentHtml::setHeadData did not return JDocumentHtml instance'
			);
		}

		// Could use native methods (JDocument::addStyleSheet, etc) like $this->mergeHeadData
	}

	/**
	 * Test...
	 *
	 * @return  void
	 *
	 * @note    MDC: <link>  https://developer.mozilla.org/en-US/docs/HTML/Element/link
	 */
	public function testAddHeadLink()
	{
		// Simple
		$this->object->addHeadLink('index.php', 'Start');

		$this->assertThat(
			$this->object->_links['index.php'],
			$this->equalTo(array('relation' => 'Start', 'relType' => 'rel', 'attribs' => array())),
			'addHeadLink did not work'
		);

		// RSS
		$link = '&format=feed&limitstart=';
		$attribs = array('type' => 'application/rss+xml', 'title' => 'RSS 2.0');

		$this->object->addHeadLink($link, 'alternate', 'rel', $attribs);

		$this->assertThat(
			$this->object->_links[$link],
			$this->equalTo(array('relation' => 'alternate', 'relType' => 'rel', 'attribs' => $attribs)),
			'JDocumentHtml::addHeadLink did not work for RSS'
		);
	}

	/**
	 * Test...
	 *
	 * @return  void
	 */
	public function testAddFavicon()
	{
		$this->object->addFavicon('templates\protostar\favicon.ico');

		$this->assertThat(
			$this->object->_links['templates/protostar/favicon.ico'],
			$this->equalTo(array('relation' => 'shortcut icon', 'relType' => 'rel', 'attribs' => array('type' => 'image/vnd.microsoft.icon'))),
			'JDocumentHtml::addFavicon did not work'
		);

		$this->object->addFavicon('favicon.gif', null);

		$this->assertThat(
			$this->object->_links['favicon.gif'],
			$this->equalTo(array('relation' => 'shortcut icon', 'relType' => 'rel', 'attribs' => array('type' => null))),
			'JDocumentHtml::addFavicon did not work'
		);
	}

	/**
	 * Test...
	 *
	 * @return  void
	 */
	public function testAddCustomTag()
	{
		$this->object->addCustomTag("\t  <script>var html5 = true;</script>\r\n");

		$this->assertThat(
			in_array('<script>var html5 = true;</script>', $this->object->_custom),
			$this->isTrue(),
			'JDocumentHtml::addCustomTag did not work'
		);
	}

	/**
	 * We test both at once
	 *
	 * @return  void
	 */
	public function testIsAndSetHtml5()
	{
		// Check true
		$this->object->setHtml5(true);

		$this->assertThat(
			$this->object->isHtml5(),
			$this->isTrue(),
			'JDocumentHtml::setHtml5(true) did not work'
		);

		// Check false
		$this->object->setHtml5(false);

		$this->assertThat(
			$this->object->isHtml5(),
			$this->isFalse(),
			'JDocumentHtml::setHtml5(false) did not work'
		);

		// Check non-boolean
		$this->object->setHtml5('non boolean');

		$this->assertThat(
			$this->object->isHtml5(),
			$this->logicalNot($this->equalTo('non boolean')),
			"JDocumentHtml::setHtml5('non boolean') did not work"
		);
	}
}
