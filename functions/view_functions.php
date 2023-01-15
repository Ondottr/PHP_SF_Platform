<?php declare( strict_types=1 );

use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\ExpectedValues;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;

function asset( string $path ): string
{
    if ( file_exists( sprintf( "%s/../../public/%s", __DIR__, $path ) ) === false )
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


function formInput(
    string     $name,
    #[ArrayShape( [ 'min' => 'int', 0 => 'int', 'max' => 'int', 1 => 'int' ] )]
    array      $length = [ 1, 255 ],
    string     $type = 'text',
    bool       $isRequired = true,
    #[ArrayShape( [ 'min' => 'int', 0 => 'int', 'max' => 'int', 1 => 'int' ] )]
    array      $minMax = [],
    string|int $defaultValue = null,
    string     $placeholder = '',
    string|int $step = null,
    #[ArrayShape( [ 'string' ] )]
    array      $classes = [],
    #[ArrayShape( [ 'string' ] )]
    array      $styles = [],
    string|int $id = '',
    #[ArrayShape( [ 'string' ] )]
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
