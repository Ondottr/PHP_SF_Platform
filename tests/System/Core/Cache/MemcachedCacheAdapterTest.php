<?php declare( strict_types=1 );
/**
 * Created by PhpStorm.
 * User: ondottr
 * Date: 02/02/2023
 * Time: 6:16 PM
 */

namespace PHP_SF\Tests\System\Core\Cache;

use DateInterval;
use PHP_SF\System\Classes\Exception\CacheValueException;
use PHP_SF\System\Classes\Exception\InvalidCacheArgumentException;
use PHP_SF\System\Classes\Exception\UnsupportedPlatformException;
use PHP_SF\System\Core\Cache\MemcachedCacheAdapter;
use PHPUnit\Framework\TestCase;

final class MemcachedCacheAdapterTest extends TestCase
{

    protected function tearDown(): void
    {
        mca()->clear();
    }


    public function testGetInstance(): void
    {
        /**
         * Test if `getInstance` returns an instance of MemcachedCacheAdapter.
         */
        $instance = MemcachedCacheAdapter::getInstance();
        $this->assertInstanceOf( MemcachedCacheAdapter::class, $instance );

        /**
         * Test if `getInstance` returns the same instance of MemcachedCacheAdapter every time it's called.
         */
        $instance1 = MemcachedCacheAdapter::getInstance();
        $instance2 = MemcachedCacheAdapter::getInstance();
        $this->assertSame( $instance1, $instance2 );
    }

    public function testGetMultiple(): void
    {
        $keys = [ 'key1', 'key2', 'key3' ];
        $values = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3'
        ];

        // Set multiple values in the cache
        mca()->setMultiple( $values );

        // Retrieve the values from the cache
        $result = mca()->getMultiple( $keys );

        // Ensure that the values are returned as expected
        $this->assertSame( $values, $result );
    }

    public function testClear(): void
    {
        // Prepare data to be stored in cache
        $keys = [ 'key1', 'key2', 'key3' ];
        $values = [ 'value1', 'value2', 'value3' ];
        $data = array_combine( $keys, $values );

        // Store data in cache
        mca()->setMultiple( $data );

        // Verify data was successfully stored
        foreach ( $keys as $key )
            $this->assertTrue( mca()->has( $key ) );

        // Clear cache
        mca()->clear();

        // Verify data was successfully cleared
        foreach ( $keys as $key )
            $this->assertFalse( mca()->has( $key ) );

    }

    public function testSet(): void
    {
        $key = 'key';
        $value = 'value';

        // Test success
        $result = mca()->set($key, $value);
        $this->assertTrue($result);
        $this->assertEquals($value, mca()->get($key));

        // Test scalar value requirement
        $this->expectException(CacheValueException::class);
        mca()->set($key, []);

        // Test invalid argument
        $this->expectException(InvalidCacheArgumentException::class);
        mca()->set('', $value);

        // Test with ttl
        $ttl = new DateInterval('PT1S');
        $result = mca()->set($key, $value, $ttl);
        $this->assertTrue($result);
        $this->assertEquals($value, mca()->get($key));
        sleep(2);
        $this->assertFalse(mca()->get($key));
    }

    public function testDeleteMultiple(): void
    {
        // Prepare sample data
        $keys = [ 'key1', 'key2', 'key3' ];
        $values = [ 1, 2, 3 ];

        // Store sample data
        foreach ( $keys as $i => $key )
            mca()->set( $key, $values[ $i ] );

        // Check if sample data has been stored
        $this->assertEquals( $values[0], mca()->get( $keys[0] ) );
        $this->assertEquals( $values[1], mca()->get( $keys[1] ) );
        $this->assertEquals( $values[2], mca()->get( $keys[2] ) );

        // Call deleteMultiple method
        $result = mca()->deleteMultiple( $keys );

        // Check if deleteMultiple method returned true
        $this->assertTrue( $result );

        // Check if sample data has been deleted
        $this->assertNull( mca()->get( $keys[0] ) );
        $this->assertNull( mca()->get( $keys[1] ) );
        $this->assertNull( mca()->get( $keys[2] ) );
    }

    public function testDelete(): void
    {
        $key   = 'test_key';
        $value = 'test_value';

        mca()->set( $key, $value );
        $this->assertTrue( mca()->has( $key ) );

        mca()->delete( $key );
        $this->assertFalse( mca()->has( $key ) );
    }

    public function testGet(): void
    {
        // Test data
        $key   = 'test_key';
        $value = 'test_value';

        // Set value in cache
        mca()->set( $key, $value );

        // Check if the value exists in cache
        $result = mca()->get( $key );
        $this->assertSame( $value, $result );

        // Try to get non-existing key
        $result = mca()->get( 'non_existing_key' );
        $this->assertNull( $result );
    }

    public function testDeleteByKeyPattern(): void
    {
        $this->expectException( UnsupportedPlatformException::class );
        mca()->deleteByKeyPattern( 'key_pattern' );
    }

    public function testSetMultiple(): void
    {
        $keys = [ 'key1', 'key2', 'key3' ];
        $values = [ 'value1', 'value2', 'value3' ];
        $ttl = new DateInterval( 'PT10S' );
        $items = array_combine( $keys, $values );

        mca()->setMultiple( $items, $ttl );

        $result = mca()->getMultiple( $keys );

        $this->assertSame( $items, $result );
    }

    public function testHas(): void
    {
        // Check non-existing key
        $this->assertFalse( mca()->has( 'non_existing_key' ) );

        // Check existing key
        mca()->set( 'existing_key', 'existing_value' );
        $this->assertTrue( mca()->has( 'existing_key' ) );
    }

}
