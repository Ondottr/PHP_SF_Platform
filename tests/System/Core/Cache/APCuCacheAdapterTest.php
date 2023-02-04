<?php declare( strict_types=1 );
/**
 * Created by PhpStorm.
 * User: ondottr
 * Date: 02/02/2023
 * Time: 6:16 PM
 */

namespace PHP_SF\Tests\System\Core\Cache;

use DateInterval;
use PHP_SF\System\Classes\Exception\CacheKeyExceptionCache;
use PHP_SF\System\Classes\Exception\CacheValueException;
use PHP_SF\System\Classes\Exception\InvalidCacheArgumentException;
use PHP_SF\System\Core\Cache\APCuCacheAdapter;
use PHPUnit\Framework\TestCase;

final class APCuCacheAdapterTest extends TestCase
{

    private bool $isAPCuEnabled = false;

    public function __construct()
    {
        $this->isAPCuEnabled = function_exists( 'apcu_enabled' ) && apcu_enabled();

        parent::__construct();
    }

    protected function setUp(): void
    {
        if ( $this->isAPCuEnabled === false )
            $this->markTestSkipped( 'APCu is not enabled' );

    }

    protected function tearDown(): void
    {
        aca()->clear();
    }


    public function testGetInstance(): void
    {

        /**
         * Test if `getInstance` returns an instance of APCuCacheAdapter.
         */
        $instance = APCuCacheAdapter::getInstance();
        $this->assertInstanceOf( APCuCacheAdapter::class, $instance );

        /**
         * Test if `getInstance` returns the same instance of APCuCacheAdapter every time it's called.
         */
        $instance1 = APCuCacheAdapter::getInstance();
        $instance2 = APCuCacheAdapter::getInstance();
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
        aca()->setMultiple( $values );

        // Retrieve the values from the cache
        $result = aca()->getMultiple( $keys );

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
        aca()->setMultiple( $data );

        // Verify data was successfully stored
        foreach ( $keys as $key )
            $this->assertTrue( aca()->has( $key ) );

        // Clear cache
        aca()->clear();

        // Verify data was successfully cleared
        foreach ( $keys as $key )
            $this->assertFalse( aca()->has( $key ) );

    }

    public function testSet(): void
    {
        $key = 'key';
        $value = 'value';

        // Test success
        $result = aca()->set($key, $value);
        $this->assertTrue($result);
        $this->assertEquals($value, aca()->get($key));

        // Test scalar value requirement
        $this->expectException(CacheValueException::class);
        aca()->set($key, []);

        // Test invalid argument
        $this->expectException(InvalidCacheArgumentException::class);
        aca()->set('', $value);

        // Test with ttl
        $ttl = new DateInterval('PT1S');
        $result = aca()->set($key, $value, $ttl);
        $this->assertTrue($result);
        $this->assertEquals($value, aca()->get($key));
        sleep(2);
        $this->assertFalse(aca()->get($key));
    }

    public function testDeleteMultiple(): void
    {
        // Prepare sample data
        $keys = [ 'key1', 'key2', 'key3' ];
        $values = [ 1, 2, 3 ];

        // Store sample data
        foreach ( $keys as $i => $key )
            aca()->set( $key, $values[ $i ] );

        // Check if sample data has been stored
        $this->assertEquals( $values[0], aca()->get( $keys[0] ) );
        $this->assertEquals( $values[1], aca()->get( $keys[1] ) );
        $this->assertEquals( $values[2], aca()->get( $keys[2] ) );

        // Call deleteMultiple method
        $result = aca()->deleteMultiple( $keys );

        // Check if deleteMultiple method returned true
        $this->assertTrue( $result );

        // Check if sample data has been deleted
        $this->assertNull( aca()->get( $keys[0] ) );
        $this->assertNull( aca()->get( $keys[1] ) );
        $this->assertNull( aca()->get( $keys[2] ) );
    }

    public function testDelete(): void
    {
        $key   = 'test_key';
        $value = 'test_value';

        aca()->set( $key, $value );
        $this->assertTrue( aca()->has( $key ) );

        aca()->delete( $key );
        $this->assertFalse( aca()->has( $key ) );
    }

    public function testGet(): void
    {
        // Test data
        $key   = 'test_key';
        $value = 'test_value';

        // Set value in cache
        aca()->set( $key, $value );

        // Check if the value exists in cache
        $result = aca()->get( $key );
        $this->assertSame( $value, $result );

        // Try to get non-existing key
        $result = aca()->get( 'non_existing_key' );
        $this->assertNull( $result );
    }

    public function testDeleteByKeyPattern(): void
    {
        // Prepare sample data
        $keys = [ 'key1', 'key2', 'key3' ];
        $values = [ 1, 2, 3 ];

        // Store sample data
        foreach ( $keys as $i => $key )
            aca()->set( $key, $values[ $i ] );

        // Check if sample data has been stored
        $this->assertEquals( $values[0], aca()->get( $keys[0] ) );
        $this->assertEquals( $values[1], aca()->get( $keys[1] ) );
        $this->assertEquals( $values[2], aca()->get( $keys[2] ) );

        // Call deleteMultiple method with * wildcard at the end
        $result = aca()->deleteByKeyPattern( 'key*' );

        // Check if deleteMultiple method returned true
        $this->assertTrue( $result );

        // Check if sample data has been deleted
        $this->assertNull( aca()->get( $keys[0] ) );
        $this->assertNull( aca()->get( $keys[1] ) );
        $this->assertNull( aca()->get( $keys[2] ) );


        // Store sample data
        foreach ( $keys as $i => $key )
            aca()->set( $key, $values[ $i ] );

        // Call deleteMultiple method with * wildcard at the beginning
        $result = aca()->deleteByKeyPattern( '*y1' );
        $result = $result && aca()->deleteByKeyPattern( '*y2' );
        $result = $result && aca()->deleteByKeyPattern( '*y3' );

        // Check if deleteMultiple method returned true
        $this->assertTrue( $result );

        // Check if sample data has been deleted
        $this->assertNull( aca()->get( $keys[0] ) );
        $this->assertNull( aca()->get( $keys[1] ) );
        $this->assertNull( aca()->get( $keys[2] ) );


        // Store sample data
        foreach ( $keys as $i => $key )
            aca()->set( $key, $values[ $i ] );

        // Call deleteMultiple method with * wildcard at the beginning and at the end
        $result = aca()->deleteByKeyPattern( '*ey*' );

        // Check if deleteMultiple method returned true
        $this->assertTrue( $result );

        // Check if sample data has been deleted
        $this->assertNull( aca()->get( $keys[0] ) );
        $this->assertNull( aca()->get( $keys[1] ) );
        $this->assertNull( aca()->get( $keys[2] ) );


        // Store sample data
        foreach ( $keys as $i => $key )
            aca()->set( $key, $values[ $i ] );

        // Call deleteMultiple method with * only
        $result = aca()->deleteByKeyPattern( '*' );

        // Check if deleteMultiple method returned true
        $this->assertTrue( $result );

        // Check if sample data has been deleted
        $this->assertNull( aca()->get( $keys[0] ) );
        $this->assertNull( aca()->get( $keys[1] ) );
        $this->assertNull( aca()->get( $keys[2] ) );


        // Call deleteMultiple method with * in the middle of the pattern
        $this->expectException( CacheKeyExceptionCache::class );
        aca()->deleteByKeyPattern( 'key*key' );
    }

    public function testSetMultiple(): void
    {
        $keys = [ 'key1', 'key2', 'key3' ];
        $values = [ 'value1', 'value2', 'value3' ];
        $ttl = new DateInterval( 'PT10S' );
        $items = array_combine( $keys, $values );

        aca()->setMultiple( $items, $ttl );

        $result = aca()->getMultiple( $keys );

        $this->assertSame( $items, $result );
    }

    public function testHas(): void
    {
        // Check non-existing key
        $this->assertFalse( aca()->has( 'non_existing_key' ) );

        // Check existing key
        aca()->set( 'existing_key', 'existing_value' );
        $this->assertTrue( aca()->has( 'existing_key' ) );
    }

}
