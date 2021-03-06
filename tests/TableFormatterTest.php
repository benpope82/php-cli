<?php

namespace splitbrain\phpcli\tests;

class TableFormatter extends \splitbrain\phpcli\TableFormatter
{
    public function calculateColLengths($columns)
    {
        return parent::calculateColLengths($columns);
    }

    public function strlen($string)
    {
        return parent::strlen($string);
    }

    public function wordwrap($str, $width = 75, $break = "\n", $cut = false)
    {
        return parent::wordwrap($str, $width, $break, $cut);
    }

}

class TableFormatterTest extends \PHPUnit_Framework_TestCase
{

    /**
     * Provide test data for column width calculations
     *
     * @return array
     */
    public function calcProvider()
    {
        return array(
            array(
                array(5, 5, 5),
                array(5, 5, 88)
            ),

            array(
                array('*', 5, 5),
                array(88, 5, 5)
            ),

            array(
                array(5, '50%', '50%'),
                array(5, 46, 47)
            ),

            array(
                array(5, '*', '50%'),
                array(5, 47, 46)
            ),
        );
    }

    /**
     * Test calculation of column sizes
     *
     * @dataProvider calcProvider
     * @param array $input
     * @param array $expect
     * @param int $max
     * @param string $border
     */
    public function test_calc($input, $expect, $max = 100, $border = ' ')
    {
        $tf = new TableFormatter();
        $tf->setMaxWidth($max);
        $tf->setBorder($border);

        $result = $tf->calculateColLengths($input);

        $this->assertEquals($max, array_sum($result) + (strlen($border) * (count($input) - 1)));
        $this->assertEquals($expect, $result);

    }

    /**
     * Check wrapping
     */
    public function test_wrap()
    {
        $text = "this is a long string something\n" .
            "123456789012345678901234567890";

        $expt = "this is a long\n" .
            "string\n" .
            "something\n" .
            "123456789012345\n" .
            "678901234567890";

        $tf = new TableFormatter();
        $this->assertEquals($expt, $tf->wordwrap($text, 15, "\n", true));

    }

}