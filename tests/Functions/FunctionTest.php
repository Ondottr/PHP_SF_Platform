<?php declare( strict_types=1 );

namespace Functions;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// @noinspection PhpIllegalPsrClassPathInspection
final class FunctionTest extends TestCase
{

    /**
     * @return array<string, array{array{string, string, string, string, string, string}}>
     *             [input, snake, screaming_snake, camel, pascal, kebab]
     */
    public static function caseProvider(): array
    {
        $cases = [
            // Basic cases
            'lower snake'                 => ['hello_world', 'hello_world', 'HELLO_WORLD', 'helloWorld', 'HelloWorld', 'hello-world'],
            'upper snake'                 => ['HELLO_WORLD', 'hello_world', 'HELLO_WORLD', 'helloWorld', 'HelloWorld', 'hello-world'],

            // camel / pascal
            'camel'                       => ['helloWorld', 'hello_world', 'HELLO_WORLD', 'helloWorld', 'HelloWorld', 'hello-world'],
            'pascal'                      => ['HelloWorld', 'hello_world', 'HELLO_WORLD', 'helloWorld', 'HelloWorld', 'hello-world'],

            // kebab
            'kebab'                       => ['hello-world', 'hello_world', 'HELLO_WORLD', 'helloWorld', 'HelloWorld', 'hello-world'],

            // mixed separators
            'mixed separators'            => ['hello.world-test_case', 'hello_world_test_case', 'HELLO_WORLD_TEST_CASE', 'helloWorldTestCase', 'HelloWorldTestCase', 'hello-world-test-case'],

            // multiple separators
            'messy separators'            => ['hello___world---test', 'hello_world_test', 'HELLO_WORLD_TEST', 'helloWorldTest', 'HelloWorldTest', 'hello-world-test'],

            // leading/trailing junk
            'leading trailing separators' => ['__hello__world__', 'hello_world', 'HELLO_WORLD', 'helloWorld', 'HelloWorld', 'hello-world'],

            // acronyms (this is where most libs fail)
            'XMLHttpRequest'              => ['XMLHttpRequest', 'xml_http_request', 'XML_HTTP_REQUEST', 'xmlHttpRequest', 'XmlHttpRequest', 'xml-http-request'],
            'HTTPRequest'                 => ['HTTPRequest', 'http_request', 'HTTP_REQUEST', 'httpRequest', 'HttpRequest', 'http-request'],
            'URLParser'                   => ['URLParser', 'url_parser', 'URL_PARSER', 'urlParser', 'UrlParser', 'url-parser'],

            // all caps acronym input
            'XML'                         => ['XML', 'xml', 'XML', 'xml', 'Xml', 'xml'],
            'JSONData'                    => ['JSONData', 'json_data', 'JSON_DATA', 'jsonData', 'JsonData', 'json-data'],

            // numbers (often broken in naive regexes)
            'user1Profile'                => ['user1Profile', 'user1_profile', 'USER1_PROFILE', 'user1Profile', 'User1Profile', 'user1-profile'],
            'version2API'                 => ['version2API', 'version2_api', 'VERSION2_API', 'version2Api', 'Version2Api', 'version2-api'],

            // unicode (accents preserved — StringCase does not transliterate)
            'unicode latin'               => ['caféUser', 'café_user', 'CAFÉ_USER', 'caféUser', 'CaféUser', 'café-user'],
            'unicode non-latin'           => ['MüllerUser', 'müller_user', 'MÜLLER_USER', 'müllerUser', 'MüllerUser', 'müller-user'],

            // edge whitespace
            'whitespace chaos'            => ['  hello   WORLD  test ', 'hello_world_test', 'HELLO_WORLD_TEST', 'helloWorldTest', 'HelloWorldTest', 'hello-world-test'],

            // already normalized
            'already snake'               => ['hello_world_test', 'hello_world_test', 'HELLO_WORLD_TEST', 'helloWorldTest', 'HelloWorldTest', 'hello-world-test'],

            // edge: empty
            'empty'                       => ['', '', '', '', '', ''],

            // edge: single word
            'single word'                 => ['hello', 'hello', 'HELLO', 'hello', 'Hello', 'hello'],

            // edge: only separators
            'only separators'             => ['___', '', '', '', '', ''],

            // slash separator
            'slash'                       => ['foo/bar', 'foo_bar', 'FOO_BAR', 'fooBar', 'FooBar', 'foo-bar'],

            // digit between lowercase — no split (only digit→uppercase triggers split)
            // MB_CASE_TITLE treats digits as word boundaries, so pascal gives Hello2World
            'digit between lowercase'     => ['hello2world', 'hello2world', 'HELLO2WORLD', 'hello2world', 'Hello2World', 'hello2world'],

            // digit inside compound acronym+word
            'myHTTP2Request'              => ['myHTTP2Request', 'my_http2_request', 'MY_HTTP2_REQUEST', 'myHttp2Request', 'MyHttp2Request', 'my-http2-request'],
        ];

        return array_map(static fn(array $row) => [$row], $cases);
    }


    #[DataProvider('caseProvider')]
    public function testStringToSnake(array $case): void
    {
        [$input, $expected] = $case;
        self::assertSame($expected, string_to_snake($input));
    }

    #[DataProvider('caseProvider')]
    public function testStringToScreamingSnake(array $case): void
    {
        [$input, , $expected] = $case;
        self::assertSame($expected, string_to_screaming_snake($input));
    }

    #[DataProvider('caseProvider')]
    public function testStringToCamel(array $case): void
    {
        [$input, , , $expected] = $case;
        self::assertSame($expected, string_to_camel($input));
    }

    #[DataProvider('caseProvider')]
    public function testStringToPascal(array $case): void
    {
        [$input, , , , $expected] = $case;
        self::assertSame($expected, string_to_pascal($input));
    }

    #[DataProvider('caseProvider')]
    public function testStringToKebab(array $case): void
    {
        [$input, , , , , $expected] = $case;
        self::assertSame($expected, string_to_kebab($input));
    }

}
