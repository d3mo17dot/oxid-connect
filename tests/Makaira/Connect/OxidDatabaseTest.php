<?php
/**
 * Created by PhpStorm.
 * User: benjamin
 * Date: 17.11.16
 * Time: 14:39
 */

namespace Makaira\Connect;


class OxidDatabaseTest extends \PHPUnit_Framework_TestCase
{

    public function setUp()
    {
        if (!defined('ADODB_FETCH_ASSOC')) {
            define('ADODB_FETCH_ASSOC', 2);
        }
    }

    public function testQuery()
    {
        $dbMock = $this->getMock(\oxLegacyDb::class, ['quote', 'setFetchMode', 'getAll']);

        $dbMock
            ->expects($this->once())
            ->method('setFetchMode')
            ->with(ADODB_FETCH_ASSOC);

        $dbMock
            ->expects($this->never())
            ->method('quote');

        $dbMock
            ->expects($this->once())
            ->method('getAll')
            ->with('SELECT OXID FROM oxarticles WHERE OXPRICE = 5 AND OXSTOCK = 10')
            ->will($this->returnValue([['OXID' => 'abc']]));

        $db = new OxidDatabase($dbMock);

        $this->assertEquals(
            [['OXID' => 'abc']],
            $db->query('SELECT OXID FROM oxarticles WHERE OXPRICE = 5 AND OXSTOCK = 10')
        );
    }

    public function testQueryWithNumericParameters()
    {
        $dbMock = $this->getMock(\oxLegacyDb::class, ['quote', 'setFetchMode', 'getAll']);

        $dbMock
            ->expects($this->once())
            ->method('setFetchMode')
            ->with(ADODB_FETCH_ASSOC);

        $dbMock
            ->expects($this->never())
            ->method('quote');

        $dbMock
            ->expects($this->once())
            ->method('getAll')
            ->with('SELECT OXID FROM oxarticles WHERE OXPRICE = 5 AND OXSTOCK = 10')
            ->will($this->returnValue([['OXID' => 'abc']]));

        $db = new OxidDatabase($dbMock);

        $this->assertEquals(
            [['OXID' => 'abc']],
            $db->query(
                'SELECT OXID FROM oxarticles WHERE OXPRICE = :price AND OXSTOCK = :stock',
                [
                    'price'  => 5,
                    'stock' => 10,
                ]
            )
        );
    }

    public function testQueryWithStringParameters()
    {
        $dbMock = $this->getMock(\oxLegacyDb::class, ['quote', 'setFetchMode', 'getAll']);

        $dbMock
            ->expects($this->once())
            ->method('setFetchMode')
            ->with(ADODB_FETCH_ASSOC);

        $dbMock
            ->expects($this->once())
            ->method('quote')
            ->will($this->returnCallback(function($s) {
                return sprintf("'%s'", $s);
            }));

        $dbMock
            ->expects($this->once())
            ->method('getAll')
            ->with("SELECT OXID FROM oxarticles WHERE OXPRICE = 5 AND OXTITLE = 'Test'")
            ->will($this->returnValue([['OXID' => 'abc']]));

        $db = new OxidDatabase($dbMock);

        $this->assertEquals(
            [['OXID' => 'abc']],
            $db->query(
                'SELECT OXID FROM oxarticles WHERE OXPRICE = :price AND OXTITLE = :title',
                [
                    'price'  => 5,
                    'title' => 'Test',
                ]
            )
        );
    }

    public function testReplaceStringsWithPartialMatches()
    {
        $dbMock = $this->getMock(\oxLegacyDb::class, ['quote', 'setFetchMode', 'getAll']);

        $dbMock
            ->expects($this->once())
            ->method('getAll')
            ->with("SELECT OXID FROM oxarticles WHERE OXPRICE = 5 AND OXPRICEA = 6 AND OXPRICEB = 7")
            ->will($this->returnValue([['OXID' => 'abc']]));

        $db = new OxidDatabase($dbMock);

        $this->assertEquals(
            [['OXID' => 'abc']],
            $db->query(
                'SELECT OXID FROM oxarticles WHERE OXPRICE = :price AND OXPRICEA = :priceA AND OXPRICEB = :priceB',
                [
                    'price'  => 5,
                    'priceA' => 6,
                    'priceB' => 7,
                ]
            )
        );
    }

    /**
     * @expectedException \OutOfBoundsException
     */
    public function testUnknownReplacementParameter()
    {
        $dbMock = $this->getMock(\oxLegacyDb::class, ['quote', 'setFetchMode', 'getAll']);

        $db = new OxidDatabase($dbMock);
        $db->query('SELECT OXID FROM oxarticles WHERE OXPRICE = :unknown', []);
    }
}
