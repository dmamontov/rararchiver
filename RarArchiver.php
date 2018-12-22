<?php
/**
 * RarArchiver
 *
 * Copyright (c) 2015, Dmitry Mamontov <d.slonyara@gmail.com>.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 *   * Redistributions of source code must retain the above copyright
 *     notice, this list of conditions and the following disclaimer.
 *
 *   * Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in
 *     the documentation and/or other materials provided with the
 *     distribution.
 *
 *   * Neither the name of Dmitry Mamontov nor the names of his
 *     contributors may be used to endorse or promote products derived
 *     from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @package   rararchiver
 * @author    Dmitry Mamontov <d.slonyara@gmail.com>
 * @copyright 2015 Dmitry Mamontov <d.slonyara@gmail.com>
 * @license   http://www.opensource.org/licenses/BSD-3-Clause  The BSD 3-Clause License
 * @since     File available since Release 1.0.0
 */

/**
 * RarArchiver - Class to create an archive Rar.
 *
 * @author    Dmitry Mamontov <d.slonyara@gmail.com>
 * @copyright 2015 Dmitry Mamontov <d.slonyara@gmail.com>
 * @license   http://www.opensource.org/licenses/BSD-3-Clause  The BSD 3-Clause License
 * @version   Release: 1.0.0
 * @link      https://github.com/dmamontov/rararchiver
 * @since     Class available since Release 1.0.0
 */
class RarArchiver
{
    /**
     * Creates a new archive
     */
    const CREATE = 1;

    /**
     * Substitution archive
     */
    const REPLACE = 2;

    /**
     * Reading archive
     */
    const READING = 'a+b';

    /**
     * Record archive
     */
    const RECORD = 'w+b';

    /**
     * The object created or opened file
     * @var SplFileObject
     * @access private
     */
    private $fileObject = null;

    /**
     * The name of the archive
     * @var string
     * @access private
     */
    private $filename = null;

    /**
     * Files and directories have been added to the archive
     * @var array
     * @access private
     */
    private $tree = array();

    /**
     * Opens the archive
     * @param string $file
     * @param integer $flag
     * @return boolean
     * @access public
     * @final
    */
    final public function __construct($file, $flag = 0)
    {
        if (version_compare(PHP_VERSION, '5.5.11', '<')) {
            throw new RuntimeException('PHP version must be not lower than 5.5.11.');
        }

        if (file_exists($file) == false && $flag != self::CREATE && $flag == self::REPLACE) {
            throw new RuntimeException('This archive is not in the specified path.');
        }

        $mode = self::READING;
        if ((file_exists($file) == false && $flag == self::CREATE) || (file_exists($file) && $flag == self::REPLACE)) {
            $mode = self::RECORD;
        }

        $this->filename = $file;
        $this->fileObject = new SplFileObject($file, $mode);

        if ($this->fileObject === false) {
            throw new RuntimeException('Unable to open or create a file.');
        }

        if ($mode == self::RECORD && ($flag == self::CREATE || $flag == self::REPLACE)) {
            $this->writeHeader(0x72, 0x1a21);
            $this->writeHeader(0x73, 0x0000, array(array(0, 2), array(0, 4)));
        } elseif ($this->isRar() == false) {
            throw new RuntimeException('The file is not an archive RAR.');
        } else {
            $this->tree = $this->getFileList();
        }

        unset($mode, $file, $flag);
    }

    /**
     * Closes the file
     * @access public
     * @final
     */
    final public function __destruct()
    {
		if (is_callable('shell_exec') && false === stripos(ini_get('disable_functions'), 'shell_exec'))
		{
			// repair archive for proper compression and create recovery records (requires Unix rar or WinRAR)
			$windows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? true : false;
			if (!$windows && `which rar`)
				shell_exec('rar -r -ma4 -av -rr ' . $this->filename);
			else
			{
				// WinRAR must reside in a default installation directory to detect it
				if (is_dir("\Program Files (x86)\WinRAR"))
					@chdir("\Program Files (x86)\WinRAR");
				elseif (is_dir("\Program Files\WinRAR"))
					@chdir("\Program Files\WinRAR");

				if (`where WinRAR.exe`)
					shell_exec('rar -r -m4 -av -rr ' . $this->filename);
			}
		}

		unset($this->fileObject);
    }

    /**
     * Add files to the archive.
     * @param string $filename
     * @param string $localname
     * @return boolean
     * @access public
     * @final
     */
    final public function addFile($filename, $localname = '')
    {
        $filename = str_replace('\\', '/', $filename);

        if (file_exists($filename) == false) {
            return false;
        }

        if (is_null($localname) || empty($localname)) {
            $localname = $filename;
        }

        $file = new SplFileObject($filename, 'r');
        $size = $file->getSize();
        if ($size > 0) {
            $contents =  $file->fread($size);
        }

        unset($size, $file, $filename);

        return $this->addFromString($localname, $contents);
    }

    /**
     * Adding content to the archive file line.
     * @param string $localname
     * @param string $contents
     * @return boolean
     * @access public
     * @final
     */
    final public function addFromString($localname, $contents)
    {
        if (is_null($localname) || empty($localname) || is_null($contents) || empty($contents)) {
            return false;
        }

        $localname = str_replace('\\', '/', $localname);

        $localname = $this->clearSeparator($localname);

        $dirname = explode('/', $localname);
        if (count($dirname) > 1) {
            array_pop($dirname);
            $this->addEmptyDir(implode('/', $dirname));
            unset($dirname);
        }

        $localname = str_replace('/', "\\", $localname);

        $add = $this->writeHeader(0x74, $this->setBits(array(15)), array(
            array(strlen($contents), 4),
            array(strlen($contents), 4),
            array(0, 1),
            array(crc32($contents), 4),
            array($this->getDateTime(), 4),
            array(20, 1),
            array(0x30, 1),
            array(strlen($localname), 2),
            array(0x20, 4),
            $localname,
        ));

        if ($add == true) {
            $this->fileObject->fwrite($contents);
        }

        unset($contents, $add, $localname, $dirname);

        return true;
    }

    /**
     * Add files to the archive.
     * @param string $dirname
     * @return boolean
     * @access public
     * @final
     */
    final public function addEmptyDir($dirname)
    {
        $dirname = str_replace('\\', '/', $dirname);

        if (is_null($dirname) || empty($dirname) || stripos($dirname, '/') === false) {
            return false;
        }

        $dirname = $this->clearSeparator($dirname);

        $parts = explode('/', $dirname);
        $dirname = '';
        for ($i = 0; $i < count($parts); $i++) {
            $dirname .= $parts[$i] . "\\";

            if (in_array($parts[$i], $this->tree) !== false) {
                continue;
            }

            $this->writeHeader(0x74, $this->setBits(array(5, 6, 7, 15)), array(
                array(0, 4),
                array(0, 4),
                array(0, 1),
                array(0, 4),
                array($this->getDateTime(), 4),
                array(20, 1),
                array(0x30, 1),
                array(strlen(substr($dirname, 0, -1)), 2),
                array(0x10, 4),
                substr($dirname, 0, -1),
            ));
        }

        unset($dirname, $parts);

        return true;
    }

    /**
     * Adds all files and folders in the archive
     * @param string $path
     * @param string $regex
     * @access public
     * @final
     */
    final public function buildFromDirectory($path, $regex = null)
    {
        $path = str_replace('\\', '/', realpath($path));
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file) {
            if (is_null($regex) == false && empty($regex) == false && preg_match($regex, $file) == false) {
                continue;
            }

            $file = str_replace('\\', '/', $file);

            if (in_array(substr($file, strrpos($file, '/') + 1), array('.', '..'))) {
                continue;
            }

            $file = realpath($file);

            if (is_dir($file) === true) {
                $this->addEmptyDir(str_replace($path . '/', '', $file));
            } elseif (is_file($file) === true) {
                $this->addFile($file, str_replace($path . '/', '', $file));
            }
        }

        unset($path, $regex, $files);
    }

    /**
     * Writes block header in accordance with the format
     * @param mixed $path
     * @param mixed $flags
     * @param array $data
     * @return boolean
     * @access private
     * @final
     */
    final private function writeHeader($type, $flags, $data = array())
    {
        if (in_array($type, array(0x72, 0x73, 0x74)) == false) {
            return false;
        }

        if (in_array($type, array(0x72, 0x73)) === false && is_string(end($data)) && in_array(end($data), $this->tree)) {
            return false;
        }

        if (is_string(end($data))) {
            array_push($this->tree, end($data));
        }

        $size = 7;
        foreach ($data as $key => $value) {
            $size += is_array($value) ? $value[1] : strlen($value);
        }

        $bytes = array_merge(array($type, array($flags, 2), array($size, 2)), $data);
        $header = '';
        for ($i = 0; $i < count($bytes); $i++) {
            if (is_array($bytes[$i])) {
                $header .= $this->getBytes($bytes[$i][0], $bytes[$i][1]);
            } else {
                $header .= $this->getBytes($bytes[$i]);
            }
        }

        $header = ($type == 0x72 ? "Ra" : $this->getCRC($header)) . $header;

        $this->fileObject->fwrite($header);

        unset($header, $type, $bytes, $flags, $size, $data);

        return true;
    }

    /**
     * Checks whether the file archive
     * @return boolean
     * @access private
     * @final
     */
    final public function isRar()
    {
        $this->fileObject->rewind();
        $head = '';
        if ($this->fileObject->getSize() > 0) {
            $head =  $this->fileObject->fread(7);
        }
        $this->fileObject->fseek(-1, SEEK_END);

        return bin2hex($head) == '526172211a0700';
    }

    /**
     * Getting a list of files in the archive
     * @return array
     * @access public
     * @final
     */
    final public function getFileList()
    {
        if ($this->skipHead() == false) {
            return array();
        }

        $skipFolder = true;
        $debug = debug_backtrace();
        if (isset($debug[1]) && isset($debug[1]['class']) && $debug[1]['class'] == 'RarArchiver') {
            $skipFolder = false;
        }

        $index = 0;
        $files = array();
        while ($this->fileObject->eof() === false) {
            $block = $this->fileObject->fread(7);
            $headSize = $this->getBytes($block, 5, 2);
            if ($headSize <= 7) {
                break;
            }

            $block .= $this->fileObject->fread($headSize - 7);
            if (ord($block[2]) == 0x74) {
                $this->fileObject->fseek($this->getBytes($block, 7, 4), SEEK_CUR);

                $attr = $this->getBytes($block, 28, 4);
                if (($attr & 0x10 || $attr & 0x4000) && $skipFolder) {
                    $index++;
                    continue;
                }

                $files[$index] = substr($block, 32, $this->getBytes($block, 26, 2));
            } elseif ($this->getBytes($block, 3, 2) & 0x8000) {
                $this->fileObject->fseek($this->getBytes($block, 7, 4), SEEK_CUR);
            }

            $index++;
        }

        $this->fileObject->fseek(-1, SEEK_END);

        unset($skipFolder, $debug, $block, $headSize, $attr, $index);

        return $files;
    }

    /**
     * Extract the archive contents
     * @param string $destination
     * @param mixed $entries
     * @return boolean
     * @access public
     * @final
     */
    final public function extractTo($destination, $entries = null)
    {
        if (is_null($destination) || empty($destination)) {
            $destination = 'tmp';
        }

        if (file_exists($destination) == false) {
            @mkdir($destination);
        }

        if ($this->skipHead() == false) {
            return false;
        }

        while ($this->fileObject->eof() === false) {
            $block = $this->fileObject->fread(7);
            $headSize = $this->getBytes($block, 5, 2);
            if ($headSize <= 7) {
                break;
            }

            $block .= $this->fileObject->fread($headSize - 7);

            if (ord($block[2]) == 0x74) {
                $fileSize = $this->getBytes($block, 7, 4);
                $name = $destination . '/' .str_replace('\\', '/', substr($block, 32, $this->getBytes($block, 26, 2)));

                if ((is_string($entries) && $name != $entries) || (is_array($entries) && in_array($name, $entries) === false)) {
                    continue;
                }

                $attr = $this->getBytes($block, 28, 4);
                if ($attr & 0x10 || $attr & 0x4000) {
                    @mkdir($name);
                } else {
                    $dirname = explode('/', $name);
                    array_pop($dirname);
                    if (count($dirname) > 0) {
                        @mkdir(implode('/', $dirname));
                    }

                    $newFile = new SplFileObject($name, self::RECORD);
                    $newFile->fwrite($this->fileObject->fread($fileSize));
                }
            } elseif ($this->getBytes($block, 3, 2) & 0x8000) {
                $this->fileObject->fseek($this->getBytes($block, 7, 4), SEEK_CUR);
            }
        }

        $this->fileObject->fseek(-1, SEEK_END);

        unset($destination, $entries, $block, $headSize, $fileSize, $name, $attr, $dirname, $newFile);

        return true;
    }

    /**
     * Returns the entry contents using its index
     * @param integer $index
     * @param integer $length
     * @return mixed
     * @access public
     * @final
     */
    final public function getFromIndex($index, $length = 0)
    {
        if (array_key_exists($index, $this->tree) === false || $length < 0 || is_integer($index) == false) {
            return false;
        }

        if ($this->skipHead() == false) {
            return false;
        }

        $content = '';
        $indexes = 0;
        while ($this->fileObject->eof() === false) {
            $block = $this->fileObject->fread(7);
            $headSize = $this->getBytes($block, 5, 2);
            if ($headSize <= 7) {
                break;
            }

            $block .= $this->fileObject->fread($headSize - 7);

            if (ord($block[2]) == 0x74) {
                $fileSize = $this->getBytes($block, 7, 4);

                $attr = $this->getBytes($block, 28, 4);
                if (($attr & 0x10) == false && ($attr & 0x4000) == false && $indexes == $index) {
                    if ($length == 0) {
                        $length = $fileSize;
                    }
                    $content = $this->fileObject->fread($length);
                    break;
                }

                $this->fileObject->fseek($fileSize, SEEK_CUR);
            } elseif ($this->getBytes($block, 3, 2) & 0x8000) {
                $this->fileObject->fseek($this->getBytes($block, 7, 4), SEEK_CUR);
            }

            $indexes++;
        }

        $this->fileObject->fseek(-1, SEEK_END);

        unset($index, $length, $indexes, $block, $headSize, $fileSize, $attr);

        return empty($content) ? false : $content;
    }

    /**
     * Returns the name of an entry using its index
     * @param integer $index
     * @return mixed
     * @access public
     * @final
     */
    final public function getNameIndex($index)
    {
        if (array_key_exists($index, $this->tree) === false || $length < 0 || is_integer($index) == false) {
            return false;
        }

        if ($this->skipHead() == false) {
            return false;
        }

        $name = '';
        $indexes = 0;
        while ($this->fileObject->eof() === false) {
            $block = $this->fileObject->fread(7);
            $headSize = $this->getBytes($block, 5, 2);
            if ($headSize <= 7) {
                break;
            }

            $block .= $this->fileObject->fread($headSize - 7);

            if (ord($block[2]) == 0x74) {
                $fileSize = $this->getBytes($block, 7, 4);
                $filename = str_replace('\\', '/', substr($block, 32, $this->getBytes($block, 26, 2)));

                $attr = $this->getBytes($block, 28, 4);
                if (($attr & 0x10) == false && ($attr & 0x4000) == false && $indexes == $index) {
                    $name = $filename;
                    break;
                }

                $this->fileObject->fseek($fileSize, SEEK_CUR);
            } elseif ($this->getBytes($block, 3, 2) & 0x8000) {
                $this->fileObject->fseek($this->getBytes($block, 7, 4), SEEK_CUR);
            }

            $indexes++;
        }

        $this->fileObject->fseek(-1, SEEK_END);

        unset($index, $indexes, $block, $headSize, $fileSize, $attr, $filename);

        return empty($name) ? false : $name;
    }

    /**
     * Returns the entry contents using its name
     * @param string $name
     * @param integer $length
     * @return mixed
     * @access public
     * @final
     */
    final public function getFromName($name, $length = 0)
    {
        if (in_array($name, $this->tree) === false || $length < 0 || is_string($name) == false) {
            return false;
        }

        if ($this->skipHead() == false) {
            return false;
        }

        $name = str_replace('\\', '/', $name);

        $content = '';
        while ($this->fileObject->eof() === false) {
            $block = $this->fileObject->fread(7);
            $headSize = $this->getBytes($block, 5, 2);
            if ($headSize <= 7) {
                break;
            }

            $block .= $this->fileObject->fread($headSize - 7);

            if (ord($block[2]) == 0x74) {
                $fileSize = $this->getBytes($block, 7, 4);
                $filename = str_replace('\\', '/', substr($block, 32, $this->getBytes($block, 26, 2)));

                $attr = $this->getBytes($block, 28, 4);
                if (($attr & 0x10) == false && ($attr & 0x4000) == false && $filename == $name) {
                    if ($length == 0) {
                        $length = $fileSize;
                    }
                    $content = $this->fileObject->fread($length);
                    break;
                }

                $this->fileObject->fseek($fileSize, SEEK_CUR);
            } elseif ($this->getBytes($block, 3, 2) & 0x8000) {
                $this->fileObject->fseek($this->getBytes($block, 7, 4), SEEK_CUR);
            }
        }

        $this->fileObject->fseek(-1, SEEK_END);

        unset($name, $length, $block, $headSize, $fileSize, $attr, $filename);

        return empty($content) ? false : $content;
    }

    /**
     * Renames an entry defined by its index
     * @param integer $index
     * @param string $newname
     * @return boolean
     * @access public
     * @final
     */
    final public function renameIndex($index, $newname)
    {
        if (array_key_exists($index, $this->tree) === false || is_integer($index) == false || empty($newname) || $this->fileObject->getSize() < 0) {
            return false;
        }

        $newname = $this->clearSeparator($newname);

        $currentFileObject = $this->fileObject;
        $currentFileObject->rewind();

        $this->fileObject = new SplFileObject("{$this->filename}.tmp", self::RECORD);

        $this->fileObject->fwrite($currentFileObject->fread(7));

        $mainHead = $currentFileObject->fread(7);
        if (ord($mainHead[2]) != 0x73) {
            $currentFileObject->fseek(-1, SEEK_END);
            $this->fileObject = $currentFileObject;
            @unlink("{$this->filename}.tmp");

            return false;
        }
        $this->fileObject->fwrite($mainHead);

        $headSize = $this->getBytes($mainHead, 5, 2);
        $this->fileObject->fwrite($currentFileObject->fread($headSize - 7));

        $newname = str_replace('/', '\\', $newname);

        $indexes = 0;
        while ($currentFileObject->eof() === false) {
            $block = $currentFileObject->fread(7);
            $headSize = $this->getBytes($block, 5, 2);
            if ($headSize <= 7) {
                break;
            }

            $block .= $currentFileObject->fread($headSize - 7);

            if (ord($block[2]) == 0x74) {
                $fileSize = $this->getBytes($block, 7, 4);

                $attr = $this->getBytes($block, 28, 4);
                if ($attr & 0x10 || $attr & 0x4000) {
                    $this->fileObject->fwrite($block);
                } elseif ($indexes == $index) {
                    $this->addFromString($newname, $currentFileObject->fread($fileSize));
                } else {
                    $this->fileObject->fwrite($block);
                    $this->fileObject->fwrite($currentFileObject->fread($fileSize));
                }
            } elseif ($this->getBytes($block, 3, 2) & 0x8000) {
                $this->fileObject->fwrite($currentFileObject->fread($this->getBytes($block, 7, 4)));
            }

            $indexes++;
        }

        @unlink($this->filename);
        @rename("{$this->filename}.tmp", $this->filename);

        $this->fileObject = new SplFileObject($this->filename, self::READING);
        $this->tree = $this->getFileList();

        unset($index, $newname, $currentFileObject, $mainHead, $headSize, $block, $fileSize, $indexes);

        return $this->isRar() ? true : false;
    }

    /**
     * Renames an entry defined by its name
     * @param string $name
     * @param string $newname
     * @return boolean
     * @access public
     * @final
     */
    final public function renameName($name, $newname)
    {
        if (in_array($name, $this->tree) === false || empty($name) || empty($newname) || $this->fileObject->getSize() < 0) {
            return false;
        }

        $name = $this->clearSeparator($name);
        $newname = $this->clearSeparator($newname);

        $currentFileObject = $this->fileObject;
        $currentFileObject->rewind();

        $this->fileObject = new SplFileObject("{$this->filename}.tmp", self::RECORD);

        $this->fileObject->fwrite($currentFileObject->fread(7));

        $mainHead = $currentFileObject->fread(7);
        if (ord($mainHead[2]) != 0x73) {
            $currentFileObject->fseek(-1, SEEK_END);
            $this->fileObject = $currentFileObject;
            @unlink("{$this->filename}.tmp");

            return false;
        }
        $this->fileObject->fwrite($mainHead);

        $headSize = $this->getBytes($mainHead, 5, 2);
        $this->fileObject->fwrite($currentFileObject->fread($headSize - 7));

        $name = str_replace('/', '\\', $name);
        $newname = str_replace('/', '\\', $newname);

        while ($currentFileObject->eof() === false) {
            $block = $currentFileObject->fread(7);
            $headSize = $this->getBytes($block, 5, 2);
            if ($headSize <= 7) {
                break;
            }

            $block .= $currentFileObject->fread($headSize - 7);

            if (ord($block[2]) == 0x74) {
                $fileSize = $this->getBytes($block, 7, 4);
                $filename = str_replace('/', '\\', substr($block, 32, $this->getBytes($block, 26, 2)));

                $attr = $this->getBytes($block, 28, 4);
                if ($attr & 0x10 || $attr & 0x4000) {
                    $this->fileObject->fwrite($block);
                } elseif ($filename == $name) {
                    $this->addFromString($newname, $currentFileObject->fread($fileSize));
                } else {
                    $this->fileObject->fwrite($block);
                    $this->fileObject->fwrite($currentFileObject->fread($fileSize));
                }
            } elseif ($this->getBytes($block, 3, 2) & 0x8000) {
                $this->fileObject->fwrite($currentFileObject->fread($this->getBytes($block, 7, 4)));
            }
        }

        @unlink($this->filename);
        @rename("{$this->filename}.tmp", $this->filename);

        $this->fileObject = new SplFileObject($this->filename, self::READING);
        $this->tree = $this->getFileList();

        unset($name, $newname, $filename, $currentFileObject, $mainHead, $headSize, $block, $fileSize);

        return $this->isRar() ? true : false;
    }

    /**
     * Delete an entry in the archive using its index
     * @param integer $index
     * @return boolean
     * @access public
     * @final
     */
    final public function deleteIndex($index)
    {
        if (array_key_exists($index, $this->tree) === false || is_integer($index) == false || $this->fileObject->getSize() < 0) {
            return false;
        }

        $currentFileObject = $this->fileObject;
        $currentFileObject->rewind();

        $this->fileObject = new SplFileObject("{$this->filename}.tmp", self::RECORD);

        $this->fileObject->fwrite($currentFileObject->fread(7));

        $mainHead = $currentFileObject->fread(7);
        if (ord($mainHead[2]) != 0x73) {
            $currentFileObject->fseek(-1, SEEK_END);
            $this->fileObject = $currentFileObject;
            @unlink("{$this->filename}.tmp");

            return false;
        }
        $this->fileObject->fwrite($mainHead);

        $headSize = $this->getBytes($mainHead, 5, 2);
        $this->fileObject->fwrite($currentFileObject->fread($headSize - 7));

        $indexes = 0;
        while ($currentFileObject->eof() === false) {
            $block = $currentFileObject->fread(7);
            $headSize = $this->getBytes($block, 5, 2);
            if ($headSize <= 7) {
                break;
            }

            $block .= $currentFileObject->fread($headSize - 7);

            if (ord($block[2]) == 0x74) {
                $fileSize = $this->getBytes($block, 7, 4);

                if ($indexes != $index) {
                    $attr = $this->getBytes($block, 28, 4);
                    if ($attr & 0x10 || $attr & 0x4000) {
                        $this->fileObject->fwrite($block);
                    } else {
                        $this->fileObject->fwrite($block);
                        $this->fileObject->fwrite($currentFileObject->fread($fileSize));
                    }
                }
            } elseif ($this->getBytes($block, 3, 2) & 0x8000) {
                $this->fileObject->fwrite($currentFileObject->fread($this->getBytes($block, 7, 4)));
            }

            $indexes++;
        }

        @unlink($this->filename);
        @rename("{$this->filename}.tmp", $this->filename);

        $this->fileObject = new SplFileObject($this->filename, self::READING);

        unset($this->tree[$index], $index, $currentFileObject, $mainHead, $headSize, $block, $fileSize, $indexes);

        return $this->isRar() ? true : false;
    }

    /**
     * Delete an entry in the archive using its name
     * @param string $name
     * @return boolean
     * @access public
     * @final
     */
    final public function deleteName($name)
    {
        if (in_array($name, $this->tree) === false || empty($name) || $this->fileObject->getSize() < 0) {
            return false;
        }

        $name = $this->clearSeparator($name);

        $currentFileObject = $this->fileObject;
        $currentFileObject->rewind();

        $this->fileObject = new SplFileObject("{$this->filename}.tmp", self::RECORD);

        $this->fileObject->fwrite($currentFileObject->fread(7));

        $mainHead = $currentFileObject->fread(7);
        if (ord($mainHead[2]) != 0x73) {
            $currentFileObject->fseek(-1, SEEK_END);
            $this->fileObject = $currentFileObject;
            @unlink("{$this->filename}.tmp");

            return false;
        }
        $this->fileObject->fwrite($mainHead);

        $headSize = $this->getBytes($mainHead, 5, 2);
        $this->fileObject->fwrite($currentFileObject->fread($headSize - 7));

        $name = str_replace('/', '\\', $name);

        while ($currentFileObject->eof() === false) {
            $block = $currentFileObject->fread(7);
            $headSize = $this->getBytes($block, 5, 2);
            if ($headSize <= 7) {
                break;
            }

            $block .= $currentFileObject->fread($headSize - 7);

            if (ord($block[2]) == 0x74) {
                $fileSize = $this->getBytes($block, 7, 4);
                $filename = str_replace('/', '\\', substr($block, 32, $this->getBytes($block, 26, 2)));

                if ($name != $filename) {
                    $attr = $this->getBytes($block, 28, 4);
                    if ($attr & 0x10 || $attr & 0x4000) {
                        $this->fileObject->fwrite($block);
                    } else {
                        $this->fileObject->fwrite($block);
                        $this->fileObject->fwrite($currentFileObject->fread($fileSize));
                    }
                }
            } elseif ($this->getBytes($block, 3, 2) & 0x8000) {
                $this->fileObject->fwrite($currentFileObject->fread($this->getBytes($block, 7, 4)));
            }
        }

        @unlink($this->filename);
        @rename("{$this->filename}.tmp", $this->filename);

        $this->fileObject = new SplFileObject($this->filename, self::READING);
        if ($index = array_search($name, $this->tree)) {
            unset($this->tree[$index]);
        }

        unset($name, $filename, $currentFileObject, $mainHead, $headSize, $block, $fileSize, $index);

        return $this->isRar() ? true : false;
    }

    /**
     * It skips the file headers
     * @return boolean
     * @access private
     * @final
     */
    final private function skipHead()
    {
        if ($this->fileObject->getSize() < 0) {
            return false;
        }

        $this->fileObject->rewind();
        $this->fileObject->fseek(7, SEEK_CUR);

        $mainHead = $this->fileObject->fread(7);
        if (ord($mainHead[2]) != 0x73) {
            return false;
        }

        $headSize = $this->getBytes($mainHead, 5, 2);
        $this->fileObject->fseek($headSize - 7, SEEK_CUR);

        unset($mainHead, $headSize);

        return true;
    }

    /**
     * The calculation of CRC for the header block
     * @param string $string
     * @return string
     * @access private
     * @final
     */
    final private function getCRC($string)
    {
        $crc = crc32($string);

        return chr($crc & 0xFF) . chr(($crc >> 8) & 0xFF);
    }

    /**
     * Writing data in the reverse order
     * @param string $data
     * @param integer $bytes
     * @param integer $count
     * @return string
     * @access private
     * @final
     */
    final private function getBytes($data, $bytes = 0, $count = 0)
    {
        $result = "";

        if ($count > 0 && $bytes != false) {
            $result = strrev(substr($data, $bytes, $count));

            return hexdec(bin2hex($result));
        }

        if ($bytes == false) {
            $bytes = strlen($bytes);
        }

        if (is_numeric($data)) {
            $data = sprintf("%0" . ($bytes * 2) . "x", $data);
            for ($i = 0; $i < strlen($data); $i += 2) {
                $result = chr(hexdec(substr($data, $i, 2))) . $result;
            }
        } else {
            $result = $data;
        }

        unset($data, $bytes, $count);

        return $result;
    }

    /**
     * Setting the appropriate bits in the number
     * @param mixed $bits
     * @return integer
     * @access private
     * @final
     */
    final private function setBits($bits)
    {
        $result = 0;

        if (is_int($bits)) {
            $bits[] = $bits;
        }

        for ($i = 0; $i < count($bits); $i++) {
            $result |= 1 << $bits[$i];
        }

        unset($bits);

        return $result;
    }

    /**
     * Removes superfluous separator out of the way
     * @param string $path
     * @return string
     * @access private
     * @final
     */
    final private function clearSeparator($path)
    {
        if (substr($path, -1) == '/') {
            $path = substr($path, 0, -1);
        }

        if ($path[0] == '/') {
            $path = substr($path, 1, strlen($path) - 1);
        }

        return $path;
    }

    /**
     * Getting the date in the 4-bit format MSDOS
     * @param integer $time
     * @return integer
     * @access private
     * @final
     */
    final private function getDateTime($time = null)
    {
        if (is_null($time)) {
            $time = time();
        }

        $dateTime = getdate($time);
        $dateTime = $dateTime["seconds"] | ($dateTime["minutes"] << 5) |
                    ($dateTime["hours"] << 11) | ($dateTime["mday"] << 16) |
                    ($dateTime["mon"] << 21) | (($dateTime["year"] - 1980) << 25);

        unset($time);

        return $dateTime;
    }
}

?>
