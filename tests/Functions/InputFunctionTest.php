<?php declare( strict_types=1 );
/**
 * Created by PhpStorm.
 * User: ondottr
 * Date: 03/02/2023
 * Time: 7:54 AM
 */

namespace PHP_SF\Tests\Functions;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class InputFunctionTest extends TestCase
{

    public function testId(): void
    {
        $actual = input( "input_name", id: 'number' );
        $expected = "<input type='text' required id='number' name='input_name' minlength='1' maxlength='255'>";

        $this->assertEquals($expected, $actual);

        $actual = input( "input_name" );
        $expected = "<input type='text' required id='input_name' name='input_name' minlength='1' maxlength='255'>";

        $this->assertEquals($expected, $actual);
    }

    public function testRequired(): void
    {
        $actual = input("input_name" );
        $expected = "<input type='text' required id='input_name' name='input_name' minlength='1' maxlength='255'>";

        $this->assertEquals($expected, $actual);

        $actual = input("input_name", [1, 255], "text", false);
        $expected = "<input type='text' id='input_name' name='input_name' minlength='1' maxlength='255'>";

        $this->assertEquals($expected, $actual);
    }

    public function testPlaceholder(): void
    {
        $actual = input( "input_name", placeholder: 'Default placeholder text' );
        $expected = "<input type='text' required id='input_name' name='input_name' placeholder='Default placeholder text' minlength='1' maxlength='255'>";

        $this->assertEquals($expected, $actual);
    }

    public function testDefaultValue(): void
    {
        $actual = input("input_name", [1, 255], "text", true, [], "default_value");
        $expected = "<input type='text' required id='input_name' name='input_name' value='default_value' minlength='1' maxlength='255'>";
        $this->assertEquals($expected, $actual);
    }


    # region Name
    /**
     * @dataProvider nameProvider
     */
    public function testName( string $name, string $expected ): void
    {
        $actual = input( $name );

        $this->assertStringContainsString( $expected, $actual );
    }

    public function nameProvider(): array
    {
        return [
            [ 'first_name', "<input type='text' required id='first_name' name='first_name' minlength='1' maxlength='255'>" ],
            [ 'last_name', "<input type='text' required id='last_name' name='last_name' minlength='1' maxlength='255'>" ],
            [ 'email', "<input type='text' required id='email' name='email' minlength='1' maxlength='255'>" ],
        ];
    }
    # endregion


    # region Length
    /**
     * Test if length is an array of two integers.
     *
     * @dataProvider lengthProvider
     */
    public function testLengthIsArrayOfTwoIntegers( array $length, string $expected): void
    {
        $this->expectException($expected);
        input('name', $length);
    }

    public function lengthProvider(): array
    {
        return [
            [ [ 1, 'a' ], InvalidArgumentException::class ],
            [ [ 'a', 'b' ], InvalidArgumentException::class ],
            [ [1], InvalidArgumentException::class ],
            [ [], InvalidArgumentException::class ],
        ];
    }
    # endregion


    # region Type
    public function testTypeIsText(): void
    {
        $actual = input("input_name" );
        $expected = "<input type='text' required id='input_name' name='input_name' minlength='1' maxlength='255'>";

        $this->assertEquals($expected, $actual);
    }

    public function testTypeIsNumber(): void
    {
        $actual = input("input_name", [1, 255], "number", true, [], null, null, 1);
        $expected = "<input type='number' required id='input_name' name='input_name' step='1' minlength='1' maxlength='255'>";

        $this->assertEquals($expected, $actual);

        // Test with step as integer
        $actual = input("input_name", [1, 255], "number", true, [], null, null, 2);
        $expected = "<input type='number' required id='input_name' name='input_name' step='2' minlength='1' maxlength='255'>";
        $this->assertEquals($expected, $actual);

        // Test with step as float
        $actual = input("input_name", [1, 255], "number", true, [], null, null, 0.5);
        $expected = "<input type='number' required id='input_name' name='input_name' step='0.5' minlength='1' maxlength='255'>";
        $this->assertEquals($expected, $actual);

        // Test step must be set
        $this->expectException(InvalidArgumentException::class);
        input("input_name", [1, 255], "number" );
    }

    public function testTypeIsPassword(): void
    {
        $actual = input("input_name", [1, 255], "password");
        $expected = "<input type='password' required id='input_name' name='input_name' minlength='1' maxlength='255'>";

        $this->assertEquals($expected, $actual);
    }
    # endregion


    # region minMax
    /**
     * @dataProvider minMaxProvider
     */
    public function testMinMax(array $minMax, string $expected): void
    {
        $actual = input('input_name', [1, 255], 'number', true, $minMax, step: 1 );
        $this->assertStringContainsString($expected, $actual);
    }

    public function minMaxProvider(): array
    {
        return [
            [[1, 10], "min='1' max='10'"],
            [[2, 20], "min='2' max='20'"],
        ];
    }

    /**
     * Test if minMax is an array of two integers.
     *
     * @dataProvider invalidMinMaxProvider
     */
    public function testInvalidMinMax(array $minMax, string $expected): void
    {
        $this->expectException($expected);
        input('input_name', [1, 255], 'number', true, $minMax, step: 1 );
    }

    public function invalidMinMaxProvider(): array
    {
        return [
            [[1, 'a'], InvalidArgumentException::class],
            [['a', 'b'], InvalidArgumentException::class],
            [[1], InvalidArgumentException::class],
        ];
    }
    # endregion


    # region Classes
    public function testClasses(): void
    {
        // Valid values
        $actual = input( "input_name", classes: [ 'class1', 'class2' ] );
        $expected = "<input type='text' required id='input_name' name='input_name' minlength='1' maxlength='255' class='class1 class2'>";
        $this->assertEquals($expected, $actual);

        // Test not unique values
        $this->expectException( InvalidArgumentException::class );
        input( "input_name", classes: [ 'class1', 'class1' ] );
    }

    public function testClassesInvalidValues(): void
    {
        // Invalid values
        $this->expectException( InvalidArgumentException::class );
        input( "input_name", classes: [ 'class1', 1 ] );
    }
    # endregion


    # region Styles
    public function testStyles(): void
    {
        // Valid values
        $actual = input( "input_name", styles: [ 'color' => 'red', 'background-color' => 'blue' ] );
        $expected = "<input type='text' required id='input_name' name='input_name' minlength='1' maxlength='255' style='color: red; background-color: blue;'>";
        $this->assertEquals($expected, $actual);
    }

    public function testStylesInvalidKey(): void
    {
        $this->expectException( InvalidArgumentException::class );
        input( "input_name", styles: [ 'color' => 'red', 1 => 'blue' ] );
    }

    public function testStylesInvalidValue(): void
    {
        $this->expectException( InvalidArgumentException::class );
        input( "input_name", styles: [ 'color' => 'red', 'background-color' => 1 ] );
    }
    # endregion


    # region Custom Attributes
    public function testCustomAttributes(): void
    {
        // Valid values
        $actual = input( "input_name", customAttributes: [ 'data-attr' => 'value', 'data-attr2' => 'value2' ] );
        $expected = "<input type='text' required id='input_name' name='input_name' minlength='1' maxlength='255' data-attr='value' data-attr2='value2'>";
        $this->assertEquals($expected, $actual);
    }

    public function testCustomAttributesInvalidKey(): void
    {
        $this->expectException( InvalidArgumentException::class );
        input( "input_name", customAttributes: [ 'data-attr' => 'value', 1 => 'value2' ] );
    }

    public function testCustomAttributesInvalidValue(): void
    {
        $this->expectException( InvalidArgumentException::class );
        input( "input_name", customAttributes: [ 'data-attr' => 'value', 'data-attr2' => 1 ] );
    }
    # endregion

}