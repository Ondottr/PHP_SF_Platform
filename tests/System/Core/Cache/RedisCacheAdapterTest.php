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
use PHP_SF\System\Core\Cache\RedisCacheAdapter;
use PHPUnit\Framework\TestCase;

final class RedisCacheAdapterTest extends TestCase
{


    protected function tearDown(): void
    {
        rca()->clear();
    }


    public function testGetInstance(): void
    {
        /**
         * Test if `getInstance` returns an instance of RedisCacheAdapter.
         */
        $instance = RedisCacheAdapter::getInstance();
        $this->assertInstanceOf( RedisCacheAdapter::class, $instance );

        /**
         * Test if `getInstance` returns the same instance of RedisCacheAdapter every time it's called.
         */
        $instance1 = RedisCacheAdapter::getInstance();
        $instance2 = RedisCacheAdapter::getInstance();
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
        rca()->setMultiple( $values );

        // Retrieve the values from the cache
        $result = rca()->getMultiple( $keys );

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
        rca()->setMultiple( $data );

        // Verify data was successfully stored
        foreach ( $keys as $key )
            $this->assertTrue( rca()->has( $key ) );

        // Clear cache
        rca()->clear();

        // Verify data was successfully cleared
        foreach ( $keys as $key )
            $this->assertFalse( rca()->has( $key ) );

    }

    public function testSet(): void
    {
        $key = 'key';
        $value = 'value';

        // Test success
        $result = rca()->set($key, $value);
        $this->assertTrue($result);
        $this->assertEquals($value, rca()->get($key));

        // Test scalar value requirement
        $this->expectException(CacheValueException::class);
        rca()->set($key, []);

        // Test invalid argument
        $this->expectException(InvalidCacheArgumentException::class);
        rca()->set('', $value);

        // Test with ttl
        $ttl = new DateInterval('PT1S');
        $result = rca()->set($key, $value, $ttl);
        $this->assertTrue($result);
        $this->assertEquals($value, rca()->get($key));
        sleep(2);
        $this->assertFalse(rca()->get($key));
    }

    public function testDeleteMultiple(): void
    {
        // Prepare sample data
        $keys = [ 'key1', 'key2', 'key3' ];
        $values = [ 1, 2, 3 ];

        // Store sample data
        foreach ( $keys as $i => $key )
            rca()->set( $key, $values[ $i ] );

        // Check if sample data has been stored
        $this->assertEquals( $values[0], rca()->get( $keys[0] ) );
        $this->assertEquals( $values[1], rca()->get( $keys[1] ) );
        $this->assertEquals( $values[2], rca()->get( $keys[2] ) );

        // Call deleteMultiple method
        $result = rca()->deleteMultiple( $keys );

        // Check if deleteMultiple method returned true
        $this->assertTrue( $result );

        // Check if sample data has been deleted
        $this->assertNull( rca()->get( $keys[0] ) );
        $this->assertNull( rca()->get( $keys[1] ) );
        $this->assertNull( rca()->get( $keys[2] ) );
    }

    public function testDelete(): void
    {
        $key   = 'test_key';
        $value = 'test_value';

        rca()->set( $key, $value );
        $this->assertTrue( rca()->has( $key ) );

        rca()->delete( $key );
        $this->assertFalse( rca()->has( $key ) );
    }

    public function testGet(): void
    {
        // Test data
        $key   = 'test_key';
        $value = 'test_value';

        // Set value in cache
        rca()->set( $key, $value );

        // Check if the value exists in cache
        $result = rca()->get( $key );
        $this->assertSame( $value, $result );

        // Try to get non-existing key
        $result = rca()->get( 'non_existing_key' );
        $this->assertNull( $result );
    }

    public function testDeleteByKeyPattern(): void
    {
        // Prepare sample data
        $keys = [ 'key1', 'key2', 'key3' ];
        $values = [ 1, 2, 3 ];

        // Store sample data
        foreach ( $keys as $i => $key )
            rca()->set( $key, $values[ $i ] );

        // Check if sample data has been stored
        $this->assertEquals( $values[0], rca()->get( $keys[0] ) );
        $this->assertEquals( $values[1], rca()->get( $keys[1] ) );
        $this->assertEquals( $values[2], rca()->get( $keys[2] ) );

        // Call deleteMultiple method with * wildcard at the end
        $result = rca()->deleteByKeyPattern( 'key*' );

        // Check if deleteMultiple method returned true
        $this->assertTrue( $result );

        // Check if sample data has been deleted
        $this->assertNull( rca()->get( $keys[0] ) );
        $this->assertNull( rca()->get( $keys[1] ) );
        $this->assertNull( rca()->get( $keys[2] ) );

        // Store sample data
        foreach ( $keys as $i => $key )
            rca()->set( $key, $values[ $i ] );

        // Call deleteMultiple method with * wildcard at the beginning
        $result = rca()->deleteByKeyPattern( '*y1' );
        $result = $result && rca()->deleteByKeyPattern( '*y2' );
        $result = $result && rca()->deleteByKeyPattern( '*y3' );

        // Check if deleteMultiple method returned true
        $this->assertTrue( $result );

        // Check if sample data has been deleted
        $this->assertNull( rca()->get( $keys[0] ) );
        $this->assertNull( rca()->get( $keys[1] ) );
        $this->assertNull( rca()->get( $keys[2] ) );

        // Store sample data
        foreach ( $keys as $i => $key )
            rca()->set( $key, $values[ $i ] );

        // Call deleteMultiple method with * wildcard at the beginning and at the end
        $result = rca()->deleteByKeyPattern( '*ey*' );

        // Check if deleteMultiple method returned true
        $this->assertTrue( $result );

        // Check if sample data has been deleted
        $this->assertNull( rca()->get( $keys[0] ) );
        $this->assertNull( rca()->get( $keys[1] ) );
        $this->assertNull( rca()->get( $keys[2] ) );
    }

    public function testSetMultiple(): void
    {
        $keys = [ 'key1', 'key2', 'key3' ];
        $values = [ 'value1', 'value2', 'value3' ];
        $ttl = new DateInterval( 'PT10S' );
        $items = array_combine( $keys, $values );

        rca()->setMultiple( $items, $ttl );

        $result = rca()->getMultiple( $keys );

        $this->assertSame( $items, $result );
    }

    public function testHas(): void
    {
        // Check non-existing key
        $this->assertFalse( rca()->has( 'non_existing_key' ) );

        // Check existing key
        rca()->set( 'existing_key', 'existing_value' );
        $this->assertTrue( rca()->has( 'existing_key' ) );
    }

}
