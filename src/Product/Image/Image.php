<?php

namespace Message\Mothership\Commerce\Product\Image;

use Message\Mothership\FileManager\File\File;
use Message\Mothership\FileManager\File\Loader as FileLoader;

use Message\ImageResize\ResizableInterface;

use Message\Cog\ValueObject\Authorship;

/**
 * Class Image
 * @package Message\Mothership\Commerce\Product\Image
 *
 * @author  Thomas Marchant <thomas@mothership.ec>
 */
class Image implements ResizableInterface
{
	/**
	 * @var int
	 */
	public $id;

	/**
	 * @var Authorship
	 */
	public $authorship;

	/**
	 * @var string
	 */
	public $type;

	/**
	 * @var \Message\Cog\Localisation\Locale
	 */
	public $locale;

	/**
	 * @var int
	 */
	public $fileID;

	/**
	 * @var \Message\Mothership\Commerce\Product\Product
	 */
	public $product;

	/**
	 * @var File
	 */
	protected $_file;

	/**
	 * @var FileLoader
	 */
	protected $_fileLoader;

	/**
	 * @var array
	 */
	private $_options = [];

	/**
	 *
	 */
	public function __construct()
	{
		$this->authorship = new Authorship;

		$this->authorship
			->disableUpdate(); // remove when making update class
	}

	/**
	 * Magic getter is parse by reference to allow backwards compatibility access to previously public `$_options`
	 * property
	 *
	 * @param $key
	 *
	 * @return array|File
	 */
	public function &__get($key)
	{
		switch ($key) {
			case 'file' :
				if (null === $this->_file) {
					$this->_loadFile();
				}

				return $this->_file;
			case 'options' :
				$this->_options = $this->_cleanOptions($this->_options);

				return $this->_options;
		}
	}

	/**
	 * Magic setter for setting file and options as if they were public
	 *
	 * @param $key
	 * @param $value
	 */
	public function __set($key, $value)
	{
		switch ($key) {
			case 'file' :
				$this->_setFile($value);
				break;
			case 'options' :
				$this->setOptions($value);
				break;
			default:
				$this->$key = $value;
		}
	}

	/**
	 * @param $key
	 *
	 * @return bool
	 */
	public function __isset($key)
	{
		return ('file' === $key);
	}

	/**
	 * Load file and drop file loader on serialization
	 *
	 * @return array
	 */
	public function __sleep()
	{
		$this->_loadFile();

		return array(
			'id',
			'authorship',
			'type',
			'locale',
			'fileID',
			'product',
			'_options',
			'_file'
		);
	}

	/**
	 * @return string | null
	 */
	public function getUrl()
	{
		if (!$this->getFile()) {
			return null;
		}

		return $this->getFile()->getUrl();
	}

	/**
	 * @return string | null
	 */
	public function getAltText()
	{
		if (!$this->getFile()) {
			return null;
		}

		return $this->getFile()->getAltText();
	}

	/**
	 * @param FileLoader $fileLoader
	 */
	public function setFileLoader(FileLoader $fileLoader)
	{
		$this->_fileLoader = $fileLoader;
	}

	/**
	 * @param array $options
	 */
	public function setOptions(array $options)
	{
		$this->_options = $this->_cleanOptions($options);
	}

	/**
	 * @return array
	 */
	public function getOptions()
	{
		return $this->_options;
	}

	/**
	 * @return File
	 */
	public function getFile()
	{
		if (null === $this->_file) {
			$this->_loadFile();
		}

		return $this->_file;
	}

	/**
	 * Load the file if it has not already been loaded
	 */
	protected function _loadFile()
	{
		if (null !== $this->_file) {
			return;
		}

		if (!$this->_fileLoader) {
			throw new \LogicException(__CLASS__ . ': No file loader set, has this object been serialized?');
		}

		$this->_file = $this->_fileLoader->getByID($this->fileID);
	}

	/**
	 * Parse options. All keys should be lower case to be consistent with product options.
	 *
	 * @param $options
	 *
	 * @return array
	 */
	private function _cleanOptions($options)
	{
		$clean = [];

		foreach ($options as $name => $value) {
			$name = strtolower($name);
			if (array_key_exists($name, $clean)) {
				throw new \LogicException('Option names are parsed to lower case, `' . $name . '` already exists in image options');
			}

			$clean[$name] = $value;
		}

		return $clean;
	}

	/**
	 * @param File $file
	 */
	private function _setFile(File $file)
	{
		$this->_file = $file;
	}
}