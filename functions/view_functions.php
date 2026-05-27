<?php declare(strict_types=1);

use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\ExpectedValues;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;

/**
 * Returns the per-session CSRF token, generating it on first call.
 * Pass $asInput = true to get a ready-to-embed hidden input element.
 *
 * Used in templates:
 *   <?= csrf_token( asInput: true ) ?>   — emits <input type="hidden" name="_token" value="...">
 *   <?= csrf_token() ?>                  — returns the raw token string (for JS / fetch)
 */
function csrf_token(bool $asInput = false): string
{
    $token = s()->get('_csrf_token');

    if (null === $token) {
        $token = bin2hex(random_bytes(32));
        s()->set('_csrf_token', $token);
    }

    if ($asInput) {
        return sprintf('<input type="hidden" name="_token" value="%s">', htmlspecialchars($token, ENT_QUOTES, 'UTF-8'));
    }

    return $token;
}

function asset(string $path): string
{
    if (false === file_exists(sprintf('%s/public/%s', project_dir(), $path))) {
        throw new FileNotFoundException("Asset not found: $path");
    }

    return "/$path";
}

function build_manifest(): array
{
    static $manifest = null;
    if (null === $manifest) {
        $manifestPath = project_dir() . '/public/build/manifest.json';
        $manifest = file_exists($manifestPath)
            ? (json_decode(file_get_contents($manifestPath), true) ?: [])
            : [];
    }

    return $manifest;
}

function manifest_asset(string $filename): string
{
    $resolved = build_manifest()[$filename] ?? "build/$filename";

    return asset(ltrim($resolved, '/'));
}

function manifest_has(string $filename): bool
{
    $resolved = build_manifest()[$filename] ?? "build/$filename";

    return file_exists(project_dir() . '/public/' . ltrim($resolved, '/'));
}

function pageTitle(): string
{
    return s()->get('page_title', APPLICATION_NAME);
}

/**
 * @noinspection GlobalVariableUsageInspection
 */
function getMessages(?string $messageType = null): array|string|false
{
    if (!array_key_exists('messages', $GLOBALS) || empty($GLOBALS['messages'])) {
        return [];
    }

    if (null === $messageType) {
        return $GLOBALS['messages'];
    }

    if (isset($GLOBALS['messages'][$messageType])) {
        return $GLOBALS['messages'][$messageType];
    }

    return false;
}

/**
 * @noinspection GlobalVariableUsageInspection
 */
function getErrors(?string $errorType = null): array|string|false
{
    if (!array_key_exists('errors', $GLOBALS) || empty($GLOBALS['errors'])) {
        return [];
    }

    if (null === $errorType) {
        return $GLOBALS['errors'];
    }

    if (isset($GLOBALS['errors'][$errorType])) {
        return $GLOBALS['errors'][$errorType];
    }

    return false;
}

/**
 * @noinspection GlobalVariableUsageInspection
 */
function formValue(string $name): string
{
    return (string) ($GLOBALS['form_data'][$name] ?? '');
}

// function showMessages( string $messageType = null, bool $onlyFirst = true ): void
// {
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
// }
//
//
// function showErrors( string $errorType = null, bool $onlyFirst = true ): void
// {
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
// }

/**
 * Creates an HTML input element.
 *
 * @param string           $name             Name of the input element
 * @param array            $length           an array of two integers specifying the minimum and maximum length of the input
 * @param string           $type             type of the input element, defaults to "text"
 * @param bool             $isRequired       indicates whether the input is required, defaults to true
 * @param array            $minMax           an array of two integers or an empty array specifying the minimum and maximum value of the input, applies only to type "number"
 * @param string|int|float $defaultValue     default value of the input
 * @param string           $placeholder      placeholder text for the input
 * @param int|float        $step             step for the input of type "number"
 * @param array            $classes          classes for the input element, must be unique
 * @param array            $styles           styles for the input element, given as key-value pairs
 * @param string           $id               ID for the input element
 * @param array            $customAttributes custom attributes for the input element, given as key-value pairs
 *
 * @return string the HTML code for the input element
 */
function input(
    string $name,
    array $length = [1, 255],
    string $type = 'text',
    bool $isRequired = true,
    array $minMax = [],
    string|int|float|null $defaultValue = null,
    ?string $placeholder = null,
    int|float|null $step = null,
    array $classes = [],
    array $styles = [],
    ?string $id = null,
    array $customAttributes = [],
): string {
    if (2 !== count($length)
        || false === is_int($length[0]) || false === is_int($length[1])
    ) {
        throw new InvalidArgumentException('Length must be an array of two integers');
    }

    if (false === empty($minMax)) {
        if (2 !== count($minMax)
            || (false === is_int($minMax[0]) && false === is_float($minMax[0]))
            || (false === is_int($minMax[1]) && false === is_float($minMax[1]))
        ) {
            throw new InvalidArgumentException('MinMax must be an array of two integers or floats or an empty array');
        }
    }

    if (count($classes) !== count(array_unique($classes))) {
        throw new InvalidArgumentException('Classes must be unique');
    }

    if (false === empty($classes)) {
        foreach ($classes as $class) {
            if (false === is_string($class)) {
                throw new InvalidArgumentException('Classes must be an array of strings');
            }
        }
    }

    if (false === empty($styles)) {
        foreach ($styles as $key => $value) {
            if (false === is_string($key) || false === is_string($value)) {
                throw new InvalidArgumentException('Key and value in styles must be strings');
            }
        }
    }

    if (false === empty($customAttributes)) {
        foreach ($customAttributes as $key => $value) {
            if (false === is_string($key) || false === is_string($value)) {
                throw new InvalidArgumentException('Key and value in customAttributes must be strings');
            }
        }
    }

    if ('number' === $type && null === $step) {
        throw new InvalidArgumentException('Step must be set for number input');
    }

    $inputStr = "<input type='$type'";

    if ($isRequired) {
        $inputStr .= ' required';
    }

    $inputStr .= $id ? " id='$id'" : " id='$name'";
    $inputStr .= " name='$name'";

    if ('number' === $type) {
        $inputStr .= null !== $step ? " step='$step'" : '';

        if (false === empty($minMax)) {
            $inputStr .= sprintf(" min='%s' max='%s'", $minMax[0], $minMax[1]);
        }
    }

    $inputStr .= $placeholder ? " placeholder='$placeholder'" : '';

    if (null !== $defaultValue) {
        $inputStr .= sprintf(" value='%s'", $defaultValue);
    } elseif (false === empty(formValue($name))) {
        $inputStr .= sprintf(" value='%s'", formValue($name));
    }

    if (false === empty($length)) {
        $inputStr .= sprintf(" minlength='%d' maxlength='%d'", $length[0], $length[1]);
    }

    $inputStr .= false === empty($classes) ? " class='" . implode(' ', $classes) . "'" : '';
    $inputStr .= false === empty($styles) ?
        sprintf(
            " style='%s;'",
            implode(
                '; ',
                array_map(
                    function ($v, $k) { return "$k: $v"; },
                    $styles,
                    array_keys($styles),
                ),
            ),
        ) : '';

    foreach ($customAttributes as $attr => $value) {
        $inputStr .= " $attr='$value'";
    }

    $inputStr .= '>';

    return $inputStr;
}

function formCheckbox(
    string $name,
    bool $isRequired = true,
    ?bool $checked = null,
    #[ArrayShape(['string'])]
    array $classes = [],
    #[ArrayShape(['string'])]
    array $styles = [],
    string|int $id = '',
    #[ArrayShape(['string'])]
    array $customAttributes = [],
): void {
    $inputStr = '<input type="checkbox" ';

    if ($isRequired) {
        $inputStr .= ' required ';
    }

    if (false === empty($name) || false === empty($id)) {
        $inputStr .= sprintf(' id="%s" ', !empty($id) ? $id : $name);
    }

    if (false === empty($name)) {
        $inputStr .= sprintf(' name="%s" ', $name);
    }

    if (true === $checked) {
        $inputStr .= ' checked ';
    } elseif (null === $checked && 'on' === formValue($name)) {
        $inputStr .= ' checked ';
    }

    if (false === empty($classes)) {
        $inputStr .= sprintf(' class="%s" ', implode(' ', $classes));
    }

    if (false === empty($styles)) {
        $inputStr .= ' style="';
        foreach ($styles as $styleName => $styleValue) {
            $inputStr .= sprintf('%s: %s; ', $styleName, $styleValue);
        }
        $inputStr .= '" ';
    }

    foreach ($customAttributes as $attr => $attrValue) {
        $inputStr .= " $attr=\"$attrValue\"";
    }

    $inputStr .= '>';

    echo trim(str_replace(PHP_EOL, '', preg_replace('/\s+/', ' ', $inputStr)));
}

function formTextarea(
    string $name,
    #[ArrayShape(['min' => 'int', 0 => 'int', 'max' => 'int', 1 => 'int'])]
    array $length = [1, 4096],
    ?int $rows = null,
    ?string $cols = null,
    #[ExpectedValues(['soft', 'hard'])]
    string $wrap = 'soft',
    bool $isRequired = true,
    ?string $defaultValue = null,
    ?string $placeholder = null,
    #[ArrayShape(['string'])]
    array $classes = [],
    #[ArrayShape(['string'])]
    array $styles = [],
    string|int $id = '',
    #[ArrayShape(['string'])]
    array $customAttributes = [],
): void {
    if ('soft' !== $wrap && 'hard' !== $wrap) {
        throw new InvalidArgumentException("Invalid wrap value for '$name' textarea. Valid values are 'soft' and 'hard'.");
    }

    $inputStr = '<textarea ';
    $inputStr .= $isRequired ? ' required ' : '';

    if (false === empty($name) || false === empty($id)) {
        $inputStr .= sprintf(' id="%s" ', !empty($id) ? $id : $name);
    }

    if (false === empty($name)) {
        $inputStr .= sprintf(' name="%s" ', $name);
    }

    if (null !== $rows) {
        $inputStr .= sprintf(' rows="%d" ', $rows);
    }

    if (null !== $cols) {
        $inputStr .= sprintf(' cols="%s" ', $cols);
    }

    $inputStr .= sprintf(' wrap="%s" ', $wrap);

    if (false === empty($placeholder)) {
        $inputStr .= sprintf(' placeholder="%s" ', $placeholder);
    }

    if (false === empty($length)) {
        $inputStr .= sprintf(' minlength="%d" maxlength="%d" ', $length['min'] ?? $length[0], $length['max'] ?? $length[1]);
    }

    if (false === empty($classes)) {
        $inputStr .= sprintf(' class="%s" ', implode(' ', $classes));
    }

    if (false === empty($styles)) {
        $inputStr .= ' style="';
        foreach ($styles as $styleName => $styleValue) {
            $inputStr .= sprintf('%s: %s; ', $styleName, $styleValue);
        }
        $inputStr .= '" ';
    }

    foreach ($customAttributes as $attr => $attrValue) {
        $inputStr .= sprintf(' %s="%s" ', $attr, $attrValue);
    }

    if (null !== $defaultValue) {
        $inputStr .= sprintf('>%s', $defaultValue);
    } elseif (null !== formValue($name)) {
        $inputStr .= sprintf('>%s', formValue($name));
    }

    $inputStr .= '</textarea>';

    echo trim(str_replace(PHP_EOL, '', preg_replace('/\s+/', ' ', $inputStr)));
}

function formSelect(
    string $name,
    #[ArrayShape(['object'])]
    array $options,
    string $value,
    string|callable $label,
    string $id = '',
    string $onChange = '',
    #[ArrayShape(['value' => 'string', 'label' => 'string'])]
    array $infoField = [],
    bool $isRequired = true,
    string|int|float|null $defaultValue = null,
    #[ArrayShape(['string'])]
    array $classes = [],
    #[ArrayShape(['string'])]
    array $styles = [],
    #[ArrayShape(['string'])]
    array $customAttributes = [],
): void {
    if ('' === $id) {
        $id = $name;
    }

    foreach ($options as $option) {
        if (false === method_exists($option, $value)) {
            throw new InvalidArgumentException("Invalid value field for '$name' select. The value field must be a public method name of the object.");
        }

        if (is_string($label) && false === method_exists($option, $label)) {
            throw new InvalidArgumentException("Invalid label field for '$name' select. The label option must be a callable with option object as the first parameter or a public method name of the object.");
        }
    }

    $inputStr = '<select ';
    $inputStr .= $isRequired ? ' required ' : '';

    $inputStr .= sprintf(' id="%s" ', $id);

    if (false === empty($name)) {
        $inputStr .= sprintf(' name="%s" ', $name);
    }

    if (false === empty($classes)) {
        $inputStr .= sprintf(' class="%s" ', implode(' ', $classes));
    }

    if (false === empty($styles)) {
        $inputStr .= ' style="';
        foreach ($styles as $styleName => $styleValue) {
            $inputStr .= sprintf('%s: %s; ', $styleName, $styleValue);
        }
        $inputStr .= '" ';
    }

    foreach ($customAttributes as $attr => $attrValue) {
        $inputStr .= sprintf(' %s="%s" ', $attr, $attrValue);
    }

    if (false === empty($onChange)) {
        $inputStr .= ' onchange="' . $onChange . '" ';
    }

    $inputStr .= '>';

    if (false === empty($infoField)) {
        $inputStr .= sprintf('<option selected disabled value="%s">%s</option>', $infoField['value'], $infoField['label']);
    }

    foreach ($options as $option) {
        $inputStr .= sprintf('<option value="%s" ', $option->$value());

        if (null !== $defaultValue && $defaultValue === $option->$value()) {
            $inputStr .= ' selected ';
        }

        if (is_string($label)) {
            $inputStr .= sprintf('>%s</option>', $option->$label());
        } else {
            $inputStr .= sprintf('>%s</option>', $label($option));
        }

        $inputStr .= '</option>';
    }

    $inputStr .= '</select>';

    echo trim(str_replace(PHP_EOL, '', preg_replace('/\s+/', ' ', $inputStr)));
}
