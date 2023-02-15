<?php declare( strict_types=1 );

use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\ExpectedValues;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;

function asset( string $path ): string
{
    if ( file_exists( sprintf( "%s/public/%s", project_dir(), $path ) ) === false )
        throw new FileNotFoundException( "Asset not found: $path" );

    return "/$path";
}

function pageTitle(): string
{
    return s()->get( 'page_title', APPLICATION_NAME );
}

/**
 * @noinspection GlobalVariableUsageInspection
 */
function getMessages( string $messageType = null ): array|string|false
{
    if ( !array_key_exists( 'messages', $GLOBALS ) || empty( $GLOBALS['messages'] ) ) {
        return [];
    }

    if ( $messageType === null ) {
        return $GLOBALS['messages'];
    }

    if ( isset( $GLOBALS['messages'][ $messageType ] ) ) {
        return $GLOBALS['messages'][ $messageType ];
    }

    return false;
}

/**
 * @noinspection GlobalVariableUsageInspection
 */
function getErrors( string $errorType = null ): array|string|false
{
    if ( !array_key_exists( 'errors', $GLOBALS ) || empty( $GLOBALS['errors'] ) ) {
        return [];
    }

    if ( $errorType === null ) {
        return $GLOBALS['errors'];
    }

    if ( isset( $GLOBALS['errors'][ $errorType ] ) ) {
        return $GLOBALS['errors'][ $errorType ];
    }

    return false;
}

/**
 * @noinspection GlobalVariableUsageInspection
 */
function formValue( string $name ): string
{
    return (string)( $GLOBALS['form_data'][ $name ] ?? '' );
}


//function showMessages( string $messageType = null, bool $onlyFirst = true ): void
//{
//    $messages = getMessages( $messageType );
//
//    if ( empty( $messages ) )
//        return;
//
//    if ( is_iterable( $messages ) === false )
//        echo $messages;
//
//    else {
//        foreach ( $messages as $message ) {
//            echo $message . ' <br />';
//
//            if ( $onlyFirst )
//                break;
//        }
//    }
//}
//
//
//function showErrors( string $errorType = null, bool $onlyFirst = true ): void
//{
//    $errors = getErrors( $errorType );
//
//    if ( empty( $errors ) )
//        return;
//
//    if ( is_iterable( $errors ) === false )
//        echo $errors;
//
//    else {
//        foreach ( $errors as $error ) {
//            echo $error . ' <br />';
//
//            if ( $onlyFirst )
//                break;
//
//        }
//    }
//}


/**
 * Creates an HTML input element.
 *
 * @param string $name Name of the input element
 * @param array $length An array of two integers specifying the minimum and maximum length of the input.
 * @param string $type Type of the input element, defaults to "text".
 * @param bool $isRequired Indicates whether the input is required, defaults to true.
 * @param array $minMax An array of two integers or an empty array specifying the minimum and maximum value of the input, applies only to type "number".
 * @param string|int|float $defaultValue Default value of the input.
 * @param string $placeholder Placeholder text for the input.
 * @param int|float $step Step for the input of type "number".
 * @param array $classes Classes for the input element, must be unique.
 * @param array $styles Styles for the input element, given as key-value pairs.
 * @param string $id ID for the input element.
 * @param array $customAttributes Custom attributes for the input element, given as key-value pairs.
 *
 * @return string The HTML code for the input element.
 */
function input(
    string $name,
    array $length = [ 1, 255 ],
    string $type = 'text',
    bool $isRequired = true,
    array $minMax = [],
    string|int|float $defaultValue = null,
    string $placeholder = null,
    int|float $step = null,
    array $classes = [],
    array $styles = [],
    string $id = null,
    array $customAttributes = []
): string
{
    if ( count( $length ) !== 2 ||
        is_int( $length[0] ) === false || is_int( $length[1] ) === false
    )
        throw new InvalidArgumentException( 'Length must be an array of two integers' );

    if ( empty( $minMax ) === false )
        if ( count( $minMax ) !== 2 ||
            ( is_int( $minMax[0] ) === false && is_float( $minMax[0] ) === false ) ||
            ( is_int( $minMax[1] ) === false && is_float( $minMax[1] ) === false )
        )
            throw new InvalidArgumentException( 'MinMax must be an array of two integers or floats or an empty array' );

    if ( count( $classes ) !== count( array_unique( $classes ) ) )
        throw new InvalidArgumentException( 'Classes must be unique' );

    if ( empty( $classes ) === false )
        foreach ( $classes as $class )
            if ( is_string( $class ) === false )
                throw new InvalidArgumentException( 'Classes must be an array of strings' );

    if ( empty( $styles ) === false )
        foreach ( $styles as $key => $value )
            if ( is_string( $key ) === false || is_string( $value ) === false )
                throw new InvalidArgumentException( 'Key and value in styles must be strings' );

    if ( empty( $customAttributes ) === false )
        foreach ( $customAttributes as $key => $value )
            if ( is_string( $key ) === false || is_string( $value ) === false )
                throw new InvalidArgumentException( 'Key and value in customAttributes must be strings' );

    if ( $type === 'number' && $step === null )
        throw new InvalidArgumentException( 'Step must be set for number input' );

    $inputStr = "<input type='$type'";

    if ( $isRequired )
        $inputStr .= " required";

    $inputStr .= $id ? " id='$id'" : " id='$name'";
    $inputStr .= " name='$name'";

    if ( $type === 'number' ) {
        $inputStr .= $step !== null ? " step='$step'" : '';

        if ( empty( $minMax ) === false )
            $inputStr .= sprintf( " min='%s' max='%s'", $minMax[0], $minMax[1] );

    }

    $inputStr .= $placeholder ? " placeholder='$placeholder'" : '';

    if ( $defaultValue !== null )
        $inputStr .= sprintf( " value='%s'", $defaultValue );

    elseif ( empty( formValue( $name ) ) === false )
        $inputStr .= sprintf( " value='%s'", formValue( $name ) );

    if ( empty( $length ) === false )
        $inputStr .= sprintf( " minlength='%d' maxlength='%d'", $length[0], $length[1] );


    $inputStr .= empty( $classes ) === false ? " class='" . implode( ' ', $classes ) . "'" : '';
    $inputStr .= empty( $styles ) === false ?
        sprintf( " style='%s;'",
                 implode( '; ',
                          array_map(
                              function ( $v, $k ) { return "$k: $v"; }, $styles, array_keys( $styles )
                          )
                 )
        ) : '';

    foreach ( $customAttributes as $attr => $value )
        $inputStr .= " $attr='$value'";

    $inputStr .= ">";

    return $inputStr;
}


/**
 * @deprecated Use {@link input()} instead
 */
function formInput(
    string     $name,
    array      $length = [ 1, 255 ],
    string     $type = 'text',
    bool       $isRequired = true,
    array      $minMax = [],
    string|int|float $defaultValue = null,
    string     $placeholder = '',
    string|int $step = null,
    array      $classes = [],
    array      $styles = [],
    string|int $id = '',
    array      $customAttributes = []
): void
{
    $inputStr = '<input ';

    $inputStr .= sprintf( ' type="%s" ', $type );

    if ( $isRequired )
        $inputStr .= ' required ';

    if ( empty( $name ) === false || empty( $id ) === false )
        $inputStr .= sprintf( ' id="%s" ', ( !empty( $id ) ? $id : $name ) );

    if ( empty( $name ) === false )
        $inputStr .= sprintf( ' name="%s" ', $name );

    if ( $type === 'number' ) {
        if ( $step !== null )
            $inputStr .= sprintf( ' step="%s" ', $step );

        if ( empty( $minMax ) === false )
            $inputStr .= sprintf( ' min="%d" max="%d" ', $minMax['min'] ?? $minMax[0], $minMax['max'] ?? $minMax[1] );
    }

    if ( $defaultValue !== null )
        $inputStr .= sprintf( ' value="%s" ', $defaultValue );

    elseif ( empty( formValue( $name ) ) === false )
        $inputStr .= sprintf( ' value="%s" ', formValue( $name ) );

    if ( empty( $placeholder ) === false )
        $inputStr .= sprintf( ' placeholder="%s" ', $placeholder );

    if ( empty( $length ) === false )
        $inputStr .= sprintf( ' minlength="%d" maxlength="%d" ', $length['min'] ?? $length[0], $length['max'] ?? $length[1] );

    if ( empty( $classes ) === false )
        $inputStr .= sprintf( ' class="%s" ', implode( ' ', $classes ) );


    if ( empty( $styles ) === false ) {
        $inputStr .= ' style="';
        foreach ( $styles as $styleName => $styleValue )
            $inputStr .= sprintf( '%s: %s; ', $styleName, $styleValue );

        $inputStr .= '" ';
    }

    foreach ( $customAttributes as $attr => $attrValue )
        $inputStr .= " $attr=\"$attrValue\"";

    $inputStr .= '>';

    echo trim( str_replace( PHP_EOL, '', preg_replace( '/\s+/', ' ', $inputStr ) ) );
}

function formCheckbox(
    string     $name,
    bool       $isRequired = true,
    bool       $checked = null,
    #[ArrayShape( [ 'string' ] )]
    array      $classes = [],
    #[ArrayShape( [ 'string' ] )]
    array      $styles = [],
    string|int $id = '',
    #[ArrayShape( [ 'string' ] )]
    array      $customAttributes = []
): void
{
    $inputStr = '<input type="checkbox" ';

    if ( $isRequired )
        $inputStr .= ' required ';

    if ( empty( $name ) === false || empty( $id ) === false )
        $inputStr .= sprintf( ' id="%s" ', ( !empty( $id ) ? $id : $name ) );

    if ( empty( $name ) === false )
        $inputStr .= sprintf( ' name="%s" ', $name );

    if ( $checked === true )
        $inputStr .= ' checked ';
    elseif ( $checked === null && formValue( $name ) === 'on' )
        $inputStr .= ' checked ';

    if ( empty( $classes ) === false )
        $inputStr .= sprintf( ' class="%s" ', implode( ' ', $classes ) );

    if ( empty( $styles ) === false ) {
        $inputStr .= ' style="';
        foreach ( $styles as $styleName => $styleValue )
            $inputStr .= sprintf( '%s: %s; ', $styleName, $styleValue );
        $inputStr .= '" ';
    }

    foreach ( $customAttributes as $attr => $attrValue )
        $inputStr .= " $attr=\"$attrValue\"";

    $inputStr .= '>';

    echo trim( str_replace( PHP_EOL, '', preg_replace( '/\s+/', ' ', $inputStr ) ) );

}

function formTextarea(
    string     $name,
    #[ArrayShape( [ 'min' => 'int', 0 => 'int', 'max' => 'int', 1 => 'int' ] )]
    array      $length = [ 1, 4096 ],
    int        $rows = null,
    string     $cols = null,
    #[ExpectedValues( [ 'soft', 'hard' ] )]
    string     $wrap = 'soft',
    bool       $isRequired = true,
    string     $defaultValue = null,
    string     $placeholder = null,
    #[ArrayShape( [ 'string' ] )]
    array      $classes = [],
    #[ArrayShape( [ 'string' ] )]
    array      $styles = [],
    string|int $id = '',
    #[ArrayShape( [ 'string' ] )]
    array      $customAttributes = []
): void
{
    if ( $wrap !== 'soft' && $wrap !== 'hard' )
        throw new InvalidArgumentException( "Invalid wrap value for '$name' textarea. Valid values are 'soft' and 'hard'." );

    $inputStr = '<textarea ';
    $inputStr .= $isRequired ? ' required ' : '';

    if ( empty( $name ) === false || empty( $id ) === false )
        $inputStr .= sprintf( ' id="%s" ', ( !empty( $id ) ? $id : $name ) );

    if ( empty( $name ) === false )
        $inputStr .= sprintf( ' name="%s" ', $name );

    if ( $rows === null )
        $inputStr .= sprintf( ' rows="%d" ', $rows );

    if ( $cols === null )
        $inputStr .= sprintf( ' cols="%s" ', $cols );

    $inputStr .= sprintf( ' wrap="%s" ', $wrap );

    if ( empty( $placeholder ) === false )
        $inputStr .= sprintf( ' placeholder="%s" ', $placeholder );

    if ( empty( $length ) === false )
        $inputStr .= sprintf( ' minlength="%d" maxlength="%d" ', $length['min'] ?? $length[0], $length['max'] ?? $length[1] );

    if ( empty( $classes ) === false )
        $inputStr .= sprintf( ' class="%s" ', implode( ' ', $classes ) );


    if ( empty( $styles ) === false ) {
        $inputStr .= ' style="';
        foreach ( $styles as $styleName => $styleValue )
            $inputStr .= sprintf( '%s: %s; ', $styleName, $styleValue );
        $inputStr .= '" ';
    }

    foreach ( $customAttributes as $attr => $attrValue )
        $inputStr .= sprintf( ' %s="%s" ', $attr, $attrValue );

    if ( $defaultValue !== null )
        $inputStr .= sprintf( '>%s', $defaultValue );
    elseif ( formValue( $name ) !== null )
        $inputStr .= sprintf( '>%s', formValue( $name ) );

    $inputStr .= '</textarea>';

    echo trim( str_replace( PHP_EOL, '', preg_replace( '/\s+/', ' ', $inputStr ) ) );
}

function formSelect(
    string $name,
    #[ArrayShape( [ 'object' ] )]
    array  $options,
    string $value,
    string|callable $label,
    string $id = '',
    string $onChange = '',
    #[ArrayShape( [ 'value' => 'string', 'label' => 'string' ] )]
    array  $infoField = [],
    bool   $isRequired = true,
    string|int|float $defaultValue = null,
    #[ArrayShape( [ 'string' ] )]
    array  $classes = [],
    #[ArrayShape( [ 'string' ] )]
    array  $styles = [],
    #[ArrayShape( [ 'string' ] )]
    array  $customAttributes = []
): void
{
    if ( $id === '' )
        $id = $name;

    foreach ( $options as $option ) {
        if ( method_exists( $option, $value ) === false )
            throw new InvalidArgumentException( "Invalid value field for '$name' select. The value field must be a public method name of the object." );

        if ( is_string( $label) && method_exists( $option, $label ) === false )
            throw new InvalidArgumentException( "Invalid label field for '$name' select. The label option must be a callable with option object as the first parameter or a public method name of the object." );
    }

    $inputStr = '<select ';
    $inputStr .= $isRequired ? ' required ' : '';

    $inputStr .= sprintf( ' id="%s" ', $id );

    if ( empty( $name ) === false )
        $inputStr .= sprintf( ' name="%s" ', $name );

    if ( empty( $classes ) === false )
        $inputStr .= sprintf( ' class="%s" ', implode( ' ', $classes ) );

    if ( empty( $styles ) === false ) {
        $inputStr .= ' style="';
        foreach ( $styles as $styleName => $styleValue )
            $inputStr .= sprintf( '%s: %s; ', $styleName, $styleValue );
        $inputStr .= '" ';
    }

    foreach ( $customAttributes as $attr => $attrValue )
        $inputStr .= sprintf( ' %s="%s" ', $attr, $attrValue );

    if ( empty( $onChange ) === false )
        $inputStr .= ' onchange="' . $onChange . '" ';

    $inputStr .= '>';

    if ( empty( $infoField ) === false )
        $inputStr .= sprintf( '<option selected disabled value="%s">%s</option>', $infoField['value'], $infoField['label'] );

    foreach ( $options as $option ) {
        $inputStr .= sprintf( '<option value="%s" ', $option->$value() );

        if ( $defaultValue !== null && $defaultValue === $option->$value() )
            $inputStr .= ' selected ';

        if ( is_string( $label ) )
            $inputStr .= sprintf( '>%s</option>', $option->$label() );
        else
            $inputStr .= sprintf( '>%s</option>', $label( $option ) );

        $inputStr .= '</option>';
    }

    $inputStr .= '</select>';

    echo trim( str_replace( PHP_EOL, '', preg_replace( '/\s+/', ' ', $inputStr ) ) );


}