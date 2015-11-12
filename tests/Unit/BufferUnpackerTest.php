<?php

/*
 * This file is part of the rybakit/msgpack.php package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MessagePack\Tests\Unit;

use MessagePack\BufferUnpacker;
use MessagePack\Exception\InsufficientDataException;

class BufferUnpackerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var BufferUnpacker
     */
    private $unpacker;

    protected function setUp()
    {
        $this->unpacker = new BufferUnpacker();
    }

    /**
     * @dataProvider MessagePack\Tests\DataProvider::provideUnpackData
     */
    public function testUnpack($title, $raw, $packed)
    {
        $this->unpacker->reset($packed);
        $isOrHasObject = is_object($raw) || is_array($raw);

        $isOrHasObject
            ? $this->assertEquals($raw, $this->unpacker->unpack())
            : $this->assertSame($raw, $this->unpacker->unpack());
    }

    /**
     * @expectedException \MessagePack\Exception\InsufficientDataException
     * @expectedExceptionMessage Not enough data to unpack: need 1, have 0.
     */
    public function testUnpackEmptyBuffer()
    {
        $this->unpacker->unpack();
    }

    /**
     * @expectedException \MessagePack\Exception\UnpackException
     * @expectedExceptionMessage Unknown code: 0xc1.
     */
    public function testUnknownCodeThrowsException()
    {
        $this->unpacker->reset("\xc1")->unpack();
    }

    /**
     * @expectedException \MessagePack\Exception\IntegerOverflowException
     * @expectedExceptionMessage The value is too large: 18446744073709551615.
     */
    public function testUnpackBigintThrowsException()
    {
        $this->unpacker->reset("\xcf"."\xff\xff\xff\xff"."\xff\xff\xff\xff");

        $this->unpacker->unpack();
    }

    public function testUnpackBigintAsString()
    {
        $unpacker = new BufferUnpacker([
            BufferUnpacker::BIGINT_MODE => BufferUnpacker::BIGINT_MODE_STR,
        ]);

        $unpacker->reset("\xcf"."\xff\xff\xff\xff"."\xff\xff\xff\xff");

        $this->assertSame('18446744073709551615', $unpacker->unpack());
    }

    /**
     * @requires extension gmp
     */
    public function testUnpackBigintAsGmp()
    {
        $unpacker = new BufferUnpacker([
            BufferUnpacker::BIGINT_MODE => BufferUnpacker::BIGINT_MODE_GMP,
        ]);

        $unpacker->reset("\xcf"."\xff\xff\xff\xff"."\xff\xff\xff\xff");
        $bigint = $unpacker->unpack();

        if (PHP_VERSION_ID < 50600) {
            $this->assertInternalType('resource', $bigint);
        } else {
            $this->assertInstanceOf('GMP', $bigint);
        }

        $this->assertSame('18446744073709551615', gmp_strval($bigint));
    }

    /**
     * @expectedException \MessagePack\Exception\InsufficientDataException
     * @expectedExceptionMessage Not enough data to unpack: need 1, have 0.
     */
    public function testReset()
    {
        $this->unpacker->append("\xc3")->reset()->unpack();
    }

    public function testResetWithBuffer()
    {
        $this->unpacker->append("\xc2")->reset("\xc3");

        $this->assertTrue($this->unpacker->unpack());
    }

    public function testTryUnpack()
    {
        $foo = [1, 2];
        $bar = 'bar';
        $packed = "\x92\x01\x02\xa3\x62\x61\x72";

        $this->unpacker->append($packed[0]);
        $this->assertSame([], $this->unpacker->tryUnpack());

        $this->unpacker->append($packed[1]);
        $this->assertSame([], $this->unpacker->tryUnpack());

        $this->unpacker->append($packed[2]);
        $this->assertSame([$foo], $this->unpacker->tryUnpack());

        $this->unpacker->append($packed[3]);
        $this->assertSame([], $this->unpacker->tryUnpack());

        $this->unpacker->append($packed[4].$packed[5]);
        $this->assertSame([], $this->unpacker->tryUnpack());

        $this->unpacker->append($packed[6]);
        $this->assertSame([$bar], $this->unpacker->tryUnpack());
    }

    public function testTryUnpackReturnsAllUnpackedData()
    {
        $foo = [1, 2];
        $bar = 'bar';
        $packed = "\x92\x01\x02\xa3\x62\x61\x72";

        $this->unpacker->append($packed);
        $this->assertSame([$foo, $bar], $this->unpacker->tryUnpack());
    }

    public function testTryUnpackTruncatesBuffer()
    {
        $this->unpacker->append("\xc3");

        $this->assertSame([true], $this->unpacker->tryUnpack());

        try {
            $this->unpacker->unpack();
        } catch (InsufficientDataException $e) {
            $this->assertSame('Not enough data to unpack: need 1, have 0.', $e->getMessage());

            return;
        }

        $this->fail('Buffer was not truncated.');
    }
}