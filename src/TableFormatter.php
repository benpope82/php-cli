<?php

namespace splitbrain\phpcli;

/**
 * Class TableFormatter
 *
 * Output text in multiple columns
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 * @license MIT
 */
class TableFormatter
{
    /** @var string border between columns */
    protected $border = ' ';

    /** @var int the terminal width */
    protected $max = 74;

    /** @var Colors for coloring output */
    protected $colors;

    /**
     * TableFormatter constructor.
     *
     * @param Colors|null $colors
     */
    public function __construct(Colors $colors = null)
    {
        // try to get terminal width
        $width = 0;
        if (isset($_SERVER['COLUMNS'])) {
            // from environment
            $width = (int)$_SERVER['COLUMNS'];
        }
        if (!$width) {
            // via tput command
            $width = @exec('tput cols');
        }
        if ($width) {
            $this->max = $width;
        }

        if ($colors) {
            $this->colors = $colors;
        } else {
            $this->colors = new Colors();
        }
    }

    /**
     * The currently set border (defaults to ' ')
     *
     * @return string
     */
    public function getBorder()
    {
        return $this->border;
    }

    /**
     * Set the border. The border is set between each column. Its width is
     * added to the column widths.
     *
     * @param string $border
     */
    public function setBorder($border)
    {
        $this->border = $border;
    }

    /**
     * Width of the terminal in characters
     *
     * initially autodetected
     *
     * @return int
     */
    public function getMaxWidth()
    {
        return $this->max;
    }

    /**
     * Set the width of the terminal to assume (in characters)
     *
     * @param int $max
     */
    public function setMaxWidth($max)
    {
        $this->max = $max;
    }

    /**
     * Takes an array with dynamic column width and calculates the correct width
     *
     * Column width can be given as fixed char widths, percentages and a single * width can be given
     * for taking the remaining available space. When mixing percentages and fixed widths, percentages
     * refer to the remaining space after allocating the fixed width
     *
     * @param array $columns
     * @return int[]
     * @throws Exception
     */
    protected function calculateColLengths($columns)
    {
        $idx = 0;
        $border = $this->strlen($this->border);
        $fixed = (count($columns) - 1) * $border; // borders are used already
        $fluid = -1;

        // first pass for format check and fixed columns
        foreach ($columns as $idx => $col) {
            // handle fixed columns
            if ((string)intval($col) === (string)$col) {
                $fixed += $col;
                continue;
            }
            // check if other colums are using proper units
            if (substr($col, -1) == '%') {
                continue;
            }
            if ($col == '*') {
                // only one fluid
                if ($fluid < 0) {
                    $fluid = $idx;
                    continue;
                } else {
                    throw new Exception('Only one fluid column allowed!');
                }
            }
            throw new Exception("unknown column format $col");
        }

        $alloc = $fixed;
        $remain = $this->max - $alloc;

        // second pass to handle percentages
        foreach ($columns as $idx => $col) {
            if (substr($col, -1) != '%') {
                continue;
            }
            $perc = floatval($col);

            $real = (int)floor(($perc * $remain) / 100);

            $columns[$idx] = $real;
            $alloc += $real;
        }

        $remain = $this->max - $alloc;
        if ($remain < 0) {
            throw new Exception("Wanted column widths exceed available space");
        }

        // assign remaining space
        if ($fluid < 0) {
            $columns[$idx] += ($remain); // add to last column
        } else {
            $columns[$fluid] = $remain;
        }

        return $columns;
    }

    /**
     * Displays text in multiple word wrapped columns
     *
     * @param int[] $columns list of column widths (in characters, percent or '*')
     * @param string[] $texts list of texts for each column
     * @param array $colors A list of color names to use for each column. use empty string for default
     * @return string
     * @throws Exception
     */
    public function format($columns, $texts, $colors = array())
    {
        $columns = $this->calculateColLengths($columns);

        $wrapped = array();
        $maxlen = 0;

        foreach ($columns as $col => $width) {
            $wrapped[$col] = explode("\n", $this->wordwrap($texts[$col], $width, "\n", true));
            $len = count($wrapped[$col]);
            if ($len > $maxlen) {
                $maxlen = $len;
            }

        }

        $last = count($columns) - 1;
        $out = '';
        for ($i = 0; $i < $maxlen; $i++) {
            foreach ($columns as $col => $width) {
                if (isset($wrapped[$col][$i])) {
                    $val = $wrapped[$col][$i];
                } else {
                    $val = '';
                }
                $chunk = sprintf('%-' . $width . 's', $val);
                if (isset($colors[$col]) && $colors[$col]) {
                    $chunk = $this->colors->wrap($chunk, $colors[$col]);
                }
                $out .= $chunk;

                // border
                if ($col != $last) {
                    $out .= $this->border;
                }
            }
            $out .= "\n";
        }
        return $out;

    }

    /**
     * Measures char length in UTF-8 when possible
     *
     * @param $string
     * @return int
     */
    protected function strlen($string)
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($string, 'utf-8');
        }

        return strlen($string);
    }

    /**
     * @param string $string
     * @param int $start
     * @param int|null $length
     * @return string
     */
    protected function substr($string, $start = 0, $length = null)
    {
        if (function_exists('mb_substr')) {
            return mb_substr($string, $start, $length);
        } else {
            return substr($string, $start, $length);
        }
    }

    /**
     * @param string $str
     * @param int $width
     * @param string $break
     * @param bool $cut
     * @return string
     * @link http://stackoverflow.com/a/4988494
     */
    protected function wordwrap($str, $width = 75, $break = "\n", $cut = false)
    {
        $lines = explode($break, $str);
        foreach ($lines as &$line) {
            $line = rtrim($line);
            if ($this->strlen($line) <= $width) {
                continue;
            }
            $words = explode(' ', $line);
            $line = '';
            $actual = '';
            foreach ($words as $word) {
                if ($this->strlen($actual . $word) <= $width) {
                    $actual .= $word . ' ';
                } else {
                    if ($actual != '') {
                        $line .= rtrim($actual) . $break;
                    }
                    $actual = $word;
                    if ($cut) {
                        while ($this->strlen($actual) > $width) {
                            $line .= $this->substr($actual, 0, $width) . $break;
                            $actual = $this->substr($actual, $width);
                        }
                    }
                    $actual .= ' ';
                }
            }
            $line .= trim($actual);
        }
        return implode($break, $lines);
    }
}