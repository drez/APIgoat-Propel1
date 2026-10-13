<?php

/**
 * MatchValidator::prepareRegexp() read $exp[0] on an empty expression and
 * logged "Uninitialized string offset 0" on PHP 8 (apicrm/vidifye prod,
 * 2026-10). An empty expression must still become the match-everything '//'
 * it always ended up as, silently; non-empty expressions are unchanged.
 *
 * Run: vendor/bin/phpunit test/testsuite/runtime/validator/MatchValidatorEmptyRegexpTest.php
 */
class MatchValidatorEmptyRegexpTest extends \PHPUnit\Framework\TestCase
{
    private function prepare($exp)
    {
        require_once dirname(__FILE__) . '/../../../../runtime/lib/validator/BasicValidator.php';
        require_once dirname(__FILE__) . '/../../../../runtime/lib/validator/MatchValidator.php';
        $m = new ReflectionMethod('MatchValidator', 'prepareRegexp');
        $m->setAccessible(true);
        set_error_handler(function ($no, $str, $file, $line) {
            throw new ErrorException($str, 0, $no, $file, $line);
        });
        try {
            return $m->invoke(new MatchValidator(), $exp);
        } finally {
            restore_error_handler();
        }
    }

    public function testEmptyExpressionIsSilentAndMatchesEverything()
    {
        $this->assertSame('//', $this->prepare(''));
        $this->assertSame('//', $this->prepare(null));
        $this->assertSame(1, preg_match($this->prepare(''), 'anything'));
    }

    public function testNonEmptyExpressionsUnchanged()
    {
        $this->assertSame('/^[a-z]+$/', $this->prepare('/^[a-z]+$/'));
        $this->assertSame('/^[a-z]+$/', $this->prepare('^[a-z]+$'));
        $this->assertSame('/a\/b/', $this->prepare('a/b'));
    }
}
