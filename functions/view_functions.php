<?php
declare( strict_types=1 );

use JetBrains\PhpStorm\ArrayShape;

function asset(string $path): string
{
    return "/$path";
}

function getMessages(string $messageType = null): array|string|false
{
    if (!array_key_exists('messages', $GLOBALS) || empty($GLOBALS[ 'messages' ])) {
        return [];
    }

    if ($messageType === null) {
        return $GLOBALS[ 'messages' ];
    }

    if (isset($GLOBALS[ 'messages' ][ $messageType ])) {
        return $GLOBALS[ 'messages' ][ $messageType ];
    }

    return false;
}

function getErrors(string $errorType = null): array|string|false
{
    if (!array_key_exists('errors', $GLOBALS) || empty($GLOBALS[ 'errors' ])) {
        return [];
    }

    if ($errorType === null) {
        return $GLOBALS[ 'errors' ];
    }

    if (isset($GLOBALS[ 'errors' ][ $errorType ])) {
        return $GLOBALS[ 'errors' ][ $errorType ];
    }

    return false;
}

function formValue(string $name): string
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
    #[ArrayShape( [ 'int', 'int' ] )]
    array      $length = [ 1, 255 ],
    string     $type = 'text',
    bool       $isRequired = true,
    array      $minMax = [],
    string|int $defaultValue = '',
    string     $placeholder = '',
    string|int $step = null,
    array      $classes = [],
    array      $styles = [],
    string|int $id = '',
    string     $checkboxValue = '',
    bool       $isChecked = false,
    array      $customAttributes = []
): void {
    $inputStr = '<input ';
    $inputStr .= sprintf(' id="%s" ', ( !empty($id) ? $id : $name ));
    $inputStr .= sprintf( ' type="%s" ', $type );
    $inputStr .= !empty( $checkboxValue ) ? sprintf( ' value="%s" ', $checkboxValue ) : '';
    $inputStr .= ( $type === 'number' && $step !== null ) ? sprintf( ' step="%s" ', $step ) : '';
    $inputStr .= sprintf(' name="%s" ', $name);
    $inputStr .= sprintf(' value="%s" ', ( !empty(formValue($name)) ? formValue($name) : $defaultValue ));
    $inputStr .= $isRequired ? ' required ' : '';
    $inputStr .= !empty($placeholder) ? sprintf(' placeholder="%s" ', $placeholder) : '';
    $inputStr .= !empty($styles) ? sprintf(' style="%s" ', implode('; ', $styles)) : '';
    $inputStr .= !empty($classes) ? sprintf('class="%s"', implode(' ', $classes)) : '';
    $inputStr .= !empty($length) ? sprintf('minlength="%d" maxlength="%d"', $length[ 0 ], $length[ 1 ]) : '';
    $inputStr .= ( $type === 'number' && !empty( $minMax ) ) ? sprintf( 'min="%d" max="%d"', $minMax[0], $minMax[1] )
        : '';
    $inputStr .= $isChecked ? ' checked ' : '';

    foreach ( $customAttributes as $attr => $attrValue )
        $inputStr .= " $attr=\"$attrValue\"" ;

    $inputStr .= '>';

    echo trim(
        str_replace(
            PHP_EOL,
            '',
            preg_replace(
                '/\s+/',
                ' ',
                $inputStr
            )
        )
    );
}

function formCheckbox(
    string     $name,
    bool       $isRequired = true,
    bool       $checked = false,
    array      $classes = [],
    array      $styles = [],
    string|int $id = '',
    array      $customAttributes = []
): void {
    $inputStr = '<input type="checkbox" ';
    $inputStr .= sprintf(' id="%s" ', ( !empty($id) ? $id : $name ));
    $inputStr .= sprintf(' name="%s" ', $name);
    $inputStr .= !empty($styles) ? sprintf(' style="%s" ', implode('; ', $styles)) : '';
    $inputStr .= !empty($classes) ? sprintf('class="%s"', implode(' ', $classes)) : '';
    $inputStr .= $isRequired ? ' required ' : '';
    $inputStr .= ( formValue($name) === 'on' || $checked ) ? 'checked' : '';

    foreach ( $customAttributes as $attr => $attrValue )
        $inputStr .= " $attr=\"$attrValue\"" ;

    $inputStr .= '>';

    echo trim(
        str_replace(
            PHP_EOL,
            '',
            preg_replace(
                '/\s+/s',
                ' ',
                $inputStr
            )
        )
    );
}

function formTextarea(
    string     $name,
    #[ArrayShape( [ 'int', 'int' ] )]
    array      $length = [ 1, 4096 ],
    int        $rows = null,
    string     $cols = null,
    string     $wrap = 'soft',
    bool       $isRequired = true,
    string     $defaultValue = null,
    string     $placeholder = null,
    array      $classes = [],
    array      $styles = [],
    string|int $id = '',
    array      $customAttributes = []
): void {
    $inputStr = '<textarea ';
    $inputStr .= $isRequired ? ' required ' : '';
    $inputStr .= sprintf(' id="%s" ', ( !empty($id) ? $id : $name ));
    $inputStr .= " name=\"$name\" rows=\"$rows\" cols=\"$cols\" wrap=\"$wrap\" ";
    $inputStr .= !empty($placeholder) ? sprintf(' placeholder="%s" ', $placeholder) : '';
    $inputStr .= !empty($classes) ? sprintf('class="%s"', implode(' ', $classes)) : '';
    $inputStr .= !empty($styles) ? sprintf(' style="%s" ', implode('; ', $styles)) : '';
    $inputStr .= !empty($length) ? sprintf('minlength="%d" maxlength="%d"', $length[ 0 ], $length[ 1 ]) : '';

    foreach ( $customAttributes as $attr => $attrValue )
        $inputStr .= " $attr=\"$attrValue\"" ;

    $inputStr .= sprintf('>%s</textarea>', $defaultValue ?? formValue($name));

    echo trim(
        str_replace(
            PHP_EOL,
            '',
            preg_replace(
                '/\s+/s',
                ' ',
                $inputStr
            )
        )
    );
}
