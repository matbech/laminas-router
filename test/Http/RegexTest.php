<?php

declare(strict_types=1);

namespace LaminasTest\Router\Http;

use Laminas\Http\Request;
use Laminas\Router\Http\Regex;
use Laminas\Router\Http\RouteMatch;
use Laminas\Stdlib\Request as BaseRequest;
use LaminasTest\Router\FactoryTester;
use PHPUnit\Framework\TestCase;

use function strlen;
use function strpos;

class RegexTest extends TestCase
{
    /**
     * @psalm-return array<string, array{
     *     0: Regex,
     *     1: string,
     *     2: null|int,
     *     3: null|array<string, string|int|float>
     * }>
     */
    public static function routeProvider(): array
    {
        return [
            'simple-match'                             => [
                new Regex('/(?<foo>[^/]+)', '/%foo%'),
                '/bar',
                null,
                ['foo' => 'bar'],
            ],
            'no-match-without-leading-slash'           => [
                new Regex('(?<foo>[^/]+)', '%foo%'),
                '/bar',
                null,
                null,
            ],
            'no-match-with-trailing-slash'             => [
                new Regex('/(?<foo>[^/]+)', '/%foo%'),
                '/bar/',
                null,
                null,
            ],
            'offset-skips-beginning'                   => [
                new Regex('(?<foo>[^/]+)', '%foo%'),
                '/bar',
                1,
                ['foo' => 'bar'],
            ],
            'offset-enables-partial-matching'          => [
                new Regex('/(?<foo>[^/]+)', '/%foo%'),
                '/bar/baz',
                0,
                ['foo' => 'bar'],
            ],
            'url-encoded-parameters-are-decoded'       => [
                new Regex('/(?<foo>[^/]+)', '/%foo%'),
                '/foo%20bar',
                null,
                ['foo' => 'foo bar'],
            ],
            'empty-matches-are-replaced-with-defaults' => [
                new Regex('/foo(?:/(?<bar>[^/]+))?/baz-(?<baz>[^/]+)', '/foo/baz-%baz%', ['bar' => 'bar']),
                '/foo/baz-baz',
                null,
                ['bar' => 'bar', 'baz' => 'baz'],
            ],
            'params-contain-non-string-scalar-values'  => [
                new Regex('/id/(?<id>\d+)/scale/(?<scale>\d+\.\d+)', '/id/%id%/scale/%scale%'),
                '/id/42/scale/4.2',
                null,
                ['id' => 42, 'scale' => 4.2],
            ],
        ];
    }

    /**
     * @dataProvider routeProvider
     * @param        string   $path
     * @param        int|null $offset
     */
    public function testMatching(Regex $route, $path, $offset, ?array $params = null)
    {
        $request = new Request();
        $request->setUri('http://example.com' . $path);
        $match = $route->match($request, $offset);

        if ($params === null) {
            $this->assertNull($match);
        } else {
            $this->assertInstanceOf(RouteMatch::class, $match);

            if ($offset === null) {
                $this->assertEquals(strlen($path), $match->getLength());
            }

            foreach ($params as $key => $value) {
                $this->assertEquals($value, $match->getParam($key));
            }
        }
    }

    /**
     * @dataProvider routeProvider
     * @param        string   $path
     * @param        int|null $offset
     */
    public function testAssembling(Regex $route, $path, $offset, ?array $params = null)
    {
        if ($params === null) {
            // Data which will not match are not tested for assembling.
            $this->expectNotToPerformAssertions();
            return;
        }

        $result = $route->assemble($params);

        if ($offset !== null) {
            $this->assertEquals($offset, strpos($path, $result, $offset));
        } else {
            $this->assertEquals($path, $result);
        }
    }

    public function testNoMatchWithoutUriMethod()
    {
        $route   = new Regex('/foo', '/foo');
        $request = new BaseRequest();

        $this->assertNull($route->match($request));
    }

    public function testGetAssembledParams()
    {
        $route = new Regex('/(?<foo>.+)', '/%foo%');
        $route->assemble(['foo' => 'bar', 'baz' => 'bat']);

        $this->assertEquals(['foo'], $route->getAssembledParams());
    }

    public function testFactory()
    {
        $tester = new FactoryTester($this);
        $tester->testFactory(
            Regex::class,
            [
                'regex' => 'Missing "regex" in options array',
                'spec'  => 'Missing "spec" in options array',
            ],
            [
                'regex' => '/foo',
                'spec'  => '/foo',
            ]
        );
    }

    public function testRawDecode()
    {
        // verify all characters which don't absolutely require encoding pass through match unchanged
        // this includes every character other than #, %, / and ?
        $raw     = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789`-=[]\\;\',.~!@$^&*()_+{}|:"<>';
        $request = new Request();
        $request->setUri('http://example.com/' . $raw);
        $route = new Regex('/(?<foo>[^/]+)', '/%foo%');
        $match = $route->match($request);

        $this->assertSame($raw, $match->getParam('foo'));
    }

    public function testEncodedDecode()
    {
        // @codingStandardsIgnoreStart
        // every character
        $in  = '%61%62%63%64%65%66%67%68%69%6a%6b%6c%6d%6e%6f%70%71%72%73%74%75%76%77%78%79%7a%41%42%43%44%45%46%47%48%49%4a%4b%4c%4d%4e%4f%50%51%52%53%54%55%56%57%58%59%5a%30%31%32%33%34%35%36%37%38%39%60%2d%3d%5b%5d%5c%3b%27%2c%2e%2f%7e%21%40%23%24%25%5e%26%2a%28%29%5f%2b%7b%7d%7c%3a%22%3c%3e%3f';
        $out = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789`-=[]\\;\',./~!@#$%^&*()_+{}|:"<>?';
        // @codingStandardsIgnoreEnd

        $request = new Request();
        $request->setUri('http://example.com/' . $in);
        $route = new Regex('/(?<foo>[^/]+)', '/%foo%');
        $match = $route->match($request);

        $this->assertSame($out, $match->getParam('foo'));
    }
}
