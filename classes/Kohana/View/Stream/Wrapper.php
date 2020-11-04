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
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id$
 */

/**
 * Stream wrapper to convert markup of mostly-PHP templates into PHP prior to
 * include().
 *
 * Based in large part on the example at
 * http://www.php.net/manual/en/function.stream-wrapper-register.php
 *
 * As well as the example provided at:
 *     http://mikenaberezny.com/2006/02/19/symphony-templates-ruby-erb/
 * written by
 *     Mike Naberezny (@link http://mikenaberezny.com)
 *     Paul M. Jones  (@link http://paul-m-jones.com)
 *
 * @category   Zend
 * @package    Zend_View
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Kohana_View_Stream_Wrapper
{
	/**
	 * Current stream position.
	 *
	 * @var int
	 */
	protected $_pos = 0;

	/**
	 * Data for streaming.
	 *
	 * @var string
	 */
	protected $_data;

	/**
	 * Stream stats.
	 *
	 * @var array
	 */
	protected $_stat;

	/**
	 * Raw output character. Prepend this on any echo variables to
	 * turn off auto encoding of the output
	 */
	protected $_raw_output_char = '!';

	/**
	 * The encoding method to use on view output. Only use the method name
	 */
	protected $_encode_method = 'HTML::chars';

	/**
	 * Opens the script file and converts markup.
	 */
	public function stream_open($path, $mode, $options, &$opened_path)
	{
		// get the view script source
		$path        = str_replace('kohana.view://', '', $path);
		$this->_data = file_get_contents($path);

		/**
		 * If reading the file failed, update our local stat store
		 * to reflect the real stat of the file, then return on failure
		 */
		if ($this->_data === false)
		{
			$this->_stat = stat($path);
			return false;
		}
                
		/**
                 * Escape all variables and convert <?= ?> to long-form <?php echo ?>
		 *
		 */
		$regex = '/<\?(\=|php)?(.+?)\?>/s';
		$this->_data = preg_replace_callback($regex, array($this, '_escape_val'), $this->_data);

		/**
		 * file_get_contents() won't update PHP's stat cache, so we grab a stat
		 * of the file to prevent additional reads should the script be
		 * requested again, which will make include() happy.
		 */
		$this->_stat = stat($path);

		// PHP7.4 requires that we return the accurate value for the filesize, otherwise it does not read the full string
        // and then causes a cryptic "unexpected end of file" error
        $this->_stat['size'] = strlen($this->_data);
		return true;
	}

	/**
	 * Escapes a variable from template matching
	 *
	 * @param   array   matches
	 * @return  string
	 */
	protected function _escape_val($matches)
	{
            // Start by trimming the echoed string
            $code = trim($matches[2]);

            // Then map all variables within the codeblock to the view class
            $code = preg_replace('/\$(?!tmp_|this->)([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]+)/',
                    '$this->var_$1', $code);

            // If this block is not the special <?= block return
            if ($matches[1] !== '=')
            {
                return '<?php '. $code . '?>';
            }

            // Remove trailing ; characters
            $var = trim($code, ';');

            if (substr($var, 0, 1) != $this->_raw_output_char)
            {
                return '<?php echo '.$this->_encode_method.'('.$var.'); ?>';
            }
            else
            {
                // Remove the "turn off escape" character
                return '<?php echo '.substr($var, strlen($this->_raw_output_char), strlen($var)-1).'; ?>';
            }
	}

	/**
	 * Included so that __FILE__ returns the appropriate info
	 *
	 * @return array
	 */
	public function url_stat()
	{
		return $this->_stat;
	}

	/**
	 * Reads from the stream.
	 */
	public function stream_read($count)
	{
		$ret = substr($this->_data, $this->_pos, $count);
		$this->_pos += strlen($ret);
		return $ret;
	}

	/**
	 * Tells the current position in the stream.
	 */
	public function stream_tell()
	{
		return $this->_pos;
	}

	/**
	 * Tells if we are at the end of the stream.
	 */
	public function stream_eof()
	{
		return $this->_pos >= strlen($this->_data);
	}

	/**
	 * Stream statistics.
	 */
	public function stream_stat()
	{
		return $this->_stat;
	}

	/**
	 * Seek to a specific point in the stream.
	 */
	public function stream_seek($offset, $whence)
	{
		switch ($whence)
		{
			case SEEK_SET:
				if ($offset < strlen($this->_data) && $offset >= 0)
				{
					$this->_pos = $offset;
					return true;
				}
				else
				{
					return false;
				}
				break;

			case SEEK_CUR:
				if ($offset >= 0)
				{
					$this->_pos += $offset;
					return true;
				}
				else
				{
					return false;
				}
				break;

			case SEEK_END:
				if (strlen($this->_data) + $offset >= 0)
				{
					$this->_pos = strlen($this->_data) + $offset;
					return true;
				}
				else
				{
					return false;
				}
				break;

			default:
				return false;
		}
	}

	public function stream_set_option($option, $arg1, $arg2)
    {
        // PHP7.4 requires this be implemented, but we can return FALSE that we don't support any of the options
        // (blocking mode, buffering, etc)
        return FALSE;
    }
}